<?php

declare(strict_types=1);

$record = is_array($record ?? null) ? $record : [];
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Edit Transfer</h2>
    <p class="mt-1 text-sm text-slate-500">
        Update pallet and quantity details. The system will reverse the original transfer before applying the new one.
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
            <div class="mt-1 font-medium text-slate-800">
                <?= htmlspecialchars((string) ($record['item_name'] ?? '')) ?>
                <?php if (!empty($record['item_code'])): ?>
                    (<?= htmlspecialchars((string) $record['item_code']) ?>)
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Source Warehouse</div>
            <div class="mt-1 font-medium text-slate-800"><?= htmlspecialchars((string) ($record['source_warehouse_name'] ?? '')) ?></div>
        </div>
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Destination Warehouse</div>
            <div class="mt-1 font-medium text-slate-800"><?= htmlspecialchars((string) ($record['destination_warehouse_name'] ?? '')) ?></div>
        </div>
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Transfer ID</div>
            <div class="mt-1 font-medium text-slate-800"><?= (int) ($record['id'] ?? 0) ?></div>
        </div>
    </div>
</div>

<form method="POST" action="/inventory/transfer/edit" class="space-y-6">
    <input type="hidden" name="id" value="<?= (int) ($record['id'] ?? 0) ?>">

    <div class="grid gap-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:grid-cols-2">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Source Pallet</label>
            <input
                type="text"
                name="source_pallet"
                value="<?= htmlspecialchars((string) ($record['source_pallet'] ?? '')) ?>"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Destination Pallet</label>
            <input
                type="text"
                name="dest_pallet"
                value="<?= htmlspecialchars((string) ($record['dest_pallet'] ?? '')) ?>"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Crates Transferred</label>
            <input
                type="number"
                name="quantity_transferred"
                min="1"
                value="<?= htmlspecialchars((string) ($record['quantity_transferred'] ?? 0)) ?>"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Pieces Transferred</label>
            <input
                type="number"
                name="pieces_transferred"
                min="0"
                value="<?= htmlspecialchars((string) ($record['pieces_transferred'] ?? 0)) ?>"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <a
            href="/inventory/transfer/history"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Cancel
        </a>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
            Update Transfer
        </button>
    </div>
</form>