<?php

declare(strict_types=1);

$kpis = is_array($kpis ?? null) ? $kpis : [];
$lowStockItems = is_array($lowStockItems ?? null) ? $lowStockItems : [];
$expiringSoonItems = is_array($expiringSoonItems ?? null) ? $expiringSoonItems : [];
$recentInbound = is_array($recentInbound ?? null) ? $recentInbound : [];
$recentOutbound = is_array($recentOutbound ?? null) ? $recentOutbound : [];
$warehouseStock = is_array($warehouseStock ?? null) ? $warehouseStock : [];
$storageTracker = is_array($storageTracker ?? null) ? $storageTracker : [];
$maxWarehouseStock = 0;

foreach ($warehouseStock as $row) {
    $maxWarehouseStock = max($maxWarehouseStock, (int) ($row['total_stock'] ?? 0));
}
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Finished Goods Dashboard</h2>
    <p class="mt-1 text-sm text-slate-500">
        Overview of current finished goods stock, warehouse distribution, and recent activity.
    </p>
</div>

<?php if (!empty($dashboardError)): ?>
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <?= htmlspecialchars((string) $dashboardError) ?>
    </div>
<?php endif; ?>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm font-medium text-slate-500">Total Pieces in Stock</div>
        <div class="mt-3 text-3xl font-bold text-slate-900">
            <?= number_format((int) ($kpis['total_pieces'] ?? 0)) ?>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm font-medium text-slate-500">Total Pallets</div>
        <div class="mt-3 text-3xl font-bold text-slate-900">
            <?= number_format((int) ($kpis['total_pallets'] ?? 0)) ?>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm font-medium text-slate-500">Distinct SKUs</div>
        <div class="mt-3 text-3xl font-bold text-slate-900">
            <?= number_format((int) ($kpis['distinct_skus'] ?? 0)) ?>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm font-medium text-slate-500">Warehouses</div>
        <div class="mt-3 text-3xl font-bold text-slate-900">
            <?= number_format((int) ($kpis['warehouses_count'] ?? 0)) ?>
        </div>
    </div>
</div>

<form method="GET" action="/dashboard" class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-col gap-4 md:flex-row md:items-end">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Set Low Stock Alert Threshold</label>
            <input
                type="number"
                name="low_stock_threshold"
                min="1"
                value="<?= htmlspecialchars((string) ($lowStockThreshold ?? 100)) ?>"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 md:w-64"
            >
        </div>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
            Set
        </button>

        <a
            href="/inventory/report"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Open Inventory Report
        </a>
    </div>
</form>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">Low Stock Items (≤ <?= number_format((int) ($lowStockThreshold ?? 100)) ?> Pieces)</h3>

        <?php if (count($lowStockItems) > 0): ?>
            <div class="mt-4 space-y-3">
                <?php foreach ($lowStockItems as $item): ?>
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                        <div class="font-medium text-slate-800">
                            <?= htmlspecialchars((string) ($item['name'] ?? '')) ?>
                        </div>
                        <div class="text-sm font-semibold text-red-700">
                            <?= number_format((int) ($item['total_pieces'] ?? 0)) ?> pcs
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                No items are currently low on stock.
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">Items Expiring in Next 14 Days</h3>

        <?php if (count($expiringSoonItems) > 0): ?>
            <div class="mt-4 space-y-3">
                <?php foreach ($expiringSoonItems as $item): ?>
                    <div class="rounded-xl bg-slate-50 px-4 py-3">
                        <div class="font-medium text-slate-800">
                            <?= htmlspecialchars((string) ($item['name'] ?? '')) ?>
                        </div>
                        <div class="mt-1 text-sm text-slate-600">
                            Pallet: <?= htmlspecialchars((string) ($item['pallet_id'] ?? '')) ?>
                        </div>
                        <div class="mt-1 text-sm text-amber-700">
                            Expires: <?= htmlspecialchars((string) ($item['expiry_date'] ?? '')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                No items are expiring soon.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">Finished Goods Pieces by Warehouse</h3>

        <?php if (count($warehouseStock) > 0): ?>
            <div class="mt-4 space-y-4">
                <?php foreach ($warehouseStock as $row): ?>
                    <?php
                    $value = (int) ($row['total_stock'] ?? 0);
                    $width = $maxWarehouseStock > 0 ? max(2, (int) round(($value / $maxWarehouseStock) * 100)) : 0;
                    ?>
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-700"><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></span>
                            <span class="text-slate-500"><?= number_format($value) ?> pcs</span>
                        </div>
                        <div class="h-3 rounded-full bg-slate-100">
                            <div class="h-3 rounded-full bg-slate-800" style="width: <?= $width ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                No warehouse stock data available.
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">Recent Activity</h3>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <div class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Recent Inbound</div>
                <?php if (count($recentInbound) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($recentInbound as $row): ?>
                            <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm">
                                <div class="font-medium text-slate-800">
                                    <?= number_format((int) ($row['quantity'] ?? 0)) ?> Crt /
                                    <?= number_format((int) ($row['items_per_pc'] ?? 0)) ?> Pcs
                                    of <?= htmlspecialchars((string) ($row['name'] ?? '')) ?>
                                </div>
                                <div class="mt-1 text-slate-500">
                                    <?= htmlspecialchars((string) ($row['transaction_date'] ?? '')) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                        No recent inbound activity.
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <div class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Recent Outbound / Deliveries</div>
                <?php if (count($recentOutbound) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($recentOutbound as $row): ?>
                            <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm">
                                <div class="font-medium text-slate-800">
                                    <?= number_format((int) ($row['quantity'] ?? 0)) ?> Crt /
                                    <?= number_format((int) ($row['items_per_pc'] ?? 0)) ?> Pcs
                                    of <?= htmlspecialchars((string) ($row['name'] ?? '')) ?>
                                </div>
                                <div class="mt-1 text-slate-500">
                                    <?= htmlspecialchars((string) ($row['transaction_date'] ?? '')) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                        No recent outbound activity.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-800">Finished Goods Storage Tracker</h3>
            <p class="mt-1 text-sm text-slate-500">
                Current stock grouped by item and warehouse.
            </p>
        </div>
    </div>

    <?php if (count($storageTracker) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Item Name</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Warehouse</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Total Crates</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Total Pieces</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($storageTracker as $row): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <?= htmlspecialchars((string) ($row['name'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['warehouse_name'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">
                                <?= number_format((int) ($row['total_quantity'] ?? 0)) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">
                                <?= number_format((int) ($row['total_items_per_pc'] ?? 0)) ?>
                            </td>
                            <td class="px-4 py-3">
                                <a
                                    href="/inventory/report/pallets?item_id=<?= (int) ($row['item_id'] ?? 0) ?>&warehouse_id=<?= (int) ($row['warehouse_id'] ?? 0) ?>"
                                    class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                                >
                                    View Pallets
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
            No finished goods inventory found.
        </div>
    <?php endif; ?>
</div>