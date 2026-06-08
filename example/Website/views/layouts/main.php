<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | Luminus</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; background: #f5f5f5; line-height: 1.6; }
        nav { background: #1a1a2e; padding: 1rem 2rem; display: flex; gap: 2rem; }
        nav a { color: #e0e0e0; text-decoration: none; font-weight: 500; }
        nav a:hover { color: #fff; }
        main { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        h1 { margin-bottom: 1rem; color: #1a1a2e; }
        p { margin-bottom: 1rem; }
        input, textarea { width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        label { font-weight: 600; display: block; margin-top: 1rem; margin-bottom: .25rem; }
        button { background: #1a1a2e; color: #fff; border: none; padding: .6rem 1.5rem; border-radius: 4px; font-size: 1rem; margin-top: 1rem; cursor: pointer; }
        button:hover { background: #16213e; }
        .error { background: #fef2f2; color: #991b1b; padding: .75rem; border-radius: 4px; margin-bottom: 1rem; }
        .success { background: #f0fdf4; color: #166534; padding: .75rem; border-radius: 4px; margin-bottom: 1rem; }
        ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 1rem; }
        li { margin-bottom: .25rem; }
    </style>
</head>
<body>
    <nav>
        <a href="/">Home</a>
        <a href="/about">About</a>
        <a href="/contact">Contact</a>
    </nav>
    <main>
        <?php $this->renderSection('content') ?>
    </main>
</body>
</html>
