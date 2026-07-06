<?php

namespace Luminus\Queue\Contracts;

interface QueueDriverInterface
{
    /**
     * Push a new job onto the queue.
     *
     * @param string $queue
     * @param string $payload
     * @param int $delay
     * @return mixed Job ID
     */
    public function push(string $queue, string $payload, int $delay = 0): mixed;

    /**
     * Pop the next available job from the queue.
     * 
     * Return format:
     * [
     *     'id' => mixed,
     *     'payload' => string,
     *     'attempts' => int,
     *     'meta' => []
     * ]
     *
     * @param string $queue
     * @return array|null
     */
    public function pop(string $queue): ?array;

    /**
     * Delete a reserved job from the queue.
     *
     * @param string $queue
     * @param mixed $id
     * @return void
     */
    public function delete(string $queue, mixed $id): void;

    /**
     * Release a reserved job back onto the queue.
     *
     * @param string $queue
     * @param mixed $id
     * @param int $delay
     * @return void
     */
    public function release(string $queue, mixed $id, int $delay = 0): void;

    /**
     * Mark a job as permanently failed.
     *
     * @param string $queue
     * @param string $payload
     * @param string $exception
     * @return void
     */
    public function fail(string $queue, string $payload, string $exception): void;
}
