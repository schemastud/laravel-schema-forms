<?php

use Rushing\SchemaForms\Models\FormSubmission;
use Rushing\SchemaForms\Models\SubmissionNotification;
use Rushing\SchemaForms\Notifiers\MailSubmissionNotifier;

return [
    /*
    |--------------------------------------------------------------------------
    | Models (Spatie swappable-model pattern)
    |--------------------------------------------------------------------------
    |
    | A consuming app may extend either model (add columns/relations) and point
    | this at its own subclass without touching the package.
    |
    */
    'models' => [
        'form_submission' => FormSubmission::class,
        'submission_notification' => SubmissionNotification::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    |
    | "shared" means shared CODE, not a shared database — every app that consumes
    | this package gets its own tables. Rename here to avoid colliding with an
    | app's existing tables.
    |
    */
    'table_names' => [
        'form_submissions' => 'form_submissions',
        'submission_notifications' => 'submission_notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration registration (multi-tenancy seam)
    |--------------------------------------------------------------------------
    |
    | true (default): auto-load the migrations as CENTRAL migrations — correct for
    | single-tenant satellites and for central splicewire-app.
    |
    | false: do NOT register them. A multi-tenant / broker publishes them into its
    | per-tenant migration set instead
    | (`vendor:publish --tag=schema-forms-migrations`) so each customer's
    | submissions and notification records live in their own schema.
    |
    */
    'register_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Forms (config-backed registry)
    |--------------------------------------------------------------------------
    |
    | The default ArraySchemaRegistry resolves a form key to its JSON-Schema
    | document from this map — enough for a host that declares a handful of forms
    | inline (e.g. central's `waitlist`) without a file-based registry. A satellite
    | rebinds the SchemaRegistry contract to a file-based registry instead.
    |
    */
    'forms' => [],

    /*
    |--------------------------------------------------------------------------
    | Submission notifier (the tenant-swappable seam)
    |--------------------------------------------------------------------------
    |
    | The bound SubmissionNotifier contract class. The default MailSubmissionNotifier
    | works out of the box. A host that wants Slack/webhook/CRM/custom-mail either
    | points this at its own invocable class OR rebinds the SubmissionNotifier
    | contract in its own provider — rebinding fully replaces the default.
    |
    */
    'notifier' => MailSubmissionNotifier::class,

    /*
    |--------------------------------------------------------------------------
    | Default notify intent (config-level fallback)
    |--------------------------------------------------------------------------
    |
    | Used when a form schema omits `x-swf-notify` (or omits individual keys). A form's
    | own `x-swf-notify` keyword always wins per key.
    |
    */
    'default_notify' => [
        'to' => env('SCHEMA_FORM_NOTIFY_TO'),
        'subject' => 'New form submission',
        'channel' => 'mail',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification outbox (track + replay)
    |--------------------------------------------------------------------------
    |
    | enabled (default true): every notification is routed through a durable
    | SubmissionNotification row (pending -> sent/failed) BEFORE the bound notifier
    | delivers, so a site can see what was attempted and REPLAY failures once mail
    | is set up. The submission is already persisted when this runs, so the outbox
    | never gates capture. Set false to deliver straight through with no ledger.
    |
    | The table follows the same `register_migrations` tenancy seam as the base
    | submissions table (central by default; per-tenant for brokers).
    |
    */
    'outbox' => [
        'enabled' => env('SCHEMA_FORM_OUTBOX', true),
        // Replay skips an entry once it has been attempted this many times.
        'max_attempts' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Central relay (turnkey delivery seam)
    |--------------------------------------------------------------------------
    |
    | Point `notifier` at CentralRelayNotifier::class to have a host FORWARD its
    | submission notifications to a central Splicewire instance that owns the mail
    | transport — so a freshly-spun site sends without its own per-domain provider.
    | Unset `url` = the seam is dark: the relay throws, the outbox records it failed,
    | and it replays once the URL is configured.
    |
    */
    'central_relay' => [
        'url' => env('SCHEMA_FORM_CENTRAL_RELAY_URL'),
        'token' => env('SCHEMA_FORM_CENTRAL_RELAY_TOKEN'),
    ],
];
