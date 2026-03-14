<?php

declare(strict_types=1);

$record = is_array($record ?? null) ? $record : [];
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Edit Outbound Transaction</h2>
    <p class="mt-1 text-sm text-slate-500">
        Adjust removed quantities. The system will reverse the original outbound first, then apply the new values.
    </p>
</div>

<?php if (!empty($formError)): ?>
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars((string) $formError) ?>
    </div>
<?php endif; ?>

<div class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="grid gap-4 md:grid-cols-4 text-sm">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Item</div>
            <div class="mt-1 font-medium text-slate-800"><?= htmlspecialchars((string) ($record['item_name'] ?? '')) ?></div>
        </div>
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Warehouse</div>
            <div class="mt-1 font-medium text-slate-800"><?= htmlspecialchars((string) ($record['warehouse_name'] ?? '')) ?></div>
        </div>
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pallet</div>
            <div class="mt-1 font-medium text-slate-800"><?= htmlspecialchars((string) ($record['pallet_id'] ?? '')) ?></div>
        </div>
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Type</div>
            <div class="mt-1 font-medium text-slate-800"><?= htmlspecialchars((string) ($record['outbound_type'] ?? '')) ?></div>
        </div>
    </div>
</div>

<form method="POST" action="/inventory/outbound/edit" class="space-y-6">
    <input type="hidden" name="id" value="<?= (int) ($record['id'] ?? 0) ?>">

    <div class="grid gap-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:grid-cols-2">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Crates Removed</label>
            <input
                type="number"
                name="quantity_removed"
                min="1"
                value="<?= htmlspecialchars((string) ($record['quantity_removed'] ?? 0)) ?>"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Pieces Removed</label>
            <input
                type="number"
                name="items_per_pc"
                min="0"
                value="<?= htmlspecialchars((string) ($record['items_per_pc'] ?? 0)) ?>"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <a
            href="/inventory/outbound/history"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Cancel
        </a>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
            Update Outbound
        </button>
    </div>
</form>