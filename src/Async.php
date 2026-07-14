<?php

namespace Luminus;

class Async
{
    public static function run(callable $callback): \Fiber
    {
        $fiber = new \Fiber($callback);
        $fiber->start();
        return $fiber;
    }

    public static function await(\Fiber $fiber): mixed
    {
        while (!$fiber->isTerminated()) {
            // If the fiber is suspended, we resume it.
            // In a real event loop, we would wait for the event that
            // the fiber is waiting for.
            if ($fiber->isStarted() && !$fiber->isTerminated()) {
                $fiber->resume();
            }

            if (!$fiber->isTerminated()) {
                // Yield control to avoid 100% CPU usage if possible,
                // though in PHP CLI without an event loop this is limited.
                usleep(1);
            }
        }
        return $fiber->getReturn();
    }

    public static function all(array $tasks): array
    {
        $fibers = [];
        foreach ($tasks as $key => $task) {
            $fibers[$key] = self::run($task);
        }

        $results = [];
        $completed = 0;
        $total = count($fibers);

        while ($completed < $total) {
            foreach ($fibers as $key => $fiber) {
                if (isset($results[$key])) {
                    continue;
                }

                if ($fiber->isTerminated()) {
                    $results[$key] = $fiber->getReturn();
                    $completed++;
                } else {
                    $fiber->resume();
                }
            }
            if ($completed < $total) {
                usleep(1);
            }
        }

        return $results;
    }

    public static function collect(iterable $iterable, callable $callback): array
    {
        $tasks = [];
        foreach ($iterable as $key => $item) {
            $tasks[$key] = fn() => $callback($item);
        }
        return self::all($tasks);
    }

    public static function httpGet(array $urls, array $options = []): array
    {
        $multi = curl_multi_init();
        $channels = [];

        foreach ($urls as $key => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $options['timeout'] ?? 30,
                CURLOPT_CONNECTTIMEOUT => $options['connect_timeout'] ?? 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'Luminus Framework',
            ]);
            curl_multi_add_handle($multi, $ch);
            $channels[$key] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 0.1); // Wait up to 100ms for activity
            }
        } while ($running > 0);

        $results = [];
        foreach ($channels as $key => $ch) {
            $results[$key] = [
                'body' => curl_multi_getcontent($ch),
                'info' => curl_getinfo($ch),
                'error' => curl_error($ch),
            ];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);

        return $results;
    }

    public static function deferred(callable $callback): \Fiber
    {
        $fiber = new \Fiber(function () use ($callback) {
            return $callback();
        });
        return $fiber;
    }

    public static function wait(\Fiber $fiber): mixed
    {
        return self::await($fiber);
    }
}
