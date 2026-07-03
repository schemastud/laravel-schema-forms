# CONTEXT — laravel-form-submissions

## What this is

The base leaf for the shared **"a form was submitted"** concept. It is deliberately dumb: it
owns a `FormSubmission` record and the act of persisting one, and emits exactly one signal
(`SubmissionReceived`). Everything reactive lives above it.

## Why it exists (engine/host seam)

Central splicewire-app already had a bespoke `Lead`/`LeadController`/`NewLeadNotification`
path that did not generalize, and the only other submission concept (`IntakeSubmission`) was
welded to the composition/knowledge engine (Circuit + Silo + Fragment + CircuitRun). Neither
could be reused for a plain waitlist form on a satellite site.

This leaf carries **no** Splicewire, circuit, or mail vocabulary so it can be reused anywhere
without dragging in the engine — central and every satellite share the *code*, each with its
own table.

## Load-bearing design

- **Persist-first ordering is a hard guarantee.** `RecordsSubmissions` writes the row, *then*
  dispatches `SubmissionReceived`. Notification is a listener on that event, never inline. This
  matters because mail delivery will fail until production SMTP is configured — a submission
  must be captured regardless.
- **The store performs no validation and knows nothing about notification.** Callers validate
  (the satellite package validates a JSON-Schema payload with `opis/json-schema`); notifiers
  listen. Keeping the store dumb is what lets it be reused.

## Swappable-model / migration seams

Model class, table name, and migration registration are all config-driven (Spatie
swappable-model pattern), mirroring `rushing/laravel-splicewire-satellite-publishing`.
Multi-tenant brokers turn `register_migrations` off and publish the migration into their
per-tenant set.
