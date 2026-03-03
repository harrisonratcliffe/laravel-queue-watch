# Laravel Queue Watch

[![Latest Version on Packagist](https://img.shields.io/packagist/v/harrisonratcliffe/laravel-queue-watch.svg?style=flat-square)](https://packagist.org/packages/harrisonratcliffe/laravel-queue-watch)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/harrisonratcliffe/laravel-queue-watch/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/harrisonratcliffe/laravel-queue-watch/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/harrisonratcliffe/laravel-queue-watch/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/harrisonratcliffe/laravel-queue-watch/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/harrisonratcliffe/laravel-queue-watch.svg?style=flat-square)](https://packagist.org/packages/harrisonratcliffe/laravel-queue-watch)

> **Notice:** This package is a fork of [rajentrivedi/queue-watch](https://github.com/rajentrivedi/queue-watch), created due to the lack of active maintenance and Laravel 12+ support in the original package.

## Supported Versions

### Laravel
| Version | PHP         | Supported          |
|---------|-------------|-------------------|
| 10.x    | 8.2 – 8.3   | :white_check_mark: |
| 11.x    | 8.2 – 8.4   | :white_check_mark: |
| 12.x    | 8.2 – 8.4   | :white_check_mark: |

Managing queue workers in a Laravel application can sometimes be tedious, especially when dealing with long-running processes. A common challenge is ensuring that workers are restarted whenever there are changes in the jobs, events, or listeners folders. Restarting workers manually can be inefficient and prone to oversight — especially during development — potentially leading to application inconsistencies or stale queue processing.

This package solves that problem by detecting file changes within your Laravel application's jobs, events, and listeners folders and automatically restarting the queue worker when changes are detected.

## Installation

```bash
composer require harrisonratcliffe/laravel-queue-watch --dev
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="queue-watch-config"
```

This is the contents of the published config file:

```php
return [
    'directories' => [
        app_path('Jobs'),
        app_path('Events'),
        app_path('Listeners'),
    ],
];
```

## Usage

```bash
php artisan queue:work:watch
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Harrison Ratcliffe](https://github.com/harrisonratcliffe)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
