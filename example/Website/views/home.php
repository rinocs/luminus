<?php $this->layout('layouts.main') ?>

<?php $this->section('content') ?>
<h1><?= e($title) ?></h1>
<p>A minimal PHP framework built for clarity and control.</p>

<h2>Features</h2>
<ul>
    <?php foreach ($features as $f): ?>
        <li><?= e($f) ?></li>
    <?php endforeach ?>
</ul>

<p style="margin-top: 2rem;">
    <a href="/about" style="color: #2563eb;">Learn more →</a>
</p>
<?php $this->endSection() ?>
