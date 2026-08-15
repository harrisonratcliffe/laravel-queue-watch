<?php

use HarrisonRatcliffe\QueueWatch\Support\PathResolver;

it('resolves relative paths against the base path', function () {
    $resolved = PathResolver::resolve(['app', 'routes'], '/var/www/html');

    expect($resolved)->toBe(['/var/www/html/app', '/var/www/html/routes']);
});

it('leaves absolute unix paths untouched', function () {
    $resolved = PathResolver::resolve(['/etc/hosts'], '/var/www/html');

    expect($resolved)->toBe(['/etc/hosts']);
});

it('leaves absolute windows paths untouched', function () {
    $resolved = PathResolver::resolve(['C:\\app'], '/var/www/html');

    expect($resolved)->toBe(['C:\\app']);
});

it('dedupes resolved paths', function () {
    $resolved = PathResolver::resolve(['app', '/var/www/html/app'], '/var/www/html');

    expect($resolved)->toBe(['/var/www/html/app']);
});

it('trims trailing slashes from the base path', function () {
    $resolved = PathResolver::resolve(['app'], '/var/www/html/');

    expect($resolved)->toBe(['/var/www/html/app']);
});
