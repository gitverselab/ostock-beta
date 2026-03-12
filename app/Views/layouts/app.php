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
        <aside class="hidden w-64 bg-slate-900 text-white md:block">
            <div class="p-6">
                <h1 class="text-xl font-bold">OStock MVC</h1>
                <p class="mt-1 text-sm text-slate-300">Inventory System</p>
            </div>

            <nav class="px-4 pb-6">
                <a href="/dashboard" class="mb-2 block rounded-lg px-4 py-2 text-sm hover:bg-slate-800">
                    Dashboard
                </a>
            </nav>
        </aside>

        <div class="flex min-h-screen flex-1 flex-col">
            <header class="border-b border-slate-200 bg-white">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800">
                            <?= htmlspecialchars($title ?? 'App') ?>
                        </h2>
                    </div>

                    <form method="POST" action="/logout">
                        <button
                            type="submit"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="flex-1 p-6">
                <?php \App\Support\View::partial('partials.flash'); ?>
                <?= $content ?>
            </main>
        </div>
    </div>
</body>
</html>