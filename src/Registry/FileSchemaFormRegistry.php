<?php

namespace Splicewire\SchemaForms\Registry;

use Splicewire\SchemaForms\Contracts\SchemaRegistry;

/**
 * Resolves a form definition by key from published JSON-Schema documents under a configurable
 * `schema_path` (files, not a DB table — keeps hosts light). A form key maps to
 * `<schema_path>/<key>.json`; the returned array is the decoded schema document (including any
 * `x-swf-notify` keyword).
 *
 * Folded DOWN into the engine package from the retired `laravel-satellite-schema-form`, with the
 * config generalised off `splicewire.*` onto `schema-forms.*` — it was always generic (any host
 * with file-based forms wants it), never satellite-specific. Implements the {@see SchemaRegistry}
 * seam, so the persist-then-notify listener resolves `x-swf-notify` from these files.
 */
class FileSchemaFormRegistry implements SchemaRegistry
{
    public function __construct(private string $schemaPath) {}

    public static function fromConfig(): static
    {
        return new static((string) config('schema-forms.schema_path', ''));
    }

    public function has(string $key): bool
    {
        return $this->find($key) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        if (! $this->isValidKey($key)) {
            return null;
        }

        $file = rtrim($this->schemaPath, '/').'/'.$key.'.json';

        if (! is_file($file)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * The stored `schema_ref` for a resolved form: the schema's `$id` if it declares one, else the
     * form key. Lets a submission record which schema/version it validated against — as a plain
     * string, keeping beam schema-source-agnostic.
     *
     * @param  array<string, mixed>  $schema
     */
    public function schemaRef(string $key, array $schema): string
    {
        $id = $schema['$id'] ?? null;

        return is_string($id) && $id !== '' ? $id : $key;
    }

    private function isValidKey(string $key): bool
    {
        return $key !== '' && preg_match('/^[a-z0-9][a-z0-9\-_]*$/i', $key) === 1;
    }
}
