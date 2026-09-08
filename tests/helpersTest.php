<?php

use PHPUnit\Framework\TestCase;

class helpersTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV = [];
        putenv('TEST_EXISTING');
    }

    public function test_env_returns_value(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $this->assertSame('production', env('APP_ENV'));
    }

    public function test_env_returns_default(): void
    {
        $this->assertSame('default', env('NONEXISTENT', 'default'));
    }

    public function test_env_returns_null_default(): void
    {
        $this->assertNull(env('NONEXISTENT'));
    }

    public function test_env_parses_true(): void
    {
        $_ENV['FLAG'] = 'true';
        $this->assertTrue(env('FLAG'));
    }

    public function test_env_parses_false(): void
    {
        $_ENV['FLAG'] = 'false';
        $this->assertFalse(env('FLAG'));
    }

    public function test_env_parses_null_string(): void
    {
        $_ENV['VAL'] = 'null';
        $this->assertNull(env('VAL'));
    }

    public function test_env_parses_true_parenthesis(): void
    {
        $_ENV['FLAG'] = '(true)';
        $this->assertTrue(env('FLAG'));
    }

    public function test_env_uses_putenv(): void
    {
        putenv('TEST_PUT=from_putenv');
        $this->assertSame('from_putenv', env('TEST_PUT'));
    }

    public function test_array_find(): void
    {
        $array = [1, 2, 3, 4, 5];
        $this->assertSame(4, array_find($array, fn($v) => $v > 3));
        $this->assertNull(array_find($array, fn($v) => $v > 10));
    }

    public function test_array_find_key(): void
    {
        $array = ['a' => 1, 'b' => 2];
        $this->assertSame('b', array_find_key($array, fn($v) => $v === 2));
    }

    public function test_array_any(): void
    {
        $array = [1, 2, 3];
        $this->assertTrue(array_any($array, fn($v) => $v === 2));
        $this->assertFalse(array_any($array, fn($v) => $v === 10));
    }

    public function test_array_all(): void
    {
        $array = [1, 2, 3];
        $this->assertTrue(array_all($array, fn($v) => $v < 10));
        $this->assertFalse(array_all($array, fn($v) => $v === 2));
    }
}
