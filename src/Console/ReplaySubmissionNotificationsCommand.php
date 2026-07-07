<?php

namespace Rushing\SchemaForms\Console;

use Illuminate\Console\Command;
use Rushing\SchemaForms\Outbox\OutboxDelivery;

/**
 * `php artisan schema-forms:replay-notifications` — redeliver notifications that never
 * sent (the ones captured while mail was down/unconfigured). Runs the same
 * {@see OutboxDelivery::replayUnsent()} a host's operator endpoint calls, synchronously,
 * so the outcome prints here.
 */
class ReplaySubmissionNotificationsCommand extends Command
{
    protected $signature = 'schema-forms:replay-notifications
        {--form= : Only replay entries for this form key}
        {--max-attempts= : Skip entries already attempted this many times (defaults to config)}';

    protected $description = 'Redeliver unsent form-notification outbox entries.';

    public function handle(OutboxDelivery $delivery): int
    {
        $summary = $delivery->replayUnsent(
            maxAttempts: $this->option('max-attempts') !== null ? (int) $this->option('max-attempts') : null,
            formKey: $this->option('form'),
        );

        $this->components->info(sprintf(
            'Replayed %d outbox entr%s — %d sent, %d still failing.',
            $summary['replayed'],
            $summary['replayed'] === 1 ? 'y' : 'ies',
            $summary['sent'],
            $summary['failed'],
        ));

        return self::SUCCESS;
    }
}
