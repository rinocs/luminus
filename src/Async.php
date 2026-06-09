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
        if (!$fiber->isStarted()) {
            $fiber->start();
        }
        while (!$fiber->isTerminated()) {
            $fiber->resume();
        }
        return $fiber->getReturn();
    }

    /**
     * Run tasks in Fibers and collect their results.
     *
     * Note: tasks only interleave if they call Fiber::suspend(); plain
     * blocking callbacks (e.g. PDO queries) run sequentially. For truly
     * concurrent I/O use httpGet(), which is backed by curl_multi.
     */
    public static function all(array $tasks): array
    {
        $fibers = [];
        foreach ($tasks as $key => $task) {
            $fibers[$key] = self::run($task);
        }

        $results = [];
        foreach ($fibers as $key => $fiber) {
            $results[$key] = self::await($fiber);
        }
        return $results;
    }

    public static function collect(iterable $iterable, callable $callback): array
    {
        $results = [];
        foreach ($iterable as $key => $item) {
            $results[$key] = self::run(fn() => $callback($item));
        }
        return array_map(fn(\Fiber $f) => self::await($f), $results);
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
            ]);
            curl_multi_add_handle($multi, $ch);
            $channels[$key] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 1);
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
