<?php $this->layout('layouts.main') ?>

<?php $this->section('content') ?>
<h1><?= e($title) ?></h1>

<?php if (!empty($success)): ?>
    <div class="success"><?= e($success) ?></div>
<?php endif ?>

<?php if (!empty($errors)): ?>
    <div class="error">
        <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<form method="POST" action="/contact">
    <?= csrf_field() ?>
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>">

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>">

    <label for="message">Message</label>
    <textarea id="message" name="message" rows="5"><?= e($old['message'] ?? '') ?></textarea>

    <button type="submit">Send</button>
</form>
<?php $this->endSection() ?>
