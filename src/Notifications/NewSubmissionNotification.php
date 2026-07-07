<?php

namespace Rushing\SchemaForms\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Rushing\SchemaForms\Data\NotifyIntent;
use Rushing\SchemaForms\Listeners\NotifyOnSubmission;
use Rushing\SchemaForms\Models\FormSubmission;

/**
 * The generic mail body for a new submission — one form-agnostic template that lists the
 * submitted payload. Implements ShouldQueue so a slow/failing mail transport is pushed to
 * the queue rather than blocking the request (persistence already completed before this is
 * dispatched). On a sync queue the send still runs inline, so {@see NotifyOnSubmission}
 * also guards the invocation.
 */
class NewSubmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public FormSubmission $submission,
        public NotifyIntent $intent,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->intent->subject ?? 'New form submission')
            ->line('A new submission was received for form "'.$this->submission->form_key.'".');

        foreach ((array) $this->submission->payload as $key => $value) {
            $message->line($key.': '.(is_scalar($value) ? (string) $value : json_encode($value)));
        }

        return $message;
    }
}
