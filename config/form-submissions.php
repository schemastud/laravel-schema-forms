<?php

use Rushing\FormSubmissions\Models\FormSubmission;

return [
    /*
    |--------------------------------------------------------------------------
    | Models (Spatie swappable-model pattern)
    |--------------------------------------------------------------------------
    |
    | A consuming app may extend the base FormSubmission (add columns/relations)
    | and point this at its own subclass without touching the package.
    |
    */
    'models' => [
        'form_submission' => FormSubmission::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    |
    | "shared" means shared CODE, not a shared database — every app that
    | consumes this leaf gets its own table. Rename it here to avoid colliding
    | with an app's existing tables.
    |
    */
    'table_names' => [
        'form_submissions' => 'form_submissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration registration (multi-tenancy seam)
    |--------------------------------------------------------------------------
    |
    | true (default): auto-load the migration as a CENTRAL migration — correct
    | for single-tenant satellites and for central splicewire-app.
    |
    | false: do NOT register it. A multi-tenant / broker satellite publishes it
    | into its per-tenant migration set instead
    | (`vendor:publish --tag=form-submissions-migrations`) so each customer's
    | submissions live in their own schema.
    |
    */
    'register_migrations' => true,
];
