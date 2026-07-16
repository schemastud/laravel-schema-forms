<?php

namespace Splicewire\SchemaForms\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Schemastud\Beam\BeamServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Splicewire\SchemaForms\Keywords;
use Splicewire\SchemaForms\SchemaFormsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            // The store collapses onto beam: BeamServiceProvider auto-loads the schema_records +
            // beam_submissions migrations the re-based RecordsSubmissions writes to.
            ActivitylogServiceProvider::class,
            MediaLibraryServiceProvider::class,
            BeamServiceProvider::class,
            SchemaFormsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // The config-backed registry resolves `waitlist` to a schema carrying x-swf-notify,
        // so the notification tests exercise the real forSchema path (no file registry
        // in the base package — a satellite supplies that).
        $app['config']->set('schema-forms.forms.waitlist', [
            '$id' => 'waitlist/1',
            Keywords::Notify => [
                'to' => 'waitlist@example.com',
                'subject' => 'New waitlist signup',
                'channel' => 'mail',
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // beam ships its substrate migrations as publish-only `.stub` files (a host renames them on
        // publish, or — like splicewire-app — owns tenant-guarded copies). The package harness
        // creates the two tables the re-based store writes to directly, mirroring beam's own tests.
        $this->createBeamTables();
    }

    private function createBeamTables(): void
    {
        if (! Schema::hasTable('schema_records')) {
            Schema::create('schema_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('schema_ref')->nullable()->index();
                $table->json('payload')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('beam_submissions')) {
            Schema::create('beam_submissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('schema_record_id')->index();
                $table->uuid('submitted_by')->nullable()->index();
                $table->timestamp('submitted_at')->nullable();
                $table->string('source')->nullable();
                $table->string('channel')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();
            });
        }
    }
}
