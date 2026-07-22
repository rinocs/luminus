<?php $this->layout('breeze::layouts.guest') ?>
<?php $this->section('content') ?>

<div class="bg-background border border-border rounded-lg shadow-sm p-6">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-foreground">Confirm your password</h2>
        <p class="text-sm text-muted-foreground mt-1">This is a secure area. Please confirm your password before continuing.</p>
    </div>

    <form action="/confirm-password" method="POST" class="space-y-4">
        <?= csrf_field() ?>

        <div class="space-y-2">
            <label for="password" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
                required
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            >
            <?php if (isset($errors['password'])): ?>
                <p class="text-sm text-destructive"><?= e($errors['password']) ?></p>
            <?php endif; ?>
        </div>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow hover:bg-primary/90 h-9 px-4 py-2 w-full"
        >
            Confirm
        </button>
    </form>
</div>

<?php $this->endSection() ?>
