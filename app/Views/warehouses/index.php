<?php

declare(strict_types=1);

$warehouses = is_array($warehouses ?? null) ? $warehouses : [];
?>

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Warehouses</h2>
        <p class="mt-1 text-sm text-slate-500">
            Manage warehouse names and addresses.
        </p>
    </div>

    <a
        href="/warehouses/create"
        class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
    >
        Add New Warehouse
    </a>
</div>

<div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <?php if (count($warehouses) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">ID</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Warehouse Name</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Address</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($warehouses as $warehouse): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700">
                                <?= (int) ($warehouse['id'] ?? 0) ?>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">
                                <?= htmlspecialchars((string) ($warehouse['name'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($warehouse['address'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a
                                        href="/warehouses/edit?id=<?= (int) ($warehouse['id'] ?? 0) ?>"
                                        class="inline-flex items-center rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="/warehouses/delete?id=<?= (int) ($warehouse['id'] ?? 0) ?>"
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
            No warehouses found yet.
        </div>
    <?php endif; ?>
</div>