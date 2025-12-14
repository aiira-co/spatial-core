<?php

declare(strict_types=1);

namespace Spatial\Queue;

/**
 * Queue Dispatcher
 * 
 * Dispatches jobs to the queue for background processing.
 * 
 * @package Spatial\Queue
 */
class Queue
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? getcwd();
    }

    /**
     * Dispatch a job to the queue.
     *
     * @param Job $job
     * @return string Job ID
     */
    public function dispatch(Job $job): string
    {
        $queue = $job->queue;
        $queueFile = $this->basePath . "/var/queue/{$queue}.json";
        
        $jobs = [];
        if (file_exists($queueFile)) {
            $jobs = json_decode(file_get_contents($queueFile), true) ?? [];
        }

        $jobData = [
            'id' => $job->getId(),
            'class' => get_class($job),
            'payload' => $this->serializePayload($job),
            'queue' => $queue,
            'attempts' => 0,
            'available_at' => time() + $job->delay,
            'created_at' => date('c')
        ];

        $jobs[] = $jobData;
        
        $dir = dirname($queueFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($queueFile, json_encode($jobs));

        return $jobData['id'];
    }

    /**
     * Dispatch a job with delay.
     *
     * @param Job $job
     * @param int $seconds Delay in seconds
     * @return string Job ID
     */
    public function later(Job $job, int $seconds): string
    {
        $job->delay = $seconds;
        return $this->dispatch($job);
    }

    /**
     * Get queue size.
     *
     * @param string $queue Queue name
     * @return int Number of pending jobs
     */
    public function size(string $queue = 'default'): int
    {
        $queueFile = $this->basePath . "/var/queue/{$queue}.json";
        
        if (!file_exists($queueFile)) {
            return 0;
        }

        $jobs = json_decode(file_get_contents($queueFile), true) ?? [];
        return count($jobs);
    }

    /**
     * Clear all jobs from a queue.
     *
     * @param string $queue Queue name
     * @return int Number of jobs cleared
     */
    public function clear(string $queue = 'default'): int
    {
        $queueFile = $this->basePath . "/var/queue/{$queue}.json";
        
        if (!file_exists($queueFile)) {
            return 0;
        }

        $jobs = json_decode(file_get_contents($queueFile), true) ?? [];
        $count = count($jobs);

        file_put_contents($queueFile, '[]');

        return $count;
    }

    /**
     * Serialize job payload.
     */
    private function serializePayload(Job $job): mixed
    {
        $reflection = new \ReflectionClass($job);
        $payload = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            if (!in_array($name, ['queue', 'tries', 'timeout', 'delay'])) {
                $payload[$name] = $property->getValue($job);
            }
        }

        return $payload;
    }
}
