<?php

namespace Rushing\SchemaForms\Notifiers;

use Illuminate\Support\Facades\Http;
use Rushing\SchemaForms\Contracts\SubmissionNotifier;
use Rushing\SchemaForms\Data\NotifyIntent;
use Rushing\SchemaForms\Models\FormSubmission;
use Rushing\SchemaForms\Notifiers\Exceptions\CentralRelayNotConfigured;

/**
 * The "central turnkey" delivery seam: instead of sending mail locally, forward the
 * submission and its notify intent to a central Splicewire instance that owns the
 * mail transport. Lets a freshly-spun host notify without standing up its own
 * per-domain provider.
 *
 * A host selects this by pointing `schema-forms.notifier` at this class. With no
 * `central_relay.url` it throws {@see CentralRelayNotConfigured} — the outbox records
 * the failure and replays it once the URL exists, so selecting the relay before central
 * is reachable loses nothing. The central RECEIVER endpoint is not part of this package.
 */
class CentralRelayNotifier implements SubmissionNotifier
{
    public function __invoke(FormSubmission $submission, NotifyIntent $intent): void
    {
        $url = config('schema-forms.central_relay.url');

        if (! is_string($url) || $url === '') {
            throw CentralRelayNotConfigured::make();
        }

        Http::withToken((string) config('schema-forms.central_relay.token'))
            ->acceptJson()
            ->post($url, [
                'origin' => config('app.url'),
                'form_key' => $submission->form_key,
                'submission_id' => $submission->getKey(),
                'payload' => $submission->payload,
                'context' => $submission->context,
                'intent' => $intent->toArray(),
            ])
            ->throw();
    }
}
