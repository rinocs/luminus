<?php

namespace Luminus\Queue\Drivers;

use Luminus\Queue\Contracts\QueueDriverInterface;
use Redis;

class RedisDriver implements QueueDriverInterface
{
    private Redis $redis;
    private string $prefix;

    public function __construct(array $config)
    {
        $this->redis = new Redis();
        
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 0.0;
        
        $this->redis->connect($host, $port, $timeout);
        
        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }
        
        if (isset($config['database'])) {
            $this->redis->select($config['database']);
        }

        $this->prefix = $config['prefix'] ?? 'luminus:queues:';
    }

    public function push(string $queue, string $payload, int $delay = 0): mixed
    {
        $id = uniqid('', true);
        
        $jobData = json_encode([
            'id' => $id,
            'payload' => $payload,
            'attempts' => 0
        ]);

        if ($delay > 0) {
            $this->redis->zAdd($this->getQueueName($queue) . ':delayed', time() + $delay, $jobData);
        } else {
            $this->redis->rPush($this->getQueueName($queue), $jobData);
        }

        return $id;
    }

    public function pop(string $queue): ?array
    {
        $this->migrateDelayedJobs($queue);

        $queueName = $this->getQueueName($queue);
        $reservedQueue = $queueName . ':reserved';

        $jobData = $this->redis->lPop($queueName);

        if (!$jobData) {
            return null;
        }

        $job = json_decode($jobData, true);
        $job['attempts']++;

        $updatedJobData = json_encode($job);
        
        // Add to reserved queue with a timeout score (e.g. 60 seconds from now)
        // This allows finding dead jobs if needed.
        $this->redis->zAdd($reservedQueue, time() + 60, $updatedJobData);

        return [
            'id' => $job['id'],
            'payload' => $job['payload'],
            'attempts' => $job['attempts'],
            'meta' => ['raw' => $updatedJobData]
        ];
    }

    public function delete(string $queue, mixed $id): void
    {
        // RedisDriver doesn't easily let us delete by ID from a ZSET if we don't know the exact value.
        // A simple approach is to use ZREMRANGEBYSCORE or a Lua script, but since we have the raw payload
        // we might not have it in this method unless we pass it. 
        // For simplicity, we can fetch all from reserved, match the ID, and remove.
        $reservedQueue = $this->getQueueName($queue) . ':reserved';
        $jobs = $this->redis->zRange($reservedQueue, 0, -1);
        
        foreach ($jobs as $jobData) {
            $job = json_decode($jobData, true);
            if ($job['id'] === $id) {
                $this->redis->zRem($reservedQueue, $jobData);
                break;
            }
        }
    }

    public function release(string $queue, mixed $id, int $delay = 0): void
    {
        $reservedQueue = $this->getQueueName($queue) . ':reserved';
        $jobs = $this->redis->zRange($reservedQueue, 0, -1);
        
        foreach ($jobs as $jobData) {
            $job = json_decode($jobData, true);
            if ($job['id'] === $id) {
                $this->redis->zRem($reservedQueue, $jobData);
                
                if ($delay > 0) {
                    $this->redis->zAdd($this->getQueueName($queue) . ':delayed', time() + $delay, $jobData);
                } else {
                    $this->redis->rPush($this->getQueueName($queue), $jobData);
                }
                break;
            }
        }
    }

    public function fail(string $queue, string $payload, string $exception): void
    {
        $failedData = json_encode([
            'queue' => $queue,
            'payload' => $payload,
            'exception' => $exception,
            'failed_at' => time()
        ]);
        
        $this->redis->rPush($this->prefix . 'failed_jobs', $failedData);
    }

    protected function migrateDelayedJobs(string $queue): void
    {
        $queueName = $this->getQueueName($queue);
        $delayedQueue = $queueName . ':delayed';

        $now = time();
        $jobs = $this->redis->zRangeByScore($delayedQueue, '-inf', (string)$now);

        if (!empty($jobs)) {
            foreach ($jobs as $jobData) {
                $this->redis->zRem($delayedQueue, $jobData);
                $this->redis->rPush($queueName, $jobData);
            }
        }
    }

    protected function getQueueName(string $queue): string
    {
        return $this->prefix . $queue;
    }
}
