<?php

declare(strict_types=1);

namespace Spatial\Console\Commands;

use Spatial\Console\AbstractCommand;
use Spatial\Console\Application;

/**
 * Queue Work Command
 * 
 * Processes jobs from the queue.
 * 
 * @example php spatial queue:work
 * @example php spatial queue:work --queue=emails
 * 
 * @package Spatial\Console\Commands
 */
class QueueWorkCommand extends AbstractCommand
{
    private bool $running = true;

    public function getName(): string
    {
        return 'queue:work';
    }

    public function getDescription(): string
    {
        return 'Process jobs from the queue';
    }

    public function execute(array $args, Application $app): int
    {
        $this->app = $app;

        $queue = $args['queue'] ?? 'default';
        $sleep = (int)($args['sleep'] ?? 3);
        $tries = (int)($args['tries'] ?? 3);
        $once = isset($args['once']);

        $this->output("Queue worker started.");
        $this->output("  Queue: {$queue}");
        $this->output("  Sleep: {$sleep}s");
        $this->output("  Max tries: {$tries}");
        $this->output("");
        $this->output("Waiting for jobs... (Ctrl+C to stop)");
        $this->output("");

        // Register signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, fn() => $this->running = false);
            pcntl_signal(SIGTERM, fn() => $this->running = false);
        }

        while ($this->running) {
            $job = $this->getNextJob($queue);

            if ($job !== null) {
                $this->processJob($job, $tries);
            } else {
                if ($once) {
                    break;
                }
                sleep($sleep);
            }

            // Handle signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        $this->output("");
        $this->success("Queue worker stopped.");

        return 0;
    }

    private function getNextJob(string $queue): ?array
    {
        $queueFile = $this->getBasePath() . "/var/queue/{$queue}.json";
        
        if (!file_exists($queueFile)) {
            return null;
        }

        $jobs = json_decode(file_get_contents($queueFile), true) ?? [];
        
        foreach ($jobs as $index => $job) {
            if (($job['available_at'] ?? 0) <= time()) {
                // Remove from queue
                unset($jobs[$index]);
                file_put_contents($queueFile, json_encode(array_values($jobs)));
                return $job;
            }
        }

        return null;
    }

    private function processJob(array $job, int $maxTries): void
    {
        $this->output("[" . date('H:i:s') . "] Processing: {$job['class']}");

        try {
            $class = $job['class'];
            $payload = $job['payload'] ?? null;

            if (!class_exists($class)) {
                $this->error("  ✗ Class not found: {$class}");
                return;
            }

            $instance = new $class($payload);
            
            // Get dependencies from DI if available
            // For now, use basic implementation
            $instance->handle(
                new \Psr\Log\NullLogger(),
                $this->createNullTracer()
            );

            $this->success("  ✓ Completed");

        } catch (\Exception $e) {
            $attempts = ($job['attempts'] ?? 0) + 1;

            if ($attempts < $maxTries) {
                $this->warning("  ⚠ Failed, retry {$attempts}/{$maxTries}");
                $this->requeueJob($job, $attempts);
            } else {
                $this->error("  ✗ Failed permanently: {$e->getMessage()}");
                $this->recordFailedJob($job, $e);
            }
        }
    }

    private function requeueJob(array $job, int $attempts): void
    {
        $queue = $job['queue'] ?? 'default';
        $queueFile = $this->getBasePath() . "/var/queue/{$queue}.json";
        
        $jobs = [];
        if (file_exists($queueFile)) {
            $jobs = json_decode(file_get_contents($queueFile), true) ?? [];
        }

        $job['attempts'] = $attempts;
        $job['available_at'] = time() + ($attempts * 5); // Exponential backoff

        $jobs[] = $job;
        
        $this->ensureDirectory(dirname($queueFile));
        file_put_contents($queueFile, json_encode($jobs));
    }

    private function recordFailedJob(array $job, \Exception $e): void
    {
        $failedFile = $this->getBasePath() . "/var/queue/failed.json";
        
        $failed = [];
        if (file_exists($failedFile)) {
            $failed = json_decode(file_get_contents($failedFile), true) ?? [];
        }

        $job['exception'] = $e->getMessage();
        $job['failed_at'] = date('c');
        $failed[] = $job;

        $this->ensureDirectory(dirname($failedFile));
        file_put_contents($failedFile, json_encode($failed, JSON_PRETTY_PRINT));
    }

    private function createNullTracer(): object
    {
        return new class {
            public function spanBuilder(string $name): object {
                return new class {
                    public function startSpan(): object {
                        return new class {
                            public function activate(): object {
                                return new class {
                                    public function detach(): void {}
                                };
                            }
                            public function recordException(\Exception $e): self { return $this; }
                            public function end(): void {}
                        };
                    }
                };
            }
        };
    }
}
