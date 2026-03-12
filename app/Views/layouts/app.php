<?php

declare(strict_types=1);

use App\Support\View;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'OStock MVC') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="min-h-screen md:flex">
        <?php View::partial('partials.sidebar', ['title' => $title ?? 'Dashboard']); ?>

        <div class="flex min-h-screen flex-1 flex-col">
            <?php View::partial('partials.navbar', ['title' => $title ?? 'Dashboard']); ?>

            <main class="flex-1 p-4 md:p-6">
                <?php View::partial('partials.flash'); ?>
                <?= $content ?>
            </main>
        </div>
    </div>
</body>
</html>