# Contributing

Contributions are welcome and will be fully credited.

## Reporting bugs and requesting features

Please use the [issue tracker](https://github.com/harrisonratcliffe/laravel-queue-watch/issues) to report bugs or request features. When reporting a bug, include your PHP version, Laravel version, operating system, and the steps to reproduce.

## Pull requests

- **Follow the existing code style.** This project uses [Laravel Pint](https://github.com/laravel/pint); run `composer format` before committing.
- **Add tests for any behaviour change.** Run `composer test` and make sure the full suite passes.
- **Static analysis must pass.** Run `composer analyse` (PHPStan/Larastan).
- **One pull request per feature or fix.** If you'd like to do more than one, please send multiple pull requests.
- **Document behaviour changes.** Update the README and, if the change is user-facing, add a `CHANGELOG.md` entry under `[Unreleased]`.
- **Describe your changes in detail.** Explain what problem the pull request solves and why you approached it the way you did.

## Running the test suite

```bash
composer install
composer test        # Pest
composer analyse     # PHPStan / Larastan
composer format      # Pint
```

## Code of conduct

Please be considerate and respectful in all interactions related to this project.

**Happy coding**!
