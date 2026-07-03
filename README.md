# laravel-form-submissions

A domain-agnostic **base leaf** owning one concept: *a form was submitted*. It ships a
swappable `FormSubmission` model, a `RecordsSubmissions` action that persists a submission
and dispatches a `SubmissionReceived` event, and nothing else — no mail, no routes, no
circuits, no vertical vocabulary. "Shared" means shared **code**: every consuming app gets
its own table.

Consumed by central splicewire-app and by `rushing/laravel-splicewire-satellite-schema-form`.

## The store

```php
use Rushing\FormSubmissions\Actions\RecordsSubmissions;

$submission = app(RecordsSubmissions::class)->record(
    formKey: 'waitlist',
    schemaRef: 'waitlist/1',   // the schema slug/version the payload validated against (nullable)
    payload: ['name' => 'Jane', 'email' => 'jane@example.com'],
    context: ['url' => $request->fullUrl(), 'ip' => $request->ip()],
    userId: $request->user()?->id,
);
```

The action performs **no validation** (the caller owns that) and knows nothing about
notification. Persistence completes *before* `SubmissionReceived` fires, so any reactive
listener (notification, forwarding, enrichment) runs only against an already-durable
record — a failing or absent listener can never lose a submission.

## Config (`config/form-submissions.php`)

- `models.form_submission` — swap in a subclass (Spatie swappable-model pattern).
- `table_names.form_submissions` — rename the table to avoid collisions.
- `register_migrations` — `true` (default) auto-loads the migration as a central migration;
  set `false` on a multi-tenant broker and publish it into the per-tenant migration set
  (`vendor:publish --tag=form-submissions-migrations`).

## Install

```bash
php artisan form-submissions:install
```

Publishes config + migration and migrates. Single-tenant apps that keep
`register_migrations=true` only need `--tag=form-submissions-config`.
