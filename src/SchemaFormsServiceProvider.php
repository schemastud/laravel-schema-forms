<?php

namespace Splicewire\SchemaForms;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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

/**
 * Wires the host-agnostic schema-form primitive: config, the submissions + notification
 * tables, the config-backed schema registry, the swappable SubmissionNotifier binding
 * (outbox-wrapped by default), the persist-then-notify listener, and the install + replay
 * commands. No routes, no HTTP, no vertical vocabulary — a host (satellite or central)
 * mounts the delivery surface and may rebind the registry to a file/DB-backed one.
 */
class SchemaFormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/schema-forms.php', 'schema-forms');

        // The default registry resolves forms from config. A satellite rebinds this
        // contract to a file-based registry; another host could back it with a DB.
        $this->app->bind(SchemaRegistry::class, fn () => new ArraySchemaRegistry(
            (array) config('schema-forms.forms', []),
        ));

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
