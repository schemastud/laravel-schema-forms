<?php

use Splicewire\SchemaForms\Validation\SchemaValidator;

$schema = [
    '$id' => 'waitlist/1',
    'type' => 'object',
    'additionalProperties' => false,
    'required' => ['name', 'email'],
    'properties' => [
        'name' => ['type' => 'string', 'minLength' => 1],
        'email' => ['type' => 'string', 'format' => 'email'],
    ],
];

it('returns no errors for a valid payload', function () use ($schema) {
    $errors = (new SchemaValidator)->validate(
        ['name' => 'Jane', 'email' => 'jane@example.com'],
        $schema,
    );

    expect($errors)->toBe([]);
});

it('returns errors for an invalid payload', function () use ($schema) {
    $errors = (new SchemaValidator)->validate(
        ['email' => 'not-an-email'],
        $schema,
    );

    expect($errors)->not->toBe([]);
});

it('treats an empty schema as accept-all instead of throwing', function () {
    // A host that resolves no schema for a form key passes `[]`; that must degrade to
    // accept-all, not a 500 (opis rejects the JSON array `[]` as an invalid schema).
    $errors = (new SchemaValidator)->validate(['email' => 'anything'], []);

    expect($errors)->toBe([]);
});
