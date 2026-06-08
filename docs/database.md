# Database

PDO wrapper with a fluent query builder.

## Connection

Configured in `config/app.php` under the `database` key. The `Database` service is registered automatically when config has a `database` key.

```php
$db = $container->get(Database::class);
$pdo = $db->connect();  // returns the raw PDO instance
```

## Raw queries

```php
$db->query('SELECT * FROM users WHERE active = ?', [1]);
$db->execute('UPDATE users SET name = ? WHERE id = ?', [$name, $id]);

// INSERT helper returns lastInsertId
$id = $db->insert('users', ['name' => 'Alice', 'email' => 'alice@test.com']);
```

## Query builder

```php
$db->table('users')
    ->where('active', '=', 1)
    ->orderBy('name', 'ASC')
    ->limit(10)
    ->get();

$db->table('users')->find(5);          // by primary key (default: 'id')
$db->table('users')->first();          // first match or null

$db->table('users')->where('email', '=', $email)->first();

$db->table('users')->where('name', 'LIKE', '%alice%')->get();

// Count
$db->table('users')->where('active', '=', 1)->count();
```

## Insert / Update / Delete

```php
// Insert
$id = $db->table('posts')->insert([
    'title' => 'Hello',
    'body' => 'Content here',
]);

// Update
$db->table('posts')
    ->where('id', '=', 5)
    ->update(['title' => 'Updated title']);

// Delete
$db->table('posts')
    ->where('id', '=', 5)
    ->delete();
```

## Transactions

```php
$db->beginTransaction();
try {
    $db->execute('UPDATE accounts SET balance = balance - 100 WHERE id = 1');
    $db->execute('UPDATE accounts SET balance = balance + 100 WHERE id = 2');
    $db->commit();
} catch (\Throwable $e) {
    $db->rollback();
}
```
