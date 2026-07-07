<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Rushing\SchemaForms\Actions\RecordsSubmissions;
use Rushing\SchemaForms\Contracts\SubmissionNotifier;
use Rushing\SchemaForms\Data\NotifyIntent;
use Rushing\SchemaForms\Models\FormSubmission;
use Rushing\SchemaForms\Models\SubmissionNotification;
use Rushing\SchemaForms\Notifications\NewSubmissionNotification;
use Rushing\SchemaForms\Notifiers\CentralRelayNotifier;
use Rushing\SchemaForms\Notifiers\Exceptions\CentralRelayNotConfigured;
use Rushing\SchemaForms\Outbox\OutboxDelivery;

/** Bind a delivery notifier whose success/failure the test flips via `shouldFail`. */
function bindTogglingNotifier(bool $shouldFail = true): object
{
    $notifier = new class implements SubmissionNotifier
    {
        public bool $shouldFail = true;

        public function __invoke(FormSubmission $submission, NotifyIntent $intent): void
        {
            if ($this->shouldFail) {
                throw new RuntimeException('mail transport down');
            }
        }
    };

    $notifier->shouldFail = $shouldFail;

    app()->instance('test.notifier', $notifier);
    config()->set('schema-forms.notifier', 'test.notifier');

    return $notifier;
}

function recordWaitlist(): void
{
    app(RecordsSubmissions::class)->record(
        formKey: 'waitlist',
        schemaRef: 'waitlist/1',
        payload: ['name' => 'Jane', 'email' => 'jane@example.com'],
    );
}

it('records a sent outbox entry on a successful submission', function () {
    Notification::fake();

    recordWaitlist();

    $entry = SubmissionNotification::sole();

    expect($entry->status)->toBe(SubmissionNotification::StatusSent)
        ->and($entry->form_key)->toBe('waitlist')
        ->and($entry->recipient)->toBe('waitlist@example.com')
        ->and($entry->attempts)->toBe(1)
        ->and($entry->sent_at)->not->toBeNull();

    Notification::assertSentTimes(NewSubmissionNotification::class, 1);
});

it('records a failed entry (never a 500) when delivery throws, and does not lose the submission', function () {
    bindTogglingNotifier(shouldFail: true);

    recordWaitlist();

    // Submission is durable; the notification is recorded failed for replay.
    expect(FormSubmission::count())->toBe(1);

    $entry = SubmissionNotification::sole();
    expect($entry->status)->toBe(SubmissionNotification::StatusFailed)
        ->and($entry->attempts)->toBe(1)
        ->and($entry->last_error)->toContain('mail transport down');
});

it('replays a previously failed entry once delivery recovers', function () {
    $notifier = bindTogglingNotifier(shouldFail: true);

    recordWaitlist();

    expect(SubmissionNotification::sole()->status)->toBe(SubmissionNotification::StatusFailed);

    // Mail comes back up; replay redelivers.
    $notifier->shouldFail = false;
    $summary = app(OutboxDelivery::class)->replayUnsent();

    $entry = SubmissionNotification::sole();
    expect($entry->status)->toBe(SubmissionNotification::StatusSent)
        ->and($entry->attempts)->toBe(2)
        ->and($summary)->toBe(['replayed' => 1, 'sent' => 1, 'failed' => 0]);
});

it('skips entries past the attempt ceiling on replay', function () {
    $entry = SubmissionNotification::query()->create([
        'form_key' => 'waitlist',
        'status' => SubmissionNotification::StatusFailed,
        'attempts' => 10,
    ]);

    $summary = app(OutboxDelivery::class)->replayUnsent(maxAttempts: 10);

    expect($summary['replayed'])->toBe(0)
        ->and($entry->fresh()->attempts)->toBe(10);
});

it('routes notifications to the central relay when selected', function () {
    Http::fake();
    config()->set('schema-forms.notifier', CentralRelayNotifier::class);
    config()->set('schema-forms.central_relay.url', 'https://central.test/relay');

    recordWaitlist();

    expect(SubmissionNotification::sole()->status)->toBe(SubmissionNotification::StatusSent);

    Http::assertSent(fn ($request) => $request->url() === 'https://central.test/relay'
        && $request['form_key'] === 'waitlist'
        && $request['intent']['to'] === 'waitlist@example.com');
});

it('records a failed entry when the central relay is unconfigured, replayable later', function () {
    config()->set('schema-forms.notifier', CentralRelayNotifier::class);
    config()->set('schema-forms.central_relay.url', null);

    recordWaitlist();

    expect(SubmissionNotification::sole()->status)->toBe(SubmissionNotification::StatusFailed)
        ->and(fn () => app(CentralRelayNotifier::class)(new FormSubmission, NotifyIntent::from([])))
        ->toThrow(CentralRelayNotConfigured::class);
});

it('delivers straight through with no outbox row when the outbox is disabled', function () {
    Notification::fake();
    config()->set('schema-forms.outbox.enabled', false);

    recordWaitlist();

    expect(SubmissionNotification::count())->toBe(0);
    Notification::assertSentTimes(NewSubmissionNotification::class, 1);
});

it('writes the swappable SubmissionNotification subclass configured by the host', function () {
    config()->set('schema-forms.models.submission_notification', CustomSubmissionNotification::class);

    $submission = FormSubmission::query()->create(['form_key' => 'waitlist', 'payload' => []]);
    $noop = new class implements SubmissionNotifier
    {
        public function __invoke(FormSubmission $submission, NotifyIntent $intent): void {}
    };

    $entry = app(OutboxDelivery::class)->record($submission, new NotifyIntent(to: 'x@example.com'), $noop);

    expect($entry)->toBeInstanceOf(CustomSubmissionNotification::class);
});

class CustomSubmissionNotification extends SubmissionNotification {}
