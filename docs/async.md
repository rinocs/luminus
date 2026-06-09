# Async

Fiber-based task helpers (PHP 8.1+). No external dependencies.

> **Important:** Fibers are *cooperative*. A task only yields control if it
> calls `Fiber::suspend()` — plain blocking calls (PDO queries, `file_get_contents`,
> `sleep`) run to completion before the next task starts. `Async::all()` with
> blocking callbacks executes them **sequentially**. For truly concurrent I/O,
> use `Async::httpGet()`, which is backed by `curl_multi`.

## Grouping tasks

Run a set of tasks and collect their results under the same keys:

```php
use Luminus\Async;

$results = Async::all([
    'users' => fn() => $db->query('SELECT * FROM users'),
    'posts' => fn() => $db->query('SELECT * FROM posts'),
    'count' => fn() => $db->table('comments')->count(),
]);

echo $results['count']; // int
```

Each task runs in a Fiber. With blocking callbacks like the ones above the
tasks run one after another — use this for structuring code, not for speed.
Tasks that call `Fiber::suspend()` will interleave.

## Collection mapping

Map a callback over items, collecting results:

```php
$uppercased = Async::collect(
    ['alice', 'bob', 'charlie'],
    fn(string $name) => strtoupper($name)
);
```

## Concurrent HTTP requests

Uses `curl_multi_exec` for true I/O concurrency — all requests are in flight
at the same time:

```php
$responses = Async::httpGet([
    'github' => 'https://api.github.com',
    'json' => 'https://jsonplaceholder.typicode.com/posts/1',
]);

echo $responses['github']['body'];       // response body
echo $responses['github']['info']['http_code']; // 200
echo $responses['github']['error'];      // empty if no error

// With options
$responses = Async::httpGet(
    ['url1' => 'https://...', 'url2' => 'https://...'],
    ['timeout' => 10, 'connect_timeout' => 3]
);
```

## Low-level API

```php
$fiber = Async::run(function () {
    // runs immediately, up to the first Fiber::suspend()
    return expensiveOperation();
});

$result = Async::await($fiber);

// Deferred: nothing runs until you wait for it
$fiber = Async::deferred(fn() => expensiveOperation());
$result = Async::wait($fiber); // starts and drives the fiber
```

## When to use async

- Multiple HTTP API calls → `Async::httpGet()` (truly concurrent)
- Structuring groups of related tasks → `Async::all()` / `Async::collect()`
- Cooperative multitasking with custom suspension points → `Async::run()` / `await()`
