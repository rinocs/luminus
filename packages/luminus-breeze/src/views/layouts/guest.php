<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? e($title) . ' — ' : '' ?>Luminus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        border: '#e2e8f0',
                        input: '#e2e8f0',
                        ring: '#0f172a',
                        background: '#ffffff',
                        foreground: '#0f172a',
                        primary: {
                            DEFAULT: '#0f172a',
                            foreground: '#f8fafc',
                        },
                        secondary: {
                            DEFAULT: '#f1f5f9',
                            foreground: '#0f172a',
                        },
                        destructive: {
                            DEFAULT: '#ef4444',
                            foreground: '#f8fafc',
                        },
                        muted: {
                            DEFAULT: '#f1f5f9',
                            foreground: '#64748b',
                        },
                        accent: {
                            DEFAULT: '#f1f5f9',
                            foreground: '#0f172a',
                        },
                    },
                    borderRadius: {
                        lg: '0.5rem',
                        md: '0.375rem',
                        sm: '0.25rem',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-muted flex items-center justify-center p-4">
    <div class="w-full max-w-[420px]">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">Luminus</h1>
            <p class="text-sm text-muted-foreground mt-1">Authentication starter kit</p>
        </div>

        <?php $this->renderSection('content') ?>

        <p class="mt-6 text-center text-xs text-muted-foreground">
            Built with Luminus Breeze
        </p>
    </div>
</body>
</html>
