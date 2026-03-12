<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
    <p class="mt-1 text-slate-600">
        Welcome, <?= htmlspecialchars((string) ($user['username'] ?? 'User')) ?>
    </p>
</div>

<div class="grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">Total Items</p>
        <h2 class="mt-2 text-3xl font-bold text-slate-800">0</h2>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">Warehouses</p>
        <h2 class="mt-2 text-3xl font-bold text-slate-800">0</h2>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">Pending Transfers</p>
        <h2 class="mt-2 text-3xl font-bold text-slate-800">0</h2>
    </div>
</div>

<div class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <h3 class="text-lg font-semibold text-slate-800">Next Step</h3>
    <p class="mt-2 text-sm leading-6 text-slate-600">
        Your MVC shell is now ready for the first real module migration.
        The best next module is Items, followed by Warehouses.
    </p>
</div>