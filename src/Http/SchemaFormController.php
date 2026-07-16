<?php

namespace Splicewire\SchemaForms\Http;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Splicewire\SchemaForms\Actions\RecordsSubmissions;
use Splicewire\SchemaForms\Data\SubmissionResponseData;
use Splicewire\SchemaForms\Registry\FileSchemaFormRegistry;
use Splicewire\SchemaForms\Validation\SchemaValidator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public form runtime door: `POST /schema-forms/{form}`. Folded DOWN from the retired
 * `laravel-satellite-schema-form` — the door is generic (any host with file-based forms wants it),
 * never satellite-specific.
 *
 * Flow: resolve schema (404 on unknown form) -> honeypot check (`website` field, silently succeed)
 * -> validate the payload against the JSON Schema via opis {@see SchemaValidator} (422 on
 * violation, the untrusted-input security boundary) -> record via the beam-collapsed store. Opis
 * validation STAYS in the door (spec §5); beam's persist does not re-validate. Notification is NOT
 * done here — it is a listener on the store's post-persist signal, so persistence always precedes
 * it. Throttling is applied at the route (`throttle:5,1`).
 */
class SchemaFormController
{
    /** The honeypot field: filled means a bot, so silently succeed without persisting. */
    private const HONEYPOT = 'website';

    public function __invoke(Request $request, string $form, FileSchemaFormRegistry $registry, RecordsSubmissions $store, SchemaValidator $validator): JsonResponse
    {
        $schema = $registry->find($form);

        if ($schema === null) {
            throw new NotFoundHttpException("Unknown form [{$form}].");
        }

        // Honeypot: return an ordinary 201 so a bot sees success, but store nothing.
        if ($request->filled(self::HONEYPOT)) {
            return SubmissionResponseData::from([
                'id' => (string) Str::uuid(),
                'formKey' => $form,
            ])->toResponse($request)->setStatusCode(201);
        }

        $payload = $request->except(self::HONEYPOT);

        $errors = $validator->validate($payload, $schema);

        if ($errors !== []) {
            throw new HttpResponseException(new JsonResponse([
                'message' => "The submission for form [{$form}] is invalid.",
                'errors' => $errors,
            ], 422));
        }

        $submission = $store->record(
            formKey: $form,
            schemaRef: $registry->schemaRef($form, $schema),
            payload: $payload,
            context: [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            userId: $request->user()?->id,
        );

        return SubmissionResponseData::from([
            'id' => $submission->id,
            'formKey' => $form,
        ])->toResponse($request)->setStatusCode(201);
    }
}
