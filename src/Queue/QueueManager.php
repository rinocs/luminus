<?php

namespace Luminus\Queue;

use Luminus\Container;
use Luminus\Queue\Contracts\QueueDriverInterface;
use Luminus\Queue\Drivers\SyncDriver;
use Luminus\Queue\Drivers\DatabaseDriver;
use Luminus\Queue\Drivers\RedisDriver;
use Luminus\Queue\Drivers\BeanstalkdDriver;

class QueueManager
{
    private array $config;
    private Container $container;
    private array $connections = [];

    public function __construct(Container $container, array $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    /**
     * Push a new job onto the queue.
     */
    public function push(Job $job, ?string $queue = null, ?string $connection = null): mixed
    {
        return $this->later(0, $job, $queue, $connection);
    }

    /**
     * Push a new job onto the queue after a delay.
     */
    public function later(int $delay, Job $job, ?string $queue = null, ?string $connection = null): mixed
    {
        $connectionName = $connection ?? $this->getDefaultConnection();
        $driver = $this->connection($connectionName);

        $queueName = $queue ?? $job->queue ?? 'default';

        $payload = json_encode([
            'job' => get_class($job),
            'data' => serialize($job)
        ]);

        return $driver->push($queueName, $payload, $delay);
    }

    /**
     * Get a queue connection instance.
     */
    public function connection(?string $name = null): QueueDriverInterface
    {
        $name = $name ?? $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    protected function resolve(string $name): QueueDriverInterface
    {
        $config = $this->config['connections'][$name] ?? null;

        if ($config === null) {
            throw new \InvalidArgumentException("Queue connection [{$name}] is not defined.");
        }

        $driver = $config['driver'] ?? $name;

        return match ($driver) {
            'sync' => new SyncDriver($this->container),
            'database' => new DatabaseDriver($this->container->get(\Luminus\Database::class), $config),
            'redis' => new RedisDriver($config),
            'beanstalkd' => new BeanstalkdDriver($config),
            default => throw new \InvalidArgumentException("Unsupported queue driver [{$driver}]."),
        };
    }

    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'sync';
    }
}
