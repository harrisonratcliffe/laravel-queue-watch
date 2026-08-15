<div align="center">

# 🔁 Laravel Queue Watch

**Auto-restart your Laravel queue worker whenever your code changes.**
Pure PHP polling — no Node, no npm, no extensions.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/harrisonratcliffe/laravel-queue-watch.svg?style=flat-square)](https://packagist.org/packages/harrisonratcliffe/laravel-queue-watch)
[![Tests](https://img.shields.io/github/actions/workflow/status/harrisonratcliffe/laravel-queue-watch/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/harrisonratcliffe/laravel-queue-watch/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Code Style](https://img.shields.io/github/actions/workflow/status/harrisonratcliffe/laravel-queue-watch/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/harrisonratcliffe/laravel-queue-watch/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/harrisonratcliffe/laravel-queue-watch/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/harrisonratcliffe/laravel-queue-watch/actions?query=workflow%3APHPStan+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/harrisonratcliffe/laravel-queue-watch.svg?style=flat-square)](https://packagist.org/packages/harrisonratcliffe/laravel-queue-watch)
[![License](https://img.shields.io/packagist/l/harrisonratcliffe/laravel-queue-watch.svg?style=flat-square)](LICENSE)

</div>

---

## What it does

`php artisan queue:watch` runs `queue:work` as a long-lived child process and restarts it automatically whenever your application code changes — so you get a production-like queue worker that picks up your edits without you stopping and starting it by hand.

```console
$ php artisan queue:watch

queue:watch started - watching 7 path(s), polling every 1000ms.
Change detected (app/Jobs/ProcessPodcast.php); restarting worker...
```

That's the whole thing. Edit a job, save, and the worker is already running your new code.

---

## 🤔 Why polling?

Most file watchers — including the Node/chokidar-based packages this one replaces — rely on OS-level filesystem events like `inotify`. That works beautifully on bare metal. It's unreliable in the environment most Laravel developers actually use day to day: **Docker on macOS or Windows**, where your app runs in a Linux container over a bind-mounted volume from the host.

Host-side edits are frequently invisible to `inotify` inside the container, because the bind mount doesn't propagate native filesystem events across the VM boundary. The watcher doesn't error — it just silently never fires.

Polling sidesteps this entirely:

- ✅ Doesn't care what kind of mount your code lives on
- ✅ No `inotify`/`fswatch` extension or binary required
- ✅ Identical behaviour on macOS, Linux, Windows, and inside any container

Statting a few thousand files once a second is negligible overhead. In exchange you get a watcher that actually works everywhere, every time. **That reliability — not raw speed — is this package's main advantage** over the Node-based alternatives.

### How it compares

|                                       | `queue:watch`                                                | `queue:listen`                     | Node-based watchers                   |
| ------------------------------------- | ------------------------------------------------------------ | ---------------------------------- | ------------------------------------- |
| **Requires Node/npm**                 | ❌ No                                                         | ❌ No                               | ✅ Yes                                 |
| **Reloads code on change**            | ✅ Yes                                                        | ✅ Yes (every job)                  | ✅ Yes                                 |
| **Runs a persistent worker**          | ✅ Yes                                                        | ❌ No — fresh process per job       | ✅ Yes                                 |
| **Restart behaviour**                 | Graceful — `SIGTERM`, waits for the current job, then `SIGKILL` | N/A                                | Package-dependent, often a hard kill  |
| **Reliable under Docker bind mounts** | ✅ Yes (polling)                                              | N/A                                | ⚠️ Often not (`inotify`)              |

> [!NOTE]
> `queue:listen` already reloads code on every job — it just does so by booting the framework from scratch each time, which is the exact overhead `queue:work` exists to avoid. `queue:watch` is for people who want `queue:work`'s persistent, production-like execution model **and** automatic reloading on change.

---

## 📦 Installation

**Requirements:** PHP 8.2+ and Laravel 11, 12, or 13.

```bash
composer require harrisonratcliffe/laravel-queue-watch
```

That's it — the package works out of the box with zero configuration.

To customise it, publish the config file:

```bash
php artisan vendor:publish --tag="queue-watch-config"
```

---

## 🚀 Usage

```bash
php artisan queue:watch
```

This starts `queue:work` and watches your application for changes, restarting the worker gracefully whenever it detects one.

### Forwarded to `queue:work`

Common worker options are passed straight through to the underlying process:

| Option          | Description                        |
| --------------- | ---------------------------------- |
| `--queue=`      | Which queue(s) to process          |
| `--connection=` | Which queue connection to use      |
| `--timeout=`    | Max seconds a child job may run    |
| `--tries=`      | Attempts before a job is failed    |
| `--memory=`     | Memory limit in megabytes          |
| `--sleep=`      | Seconds to sleep when no job is available |

### Watcher options

| Option                 | Description                                              |
| ---------------------- | -------------------------------------------------------- |
| `--path=*`             | Extra path to watch, merged with config (repeatable)      |
| `--poll=`              | Polling interval in milliseconds (default `1000`)         |
| `--extensions=`        | Comma-separated extensions to watch (default `php`)       |
| `--no-restart-on-env`  | Don't watch `.env` for changes                            |

**Examples:**

```bash
# Watch a specific queue, and poll twice as often
php artisan queue:watch --queue=emails --poll=500

# Also watch a custom directory, including its JSON files
php artisan queue:watch --path=modules --extensions=php,json
```

---

## ⚙️ Configuration

```php
return [
    // Relative paths resolve against base_path(). A path can point at a
    // single file (like .env) or a directory, which is walked recursively.
    'paths' => ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views', '.env'],

    // Directories that are never descended into while scanning.
    'ignore' => ['vendor', 'node_modules', 'storage', '.git', 'public/build'],

    // File extensions to watch.
    'extensions' => ['php'],

    // How often (ms) the watched paths are rescanned.
    'poll_interval' => 1000,

    // Changes within this many milliseconds of each other are coalesced
    // into a single restart, so a formatter or multi-file save doesn't
    // trigger a restart storm.
    'debounce' => 300,

    // Seconds to wait after SIGTERM before force-killing the worker.
    'restart_timeout' => 10,

    // Override the full command used to spawn the worker. Leave null to
    // derive it from the running PHP binary:
    // [PHP_BINARY, base_path('artisan'), 'queue:work'].
    // Useful under Sail, Herd, Homestead, or a custom PHP install.
    'worker_command' => null,
];
```

> [!TIP]
> Watching a large `resources/` tree and finding restarts sluggish? Trim `paths` down to what actually affects your jobs — usually just `app`, `config`, and `.env`.

---

## 🛡️ Graceful restarts

Naively killing a queue worker to restart it is dangerous. A job can be mid-flight when the worker dies, and with a database queue driver that job then sits reserved until it times out, only to retry — duplicating side effects if the original invocation had already partially completed.

`queue:watch` avoids this:

1. **`SIGTERM`** is sent to the worker.
2. Laravel's worker responds by **finishing the job it's currently processing**, then exiting cleanly.
3. If it hasn't exited within `restart_timeout` seconds (default `10`), it's escalated to **`SIGKILL`**.
4. A **fresh worker** is spawned with your updated code.

> [!WARNING]
> **Windows limitation.** POSIX signals need `ext-pcntl`, which isn't available on Windows, so this graceful handshake isn't possible there — `queue:watch` falls back to a hard stop, the same as `Process::stop()` would. A restart can therefore interrupt an in-flight job, and Ctrl+C won't get the chance to shut the worker down cleanly. **WSL2 is strongly recommended on Windows.**

### When the worker exits on its own

If the worker dies by itself — a crash, `--max-jobs`, or an internal `queue:restart` — `queue:watch` reports the exit code and respawns it rather than hanging or exiting silently.

A worker that exits *immediately* is treated as a failure rather than a restart: repeated fast exits back off exponentially (250ms, doubling, capped at 10s) and `queue:watch` gives up after 10 in a row. This keeps a genuinely broken worker — a bad `worker_command`, a missing `artisan`, a boot-time exception — from being respawned in a tight loop that scrolls the underlying error out of view. Any worker that stays up for 5 seconds clears the streak.

---

## 🧪 Testing

```bash
composer test
```

## Contributing

Contributions are welcome — please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Changelog

See [CHANGELOG](CHANGELOG.md) for what's changed recently.

## Security

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Harrison Ratcliffe](https://github.com/harrisonratcliffe)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see the [License File](LICENSE) for more information.
