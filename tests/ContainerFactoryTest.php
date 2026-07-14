<?php

use PHPUnit\Framework\TestCase;
use Luminus\Container;

class ContainerFactoryTest extends TestCase
{
    public function test_factory_binding(): void
    {
        $container = new Container();
        $container->set('factory', fn() => new stdClass());

        $instance1 = $container->get('factory');
        $instance2 = $container->get('factory');

        $this->assertNotSame($instance1, $instance2);
    }

    public function test_singleton_binding(): void
    {
        $container = new Container();
        $container->singleton('singleton', fn() => new stdClass());

        $instance1 = $container->get('singleton');
        $instance2 = $container->get('singleton');

        $this->assertSame($instance1, $instance2);
    }
}
