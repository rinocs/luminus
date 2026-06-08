# Container

The DI container resolves classes via autowiring and manages singletons.

## Usage

```php
$container = $app->getContainer();
```

## Autowiring

Classes with type-hinted constructor parameters are resolved automatically:

```php
class UserController
{
    public function __construct(private Database $db) {}

    public function list(): array
    {
        return $this->db->query('SELECT * FROM users');
    }
}

// Container resolves Database automatically
$controller = $container->get(UserController::class);
```

## Binding

```php
// Factory (new instance each time)
$container->set(Logger::class, fn() => new Logger('/tmp/app.log'));

// Singleton (same instance every time)
$container->singleton(Cache::class, fn() => new Cache('/tmp/cache'));
```

## Resolving

```php
$container->get(Logger::class);  // resolves or returns cached
$container->has(Logger::class);  // bool
```

## How autowiring works

1. If the class is bound, return the bound instance
2. Otherwise, reflect on the constructor
3. Resolve each parameter recursively from the container
4. Return the new instance (cached as singleton)

## Concrete values

You can store any value, not just objects:

```php
$container->set('config', $config);
$container->get('config');  // returns the array
```
