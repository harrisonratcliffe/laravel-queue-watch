<?php

namespace HarrisonRatcliffe\QueueWatch\Tests;

use HarrisonRatcliffe\QueueWatch\QueueWatchServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            QueueWatchServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
