<?php

namespace Luminus\Queue;

use Luminus\Queue\QueueManager;

class Worker
{
    private QueueManager $manager;

    public function __construct(QueueManager $manager)
    {
        $this->manager = $manager;
    }

    public function work(string $queue = 'default', ?string $connection = null, int $sleep = 3): void
    {
        $driver = $this->manager->connection($connection);

        echo "Listening for jobs on queue [{$queue}] via connection [" . ($connection ?? 'default') . "]...\n";

        while (true) {
            $jobData = $driver->pop($queue);

            if ($jobData === null) {
                sleep($sleep);
                continue;
            }

            $this->process($driver, $queue, $jobData);
        }
    }

    protected function process($driver, string $queue, array $jobData): void
    {
        $payload = json_decode($jobData['payload'], true);

        if (!$payload || !isset($payload['job'], $payload['data'])) {
            // Invalid payload, mark as failed
            $driver->fail($queue, $jobData['payload'], 'Invalid job payload');
            $driver->delete($queue, $jobData['id']);
            return;
        }

        try {
            $jobClass = $payload['job'];

            // Security: Defense in depth - verify class is a valid subclass of Job before deserialization
            if (!is_subclass_of($jobClass, Job::class)) {
                throw new \RuntimeException("Job class [{$jobClass}] must be a subclass of " . Job::class);
            }

            // Security: Limit class deserialization to the specific job class to prevent PHP Object Injection
            $job = unserialize($payload['data'], ['allowed_classes' => [$jobClass]]);

            if (!$job instanceof Job) {
                throw new \RuntimeException("Job must be an instance of " . Job::class);
            }

            echo "Processing job: {$jobClass} (ID: {$jobData['id']}, Attempts: {$jobData['attempts']})\n";

            $job->handle();

            // Success! Remove from queue.
            $driver->delete($queue, $jobData['id']);

            echo "Processed job: {$jobClass} successfully.\n";

        } catch (\Throwable $e) {
            $failedClass = $jobClass ?? 'Unknown';
            echo "Failed job: {$failedClass} (ID: {$jobData['id']})\n";
            echo $e->getMessage() . "\n";

            // If job instance available and has failed() method
            if (isset($job) && $job instanceof Job) {
                try {
                    $job->failed($e);
                } catch (\Throwable $failedE) {
                    // Ignore exceptions from the failed method itself
                }

                if ($jobData['attempts'] >= $job->tries) {
                    $driver->fail($queue, $jobData['payload'], (string)$e);
                    $driver->delete($queue, $jobData['id']);
                    echo "Permanently failed job after {$jobData['attempts']} attempts.\n";
                } else {
                    $driver->release($queue, $jobData['id'], $job->backoff);
                    echo "Released job back to queue (Backoff: {$job->backoff}s).\n";
                }
            } else {
                // If we couldn't even unserialize it, just fail it permanently
                $driver->fail($queue, $jobData['payload'], (string)$e);
                $driver->delete($queue, $jobData['id']);
            }
        }
    }
}
