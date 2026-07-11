<?php

namespace Rushing\SchemaForms\Contracts;

use Rushing\SchemaForms\Registry\ArraySchemaRegistry;

/**
 * Resolves a form key to its JSON-Schema document (including any `x-swf-notify` keyword),
 * so the notification listener can rebuild a NotifyIntent after persistence without the
 * base package knowing HOW schemas are stored.
 *
 * The base ships a config-backed {@see ArraySchemaRegistry}
 * (forms declared inline in `schema-forms.forms`). A satellite rebinds this to a
 * file-based registry; another host could back it with a database. `find` returns the
 * decoded schema array, or null when the key is unknown (the listener then falls back to
 * the config-level `default_notify`).
 */
interface SchemaRegistry
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(string $formKey): ?array;
}
