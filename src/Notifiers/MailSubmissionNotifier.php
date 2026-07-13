<?php

namespace Splicewire\SchemaForms\Notifiers;

use Illuminate\Support\Facades\Notification;
use Splicewire\SchemaForms\Contracts\SubmissionNotifier;
use Splicewire\SchemaForms\Data\NotifyIntent;
use Splicewire\SchemaForms\Models\FormSubmission;
use Splicewire\SchemaForms\Notifications\NewSubmissionNotification;

/**
 * The default binding: mail a NewSubmissionNotification to the intent's recipient. With no
 * recipient (no `x-swf-notify.to` and no config default) there is nowhere to send, so it does
 * nothing — the submission is already persisted, so silence here loses nothing.
 *
 * Sends with `notifyNow` (synchronous), NOT the queued `notify`, on purpose: the outbox is
 * itself the durable retry layer, so it must observe the real send outcome. `notify` on a
 * queued notification (NewSubmissionNotification is ShouldQueue) would only enqueue and
 * return without throwing, letting the outbox record `sent` before the transport ran — a
 * later queue-side failure would then never be replayed. `notifyNow` surfaces transport
 * errors synchronously so the outbox records `failed` and can replay.
 */
class MailSubmissionNotifier implements SubmissionNotifier
{
    public function __invoke(FormSubmission $submission, NotifyIntent $intent): void
    {
        if ($intent->to === null || $intent->to === '') {
            return;
        }

        Notification::route('mail', $intent->to)
            ->notifyNow(new NewSubmissionNotification($submission, $intent));
    }
}
