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
}
