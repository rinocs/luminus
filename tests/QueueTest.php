<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Luminus\Container;
use Luminus\Database;
use Luminus\Queue\QueueManager;
use Luminus\Queue\Job;
use Luminus\Queue\Worker;

class TestJob extends Job
{
    public static bool $handled = false;
    public static bool $failedCalled = false;
    public bool $shouldFail = false;
    public int $tries = 2;

    public function handle(): void
    {
        if ($this->shouldFail) {
            throw new \Exception("Job failed intentionally");
        }
        self::$handled = true;
    }

    public function failed(\Throwable $e): void
    {
        self::$failedCalled = true;
    }
}

class QueueTest extends TestCase
{
    private Container $container;
    private array $config;

    protected function setUp(): void
    {
        TestJob::$handled = false;
        TestJob::$failedCalled = false;
        
        $this->container = new Container();
        $this->config = [
            'default' => 'sync',
            'connections' => [
                'sync' => ['driver' => 'sync'],
                'database' => [
                    'driver' => 'database',
                    'table' => 'jobs',
                    'failed_table' => 'failed_jobs'
                ]
            ]
        ];
    }

    public function testDriverResolution(): void
    {
        $manager = new QueueManager($this->container, $this->config);
        
        $syncDriver = $manager->connection('sync');
        $this->assertInstanceOf(\Luminus\Queue\Drivers\SyncDriver::class, $syncDriver);
    }

    public function testSyncDriverExecution(): void
    {
        $manager = new QueueManager($this->container, $this->config);
        $job = new TestJob();
        
        $manager->push($job);
        
        $this->assertTrue(TestJob::$handled);
    }

    public function testSyncDriverFailure(): void
    {
        $manager = new QueueManager($this->container, $this->config);
        $job = new TestJob();
        $job->shouldFail = true;
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Job failed intentionally");
        
        $manager->push($job);
        $this->assertTrue(TestJob::$failedCalled);
    }

    public function testDatabaseDriverPushPop(): void
    {
        // Mock the Database class
        $dbMock = $this->createMock(Database::class);
        
        $dbMock->expects($this->once())
            ->method('insert')
            ->willReturn('1');

        $this->container->set(Database::class, $dbMock);

        $manager = new QueueManager($this->container, $this->config);
        $job = new TestJob();
        
        $manager->push($job, 'default', 'database');
    }

    public function testWorkerProcessing(): void
    {
        $manager = new QueueManager($this->container, $this->config);
        $driver = $this->createMock(\Luminus\Queue\Contracts\QueueDriverInterface::class);
        
        $job = new TestJob();
        $payload = json_encode([
            'job' => get_class($job),
            'data' => serialize($job)
        ]);

        $driver->method('pop')->willReturn([
            'id' => 1,
            'payload' => $payload,
            'attempts' => 1,
            'meta' => []
        ]);

        $driver->expects($this->once())->method('delete')->with('default', 1);

        // Inject the mock driver through reflection for testing
        $reflection = new \ReflectionClass($manager);
        $property = $reflection->getProperty('connections');
        $property->setAccessible(true);
        $property->setValue($manager, ['mock' => $driver]);

        $worker = new Worker($manager);
        
        // Expose process method to test without loop
        $workerReflection = new \ReflectionClass($worker);
        $processMethod = $workerReflection->getMethod('process');
        $processMethod->setAccessible(true);

        $processMethod->invokeArgs($worker, [$driver, 'default', [
            'id' => 1,
            'payload' => $payload,
            'attempts' => 1,
            'meta' => []
        ]]);
        
        // Cannot easily assert the unserialized job state unless it's shared, 
        // but 'delete' expectation verifies success.
    }

    public function testWorkerRetryAndPermanentFailure(): void
    {
        $manager = new QueueManager($this->container, $this->config);
        $driver = $this->createMock(\Luminus\Queue\Contracts\QueueDriverInterface::class);
        
        $job = new TestJob();
        $job->shouldFail = true;
        
        $payload = json_encode([
            'job' => get_class($job),
            'data' => serialize($job)
        ]);

        // Mock 1: Attempt 1, should release
        $driver->expects($this->once())->method('release')->with('default', 1, 0);

        $worker = new Worker($manager);
        $workerReflection = new \ReflectionClass($worker);
        $processMethod = $workerReflection->getMethod('process');
        $processMethod->setAccessible(true);

        $processMethod->invokeArgs($worker, [$driver, 'default', [
            'id' => 1,
            'payload' => $payload,
            'attempts' => 1, // Below $job->tries (2)
            'meta' => []
        ]]);

        // Mock 2: Attempt 2, should permanently fail
        $driver2 = $this->createMock(\Luminus\Queue\Contracts\QueueDriverInterface::class);
        $driver2->expects($this->once())->method('fail');
        $driver2->expects($this->once())->method('delete');

        $processMethod->invokeArgs($worker, [$driver2, 'default', [
            'id' => 1,
            'payload' => $payload,
            'attempts' => 2, // Reached $job->tries
            'meta' => []
        ]]);
    }
}
