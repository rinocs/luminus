<?php

use PHPUnit\Framework\TestCase;
use Luminus\Container;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->set(Container::class, $this->container);
    }

    public function test_set_and_get_concrete_value(): void
    {
        $this->container->set('foo', 'bar');
        $this->assertSame('bar', $this->container->get('foo'));
    }

    public function test_set_and_get_callable(): void
    {
        $this->container->set('random', fn() => rand());
        $this->assertIsInt($this->container->get('random'));
    }

    public function test_singleton_returns_same_instance(): void
    {
        $this->container->singleton('obj', fn() => new stdClass());
        $a = $this->container->get('obj');
        $b = $this->container->get('obj');
        $this->assertSame($a, $b);
    }

    public function test_set_replaces_singleton(): void
    {
        $this->container->singleton('obj', fn() => new stdClass());
        $first = $this->container->get('obj');

        $this->container->set('obj', fn() => new stdClass());
        $second = $this->container->get('obj');

        $this->assertNotSame($first, $second);
    }

    public function test_has_returns_true_for_bound(): void
    {
        $this->container->set('foo', 'bar');
        $this->assertTrue($this->container->has('foo'));
    }

    public function test_has_returns_false_for_unbound(): void
    {
        $this->assertFalse($this->container->has('nonexistent'));
    }

    public function test_autowiring_resolves_class(): void
    {
        $obj = $this->container->get(ContainerTest_Dependency::class);
        $this->assertInstanceOf(ContainerTest_Dependency::class, $obj);
    }

    public function test_autowiring_injects_dependencies(): void
    {
        $obj = $this->container->get(ContainerTest_NeedsDependency::class);
        $this->assertInstanceOf(ContainerTest_NeedsDependency::class, $obj);
        $this->assertInstanceOf(ContainerTest_Dependency::class, $obj->dep);
    }

    public function test_autowiring_uses_bound_dependencies(): void
    {
        $mock = new ContainerTest_Dependency();
        $mock->value = 'injected';
        $this->container->set(ContainerTest_Dependency::class, fn() => $mock);

        $obj = $this->container->get(ContainerTest_NeedsDependency::class);
        $this->assertSame('injected', $obj->dep->value);
    }

    public function test_container_returns_self(): void
    {
        $this->assertSame($this->container, $this->container->get(Container::class));
    }

    public function test_resolve_uninstantiable_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->get(ContainerTest_Interface::class);
    }
}

class ContainerTest_Dependency
{
    public string $value = 'default';
}

class ContainerTest_NeedsDependency
{
    public ContainerTest_Dependency $dep;

    public function __construct(ContainerTest_Dependency $dep)
    {
        $this->dep = $dep;
    }
}

interface ContainerTest_Interface {}
