<?php

use Illuminate\Support\Facades\Notification;
use Rushing\SchemaForms\Actions\RecordsSubmissions;
use Rushing\SchemaForms\Contracts\SubmissionNotifier;
use Rushing\SchemaForms\Data\NotifyIntent;
use Rushing\SchemaForms\Models\FormSubmission;
use Rushing\SchemaForms\Notifications\NewSubmissionNotification;

it('sends the default mail notification to the intent recipient', function () {
    Notification::fake();

    // Recording drives SubmissionReceived -> NotifyOnSubmission -> the bound (default) notifier.
    app(RecordsSubmissions::class)->record(
        formKey: 'waitlist',
        schemaRef: 'waitlist/1',
        payload: ['name' => 'Jane', 'email' => 'jane@example.com'],
    );

    Notification::assertSentOnDemand(
        NewSubmissionNotification::class,
        fn ($notification, $channels, $notifiable) => ($notifiable->routes['mail'] ?? null) === 'waitlist@example.com'
    );
});

it('lets a host rebind the notifier, bypassing the default mailer', function () {
    Notification::fake();

    $spy = new class implements SubmissionNotifier
    {
        public array $calls = [];

        public function __invoke(FormSubmission $submission, NotifyIntent $intent): void
        {
            $this->calls[] = [$submission->form_key, $intent->to];
        }
    };

    app()->instance(SubmissionNotifier::class, $spy);

    app(RecordsSubmissions::class)->record(
        formKey: 'waitlist',
        schemaRef: 'waitlist/1',
        payload: ['name' => 'Jane', 'email' => 'jane@example.com'],
    );

    expect($spy->calls)->toBe([['waitlist', 'waitlist@example.com']]);

    // The default mailer is fully bypassed by the rebind.
    Notification::assertNothingSent();
});
