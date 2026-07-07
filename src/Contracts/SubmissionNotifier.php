<?php

namespace Rushing\SchemaForms\Contracts;

use Rushing\SchemaForms\Data\NotifyIntent;
use Rushing\SchemaForms\Models\FormSubmission;

/**
 * The host-swappable seam. A single-method invocable: given a persisted submission and
 * its resolved notify intent, do whatever this host does with a new submission — mail,
 * Slack, webhook, CRM. The package ships one binding (MailSubmissionNotifier, default). A
 * host rebinds this contract in its own service provider (or points
 * `schema-forms.notifier` at its class) to replace it entirely — the built-in mailer is
 * then not invoked.
 *
 * Invoked by the NotifyOnSubmission listener on SubmissionReceived, so persistence always
 * precedes notification: a failing, misconfigured, or absent notifier never loses a
 * submission.
 */
interface SubmissionNotifier
{
    public function __invoke(FormSubmission $submission, NotifyIntent $intent): void;
}
