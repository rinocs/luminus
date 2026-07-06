<?php

namespace Luminus\Queue\Drivers;

use Luminus\Queue\Contracts\QueueDriverInterface;
use Pheanstalk\Pheanstalk;

class BeanstalkdDriver implements QueueDriverInterface
{
    private Pheanstalk $pheanstalk;

    public function __construct(array $config)
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 11300;
        $timeout = $config['timeout'] ?? 10;
        
        $this->pheanstalk = Pheanstalk::create($host, $port, $timeout);
    }

    public function push(string $queue, string $payload, int $delay = 0): mixed
    {
        $jobData = json_encode([
            'payload' => $payload,
            'attempts' => 0
        ]);

        $job = $this->pheanstalk
            ->useTube($queue)
            ->put(
                $jobData,
                Pheanstalk::DEFAULT_PRIORITY,
                $delay,
                Pheanstalk::DEFAULT_TTR
            );

        return $job->getId();
    }

    public function pop(string $queue): ?array
    {
        $job = $this->pheanstalk->watch($queue)->reserveWithTimeout(0);

        if (!$job) {
            return null;
        }

        $data = json_decode($job->getData(), true);
        $attempts = ($data['attempts'] ?? 0) + 1;

        $data['attempts'] = $attempts;

        return [
            'id' => $job->getId(),
            'payload' => $data['payload'],
            'attempts' => $attempts,
            'meta' => ['raw_job' => $job, 'updated_data' => $data]
        ];
    }

    public function delete(string $queue, mixed $id): void
    {
        // $id should be the actual Pheanstalk Job instance or we can just delete by ID if needed.
        // Pheanstalk requires a Job instance, so we assume $id might be passed as an array or object.
        // In our manager/worker, we just pass the ID. We'll fetch it first.
        try {
            $job = $this->pheanstalk->peek((int)$id);
            $this->pheanstalk->delete($job);
        } catch (\Throwable $e) {
            // Ignore if not found
        }
    }

    public function release(string $queue, mixed $id, int $delay = 0): void
    {
        try {
            $job = $this->pheanstalk->peek((int)$id);
            
            // To update the attempts count we actually need to put a new job and delete the old one
            // or just rely on Pheanstalk's built-in stats. 
            // We'll just release the current job to keep it simple, but we can't easily update the payload.
            // For robust attempt tracking, deleting and putting a new one is better.
            
            // Re-fetch the data we updated in pop()
            $data = json_decode($job->getData(), true);
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
            
            $this->pheanstalk->useTube($queue)->put(
                json_encode($data),
                Pheanstalk::DEFAULT_PRIORITY,
                $delay,
                Pheanstalk::DEFAULT_TTR
            );
            
            $this->pheanstalk->delete($job);
        } catch (\Throwable $e) {
            // Ignore if not found
        }
    }

    public function fail(string $queue, string $payload, string $exception): void
    {
        // Beanstalkd doesn't have native failed jobs, so we could insert into a failed DB or log.
        // For simplicity and driver completeness, we log to error log since we don't have DB injected here.
        error_log("Failed Beanstalkd Job on queue '{$queue}': {$exception}");
    }
}
