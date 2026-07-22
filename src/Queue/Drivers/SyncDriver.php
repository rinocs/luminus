<?php

namespace Luminus\Queue\Drivers;

use Luminus\Container;
use Luminus\Queue\Contracts\QueueDriverInterface;

class SyncDriver implements QueueDriverInterface
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function push(string $queue, string $payload, int $delay = 0): mixed
    {
        $data = json_decode($payload, true);
        
        if (isset($data['data'])) {
            $jobClass = $data['job'] ?? null;

            // Security: Defense in depth - verify class is a valid subclass of Job before deserialization
            if (!$jobClass || !is_subclass_of($jobClass, \Luminus\Queue\Job::class)) {
                throw new \RuntimeException("Job class must be a subclass of Luminus\Queue\Job");
            }

            $options = ['allowed_classes' => [$jobClass]];
            // Security: Limit class deserialization to the specific job class to prevent PHP Object Injection
            $job = unserialize($data['data'], $options);
            
            try {
                $job->handle();
            } catch (\Throwable $e) {
                $job->failed($e);
                throw $e;
            }
        }

        return 0; // Sync jobs are processed immediately, returning 0 as a dummy ID
    }

    public function pop(string $queue): ?array
    {
        return null; // Sync driver does not have jobs to pop
    }

    public function delete(string $queue, mixed $id): void
    {
        // No-op
    }

    public function release(string $queue, mixed $id, int $delay = 0): void
    {
        // No-op
    }

    public function fail(string $queue, string $payload, string $exception): void
    {
        // No-op, exceptions are thrown immediately
    }
}
