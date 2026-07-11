<?php

namespace Rushing\SchemaForms\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\SchemaForms\Keywords;
use Rushing\SchemaForms\SchemaFormsServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
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
}
