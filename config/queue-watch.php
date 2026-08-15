<?php

// config for HarrisonRatcliffe/QueueWatch
return [

    /*
    |--------------------------------------------------------------------------
    | Watched paths
    |--------------------------------------------------------------------------
    |
    | Relative paths are resolved against base_path(). A path pointing at a
    | single file (like .env) is watched directly; a directory is walked
    | recursively.
    |
    */
    'paths' => ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views', '.env'],

    /*
    |--------------------------------------------------------------------------
    | Ignored directories
    |--------------------------------------------------------------------------
    |
    | These directories are never descended into while scanning, so their
    | contents can never trigger a restart no matter how large they are.
    |
    */
    'ignore' => ['vendor', 'node_modules', 'storage', '.git', 'public/build'],

    /*
    |--------------------------------------------------------------------------
    | Watched extensions
    |--------------------------------------------------------------------------
    */
    'extensions' => ['php'],

    /*
    |--------------------------------------------------------------------------
    | Poll interval
    |--------------------------------------------------------------------------
    |
    | How often (in milliseconds) the watched paths are rescanned.
    |
    */
    'poll_interval' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Debounce
    |--------------------------------------------------------------------------
    |
    | Changes within this many milliseconds of each other are coalesced into
    | a single restart, so a formatter or multi-file save doesn't trigger a
    | restart storm.
    |
    */
    'debounce' => 300,

    /*
    |--------------------------------------------------------------------------
    | Restart timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait after sending SIGTERM for the worker to finish its
    | current job and exit on its own, before it is forcefully killed.
    |
    */
    'restart_timeout' => 10,

    /*
    |--------------------------------------------------------------------------
    | Worker command
    |--------------------------------------------------------------------------
    |
    | Override the full argv used to spawn the worker. Leave null to derive
    | it from the running PHP binary: [PHP_BINARY, base_path('artisan'), 'queue:work'].
    |
    */
    'worker_command' => null,

];
