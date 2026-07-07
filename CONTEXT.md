# CONTEXT — laravel-schema-forms

## What this is

The host-agnostic **schema-form primitive**: *a JSON-Schema-defined form is validated,
stored, and optionally notified*. It owns the `FormSubmission` ledger and the act of
persisting one, opis validation (`SchemaValidator`), the `SubmissionNotifier` seam, the
notification outbox (`SubmissionNotification` + `OutboxDelivery`, track + replay), and the
`x-notify` keyword. Everything transport-specific (HTTP routes, file-based schema
resolution) lives in a host above it.

## Why it exists (engine/host seam)

Central splicewire-app already had a bespoke `Lead`/`LeadController`/`NewLeadNotification`
path that did not generalize, and the only other submission concept (`IntakeSubmission`) was
welded to the composition/knowledge engine (Circuit + Silo + Fragment + CircuitRun). Neither
could be reused for a plain waitlist form on a satellite site.

This package carries **no** Splicewire, circuit, or route vocabulary, so it can be reused
anywhere without dragging in the engine — central and every satellite share the *code*, each
with its own tables.

## History — the US-17 reversal (ADR)

This package was originally `rushing/laravel-form-submissions`, a deliberately *dumb* base
leaf that "knows nothing about mail" (that PRD's US-17). The primitive (validation, the
notifier seam, the outbox, `x-notify`) lived one layer up in the satellite package. It was
merged **down** here and the package renamed, because every submission already carries a
`schema_ref` — there is no schema-less consumer, so the dumb-ledger split was not earning its
keep. `opis/json-schema` + `spatie/laravel-data` are now transitive deps (the reversal's
accepted cost). The "record an arbitrary schema-less event" door is intentionally closed;
re-split only if a real consumer appears.

## Load-bearing design

- **Persist-first ordering is a hard guarantee.** `RecordsSubmissions` writes the row, *then*
  dispatches `SubmissionReceived`. Notification is a listener on that event, never inline —
  mail delivery will fail until production SMTP is configured, and a submission must be
  captured regardless.
- **Record-before-deliver outbox.** `OutboxDelivery` writes a `SubmissionNotification`
  `pending` before attempting delivery and flips it to `sent`/`failed` after; it never throws
  (a failed send is a replayable state, not an exception). Replay is one service method driven
  identically by a job, a console command, and a host's operator endpoint (the last two call
  the service directly — a dispatched job runs on a serialized copy, so its result is not
  readable by the dispatcher).
- **Two swappable seams.** `SubmissionNotifier` (how a submission is handled — mail, relay,
  CRM) and `SchemaRegistry` (how a form key resolves to a schema — config array here, files in
  a satellite, a table elsewhere).

## Swappable-model / migration seams

Both models (`FormSubmission`, `SubmissionNotification`), their table names, and migration
registration are config-driven (Spatie swappable-model pattern). Multi-tenant brokers turn
`register_migrations` off and publish the migrations into their per-tenant set.
