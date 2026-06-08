# Views

Plain PHP templates. No custom syntax, no compilation step.

## Render a view

```php
$router->get('/', function (View $view): string {
    return $view->render('home', ['title' => 'Welcome']);
});
```

Templates live in `views/`. Dot notation maps to directories:

```php
$view->render('home');              // views/home.php
$view->render('admin.dashboard');   // views/admin/dashboard.php
```

## Layouts

```php
<?php // views/home.php
$this->layout('layouts.main');
?>
<h1><?= $title ?></h1>
```

```php
<?php // views/layouts/main.php ?>
<!DOCTYPE html>
<html>
<head><title>My App</title></head>
<body>
    <?php $this->renderSection('content') ?>
</body>
</html>
```

Output from the child template is placed in the `content` section of the layout.

## Sections

Define multiple named sections:

```php
<?php $this->layout('layouts.main') ?>

<?php $this->section('sidebar') ?>
<ul>
    <li>Link 1</li>
    <li>Link 2</li>
</ul>
<?php $this->endSection() ?>

<?php $this->section('content') ?>
<h1>Page title</h1>
<p>Main content here.</p>
<?php $this->endSection() ?>
```

Render them in the layout:

```php
<aside><?php $this->renderSection('sidebar') ?></aside>
<main><?php $this->renderSection('content') ?></main>
```

## Data

Pass data as the second argument to `render()`. Available as variables in the template:

```php
$view->render('profile', [
    'user' => ['name' => 'Alice', 'email' => 'alice@example.com'],
]);
```

```php
<h1><?= $user['name'] ?></h1>
<p><?= $user['email'] ?></p>
```
