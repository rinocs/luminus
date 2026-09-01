<?php $this->layout('breeze::layouts.guest') ?>
<?php $this->section('content') ?>

<div class="bg-background border border-border rounded-lg shadow-sm p-6">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-foreground">Welcome back</h2>
        <p class="text-sm text-muted-foreground mt-1">Enter your credentials to access your account.</p>
    </div>

    <form action="/login" method="POST" class="space-y-4">
        <?= csrf_field() ?>

        <div class="space-y-2">
            <label for="email" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= e($old['email'] ?? '') ?>"
                placeholder="name@example.com"
                required
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            >
            <?php if (isset($errors['email'])): ?>
                <p class="text-sm text-destructive"><?= e($errors['email']) ?></p>
            <?php endif; ?>
        </div>

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

        <div class="flex items-center space-x-2">
            <input
                type="checkbox"
                id="remember"
                name="remember"
                value="1"
                <?= (!empty($old['remember']) ? 'checked' : '') ?>
                class="h-4 w-4 rounded border-border text-primary focus:ring-ring"
            >
            <label for="remember" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">Remember me</label>
        </div>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow hover:bg-primary/90 h-9 px-4 py-2 w-full"
        >
            Sign in
        </button>
    </form>

    <div class="mt-4 text-center text-sm">
        <span class="text-muted-foreground">Don't have an account?</span>
        <a href="/register" class="font-medium text-foreground underline-offset-4 hover:underline">Sign up</a>
    </div>
</div>

<?php $this->endSection() ?>
