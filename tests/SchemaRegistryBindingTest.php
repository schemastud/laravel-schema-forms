<?php

use Splicewire\SchemaForms\Contracts\SchemaRegistry;
use Splicewire\SchemaForms\Registry\ArraySchemaRegistry;
use Splicewire\SchemaForms\SchemaFormsServiceProvider;

/**
 * Regression: the base provider must bind its default registry with `bindIf`, not `bind`,
 * so a satellite/host rebinding survives. Auto-discovery registers providers
 * alphabetically — `laravel-satellite-schema-form` loads *before* `laravel-schema-forms`,
 * so the satellite's file-based registry is bound first and the base provider registers
 * second. A plain `bind` clobbered it, silently starving the persist-then-notify listener
 * of the schema's `x-swf-notify` (relay never fired).
 */
it('defers to a SchemaRegistry a satellite/host bound first (bindIf, not bind)', function () {
    // Stand in for the satellite provider that auto-discovery loads first.
    $satelliteRegistry = new class implements SchemaRegistry
    {
        public function find(string $formKey): ?array
        {
            return ['$id' => 'sentinel/1'];
        }
    };
    app()->instance(SchemaRegistry::class, $satelliteRegistry);

    // Re-run the base provider's register() — it must not overwrite the prior binding.
    (new SchemaFormsServiceProvider(app()))->register();

    expect(app(SchemaRegistry::class))->toBe($satelliteRegistry)
        ->and(app(SchemaRegistry::class))->not->toBeInstanceOf(ArraySchemaRegistry::class);
});

it('still binds the config-backed default when nothing else has (bindIf falls through)', function () {
    // Nothing pre-bound here besides the default the TestCase's provider already set;
    // forget it so the fresh register() is the one that binds.
    app()->forgetInstance(SchemaRegistry::class);
    app()->offsetUnset(SchemaRegistry::class);

    (new SchemaFormsServiceProvider(app()))->register();

    expect(app(SchemaRegistry::class))->toBeInstanceOf(ArraySchemaRegistry::class);
});
