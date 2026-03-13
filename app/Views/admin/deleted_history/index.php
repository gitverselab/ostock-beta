<?php

declare(strict_types=1);

$records = is_array($records ?? null) ? $records : [];
$transactionTypes = is_array($transactionTypes ?? null) ? $transactionTypes : [];
$filters = is_array($filters ?? null) ? $filters : [];

$transactionType = (string) ($filters['transaction_type'] ?? '');
$deletedBy = (string) ($filters['deleted_by'] ?? '');
$startDate = (string) ($filters['start_date'] ?? '');
$endDate = (string) ($filters['end_date'] ?? '');
?>

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Deleted Transaction History</h2>
        <p class="mt-1 text-sm text-slate-500">
            Review deleted inventory-related records for audit and traceability.
        </p>
    </div>

    <a
        href="/dashboard"
        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
    >
        Return to Dashboard
    </a>
</div>

<form method="GET" action="/admin/deleted-history" class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Transaction Type</label>
            <select
                name="transaction_type"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
                <option value="">All Types</option>
                <?php foreach ($transactionTypes as $type): ?>
                    <?php $value = (string) ($type['transaction_type'] ?? ''); ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $transactionType === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($value) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Deleted By (Username)</label>
            <input
                type="text"
                name="deleted_by"
                value="<?= htmlspecialchars($deletedBy) ?>"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                placeholder="Search username"
            >
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
    </div>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
            Filter
        </button>

        <a
            href="/admin/deleted-history"
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
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Original ID</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Deleted By</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Deleted Date</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($records as $row): ?>
                        <?php
                        $decoded = json_decode((string) ($row['details'] ?? ''), true);
                        $formattedDetails = is_array($decoded)
                            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : (string) ($row['details'] ?? '');
                        ?>
                        <tr class="hover:bg-slate-50 align-top">
                            <td class="px-4 py-3 text-slate-700">
                                <?= (int) ($row['id'] ?? 0) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['transaction_type'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['original_id'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['deleted_by'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <?= htmlspecialchars((string) ($row['deleted_date'] ?? '')) ?>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                <pre class="max-w-md whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-3 text-xs text-slate-700"><?= htmlspecialchars($formattedDetails) ?></pre>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
            No deleted transaction records found for the selected filters.
        </div>
    <?php endif; ?>
</div>