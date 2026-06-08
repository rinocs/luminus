# Request & Response

## Request

```php
$router->post('/test', function (Request $req) {
    $req->method();           // GET, POST, PUT, DELETE
    $req->path();             // /test
    $req->uri();              // /test?foo=bar
    $req->isMethod('post');   // true

    $req->input('name');      // $_GET or $_POST
    $req->query('page');      // $_GET only
    $req->post('email');      // $_POST only
    $req->all();              // $_GET + $_POST

    $req->header('Content-Type');
    $req->json();             // parsed JSON body (associative array)
    $req->file('avatar');     // $_FILES entry
    $req->hasFile('avatar');  // bool

    $req->scheme();           // http or https
    $req->host();             // example.com:8080
    $req->isSecure();         // bool
});
```

## Response

**Plain text / HTML:**

```php
$router->get('/', function () {
    return '<h1>Hello</h1>';
});
```

**JSON:**

```php
$router->get('/api/users', function (Response $res): Response {
    return $res->json(['users' => [...]]);
});
```

**Status code:**

```php
$router->get('/not-found', function (Response $res): Response {
    return $res->status(404)->json(['error' => 'Not found']);
});
```

**Redirect:**

```php
$router->get('/old-page', function (Response $res): Response {
    return $res->redirect('/new-page');
});
```

Redirect sets the Location header and status code. The response must be **returned** from the handler (redirect no longer calls `exit`).

**Custom headers:**

```php
return (new Response())
    ->status(201)
    ->header('X-Custom', 'value')
    ->json(['created' => true]);
```

## Return values

Handlers can return:
- A `Response` object — sent as-is
- A `string` — wrapped in a `Response` with body set
