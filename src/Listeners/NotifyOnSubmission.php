<?php

namespace Rushing\SchemaForms\Listeners;

use Rushing\SchemaForms\Contracts\SchemaRegistry;
use Rushing\SchemaForms\Contracts\SubmissionNotifier;
use Rushing\SchemaForms\Data\NotifyIntent;
use Rushing\SchemaForms\Events\SubmissionReceived;

/**
 * Bridges the base store's SubmissionReceived event to the host-swappable
 * SubmissionNotifier seam. Runs *after* persistence (the event fires post-write), so the
 * hard ordering guarantee holds: a submission is durable before any notification is
 * attempted.
 *
 * Rebuilds the NotifyIntent from the submission's form schema (`x-notify`, looked up via
 * the SchemaRegistry) with the config fallback, then invokes whatever SubmissionNotifier
 * is bound (default mail, or a host override).
 */
class NotifyOnSubmission
{
    public function __construct(
        private SchemaRegistry $registry,
        private SubmissionNotifier $notifier,
    ) {}

    public function handle(SubmissionReceived $event): void
    {
        $schema = $this->registry->find($event->submission->form_key) ?? [];

        $intent = NotifyIntent::forSchema(
            $schema,
            (array) config('schema-forms.default_notify', []),
        );

        // The submission is already durable. A failing, misconfigured, or absent notifier
        // (mail down until SMTP is set up, a broken host binding) must never turn a
        // captured submission into a 500 — report and move on. This is the request-side
        // half of the persist-then-notify guarantee.
        try {
            ($this->notifier)($event->submission, $intent);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
