<?php

declare(strict_types=1);

namespace Rushing\FormSubmissions\Console;

use Illuminate\Console\Command;

/**
 * Publishes the store's config + migration and migrates. Single-tenant apps that
 * keep `register_migrations=true` only need the config publish; a broker that turns
 * registration off uses this to publish the migration into its per-tenant set.
 */
final class FormSubmissionsInstallCommand extends Command
{
    protected $signature = 'form-submissions:install {--force : Overwrite existing published files}';

    protected $description = 'Publish the form-submissions config and migration, then migrate.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $params = ['--tag' => 'form-submissions-config'];
        if ($force) {
            $params['--force'] = true;
        }
        $this->call('vendor:publish', $params);

        $this->components->info('Running migrations…');
        $this->call('migrate', $force ? ['--force' => true] : []);

        return self::SUCCESS;
    }
}
