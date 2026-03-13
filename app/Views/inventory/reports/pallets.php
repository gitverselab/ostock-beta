<?php

declare(strict_types=1);

$pallets = is_array($pallets ?? null) ? $pallets : [];
$first = $pallets[0] ?? [];

$itemName = (string) ($first['item_name'] ?? '');
$itemCode = (string) ($first['item_code'] ?? '');
$warehouseName = (string) ($first['warehouse_name'] ?? '');
?>

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Pallet Details</h2>
        <p class="mt-1 text-sm text-slate-500">
            Item:
            <span class="font-medium text-slate-700"><?= htmlspecialchars($itemName) ?></span>
            <?php if ($itemCode !== ''): ?>
                (<?= htmlspecialchars($itemCode) ?>)
            <?php endif; ?>
            —
            Warehouse:
            <span class="font-medium text-slate-700"><?= htmlspecialchars($warehouseName) ?></span>
        </p>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <a
            href="/dashboard"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Dashboard
        </a>

        <a
            href="/inventory/report"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
        >
            Back to Inventory Report
        </a>
    </div>
</div>

<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <?php if (count($pallets) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Pallet ID</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Quantity (Crates)</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Items Per PC</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">UOM</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Production Date</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Expiry Date</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Date Received</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Processed By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($pallets as $row): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <?= htmlspecialchars((string) ($row['pallet_id'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">
                                <?= number_format((int) ($row['quantity'] ?? 0)) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">
                                <?= number_format((int) ($row['items_per_pc'] ?? 0)) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['uom'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['production_date'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['expiry_date'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['date_received'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['processed_by'] ?? '')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
            No pallet details found for this selection.
        </div>
    <?php endif; ?>
</div>