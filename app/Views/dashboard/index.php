<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Dashboard</h1>
    <p class="mt-1 text-slate-600">
        Welcome, <?= htmlspecialchars($user['username'] ?? 'User') ?>
    </p>
</div>

<div class="grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl bg-white p-5 shadow">
        <p class="text-sm text-slate-500">Total Items</p>
        <h2 class="mt-2 text-3xl font-bold">0</h2>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow">
        <p class="text-sm text-slate-500">Warehouses</p>
        <h2 class="mt-2 text-3xl font-bold">0</h2>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow">
        <p class="text-sm text-slate-500">Pending Transfers</p>
        <h2 class="mt-2 text-3xl font-bold">0</h2>
    </div>
</div>