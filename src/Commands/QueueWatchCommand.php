<?php

namespace HarrisonRatcliffe\QueueWatch\Commands;

use HarrisonRatcliffe\QueueWatch\Process\OutputChannel;
use HarrisonRatcliffe\QueueWatch\Process\RespawnPolicy;
use HarrisonRatcliffe\QueueWatch\Process\SymfonyWorkerProcess;
use HarrisonRatcliffe\QueueWatch\Process\WorkerProcessManager;
use HarrisonRatcliffe\QueueWatch\Support\PathResolver;
use HarrisonRatcliffe\QueueWatch\Support\WatchOptions;
use HarrisonRatcliffe\QueueWatch\Watching\Debouncer;
use HarrisonRatcliffe\QueueWatch\Watching\PollingWatcher;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueueWatchCommand extends Command implements SignalableCommandInterface
{
    protected $signature = 'queue:watch
        {--queue= : Forwarded to queue:work}
        {--connection= : Forwarded to queue:work}
        {--timeout= : Forwarded to queue:work}
        {--tries= : Forwarded to queue:work}
        {--memory= : Forwarded to queue:work}
        {--sleep= : Forwarded to queue:work}
        {--path=* : Extra path(s) to watch, merged with config}
        {--poll= : Polling interval in milliseconds}
        {--extensions= : Comma-separated list of extensions to watch}
        {--no-restart-on-env : Do not watch .env for changes}';

    protected $description = 'Run queue:work and automatically restart it when application code changes';

    private bool $shouldStop = false;

    public function handle(): int
    {
        $options = $this->resolveOptions();

        $watcher = new PollingWatcher($options->paths, $options->ignore, $options->extensions);
        $watcher->baseline();

        $debouncer = new Debouncer($options->debounceMs);
        $respawn = new RespawnPolicy;

        $manager = new WorkerProcessManager(
            processFactory: fn () => new SymfonyWorkerProcess(
                $options->workerCommand,
                base_path(),
                fn (OutputChannel $channel, string $buffer) => $this->relayOutput($channel, $buffer),
            ),
            restartTimeoutSeconds: $options->restartTimeoutSeconds,
            posixSignalsAvailable: PHP_OS_FAMILY !== 'Windows' && extension_loaded('pcntl'),
        );

        $this->info(sprintf(
            'queue:watch started - watching %d path(s), polling every %dms.',
            count($options->paths), $options->pollIntervalMs
        ));
        $manager->start();
        $workerStartedAt = $this->nowMs();

        $tickMs = 50;
        $elapsedSincePoll = 0;
        $respawnAt = null;

        while (! $this->shouldStop) {
            usleep($tickMs * 1000);

            $manager->pumpOutput();

            if (! $manager->isRunning()) {
                if ($respawnAt === null) {
                    $exitCode = $manager->exitCode();
                    $respawn->recordExit((int) ($this->nowMs() - $workerStartedAt));

                    if ($respawn->shouldGiveUp()) {
                        $this->error(sprintf(
                            'Worker exited immediately %d times in a row (last exit code %s). Giving up - check your worker_command and that the app boots.',
                            $respawn->consecutiveFailures(),
                            $exitCode ?? 'unknown',
                        ));

                        return self::FAILURE;
                    }

                    $delay = $respawn->delayMs();
                    $this->warn(sprintf(
                        'Worker exited unexpectedly (exit code %s); restarting%s.',
                        $exitCode ?? 'unknown',
                        $delay > 0 ? " in {$delay}ms" : '',
                    ));

                    $respawnAt = $this->nowMs() + $delay;
                }

                // Wait out the backoff across loop ticks rather than sleeping,
                // so Ctrl+C stays responsive while we're backing off.
                if ($this->nowMs() >= $respawnAt) {
                    $manager->start();
                    $workerStartedAt = $this->nowMs();
                    $respawnAt = null;
                }
            }

            $elapsedSincePoll += $tickMs;
            if ($elapsedSincePoll < $options->pollIntervalMs) {
                continue;
            }
            $elapsedSincePoll = 0;

            foreach ($watcher->poll() as $change) {
                $debouncer->recordChange($this->relativePath($change->path));
            }

            if ($debouncer->isReady()) {
                $reason = $debouncer->consume();
                $this->info("Change detected ({$reason}); restarting worker...");
                $manager->restart();
                $workerStartedAt = $this->nowMs();
                $respawnAt = null;
                $respawn->reset();
            }
        }

        $this->info('Stopping worker...');
        $manager->stop();
        $this->info('Stopped.');

        return self::SUCCESS;
    }

    public function getSubscribedSignals(): array
    {
        // SIGINT/SIGTERM are defined by ext-pcntl. Symfony calls this method
        // unconditionally for any SignalableCommandInterface command - before
        // it consults its own signal registry - so referencing the constants
        // directly is a fatal error on hosts without pcntl (notably Windows,
        // where the whole command would die on startup rather than falling
        // back to the documented hard-stop behaviour).
        if (! defined('SIGINT') || ! defined('SIGTERM')) {
            return [];
        }

        return [SIGINT, SIGTERM];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        // Let the main loop perform its own graceful child shutdown rather
        // than letting Symfony force-exit us immediately. Same pattern
        // Laravel's own queue:work (WorkCommand) uses.
        $this->shouldStop = true;

        return false;
    }

    private function resolveOptions(): WatchOptions
    {
        $config = config('queue-watch');

        $paths = array_merge($config['paths'], $this->option('path'));
        if ($this->option('no-restart-on-env')) {
            $paths = array_values(array_diff($paths, ['.env']));
        }

        $extensions = $this->option('extensions')
            ? array_map('trim', explode(',', $this->option('extensions')))
            : $config['extensions'];

        return new WatchOptions(
            paths: PathResolver::resolve($paths, base_path()),
            ignore: PathResolver::resolve($config['ignore'], base_path()),
            extensions: $extensions,
            pollIntervalMs: (int) ($this->option('poll') ?? $config['poll_interval']),
            debounceMs: (int) $config['debounce'],
            restartTimeoutSeconds: (int) $config['restart_timeout'],
            workerCommand: $this->buildWorkerCommand($config),
        );
    }

    private function buildWorkerCommand(array $config): array
    {
        $base = $config['worker_command'] ?? [PHP_BINARY, base_path('artisan'), 'queue:work'];

        $forwarded = [];
        foreach (['queue', 'connection', 'timeout', 'tries', 'memory', 'sleep'] as $option) {
            if (($value = $this->option($option)) !== null) {
                $forwarded[] = "--{$option}={$value}";
            }
        }

        return [...$base, ...$forwarded];
    }

    private function relayOutput(OutputChannel $channel, string $buffer): void
    {
        // Written raw (OUTPUT_RAW) so the worker's own output reaches the
        // console byte-for-byte, exactly as it would running queue:work
        // directly - no Symfony tag interpretation of arbitrary child output.
        $this->getOutput()->write($buffer, false, OutputInterface::OUTPUT_RAW);
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    private function nowMs(): float
    {
        return microtime(true) * 1000;
    }
}
