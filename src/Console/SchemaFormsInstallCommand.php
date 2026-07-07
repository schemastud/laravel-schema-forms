<?php

namespace Rushing\SchemaForms\Console;

use Illuminate\Console\Command;

/**
 * Publishes the primitive's config + migrations and migrates. Single-tenant apps that
 * keep `register_migrations=true` only need the config publish; a broker that turns
 * registration off uses this to publish the migrations into its per-tenant set.
 */
class SchemaFormsInstallCommand extends Command
{
    protected $signature = 'schema-forms:install {--force : Overwrite existing published files}';

    protected $description = 'Publish the schema-forms config and migrations, then migrate.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        // Publish config always; publish the migrations too so a broker that turns off
        // register_migrations (auto-load) can still land them in its own migration set.
        foreach (['schema-forms-config', 'schema-forms-migrations'] as $tag) {
            $params = ['--tag' => $tag];
            if ($force) {
                $params['--force'] = true;
            }
            $this->call('vendor:publish', $params);
        }

        $this->components->info('Running migrations…');
        $this->call('migrate', $force ? ['--force' => true] : []);

        return self::SUCCESS;
    }
}
