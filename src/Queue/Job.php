<?php

namespace Luminus\Queue;

abstract class Job
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'default';

    /**
     * The number of seconds to wait before retrying a failed job.
     */
    public int $backoff = 0;

    /**
     * Execute the job.
     *
     * @return void
     */
    abstract public function handle(): void;

    /**
     * Handle a job failure.
     *
     * @param \Throwable $e
     * @return void
     */
    public function failed(\Throwable $e): void
    {
        // Custom failure logic can be implemented in the child class
    }
}
