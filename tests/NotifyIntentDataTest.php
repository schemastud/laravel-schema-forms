<?php

use Rushing\SchemaForms\Data\NotifyIntent;
use Rushing\SchemaForms\Keywords;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

it('declares a stable, versioned schema identity', function () {
    expect(NotifyIntent::schemaName())->toBe('schema-form/notify-intent')
        ->and(NotifyIntent::schemaVersion())->toBe(1);
});

it('marks the platform data object for TypeScript generation', function () {
    $attributes = (new ReflectionClass(NotifyIntent::class))->getAttributes(TypeScript::class);

    expect($attributes)->not->toBeEmpty();
});

it('builds a notify intent from the x-notify keyword, config filling the gaps', function () {
    $intent = NotifyIntent::fromSchema(
        [Keywords::Notify => ['to' => 'ops@example.com']],
        ['subject' => 'Fallback subject', 'channel' => 'mail'],
    );

    expect($intent->to)->toBe('ops@example.com')
        ->and($intent->subject)->toBe('Fallback subject')
        ->and($intent->channel)->toBe('mail');
});

it('falls back entirely to config when x-notify is absent', function () {
    $intent = NotifyIntent::fromSchema([], ['to' => 'default@example.com']);

    expect($intent->to)->toBe('default@example.com')
        ->and($intent->channel)->toBe('mail');
});
