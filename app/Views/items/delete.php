<?php

declare(strict_types=1);

$item = is_array($item ?? null) ? $item : [];
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Delete Item</h2>
    <p class="mt-1 text-sm text-slate-500">
        Review the item details before permanent deletion.
    </p>
</div>

<div class="rounded-2xl border border-red-200 bg-white p-6 shadow-sm ring-1 ring-red-100">
    <div class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">
        This action permanently deletes the item record.
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Item Name</div>
            <div class="mt-1 text-base font-medium text-slate-800">
                <?= htmlspecialchars((string) ($item['name'] ?? '')) ?>
            </div>
        </div>

        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Item Code</div>
            <div class="mt-1 text-base font-medium text-slate-800">
                <?= htmlspecialchars((string) ($item['item_code'] ?? '')) ?>
            </div>
        </div>

        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Category</div>
            <div class="mt-1 text-base font-medium text-slate-800">
                <?= htmlspecialchars((string) ($item['category_name'] ?? 'Uncategorized')) ?>
            </div>
        </div>

        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cost</div>
            <div class="mt-1 text-base font-medium text-slate-800">
                ₱<?= number_format((float) ($item['cost'] ?? 0), 2) ?>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="/items/delete" class="mt-6 flex flex-col gap-3 sm:flex-row">
    <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">

    <a
        href="/items"
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