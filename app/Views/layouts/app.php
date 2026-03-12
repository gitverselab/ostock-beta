<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'App') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-slate-900 text-white p-6">
            <h1 class="text-xl font-bold">OStock MVC</h1>
            <nav class="mt-6 space-y-2">
                <a href="/dashboard" class="block rounded px-3 py-2 hover:bg-slate-800">Dashboard</a>
            </nav>
        </aside>

        <main class="flex-1 p-6">
            <?= $content ?>
        </main>
    </div>
</body>
</html>