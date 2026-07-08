<?php

namespace Rushing\SchemaForms\Validation;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validates a form payload against its JSON-Schema document with opis/json-schema (the
 * same engine central's ValidatesTypedPayload uses). Returns the formatted error map;
 * an empty array means the payload is valid. Kept transport-agnostic — the caller decides
 * how to surface a failure (the satellite controller raises a 422).
 */
class SchemaValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed> Formatted opis errors, empty when valid.
     */
    public function validate(array $payload, array $schema): array
    {
        // opis requires a root `$id` to be an absolute URI. Our form docs use a
        // human-readable relative `$id` (`waitlist/1`) purely as the stored schema_ref,
        // so drop a non-absolute one before validation — opis then assigns its own.
        if (isset($schema['$id']) && ! str_contains((string) $schema['$id'], '://')) {
            unset($schema['$id']);
        }

        // An empty schema means "no constraints" (JSON Schema treats `{}` as accept-all).
        // Encode `[]` as the object `{}`, not the JSON array `[]` (which opis rejects as an
        // invalid schema and throws) — so a host that resolves no schema for a form key
        // degrades to accept-all instead of a 500 on every submission.
        $schemaObject = json_decode(json_encode($schema ?: new \stdClass), false);
        $payloadObject = json_decode(json_encode($payload ?: new \stdClass), false);

        $result = (new Validator)->validate($payloadObject, $schemaObject);

        if ($result->isValid()) {
            return [];
        }

        return (new ErrorFormatter)->format($result->error());
    }
}
