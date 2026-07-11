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

it('builds a notify intent from the x-swf-notify keyword, config filling the gaps', function () {
    $intent = NotifyIntent::forSchema(
        [Keywords::Notify => ['to' => 'ops@example.com']],
        ['subject' => 'Fallback subject', 'channel' => 'mail'],
    );

    expect($intent->to)->toBe('ops@example.com')
        ->and($intent->subject)->toBe('Fallback subject')
        ->and($intent->channel)->toBe('mail');
});

it('falls back entirely to config when x-swf-notify is absent', function () {
    $intent = NotifyIntent::forSchema([], ['to' => 'default@example.com']);

    expect($intent->to)->toBe('default@example.com')
        ->and($intent->channel)->toBe('mail');
});

it('round-trips a flat intent array through from() without the schema constructor hijacking it', function () {
    // The outbox rebuilds the intent from a stored snapshot on replay via NotifyIntent::from().
    // The schema constructor is `forSchema` (not `fromSchema`) precisely so it does NOT hijack
    // this call and drop to/subject.
    $intent = NotifyIntent::from([
        'to' => 'ops@example.com',
        'subject' => 'New submission',
        'channel' => 'slack',
    ]);

    expect($intent->to)->toBe('ops@example.com')
        ->and($intent->subject)->toBe('New submission')
        ->and($intent->channel)->toBe('slack');
});
