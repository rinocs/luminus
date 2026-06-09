<?php

use PHPUnit\Framework\TestCase;
use Luminus\Async;

class AsyncTest extends TestCase
{
    public function test_run_and_await(): void
    {
        $fiber = Async::run(fn() => 21 * 2);
        $this->assertSame(42, Async::await($fiber));
    }

    public function test_all_returns_keyed_results(): void
    {
        $results = Async::all([
            'a' => fn() => 1,
            'b' => fn() => 2,
        ]);
        $this->assertSame(['a' => 1, 'b' => 2], $results);
    }

    public function test_collect_maps_items(): void
    {
        $results = Async::collect([1, 2, 3], fn(int $n) => $n * 10);
        $this->assertSame([10, 20, 30], $results);
    }

    public function test_deferred_and_wait(): void
    {
        $fiber = Async::deferred(fn() => 'lazy');
        $this->assertFalse($fiber->isStarted());
        $this->assertSame('lazy', Async::wait($fiber));
    }

    public function test_await_handles_suspending_fiber(): void
    {
        $fiber = Async::run(function () {
            \Fiber::suspend();
            return 'resumed';
        });
        $this->assertSame('resumed', Async::await($fiber));
    }
}
