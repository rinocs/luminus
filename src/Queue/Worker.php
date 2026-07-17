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

        // Keep track of active fibers processing jobs
        $fibers = [];

        while (true) {
            // First, filter out completed / terminated fibers
            $fibers = array_filter($fibers, fn(\Fiber $f) => !$f->isTerminated());

            // If we have capacity and there are jobs, try to pop a job to process concurrently
            // For safety and simplicity, let's process up to 20 concurrent jobs
            if (count($fibers) < 20) {
                $jobData = $driver->pop($queue);

                if ($jobData !== null) {
                    // Start processing the job inside a new Fiber
                    $fiber = \Luminus\Async::run(function () use ($driver, $queue, $jobData) {
                        $this->process($driver, $queue, $jobData);
                    });
                    $fibers[] = $fiber;
                    continue;
                }
            }

            // If we didn't pop a new job and no fibers are running, sleep
            if (empty($fibers)) {
                sleep($sleep);
                continue;
            }

            // Tick through active fibers, resuming them if needed
            foreach ($fibers as $fiber) {
                if ($fiber->isStarted() && !$fiber->isTerminated()) {
                    try {
                        $fiber->resume();
                    } catch (\Throwable $e) {
                        // Let exceptions inside the process finish safely
                    }
                }
            }

            // Small sleep/pause to avoid pegged CPU in high-frequency empty loops
            usleep(10000); // 10ms
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
            $job = unserialize($payload['data']);

            if (!$job instanceof Job) {
                throw new \RuntimeException("Job must be an instance of Luminus\Queue\Job");
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
