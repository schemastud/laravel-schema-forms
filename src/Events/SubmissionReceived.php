<?php

namespace Splicewire\SchemaForms\Events;

use Splicewire\SchemaForms\Models\FormSubmission;

/**
 * The only outward signal the base store emits: a submission has been durably
 * persisted. Anything reactive (notification, forwarding, enrichment) listens
 * for this, guaranteeing persistence always precedes any side effect — a failing
 * or absent listener can never lose a submission.
 */
class SubmissionReceived
{
    public function __construct(public FormSubmission $submission) {}
}
