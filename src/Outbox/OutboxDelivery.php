<?php

namespace Rushing\SchemaForms\Outbox;

use Illuminate\Support\Str;
use Rushing\SchemaForms\Contracts\SubmissionNotifier;
use Rushing\SchemaForms\Data\NotifyIntent;
use Rushing\SchemaForms\Models\FormSubmission;
use Rushing\SchemaForms\Models\SubmissionNotification;
use Rushing\SchemaForms\Notifiers\MailSubmissionNotifier;
use Rushing\SchemaForms\Notifiers\OutboxSubmissionNotifier;

/**
 * The transactional-outbox engine behind the notifier seam. Every notification is
 * recorded before it is delivered and its outcome written back, so failures survive
 * to be replayed. Two entry points share one attempt path:
 *
 *  - {@see record()} — first delivery, driven by {@see OutboxSubmissionNotifier}
 *    on a fresh submission.
 *  - {@see replay()} — redelivery of an existing entry, driven by the replay job.
 *
 * The delivery notifier is always the INNER (mail/central/custom) notifier, never the
 * outbox decorator — so replay never re-wraps and re-records. The persisted
 * SubmissionNotification model is resolved through config so a host can swap it.
 */
class OutboxDelivery
{
    public function record(FormSubmission $submission, NotifyIntent $intent, SubmissionNotifier $delivery): ?SubmissionNotification
    {
        // An intent with no recipient has nothing to deliver and nothing to replay, so
        // recording it would only litter the outbox with permanently-inert rows (every
        // notify-less submission — e.g. per-tenant circuit intake — would leave one). Skip.
        if ($intent->to === null || $intent->to === '') {
            return null;
        }

        $entry = $this->model()::query()->create([
            'submission_id' => $submission->getKey(),
            'form_key' => $submission->form_key,
            'channel' => $intent->channel,
            'recipient' => $intent->to,
            'subject' => $intent->subject,
            'intent' => $intent->toArray(),
            'notifier' => $delivery::class,
            'status' => SubmissionNotification::StatusPending,
        ]);

        $this->attempt($entry, $submission, $intent, $delivery);

        return $entry;
    }

    /**
     * Redeliver every unsent entry (below the attempt ceiling, optionally scoped to a
     * form) and report the tally. The unsent set is snapshotted before iterating,
     * since replay mutates each row's status. Returns the summary directly so console
     * and HTTP callers get a reliable result without reading back a dispatched job.
     *
     * @return array{replayed:int, sent:int, failed:int}
     */
    public function replayUnsent(?int $maxAttempts = null, ?string $formKey = null): array
    {
        $ceiling = $maxAttempts ?? (int) config('schema-forms.outbox.max_attempts', 10);

        $entries = $this->model()::query()
            ->unsent($ceiling)
            ->when($formKey, fn ($q) => $q->where('form_key', $formKey))
            ->orderBy('created_at')
            ->get();

        $summary = ['replayed' => 0, 'sent' => 0, 'failed' => 0];

        foreach ($entries as $entry) {
            $this->replay($entry);

            $summary['replayed']++;
            $summary[$entry->status === SubmissionNotification::StatusSent ? 'sent' : 'failed']++;
        }

        return $summary;
    }

    /**
     * Redeliver an already-recorded entry through the currently-configured delivery
     * notifier. The submission is refetched and the intent rebuilt from the stored
     * snapshot; a submission that no longer exists is marked failed, not retried.
     */
    public function replay(SubmissionNotification $entry): void
    {
        $model = config('schema-forms.models.form_submission', FormSubmission::class);

        $submission = $entry->submission_id ? $model::query()->find($entry->submission_id) : null;

        if ($submission === null) {
            $entry->forceFill([
                'status' => SubmissionNotification::StatusFailed,
                'last_error' => 'Submission no longer exists; cannot redeliver.',
            ])->save();

            return;
        }

        $this->attempt($entry, $submission, NotifyIntent::from($entry->intent ?? []), $this->deliveryNotifier());
    }

    /**
     * Invoke the delivery notifier and record the outcome. Never throws: a failed
     * send is a recorded state (replayable), not an exception the caller must handle
     * — the submission is already durable.
     */
    private function attempt(SubmissionNotification $entry, FormSubmission $submission, NotifyIntent $intent, SubmissionNotifier $delivery): void
    {
        $entry->attempts++;

        try {
            $delivery($submission, $intent);

            $entry->forceFill([
                'status' => SubmissionNotification::StatusSent,
                'last_error' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            $entry->forceFill([
                'status' => SubmissionNotification::StatusFailed,
                'last_error' => Str::limit($e->getMessage(), 1000),
            ]);
        }

        $entry->save();
    }

    /**
     * The raw delivery notifier (the configured inner), resolved without the outbox
     * decorator so replay does not re-wrap.
     */
    private function deliveryNotifier(): SubmissionNotifier
    {
        return app(config('schema-forms.notifier', MailSubmissionNotifier::class));
    }

    /**
     * The swappable SubmissionNotification model class.
     *
     * @return class-string<SubmissionNotification>
     */
    private function model(): string
    {
        return config('schema-forms.models.submission_notification', SubmissionNotification::class);
    }
}
