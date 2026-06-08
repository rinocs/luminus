# Async

Fiber-based concurrency (PHP 8.1+). No external dependencies.

## Parallel execution

Run multiple independent tasks concurrently:

```php
use Luminus\Async;

$results = Async::all([
    'users' => fn() => $db->query('SELECT * FROM users'),
    'posts' => fn() => $db->query('SELECT * FROM posts'),
    'count' => fn() => $db->table('comments')->count(),
]);

echo $results['count']; // int
```

Each task runs in a Fiber. Results are collected in the same order.

## Collection mapping

Process items concurrently:

```php
$uppercased = Async::collect(
    ['alice', 'bob', 'charlie'],
    fn(string $name) => strtoupper($name)
);
```

## Concurrent HTTP requests

Uses `curl_multi_exec` for true I/O concurrency:

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
    $result = expensiveOperation();
    return $result;
});

// ... do other work ...

$result = Async::await($fiber);
```

## When to use async

- Multiple independent database queries
- Multiple HTTP API calls
- Parallel I/O operations where order doesn't matter

Queries still block individually. The gain comes from running them concurrently instead of sequentially.
