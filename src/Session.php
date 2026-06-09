<?php

namespace Luminus;

class Session
{
    /**
     * Start the session if it hasn't been started yet.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = false;
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $secure = true;
            } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
                $secure = true;
            }

            if (php_sapi_name() !== 'cli' && !headers_sent()) {
                session_start([
                    'cookie_httponly' => true,
                    'cookie_secure' => $secure,
                    'cookie_samesite' => 'Lax',
                    'use_strict_mode' => true,
                ]);
            } else {
                @session_start();
            }
        }

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
    }

    /**
     * Get a value from the session.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Store a key/value pair in the session.
     */
    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Check if the session contains a key.
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a key from the session.
     */
    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Get the CSRF token from the session, generating it if it doesn't exist.
     */
    public static function token(): string
    {
        self::start();
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }

    /**
     * Regenerate the session ID (call after login/privilege changes
     * to prevent session fixation).
     */
    public static function regenerate(): void
    {
        self::start();
        if (php_sapi_name() !== 'cli' && !headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Regenerate the CSRF token.
     */
    public static function regenerateToken(): string
    {
        self::start();
        $_SESSION['_token'] = bin2hex(random_bytes(32));
        return $_SESSION['_token'];
    }

    /**
     * Flash a key/value pair to the session for the next request.
     */
    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash']['new'][$key] = $value;
    }

    /**
     * Get a flashed value from the session.
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION['_flash']['old'][$key] ?? $_SESSION['_flash']['new'][$key] ?? $default;
    }

    /**
     * Age the flashed data (moving 'new' to 'old' and clearing 'old').
     */
    public static function ageFlashData(): void
    {
        self::start();
        $_SESSION['_flash']['old'] = $_SESSION['_flash']['new'] ?? [];
        $_SESSION['_flash']['new'] = [];
    }

    /**
     * Clear all session data and destroy the session.
     */
    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (session_status() !== PHP_SESSION_NONE) {
            if (php_sapi_name() !== 'cli' && !headers_sent() && ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();
        }
    }
}
