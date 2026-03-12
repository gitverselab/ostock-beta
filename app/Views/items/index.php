<?php

declare(strict_types=1);

$items = is_array($items ?? null) ? $items : [];
?>

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Items</h2>
        <p class="mt-1 text-sm text-slate-500">
            Manage finished goods and other item master records.
        </p>
    </div>

    <a
        href="/items/create"
        class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
    >
        Add New Item
    </a>
</div>

<div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <?php if (count($items) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Item Name</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Item Code</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Category</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Cost</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Primary Unit</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Secondary Unit</th>
                        <th class="px-4 py-3 text-center font-semibold text-slate-600">Calendar</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <?= htmlspecialchars((string) ($item['name'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($item['item_code'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($item['category_name'] ?? 'Uncategorized')) ?>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">
                                ₱<?= number_format((float) ($item['cost'] ?? 0), 2) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($item['primary_uom_label'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($item['secondary_uom_label'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if ((int) ($item['is_calendar_item'] ?? 0) === 1): ?>
                                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
                                        Yes
                                    </span>
                                <?php else: ?>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                        No
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a
                                        href="/items/edit?id=<?= (int) ($item['id'] ?? 0) ?>"
                                        class="inline-flex items-center rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="/items/delete?id=<?= (int) ($item['id'] ?? 0) ?>"
                                        class="inline-flex items-center rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100"
                                    >
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="px-6 py-10 text-center text-sm text-slate-500">
            No items found yet.
        </div>
    <?php endif; ?>
</div>