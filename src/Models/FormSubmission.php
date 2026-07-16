<?php

namespace Splicewire\SchemaForms\Models;

use Illuminate\Database\Eloquent\Model;
use Schemastud\Beam\Concerns\PersistsSchemaRecord;

/**
 * The submission populator's record — a form submission IS a beam SchemaRecord (spec §1, the third
 * populator, peer to generation's `PersistsGeneratedComposition` and the frame editor's manual
 * edit). It composes beam's {@see PersistsSchemaRecord} — the narrow, populator-agnostic skeleton
 * (uuid7 primary key, `payload`/`meta` casts, the inert `extract()` seam) — and layers ONLY the
 * form-runtime-specific facets over it (composition, not inheritance):
 *
 *   (from beam)  payload, meta (json) · uuid7 id · extract()
 *   (overlay)    form_key · context (json) · schema_ref (the string schema identity, §7)
 *
 * `schema_ref` is a plain string (a form-def `$id`/key straight from the file registry, NOT a
 * SchemaIdentity object) — that is what keeps beam schema-source-agnostic.
 *
 * Not final — a consuming app may extend it (add columns/relations, pin a connection) and point
 * `config('schema-forms.models.form_submission')` at the subclass (Spatie swappable-model pattern).
 * Table name is read from config at runtime.
 */
class FormSubmission extends Model
{
    use PersistsSchemaRecord;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'context' => 'array',
    ];

    public function getTable(): string
    {
        return config('schema-forms.table_names.form_submissions', 'form_submissions');
    }
}
