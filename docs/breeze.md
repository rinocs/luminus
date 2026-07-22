# Breeze — Auth Starter Kit

Luminus Breeze provides complete authentication scaffolding for the Luminus framework. One command gives you register, login, logout, and password confirmation flows with clean, shadcn/ui-inspired views.

No build step, no React — pure PHP templates styled with Tailwind CSS utility classes.

---

## Installation

### Option 1: During project creation (recommended)

When you create a new Luminus project with `bin/new`, the installer asks:

```
Install Breeze auth scaffolding? [y/N]
```

Answer `y` and the script will:

1. Add `luminus/breeze` as a Composer dependency
2. Auto-wire `StartSessionMiddleware` and `CsrfMiddleware` in `public/index.php`
3. Register `BreezeServiceProvider`
4. Publish Breeze views and migrations

After creation, run migrations and start the dev server:

```bash
cd my-app
php bin/migrate up
make dev
```

Visit `/login` and `/register` in your browser.

### Option 2: Add to an existing project

```bash
composer require luminus/breeze
```

Then run the install command to publish assets:

```bash
php vendor/bin/breeze install
```

Finally, wire the provider and middleware in `public/index.php`:

```php
use Luminus\Breeze\BreezeServiceProvider;
use Luminus\CsrfMiddleware;
use Luminus\StartSessionMiddleware;

$app = new Luminus\App();
$app->loadEnv();
$app->loadConfig(require __DIR__ . '/../config/app.php');

$app->addMiddleware(new StartSessionMiddleware());
$app->addMiddleware(new CsrfMiddleware());

$app->registerProviders([
    BreezeServiceProvider::class,
]);

$app->loadRoutes(__DIR__ . '/../routes/web.php');
$app->run();
```

Run migrations:

```bash
php bin/migrate up
```

---

## What you get

### Routes

| Method | Path | Action |
|--------|------|--------|
| GET | `/login` | Show login form |
| POST | `/login` | Authenticate user |
| POST | `/logout` | Destroy session |
| GET | `/register` | Show registration form |
| POST | `/register` | Create account |
| GET | `/confirm-password` | Show password confirmation |
| POST | `/confirm-password` | Verify password |

### Controllers

- **`AuthController`** — Handles login, logout, and registration
- **`ConfirmablePasswordController`** — Handles password confirmation for sensitive areas

Both use the container for dependency injection (`App`, `View`, `Database`).

### Middleware

- **`Authenticate`** — Redirects guests to `/login`. Flashes the intended URL so users return to where they were after signing in.
- **`RedirectIfAuthenticated`** — Redirects logged-in users away from auth pages (e.g. `/login`, `/register`).

Use them in your own routes:

```php
use Luminus\Breeze\Middleware\Authenticate;
use Luminus\Breeze\Middleware\RedirectIfAuthenticated;

$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(new Authenticate());

$router->get('/login', [AuthController::class, 'create'])
    ->middleware(new RedirectIfAuthenticated());
```

### Views

Breeze registers a view namespace called `breeze::`. Templates live inside the package but are copied to your app on install so you can customize them.

```php
$view->render('breeze::auth.login');     // views/vendor/breeze/auth/login.php
$view->render('breeze::auth.register');  // views/vendor/breeze/auth/register.php
$view->render('breeze::auth.confirm-password');
```

All auth pages share a `breeze::layouts.guest` layout that provides:

- Tailwind CSS via CDN (no build step)
- shadcn/ui-inspired color tokens (`primary`, `secondary`, `destructive`, `muted`, etc.)
- A centered card layout with clean form styling
- The Inter font from Google Fonts

### Database

Breeze publishes a migration that creates the `users` table:

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    remember_token TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

The migration uses portable SQL that works on both **SQLite** and **MySQL**.

---

## How it works

### Registration

1. User fills name, email, password, and confirmation
2. Server validates:
   - Name is required and ≤ 255 characters
   - Email is valid and unique
   - Password is at least 8 characters
   - Password matches confirmation
3. Password is hashed with `password_hash(..., PASSWORD_BCRYPT)`
4. User row is inserted and the user is signed in immediately

### Login

1. Server validates email and password presence
2. Looks up the user by email
3. Verifies password with `password_verify()`
4. Regenerates the session ID (prevents session fixation)
5. Stores `user_id`, `user_email`, and `user_name` in session
6. Redirects to the intended URL or `/`

### Logout

1. Clears session auth keys
2. Regenerates the session ID and CSRF token
3. Redirects to `/`

### Password Confirmation

Some actions (like changing email or deleting an account) should require the user to re-enter their password. Breeze provides a reusable `/confirm-password` flow:

1. `Authenticate` middleware ensures the user is logged in
2. User submits their current password
3. Server verifies it against the hashed password in the database
4. On success, stores `auth_password_confirmed_at` in the session

You can check this timestamp in your own controllers:

```php
if (!Session::has('auth_password_confirmed_at')) {
    return (new Response())->redirect('/confirm-password');
}
```

---

## Customizing views

After running `breeze install`, views are copied to `views/vendor/breeze/`. Edit them directly — the package will load your local copies first.

### Changing colors

The guest layout defines a custom Tailwind config inline:

```html
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                primary: { DEFAULT: '#0f172a', foreground: '#f8fafc' },
                // ...
            }
        }
    }
}
</script>
```

Change the hex values to match your brand.

### Adding a logo

Edit `views/vendor/breeze/layouts/guest.php`:

```html
<div class="mb-6 text-center">
    <img src="/logo.svg" alt="Logo" class="mx-auto h-12 w-auto">
    <h1 class="text-2xl font-semibold tracking-tight text-foreground">Your App</h1>
</div>
```

---

## Checking authentication in your app

Breeze stores the authenticated user in the session. Check it anywhere:

```php
use Luminus\Session;

// Check if logged in
if (Session::has('user_id')) {
    // user is authenticated
}

// Get current user data
$userId = Session::get('user_id');
$userEmail = Session::get('user_email');
$userName = Session::get('user_name');
```

Or fetch the full record from the database:

```php
$user = $db->query('SELECT * FROM users WHERE id = ? LIMIT 1', [
    Session::get('user_id')
]);
```

---

## CLI commands

```bash
# Publish views and migrations
php vendor/bin/breeze install

# Show help
php vendor/bin/breeze help
```

---

## Requirements

- PHP 8.2+
- `luminus/luminus` framework
- PDO database connection configured in `config/app.php`
- `StartSessionMiddleware` and `CsrfMiddleware` registered on the router
