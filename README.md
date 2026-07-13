# laravel-schema-forms

The host-agnostic **schema-form primitive**: a JSON-Schema-defined form is *validated*,
*stored*, and *optionally notified* — with reliable, replayable delivery. It owns the
submission ledger, opis validation, the swappable `SubmissionNotifier` seam, the
notification **outbox** (track + replay), and the `x-swf-notify` keyword. No routes, no HTTP,
no vertical vocabulary — a host mounts the delivery surface on top.

Consumed by central splicewire-app (leads) and by
`splicewire/laravel-satellite-schema-form` (the satellite HTTP delivery layer).

> Not to be confused with `schemastud/laravel-data-schemas`, which *generates* JSON Schema
> from Data classes. This package *consumes* JSON schemas to validate and store forms.

## The store

```php
use Splicewire\SchemaForms\Actions\RecordsSubmissions;

$submission = app(RecordsSubmissions::class)->record(
    formKey: 'waitlist',
    schemaRef: 'waitlist/1',   // the schema slug/version the payload validated against (nullable)
    payload: ['name' => 'Jane', 'email' => 'jane@example.com'],
    context: ['url' => $request->fullUrl(), 'ip' => $request->ip()],
    userId: $request->user()?->id,
);
```

`RecordsSubmissions` performs **no validation** (the caller owns that — see `SchemaValidator`)
and knows nothing about notification. Persistence completes *before* `SubmissionReceived`
fires, so any reactive listener runs only against an already-durable record — a failing or
absent listener can never lose a submission.

## Notification (the seam)

`SubmissionReceived` is handled by `NotifyOnSubmission`, which rebuilds a `NotifyIntent`
from the form schema's `x-swf-notify` (resolved through the `SchemaRegistry` contract, with a
`schema-forms.default_notify` fallback) and invokes the bound `SubmissionNotifier`.

- Default binding: `MailSubmissionNotifier`. Swap it by pointing `schema-forms.notifier` at
  your class, or rebind the `SubmissionNotifier` contract in your provider.
- With `outbox.enabled` (default), every send is wrapped by `OutboxSubmissionNotifier`: a
  `SubmissionNotification` row is written `pending` → `sent`/`failed`, so a host can see what
  was attempted and **replay** failures once mail is configured
  (`php artisan schema-forms:replay-notifications`).
- `CentralRelayNotifier` forwards to a central instance that owns the mail transport, so a
  freshly-spun host notifies without its own provider.

## The registry seam

`SchemaRegistry::find(string $formKey): ?array` resolves a form key to its JSON-Schema
document. The base binds a config-backed `ArraySchemaRegistry` (`schema-forms.forms`); a
satellite rebinds it to a file-based registry, another host to a DB-backed one.

## Config (`config/schema-forms.php`)

- `models.form_submission` / `models.submission_notification` — swap in subclasses (Spatie pattern).
- `table_names.*` — rename the tables to avoid collisions.
- `register_migrations` — `true` (default) auto-loads both migrations centrally; set `false`
  on a multi-tenant broker and publish them into the per-tenant set
  (`vendor:publish --tag=schema-forms-migrations`).
- `forms` — inline form schemas for the default `ArraySchemaRegistry`.
- `notifier`, `default_notify`, `outbox`, `central_relay` — the notification seam.

## Install

```bash
php artisan schema-forms:install
```

Publishes config + migrations and migrates. Single-tenant apps that keep
`register_migrations=true` only need `--tag=schema-forms-config`.
