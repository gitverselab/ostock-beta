<?php

declare(strict_types=1);

$records = is_array($records ?? null) ? $records : [];
$items = is_array($items ?? null) ? $items : [];
$warehouses = is_array($warehouses ?? null) ? $warehouses : [];
$filters = is_array($filters ?? null) ? $filters : [];

$itemId = (int) ($filters['item_id'] ?? 0);
$warehouseId = (int) ($filters['warehouse_id'] ?? 0);
$startDate = (string) ($filters['start_date'] ?? '');
$endDate = (string) ($filters['end_date'] ?? '');
$limit = (string) ($filters['limit'] ?? '20');
?>

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Inventory History</h2>
        <p class="mt-1 text-sm text-slate-500">
            Unified audit trail for inbound, outbound, transfer, and other inventory movements.
        </p>
    </div>

    <a
        href="/dashboard"
        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
    >
        Return to Dashboard
    </a>
</div>

<form method="GET" action="/inventory/history" class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Item Name</label>
            <select
                name="item_id"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
                <option value="">All Items</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= (int) ($item['id'] ?? 0) ?>" <?= $itemId === (int) ($item['id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($item['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Warehouse</label>
            <select
                name="warehouse_id"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
                <option value="">All Warehouses</option>
                <?php foreach ($warehouses as $warehouse): ?>
                    <option value="<?= (int) ($warehouse['id'] ?? 0) ?>" <?= $warehouseId === (int) ($warehouse['id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($warehouse['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Start Date</label>
            <input
                type="date"
                name="start_date"
                value="<?= htmlspecialchars($startDate) ?>"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">End Date</label>
            <input
                type="date"
                name="end_date"
                value="<?= htmlspecialchars($endDate) ?>"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Show Entries</label>
            <select
                name="limit"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
                <?php foreach (['20', '50', '100', '500', 'ALL'] as $option): ?>
                    <option value="<?= $option ?>" <?= $limit === $option ? 'selected' : '' ?>>
                        <?= $option ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
            Filter
        </button>

        <a
            href="/inventory/history"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Reset
        </a>

        <button
            type="button"
            onclick="window.print()"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Print
        </button>
    </div>
</form>

<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <?php if (count($records) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">ID</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Type</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Ref ID</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Item</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Pallet ID</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Warehouse</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Crates</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Pieces</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Date</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">By</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($records as $row): ?>
                        <tr class="hover:bg-slate-50 align-top">
                            <td class="px-4 py-3 text-slate-700">
                                <?= (int) ($row['id'] ?? 0) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['transaction_type'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['reference_id'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <?= htmlspecialchars((string) ($row['item_name'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['pallet_id'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['warehouse_name'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">
                                <?= number_format((int) ($row['quantity'] ?? 0)) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">
                                <?= number_format((int) ($row['items_per_pc'] ?? 0)) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['transaction_date'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['processed_by'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <div class="max-w-xs whitespace-pre-wrap break-words">
                                    <?= htmlspecialchars((string) ($row['details'] ?? '')) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
            No inventory history records found for the selected filters.
        </div>
    <?php endif; ?>
</div>