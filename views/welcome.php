<?php $this->layout('layouts.main') ?>

<?php $this->section('content') ?>
<div style="text-align: center; padding: 4rem 2rem;">
    <h1>Welcome to <?= $name ?></h1>
    <p style="color: #666; font-size: 1.2rem;">
        A minimal PHP framework with zero dependencies.
    </p>

    <hr style="margin: 2rem auto; max-width: 400px;">

    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
        <span style="background: #e3f2fd; padding: .5rem 1rem; border-radius: 8px;"><?= 0 ?> external deps</span>
        <span style="background: #e8f5e9; padding: .5rem 1rem; border-radius: 8px;">PHP 8.2+</span>
        <span style="background: #fff3e0; padding: .5rem 1rem; border-radius: 8px;">PSR-4 autoload</span>
    </div>
</div>
<?php $this->endSection() ?>
