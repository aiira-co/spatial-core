<?php

declare(strict_types=1);

namespace Spatial\Queue;

/**
 * Job Base Class
 * 
 * Base class for background jobs.
 * 
 * @package Spatial\Queue
 */
abstract class Job
{
    public string $queue = 'default';
    public int $tries = 3;
    public int $timeout = 60;
    public int $delay = 0;

    /**
     * Execute the job.
     *
     * @param mixed ...$dependencies Dependencies injected from DI
     * @return void
     */
    abstract public function handle(...$dependencies): void;

    /**
     * Handle job failure after all retries.
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception): void
    {
        // Override in subclass to handle failures
    }

    /**
     * Get unique job ID.
     */
    public function getId(): string
    {
        return md5(static::class . serialize($this));
    }
}
