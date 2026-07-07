<?php

namespace Rushing\SchemaForms\Notifiers\Exceptions;

use RuntimeException;

/**
 * Thrown when the central relay is selected as the notifier but no relay URL is set.
 * The outbox records it as a failed attempt, so the notification is not lost — it
 * replays the moment `schema-forms.central_relay.url` is configured.
 */
class CentralRelayNotConfigured extends RuntimeException
{
    public static function make(): self
    {
        return new self('Central relay notifier is selected but schema-forms.central_relay.url is not set.');
    }
}
