<?php

use Schemastud\Beam\Concerns\PersistsSchemaRecord;
use Schemastud\Beam\Models\BeamSubmission;
use Splicewire\SchemaForms\Actions\RecordsSubmissions;
use Splicewire\SchemaForms\Models\FormSubmission;

it('persists a submission as a beam SchemaRecord via the third populator', function () {
    // The submission record IS a beam SchemaRecord: it composes PersistsSchemaRecord (the third
    // populator, peer to generation and manual-edit).
    expect(in_array(PersistsSchemaRecord::class, class_uses_recursive(FormSubmission::class), true))->toBeTrue();

    $submission = app(RecordsSubmissions::class)->record(
        formKey: 'waitlist',
        schemaRef: 'waitlist/1',
        payload: ['name' => 'Jane', 'email' => 'jane@example.com'],
        context: ['url' => 'https://numero.test/join', 'ip' => '203.0.113.7'],
    );

    // uuid7 primary key from beam's trait (time-ordered, 36-char).
    expect($submission->getKey())->toBeString()
        ->and($submission->getKey())->toMatch('/^[0-9a-f-]{36}$/')
        ->and($submission->schema_ref)->toBe('waitlist/1')
        ->and($submission->payload)->toBe(['name' => 'Jane', 'email' => 'jane@example.com']);
});

it('creates a BeamSubmission reference by composition, not inheritance', function () {
    $submission = app(RecordsSubmissions::class)->record(
        formKey: 'contact',
        schemaRef: 'contact',
        payload: ['message' => 'hi'],
        context: ['url' => 'https://x.test/contact', 'ip' => '198.51.100.4'],
        userId: null,
    );

    // Exactly one generic reference, pointing AT the record (composition, not a SchemaRecord subclass).
    expect(BeamSubmission::count())->toBe(1);

    $ref = BeamSubmission::first();
    expect($ref)->not->toBeInstanceOf(FormSubmission::class)
        ->and($ref->schema_record_id)->toBe($submission->getKey())
        ->and($ref->channel)->toBe('form')
        ->and($ref->context)->toBe(['url' => 'https://x.test/contact', 'ip' => '198.51.100.4'])
        // submitted_by is nullable — public forms are anonymous.
        ->and($ref->submitted_by)->toBeNull()
        // the generic reference bears NO form_key (the record carries its schema_ref).
        ->and($ref->getAttributes())->not->toHaveKey('form_key');
});

it('keeps schema_ref a plain string so beam stays schema-source-agnostic', function () {
    // A file-registry key with no Data class behind it — still a plain string ref.
    $submission = app(RecordsSubmissions::class)->record(
        formKey: 'contact',
        schemaRef: 'contact',
        payload: ['message' => 'hi'],
    );

    expect($submission->schema_ref)->toBeString()->toBe('contact');
});
