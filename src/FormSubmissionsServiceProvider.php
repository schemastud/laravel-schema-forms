<?php

namespace Rushing\FormSubmissions;

use Illuminate\Support\ServiceProvider;
use Rushing\FormSubmissions\Console\FormSubmissionsInstallCommand;

/**
 * Registers the domain-agnostic submissions store: the swappable-model config and
 * the single migration. No routes, no notification, no vertical vocabulary — this
 * leaf owns only "a form was submitted".
 */
class FormSubmissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/form-submissions.php', 'form-submissions');
    }

    public function boot(): void
    {
        // Single-tenant apps auto-load this as a central migration; multi-tenant
        // brokers set form-submissions.register_migrations=false and publish it into
        // their per-tenant migration set instead (see config).
        if (config('form-submissions.register_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/form-submissions.php' => $this->app->configPath('form-submissions.php'),
            ], 'form-submissions-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'form-submissions-migrations');

            $this->commands([
                FormSubmissionsInstallCommand::class,
            ]);
        }
    }
}
