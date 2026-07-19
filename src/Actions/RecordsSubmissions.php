<?php

namespace Splicewire\SchemaForms\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Splicewire\Beam\Models\BeamSubmission;
use Splicewire\SchemaForms\Events\SubmissionReceived;
use Splicewire\SchemaForms\Models\FormSubmission;

/**
 * The re-based store orchestrator (spec §3): the store verb collapses onto beam. Synchronously,
 * in tenant context, it
 *
 *   1. persists the submission as a beam SchemaRecord (the {@see FormSubmission} populator — form_key
 *      / context / schema_ref / payload), then
 *   2. creates a generic {@see BeamSubmission} REFERENCE pointing at that record (composition, not
 *      inheritance — the record bears the payload, the reference bears the submission facets), then
 *   3. fires the post-persist signals.
 *
 * Store is direct + synchronous (engine -> substrate is the blessed edge, mirroring frame -> beam;
 * a "received but not persisted" state is a worse failure than a synchronous 500). It performs NO
 * validation (the door owns that) and knows nothing about notification — persistence completes
 * before any signal fires, so a reactive listener only ever runs against an already-durable record.
 *
 * Two signals fire, both post-persist:
 *   - `BeamSubmission::created` (the ordinary Eloquent event) — the generic substrate signal the
 *     notify seam re-homes onto (a separate ticket).
 *   - {@see SubmissionReceived} — the existing schema-forms notify signal, kept firing so the
 *     current (out-of-scope) notification path stays green through the beam collapse.
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

        // The submission record — a beam SchemaRecord populated by the form intake. schema_ref is a
        // plain string; beam stays schema-source-agnostic.
        $submission = $model::query()->create([
            'form_key' => $formKey,
            'schema_ref' => $schemaRef,
            'payload' => $payload,
            'context' => $context,
            'user_id' => $userId,
        ]);

        // The generic reference: a beam-native BeamSubmission pointing at the record, carrying only
        // generic submission facets (never form_key — the record already bears its schema). Its
        // `created` event is the substrate submission signal.
        /** @var class-string<BeamSubmission> $submissionModel */
        $submissionModel = config('beam.models.submission', BeamSubmission::class);

        $submissionModel::query()->create([
            'schema_record_id' => $submission->getKey(),
            'submitted_by' => $userId,
            'submitted_at' => now(),
            'source' => $context['url'] ?? null,
            'channel' => 'form',
            'context' => $context,
        ]);

        $this->events->dispatch(new SubmissionReceived($submission));

        return $submission;
    }
}
