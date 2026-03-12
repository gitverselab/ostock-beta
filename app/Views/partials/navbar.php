<?php

declare(strict_types=1);

use App\Support\Auth;
?>

<header class="border-b border-slate-200 bg-white">
    <div class="flex flex-col gap-4 px-4 py-4 md:flex-row md:items-center md:justify-between md:px-6">
        <div>
            <h1 class="text-xl font-bold text-slate-800">
                <?= htmlspecialchars($title ?? 'Dashboard') ?>
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Welcome back, <?= htmlspecialchars(Auth::username() ?? 'User') ?>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div class="rounded-xl bg-slate-100 px-4 py-2 text-sm text-slate-600">
                Logged in as
                <span class="font-semibold text-slate-800">
                    <?= htmlspecialchars(Auth::username() ?? 'User') ?>
                </span>
            </div>

            <form method="POST" action="/logout">
                <button
                    type="submit"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                    Logout
                </button>
            </form>
        </div>
    </div>
</header>