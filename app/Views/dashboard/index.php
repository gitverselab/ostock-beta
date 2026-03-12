<?php

declare(strict_types=1);

$stats = is_array($stats ?? null) ? $stats : [];
$recentActivity = is_array($recentActivity ?? null) ? $recentActivity : [];
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Finished Goods Dashboard</h2>
    <p class="mt-1 text-sm text-slate-500">
        Overview of your current finished goods inventory.
    </p>
</div>

<?php if (!empty($dashboardError)): ?>
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <?= htmlspecialchars((string) $dashboardError) ?>
    </div>
<?php endif; ?>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm font-medium text-slate-500">Total Pieces</div>
        <div class="mt-3 text-3xl font-bold text-slate-900">
            <?= number_format((int) ($stats['total_pieces'] ?? 0)) ?>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm font-medium text-slate-500">Total Pallets</div>
        <div class="mt-3 text-3xl font-bold text-slate-900">
            <?= number_format((int) ($stats['total_pallets'] ?? 0)) ?>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm font-medium text-slate-500">Distinct SKUs</div>
        <div class="mt-3 text-3xl font-bold text-slate-900">
            <?= number_format((int) ($stats['distinct_skus'] ?? 0)) ?>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm font-medium text-slate-500">Warehouses</div>
        <div class="mt-3 text-3xl font-bold text-slate-900">
            <?= number_format((int) ($stats['warehouses_count'] ?? 0)) ?>
        </div>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <div class="xl:col-span-2 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-800">Recent Activity</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Latest inventory history entries for finished goods.
                </p>
            </div>
        </div>

        <?php if (count($recentActivity) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Date</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Item</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Type</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">Crates</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">Pieces</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($recentActivity as $activity): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                    <?= htmlspecialchars((string) ($activity['transaction_date'] ?? '')) ?>
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-800">
                                    <?= htmlspecialchars((string) ($activity['item_name'] ?? '')) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                        <?= htmlspecialchars((string) ($activity['transaction_type'] ?? '')) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700">
                                    <?= number_format((int) ($activity['quantity'] ?? 0)) ?>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700">
                                    <?= number_format((int) ($activity['items_per_pc'] ?? 0)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                No recent finished goods activity found.
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h3 class="text-lg font-semibold text-slate-800">Next Step</h3>
        <p class="mt-2 text-sm leading-6 text-slate-600">
            Your shell is now working and the dashboard is reading real data.
            The next migration should be the Items module, followed by Warehouses.
        </p>

        <div class="mt-6 space-y-3">
            <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                Batch B will add:
                <div class="mt-2 font-medium text-slate-900">Items list, create, and edit pages</div>
            </div>

            <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                After that:
                <div class="mt-2 font-medium text-slate-900">Warehouses, then stock movement modules</div>
            </div>
        </div>
    </div>
</div>