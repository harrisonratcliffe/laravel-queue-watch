# Changelog

All notable changes to `laravel-queue-watch` will be documented in this file.

## 1.0.0 - 2026-08-15

Initial release.

### Added

- `php artisan queue:watch` — runs `queue:work` as a long-lived child process and restarts it automatically on application code changes, using pure-PHP polling. No Node, no npm, no PHP extension required.
- Graceful restarts: `SIGTERM` first, waits up to a configurable `restart_timeout` for the current job to finish, then `SIGKILL`.
- Configurable watched paths, ignored directories, extensions, poll interval, and debounce.
- Common `queue:work` options (`--queue`, `--connection`, `--timeout`, `--tries`, `--memory`, `--sleep`) are forwarded to the worker.
- Watcher options: `--path`, `--poll`, `--extensions`, `--no-restart-on-env`.
- Exponential backoff when the worker exits immediately and repeatedly (250ms, doubling, capped at 10s), giving up after 10 consecutive fast exits instead of respawning in a tight loop. A worker that stays up for 5 seconds clears the streak.
- `ext-pcntl` declared under `suggest`, since graceful restarts depend on it.

### Fixed

- The command no longer fatals on startup on hosts without `ext-pcntl` (notably every Windows host). Symfony calls `getSubscribedSignals()` unconditionally for a `SignalableCommandInterface` command, so returning the pcntl-defined `SIGINT`/`SIGTERM` constants unguarded raised `Error: Undefined constant "SIGINT"` before the documented Windows hard-stop fallback could ever run.
- A single unreadable directory no longer takes down the watch loop. `RecursiveDirectoryIterator` throws `UnexpectedValueException` out of `getChildren()` on a permission-denied directory; traversal now uses `CATCH_GET_CHILD` and skips the branch, and an unreadable watch root is skipped rather than propagating. Both are easy to hit on Docker bind mounts and root-owned artifacts.
