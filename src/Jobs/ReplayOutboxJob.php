<?php

namespace Rushing\SchemaForms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Rushing\SchemaForms\Outbox\OutboxDelivery;

/**
 * The queueable wrapper for the "back-send the failures once mail is configured"
 * operator action. The redelivery logic itself lives in
 * {@see OutboxDelivery::replayUnsent()} so it can be driven identically from this
 * job (async / scheduled), the console command, and a host's operator endpoint —
 * the last two call the service directly for an immediate, reliable summary (a
 * dispatched job runs on a serialized copy, so its result is not readable by the
 * dispatcher).
 */
class ReplayOutboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ?int $maxAttempts = null,
        public ?string $formKey = null,
    ) {}

    public function handle(OutboxDelivery $delivery): void
    {
        $delivery->replayUnsent($this->maxAttempts, $this->formKey);
    }
}
