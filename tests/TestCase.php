<?php

namespace Rushing\FormSubmissions\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\FormSubmissions\FormSubmissionsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FormSubmissionsServiceProvider::class,
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
    }
}
