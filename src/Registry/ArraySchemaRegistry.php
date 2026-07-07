<?php

namespace Rushing\SchemaForms\Registry;

use Rushing\SchemaForms\Contracts\SchemaRegistry;

/**
 * The default registry: resolves a form key against an in-memory map of decoded
 * JSON-Schema documents (the base binds it from `schema-forms.forms`). Enough for a host
 * that declares a handful of forms inline — e.g. central's `waitlist` — without a
 * file-based store. A satellite rebinds {@see SchemaRegistry} to a file-based registry.
 */
class ArraySchemaRegistry implements SchemaRegistry
{
    /**
     * @param  array<string, array<string, mixed>>  $forms
     */
    public function __construct(private array $forms = []) {}

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $formKey): ?array
    {
        $schema = $this->forms[$formKey] ?? null;

        return is_array($schema) ? $schema : null;
    }
}
