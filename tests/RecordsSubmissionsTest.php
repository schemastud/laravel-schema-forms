<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Rushing\FormSubmissions\Actions\RecordsSubmissions;
use Rushing\FormSubmissions\Events\SubmissionReceived;
use Rushing\FormSubmissions\Models\FormSubmission;

it('persists a submission and fires SubmissionReceived', function () {
    Event::fake([SubmissionReceived::class]);

    $submission = app(RecordsSubmissions::class)->record(
        formKey: 'waitlist',
        schemaRef: 'waitlist/1',
        payload: ['name' => 'Jane', 'email' => 'jane@example.com'],
        context: ['url' => 'https://numero.test/join'],
        userId: null,
    );

    expect(FormSubmission::count())->toBe(1)
        ->and($submission->form_key)->toBe('waitlist')
        ->and($submission->schema_ref)->toBe('waitlist/1')
        ->and($submission->payload)->toBe(['name' => 'Jane', 'email' => 'jane@example.com'])
        ->and($submission->context)->toBe(['url' => 'https://numero.test/join']);

    Event::assertDispatched(SubmissionReceived::class, fn (SubmissionReceived $e) => $e->submission->is($submission));
});

it('writes to the config-driven table name', function () {
    config()->set('form-submissions.table_names.form_submissions', 'form_submissions');

    app(RecordsSubmissions::class)->record('contact', null, ['message' => 'hi']);

    expect(app(FormSubmission::class)->getTable())->toBe('form_submissions')
        ->and(FormSubmission::first()->schema_ref)->toBeNull();
});

it('resolves the model through swappable-model config', function () {
    config()->set('form-submissions.models.form_submission', CustomSubmission::class);

    $submission = app(RecordsSubmissions::class)->record('demo', null, ['ok' => true]);

    expect($submission)->toBeInstanceOf(CustomSubmission::class);
});

class CustomSubmission extends FormSubmission {}
