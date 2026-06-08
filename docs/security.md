# Security

Luminus is designed to be lightweight and simple, yet highly secure. This guide explains how to use the built-in security features to protect your application from common web vulnerabilities, including **Cross-Site Scripting (XSS)**, **Cross-Site Request Forgery (CSRF)**, **Clickjacking**, and **MIME sniffing**.

---

## 1. Cross-Site Scripting (XSS) Protection

XSS occurs when malicious scripts are injected into trusted websites and executed by client browsers. Rather than corrupting raw inputs via aggressive global sanitization, Luminus solves XSS using **context-aware output escaping** at the time of rendering.

### The `e()` Helper Function

The global `e()` helper function secures template outputs:

```php
function e(mixed $value, bool $doubleEncode = true): string
```

It uses `htmlspecialchars()` with safe default configurations:
* Uses the flags `ENT_QUOTES | ENT_SUBSTITUTE` (escapes single and double quotes, and replaces invalid code unit sequences instead of returning an empty string).
* Defaults to `UTF-8` encoding.

### Usage in Views

Always wrap dynamic output variables in the `e()` helper:

```html
<!-- Secure Output Rendering -->
<h1><?= e($title) ?></h1>
<p>Welcome back, <?= e($username) ?>!</p>
```

---

## 2. Session Management

Luminus provides an object-oriented [Session](file:///home/rinocs/projects/luminus/src/Session.php) class that wraps PHP's native sessions, enforcing secure cookie parameters.

### Secure Cookies

When a session starts, Luminus automatically applies the following security configurations:
* `cookie_httponly`: `true` (Prevents client-side scripts from reading the session cookie, stopping cookie-theft XSS).
* `cookie_samesite`: `'Lax'` (Helps protect against CSRF attacks).
* `cookie_secure`: Enforced automatically when running on HTTPS.

### Working with Session Data

You can interact with session data using the static `Session` class or the global `session()` helper:

```php
use Luminus\Session;

// Using the Session class
Session::put('user_id', 42);
$userId = Session::get('user_id');
$exists = Session::has('user_id');
Session::forget('user_id');

// Or using the session() helper
session(['user_id' => 42]); // Write
$userId = session('user_id'); // Read
```

### Session Flash Messages

Flash messages are temporary session variables that persist **only for the next request**. They are ideal for validation errors or status alerts:

```php
// In your controller
Session::flash('success', 'Profile updated successfully!');

// In your view
<?php if (Session::has('success')): ?>
    <div class="alert"><?= e(Session::getFlash('success')) ?></div>
<?php endif ?>
```

---

## 3. Cross-Site Request Forgery (CSRF) Protection

CSRF is an attack that forces an authenticated user to execute unwanted actions on a web application.

### How it Works in Luminus

1. When a user session starts, a unique, cryptographically secure **CSRF token** is generated and stored in the session.
2. The `CsrfMiddleware` intercepts all state-changing HTTP requests (`POST`, `PUT`, `PATCH`, `DELETE`).
3. It extracts the token from the input body (`_token`), the `X-CSRF-TOKEN` header, or the `X-XSRF-TOKEN` header, and compares it against the session's token using timing-attack safe comparison (`hash_equals`).
4. On every response, `CsrfMiddleware` sets an `XSRF-TOKEN` cookie containing the token. Frontend JS clients (like Axios) read this cookie automatically and attach it as the `X-XSRF-TOKEN` header in AJAX requests.

### 1. Registering the Middleware

Register the middlewares on the Router. **Note**: `StartSessionMiddleware` must be registered *before* `CsrfMiddleware`.

```php
use Luminus\StartSessionMiddleware;
use Luminus\CsrfMiddleware;

$router->addMiddleware(new StartSessionMiddleware());
$router->addMiddleware(new CsrfMiddleware());
```

### 2. Adding CSRF Fields to Forms

Use the `csrf_field()` helper inside POST/PUT/PATCH/DELETE forms to inject a hidden input containing the token:

```html
<form method="POST" action="/posts">
    <?= csrf_field() ?>
    
    <label for="title">Title</label>
    <input type="text" name="title" id="title">
    
    <button type="submit">Create Post</button>
</form>
```

The helper generates the following HTML:

```html
<input type="hidden" name="_token" value="8a7b6c5d4e3f2...your-token...">
```

### Excluding Routes (e.g. APIs)

Stateless endpoints (such as APIs authenticated with bearer keys or API tokens) do not use sessions and should bypass CSRF checks. You can configure exclusions in the middleware constructor using wildcard matching:

```php
// Exclude all routes starting with /api/
$router->addMiddleware(new CsrfMiddleware(except: [
    '/api/*',
    '/webhook/stripe'
]));
```

---

## 4. Security Headers

Luminus includes a `SecurityHeadersMiddleware` that adds defense-in-depth security headers to every response, instructing browser clients to enforce secure behaviors.

### Registered Headers

By default, the middleware adds:
* `X-Content-Type-Options: nosniff`: Instructs browsers not to sniff the MIME type of a file, forcing them to adhere to the `Content-Type` header (prevents malicious uploads executing as HTML/JS).
* `X-Frame-Options: SAMEORIGIN`: Prevents the page from being rendered inside an `<iframe>` on external websites (protects against clickjacking attacks).
* `X-XSS-Protection: 1; mode=block`: Activates browser-level reflective XSS protection.
* `Referrer-Policy: strict-origin-when-cross-origin`: Controls how much referrer information is sent along with cross-origin requests.

### Configuration

Register the middleware on the Router:

```php
use Luminus\SecurityHeadersMiddleware;

$router->addMiddleware(new SecurityHeadersMiddleware());
```

You can customize or add headers (such as a Content-Security-Policy) by passing them to the constructor:

```php
$router->addMiddleware(new SecurityHeadersMiddleware([
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' https://trusted-cdn.com;",
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
]));
```

---

## 5. Secure Cookie Management

Luminus extends Request and Response classes with helper methods to safely configure cookie lifecycles.

### Setting Cookies

The `cookie()` method on the `Response` object allows you to write cookies securely:

```php
public function cookie(
    string $name,
    string $value = '',
    int $expires = 0,
    string $path = '/',
    string $domain = '',
    bool $secure = false,
    bool $httpOnly = true,
    string $sameSite = 'Lax'
): static
```

#### Example:

```php
$router->get('/dark-mode', function(Response $response) {
    return $response
        ->cookie(
            name: 'theme',
            value: 'dark',
            expires: time() + 3600 * 24 * 30, // 30 days
            path: '/',
            secure: true,      // Sent only over HTTPS
            httpOnly: true,    // Inaccessible to JavaScript
            sameSite: 'Strict' // Prevents cookie transmission in cross-site requests
        )
        ->body('Theme set to dark mode!');
});
```

### Reading Cookies

Retrieve cookies from the `Request` object using the `cookie()` helper method:

```php
$router->get('/profile', function(Request $request) {
    $theme = $request->cookie('theme', 'light'); // Defaults to 'light'
    // ...
});
```
