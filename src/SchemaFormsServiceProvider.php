<?php

namespace Splicewire\SchemaForms;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Splicewire\SchemaForms\Actions\RecordsSubmissions;
use Splicewire\SchemaForms\Console\ReplaySubmissionNotificationsCommand;
use Splicewire\SchemaForms\Console\SchemaFormsInstallCommand;
use Splicewire\SchemaForms\Contracts\SchemaRegistry;
use Splicewire\SchemaForms\Contracts\SubmissionNotifier;
use Splicewire\SchemaForms\Events\SubmissionReceived;
use Splicewire\SchemaForms\Listeners\NotifyOnSubmission;
use Splicewire\SchemaForms\Notifiers\MailSubmissionNotifier;
use Splicewire\SchemaForms\Notifiers\OutboxSubmissionNotifier;
use Splicewire\SchemaForms\Outbox\OutboxDelivery;
use Splicewire\SchemaForms\Registry\ArraySchemaRegistry;
use Splicewire\SchemaForms\Registry\FileSchemaFormRegistry;

/**
 * Wires the public form runtime: config, the submissions table, the schema registry (config-backed
 * default + the folded-down file-based registry), the public door (`POST /schema-forms/{form}` +
 * opis validation), the store-collapsed-onto-beam action, the swappable SubmissionNotifier binding
 * (outbox-wrapped by default), the persist-then-notify listener, and the install + replay commands.
 *
 * The store verb now collapses onto beam (spec §3): {@see RecordsSubmissions}
 * persists the submission as a beam SchemaRecord + a generic BeamSubmission reference. The door +
 * file registry were folded down from the retired laravel-satellite-schema-form; only notify still
 * lives fully here (a separate ticket re-homes it onto BeamSubmission::created).
 */
class SchemaFormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/schema-forms.php', 'schema-forms');

        // The default registry resolves forms from config. A satellite rebinds this
        // contract to a file-based registry; another host could back it with a DB.
        // `bindIf` — not `bind` — so this is a true default: any satellite/host rebind
        // wins regardless of provider load order. (Auto-discovery registers providers
        // alphabetically, so `laravel-satellite-schema-form` loads *before*
        // `laravel-schema-forms`; a plain `bind` here would clobber the satellite's
        // file-based registry that registered first, silently starving the
        // persist-then-notify listener of the schema's `x-swf-notify`.)
        $this->app->bindIf(SchemaRegistry::class, fn () => new ArraySchemaRegistry(
            (array) config('schema-forms.forms', []),
        ));

        // The file-based registry (folded down from the retired satellite package). Bound (not
        // singleton) so it reads the current `schema_path` each resolution — a broker that scopes
        // the schema path per tenant is not pinned to whichever tenant resolved it first. The
        // public door type-hints this concretely (it needs schemaRef()); notify resolution goes
        // through the SchemaRegistry seam above, which an app may still override.
        $this->app->bind(FileSchemaFormRegistry::class, fn () => FileSchemaFormRegistry::fromConfig());

        // The host-swappable seam. A host either points `schema-forms.notifier` at its
        // class (honored here) or rebinds the SubmissionNotifier contract in its own
        // provider (registered after, so it wins). With the outbox enabled (default),
        // the configured notifier is wrapped by the OutboxSubmissionNotifier so every
        // send is tracked and failures are replayable; a direct rebind of the contract
        // bypasses the outbox entirely.
        $this->app->bind(SubmissionNotifier::class, function ($app) {
            $delivery = $app->make(config('schema-forms.notifier', MailSubmissionNotifier::class));

            if (! config('schema-forms.outbox.enabled', true)) {
                return $delivery;
            }

            return new OutboxSubmissionNotifier($delivery, $app->make(OutboxDelivery::class));
        });
    }

    public function boot(): void
    {
        // Persist-then-notify: the base store fires SubmissionReceived only after the row
        // is durable; this listener is the sole place notification is triggered.
        Event::listen(SubmissionReceived::class, NotifyOnSubmission::class);

        // Single-tenant apps auto-load these as central migrations; multi-tenant brokers
        // set schema-forms.register_migrations=false and publish them into their
        // per-tenant migration set instead (see config).
        if (config('schema-forms.register_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // The public door (folded down from the retired satellite package). A host that mounts the
        // endpoint itself, or drives the store directly from its own controllers (central's
        // LeadController / IntakeFormController), sets schema-forms.register_routes=false.
        if (config('schema-forms.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/schema-form.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/schema-forms.php' => $this->app->configPath('schema-forms.php'),
            ], 'schema-forms-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'schema-forms-migrations');

            $this->commands([
                SchemaFormsInstallCommand::class,
                ReplaySubmissionNotificationsCommand::class,
            ]);
        }
    }
}
