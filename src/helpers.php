<?php

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML special characters in a string securely.
     */
    function e(mixed $value, bool $doubleEncode = true): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
        }

        return '';
    }
}

// Compatibility polyfills for PHP < 8.4 array helper functions
if (!function_exists('array_find')) {
    function array_find(array $array, callable $callback): mixed
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return null;
    }
}

if (!function_exists('array_find_key')) {
    function array_find_key(array $array, callable $callback): mixed
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $key;
            }
        }
        return null;
    }
}

if (!function_exists('array_any')) {
    function array_any(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('array_all')) {
    function array_all(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the active CSRF token.
     */
    function csrf_token(): string
    {
        return \Luminus\Session::token();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a CSRF HTML hidden input field.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('session')) {
    /**
     * Get or set session values.
     */
    function session(string|array|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return null;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                \Luminus\Session::put($k, $v);
            }
            return null;
        }

        return \Luminus\Session::get($key, $default);
    }
}

