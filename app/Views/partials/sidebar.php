<?php

declare(strict_types=1);

use App\Support\Auth;

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentPath = is_string($currentPath) ? rtrim($currentPath, '/') ?: '/' : '/';

$isDashboard = $currentPath === '/dashboard';
$isItems = str_starts_with($currentPath, '/items');
$isWarehouses = str_starts_with($currentPath, '/warehouses');

$navLinkClass = function (bool $active): string {
    if ($active) {
        return 'block rounded-xl bg-slate-800 px-4 py-3 text-sm font-semibold text-white';
    }

    return 'block rounded-xl px-4 py-3 text-sm text-slate-300 hover:bg-slate-800 hover:text-white';
};
?>

<aside class="w-full bg-slate-900 text-white md:min-h-screen md:w-72">
    <div class="border-b border-slate-800 px-6 py-5">
        <div class="text-2xl font-bold tracking-tight">OStock MVC</div>
        <div class="mt-1 text-sm text-slate-400">Inventory Management</div>
    </div>

    <div class="px-6 py-5">
        <div class="rounded-2xl bg-slate-800/70 p-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">Signed in as</div>
            <div class="mt-1 text-sm font-semibold text-white">
                <?= htmlspecialchars(Auth::username() ?? 'Guest') ?>
            </div>
        </div>
    </div>

    <nav class="px-4 pb-6">
        <div class="mb-3 px-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
            Main
        </div>

        <div class="space-y-1">
            <a href="/dashboard" class="<?= $navLinkClass($isDashboard) ?>">
                Dashboard
            </a>

            <a href="/items" class="<?= $navLinkClass($isItems) ?>">
                Items
            </a>

            <a href="/warehouses" class="<?= $navLinkClass($isWarehouses) ?>">
                Warehouses
            </a>
        </div>

        <div class="mt-6 mb-3 px-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
            Next Modules
        </div>

        <div class="rounded-xl border border-dashed border-slate-700 px-4 py-3 text-sm text-slate-400">
            Inbound, outbound, and transfer modules come after Warehouses.
        </div>
    </nav>
</aside>