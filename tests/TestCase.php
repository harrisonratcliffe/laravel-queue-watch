<?php

namespace Harrisonratcliffe\LaravelQueueWatch\Tests;

use Harrisonratcliffe\LaravelQueueWatch\LaravelQueueWatchServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Harrisonratcliffe\\LaravelQueueWatch\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelQueueWatchServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_queue-watch_table.php.stub';
        $migration->up();
        */
    }
}
