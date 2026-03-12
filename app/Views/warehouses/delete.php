<?php

declare(strict_types=1);

$warehouse = is_array($warehouse ?? null) ? $warehouse : [];
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Delete Warehouse</h2>
    <p class="mt-1 text-sm text-slate-500">
        Review the warehouse details before permanent deletion.
    </p>
</div>

<div class="rounded-2xl border border-red-200 bg-white p-6 shadow-sm ring-1 ring-red-100">
    <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">
        This action permanently deletes the warehouse record.
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Warehouse Name</div>
            <div class="mt-1 text-base font-medium text-slate-800">
                <?= htmlspecialchars((string) ($warehouse['name'] ?? '')) ?>
            </div>
        </div>

        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</div>
            <div class="mt-1 text-base font-medium text-slate-800">
                <?= htmlspecialchars((string) ($warehouse['address'] ?? '')) ?>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="/warehouses/delete" class="mt-6 flex flex-col gap-3 sm:flex-row">
    <input type="hidden" name="id" value="<?= (int) ($warehouse['id'] ?? 0) ?>">

    <a
        href="/warehouses"
        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
    >
        Cancel
    </a>

    <button
        type="submit"
        class="inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700"
    >
        Confirm Delete
    </button>
</form>