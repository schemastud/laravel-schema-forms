<?php

namespace Splicewire\SchemaForms\Data;

use Schemastud\DataSchemas\Contracts\SchemaIdentity;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\SchemaForms\Keywords;

/**
 * Where a submission's notification should go, built from the form schema's
 * `x-swf-notify` keyword (`{"x-swf-notify": {"to": "...", "subject": "...", "channel": "..."}}`)
 * with a config-level fallback (`schema-forms.default_notify`). The keyword is
 * owned/declared by {@see Keywords} — prefixed `x-swf-*` because notification is this
 * engine's own submission/outbox machinery (tier doctrine, issue 04).
 *
 * A platform Data object: it emits a TypeScript type (#[TypeScript]) and a JSON Schema
 * (SchemaIdentity) from one declaration, so the object contract and its artifacts never
 * drift.
 *
 * The schema constructor is named `forSchema`, NOT `fromSchema`: Spatie routes the generic
 * `NotifyIntent::from($array)` to any magic `from*` method whose first parameter accepts the
 * payload, so a `fromSchema(array)` hijacks `from()` and silently mis-hydrates a flat
 * `{to,subject,channel}` array (returning nulls, since it looks for `x-swf-notify`). Keeping the
 * name off the `from` prefix leaves `from()` on Spatie's standard property mapping — which the
 * outbox relies on when it rebuilds the intent from a stored snapshot on replay.
 */
#[TypeScript]
class NotifyIntent extends Data implements SchemaIdentity
{
    public function __construct(
        public ?string $to = null,
        public ?string $subject = null,
        public string $channel = 'mail',
    ) {}

    /**
     * Build from a form schema's `x-swf-notify`, falling back per-key to the config default.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $default
     */
    public static function forSchema(array $schema, array $default = []): self
    {
        $notify = is_array($schema[Keywords::Notify] ?? null) ? $schema[Keywords::Notify] : [];

        return new self(
            to: $notify['to'] ?? ($default['to'] ?? null),
            subject: $notify['subject'] ?? ($default['subject'] ?? null),
            channel: $notify['channel'] ?? ($default['channel'] ?? 'mail'),
        );
    }

    public static function schemaName(): string
    {
        return 'schema-form/notify-intent';
    }

    public static function schemaVersion(): int
    {
        return 1;
    }
}
