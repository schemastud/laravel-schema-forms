<?php

namespace Rushing\SchemaForms\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Rushing\SchemaForms\Events\SubmissionReceived;
use Rushing\SchemaForms\Models\FormSubmission;

/**
 * The single deep interface of the base store: persist a {@see FormSubmission}
 * from a form key, schema ref, payload, and context, then dispatch
 * {@see SubmissionReceived}. It performs NO validation (the caller owns that) and
 * knows nothing about notification — persistence completes before the event fires,
 * so any reactive listener runs only against an already-durable record.
 */
class RecordsSubmissions
{
    public function __construct(private Dispatcher $events) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $formKey,
        ?string $schemaRef,
        array $payload,
        array $context = [],
        ?string $userId = null,
    ): FormSubmission {
        /** @var class-string<FormSubmission> $model */
        $model = config('schema-forms.models.form_submission', FormSubmission::class);

        $submission = $model::query()->create([
            'form_key' => $formKey,
            'schema_ref' => $schemaRef,
            'payload' => $payload,
            'context' => $context,
            'user_id' => $userId,
        ]);

        $this->events->dispatch(new SubmissionReceived($submission));

        return $submission;
    }
}
