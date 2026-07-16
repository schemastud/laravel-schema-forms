<?php

namespace Splicewire\SchemaForms\Data;

use Schemastud\DataSchemas\Contracts\SchemaIdentity;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The door's success payload — the single contract a host's React form types its success handling
 * against. A platform Data object: it emits a TypeScript type (#[TypeScript]) and a JSON Schema
 * (SchemaIdentity) from one declaration.
 *
 * `id` is the persisted submission id; on a silently-rejected honeypot it is a throwaway uuid so a
 * bot sees an ordinary 201 while nothing is stored.
 */
#[TypeScript]
class SubmissionResponseData extends Data implements SchemaIdentity
{
    public function __construct(
        public string $id,
        public string $formKey,
        public string $status = 'received',
    ) {}

    public static function schemaName(): string
    {
        return 'schema-form/submission-response';
    }

    public static function schemaVersion(): int
    {
        return 1;
    }
}
