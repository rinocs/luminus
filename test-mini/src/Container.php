<?php

namespace Luminus;

class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function set(string $id, mixed $resolver): void
    {
        $this->bindings[$id] = $resolver;
        unset($this->instances[$id]);
    }

    public function singleton(string $id, callable $resolver): void
    {
        $this->bindings[$id] = $resolver;
        unset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $resolver = $this->bindings[$id];
            $instance = is_callable($resolver) ? $resolver($this) : $resolver;
        } else {
            $instance = $this->resolve($id);
        }

        $this->instances[$id] = $instance;
        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    private function resolve(string $class): object
    {
        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException("Cannot instantiate {$class}");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class;
        }

        $params = $constructor->getParameters();
        $dependencies = [];

        foreach ($params as $param) {
            $type = $param->getType();

            if ($type === null || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw new \RuntimeException(
                        "Cannot resolve parameter \${$param->getName()} for {$class}"
                    );
                }
            } else {
                $dependencies[] = $this->get($type->getName());
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
