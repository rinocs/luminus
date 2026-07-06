# Queues

Luminus provides a unified, pluggable queue system for processing background jobs. This allows you to defer the processing of time-consuming tasks, such as sending emails, significantly speeding up web requests.

## Supported Drivers

Luminus supports the following queue drivers out of the box:

- `sync` (default, executes jobs immediately in the current process)
- `database` (stores jobs in a database table)
- `redis` (uses Redis lists for fast queueing)
- `beanstalkd` (uses the Beanstalkd work queue)

## Configuration

Queue configuration is located in `config/queue.php`. You can set your default queue driver and connection details using environment variables in your `.env` file:

```env
QUEUE_DRIVER=database

QUEUE_DB_TABLE=jobs
QUEUE_FAILED_TABLE=failed_jobs

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_QUEUE_DB=1
REDIS_QUEUE_PREFIX=luminus:queues:

BEANSTALKD_HOST=127.0.0.1
BEANSTALKD_PORT=11300
```

## Creating Jobs

Jobs should extend the `Luminus\Queue\Job` base class. You must implement the `handle()` method, which is called when the job is processed by the worker.

```php
<?php

namespace App\Jobs;

use Luminus\Queue\Job;

class SendWelcomeEmail extends Job
{
    private string $email;

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function handle(): void
    {
        // Logic to send the email
        // mail($this->email, 'Welcome!', 'Thanks for joining.');
    }
}
```

### Job Properties

You can customize the behavior of a specific job by defining properties on the job class:

- `public int $tries = 3;`: The number of times the job may be attempted before failing permanently.
- `public int $timeout = 60;`: The number of seconds the job can run before timing out.
- `public string $queue = 'default';`: The default queue to which the job should be sent.
- `public int $backoff = 0;`: The number of seconds to wait before retrying a failed job.

### Handling Failures

You can define a `failed()` method on your job class to handle custom logic when a job fails after exhausting all attempts:

```php
    public function failed(\Throwable $e): void
    {
        // Send alert, log error, etc.
    }
```

## Dispatching Jobs

You can dispatch jobs using the `QueueManager` service, which is available in the application container.

```php
use Luminus\Queue\QueueManager;

// Get the QueueManager from the container
$queueManager = $app->getContainer()->get(QueueManager::class);

// Push to the default queue
$queueManager->push(new SendWelcomeEmail('user@example.com'));

// Push to a specific queue
$queueManager->push(new SendWelcomeEmail('user@example.com'), 'emails');

// Push with a delay (in seconds)
$queueManager->later(60, new SendWelcomeEmail('user@example.com'));
```

## Running the Worker

To process jobs in the queue, you need to run a worker process. Luminus includes a CLI tool for this.

```bash
# Basic usage (listens on the default queue)
php bin/worker

# Listen on a specific queue
php bin/worker emails

# Specify connection and sleep duration (in seconds)
# php bin/worker [queue] [connection] [sleep]
php bin/worker emails redis 5
```

The worker is a long-running process and will continuously poll for new jobs. In a production environment, you should use a process monitor like Supervisor to ensure the worker process stays running.
