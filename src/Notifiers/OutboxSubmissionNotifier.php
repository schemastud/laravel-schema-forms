<?php

namespace Splicewire\SchemaForms\Notifiers;

use Splicewire\SchemaForms\Contracts\SubmissionNotifier;
use Splicewire\SchemaForms\Data\NotifyIntent;
use Splicewire\SchemaForms\Models\FormSubmission;
use Splicewire\SchemaForms\Outbox\OutboxDelivery;

/**
 * The default binding when the outbox is enabled: a decorator that records every
 * notification in the outbox before (and after) delegating to the real delivery
 * notifier ($inner — mail, central relay, or a custom class). Transparent — it
 * implements the same seam, so callers are unchanged and a host that rebinds
 * SubmissionNotifier directly still fully replaces it.
 */
class OutboxSubmissionNotifier implements SubmissionNotifier
{
    public function __construct(
        private SubmissionNotifier $inner,
        private OutboxDelivery $outbox,
    ) {}

    public function __invoke(FormSubmission $submission, NotifyIntent $intent): void
    {
        $this->outbox->record($submission, $intent, $this->inner);
    }
}
