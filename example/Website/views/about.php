<?php $this->layout('layouts.main') ?>

<?php $this->section('content') ?>
<h1><?= $title ?></h1>
<p><?= $description ?></p>

<h2>Why?</h2>
<p>
    Laravel, Symfony — great tools, but sometimes you just need something smaller.
    No service providers, no facades, no artisan. Just routes, controllers, and views.
</p>

<h2>Stack</h2>
<ul>
    <li>PHP 8.2+</li>
    <li>Zero third-party dependencies</li>
    <li>PSR-4 autoloading via Composer</li>
    <li>PDO for databases (optional)</li>
</ul>
<?php $this->endSection() ?>
