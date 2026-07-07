<?php

namespace Rushing\SchemaForms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared "a form was submitted" record. Domain-agnostic: it knows a form key,
 * the schema it validated against (if any), the submitted payload, arrival context,
 * and an optional user — nothing about mail, circuits, or any vertical vocabulary.
 *
 * Not final — a consuming app may extend it (add columns/relations) and point
 * `config('schema-forms.models.form_submission')` at the subclass (Spatie
 * swappable-model pattern). Table name is read from config at runtime.
 */
class FormSubmission extends Model
{
    use HasUuids;

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
