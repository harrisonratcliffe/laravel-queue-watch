<?php

namespace HarrisonRatcliffe\QueueWatch;

use HarrisonRatcliffe\QueueWatch\Commands\QueueWatchCommand;
use Illuminate\Support\ServiceProvider;

class QueueWatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/queue-watch.php', 'queue-watch');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/queue-watch.php' => config_path('queue-watch.php'),
            ], 'queue-watch-config');

            $this->commands([
                QueueWatchCommand::class,
            ]);
        }
    }
}
