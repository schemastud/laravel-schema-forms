<?php

use Schemastud\Beam\Models\BeamSubmission;
use Splicewire\SchemaForms\Models\FormSubmission;

beforeEach(function () {
    // A file-based form definition the folded-down door + registry resolve.
    $dir = sys_get_temp_dir().'/schema-forms-door-'.uniqid();
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/contact.json', json_encode([
        '$id' => 'contact/1',
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['email'],
        'properties' => [
            'email' => ['type' => 'string', 'format' => 'email'],
            'message' => ['type' => 'string'],
        ],
    ]));

    config()->set('schema-forms.schema_path', $dir);
    $this->schemaDir = $dir;
});

afterEach(function () {
    @unlink($this->schemaDir.'/contact.json');
    @rmdir($this->schemaDir);
});

it('records a valid submission through the public door and collapses it onto beam', function () {
    $this->postJson('/schema-forms/contact', [
        'email' => 'jane@example.com',
        'message' => 'hello',
    ])->assertStatus(201)
        ->assertJsonPath('formKey', 'contact');

    expect(FormSubmission::count())->toBe(1)
        ->and(BeamSubmission::count())->toBe(1)
        ->and(FormSubmission::first()->schema_ref)->toBe('contact/1');
});

it('rejects an invalid submission with 422 — opis validation stays in the door', function () {
    $this->postJson('/schema-forms/contact', [
        'email' => 'not-an-email',
    ])->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);

    // Nothing persisted — the door is the untrusted-input boundary; beam never saw it.
    expect(FormSubmission::count())->toBe(0)
        ->and(BeamSubmission::count())->toBe(0);
});

it('404s an unknown form', function () {
    $this->postJson('/schema-forms/does-not-exist', ['x' => 1])->assertStatus(404);
});

it('silently succeeds on a honeypot hit without persisting', function () {
    $this->postJson('/schema-forms/contact', [
        'email' => 'jane@example.com',
        'website' => 'http://spam.example',
    ])->assertStatus(201);

    expect(FormSubmission::count())->toBe(0)
        ->and(BeamSubmission::count())->toBe(0);
});
