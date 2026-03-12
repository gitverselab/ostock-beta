<?php

declare(strict_types=1);

$warehouse = is_array($warehouse ?? null) ? $warehouse : [];
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Edit Warehouse</h2>
    <p class="mt-1 text-sm text-slate-500">
        Update the selected warehouse record.
    </p>
</div>

<?php if (!empty($formError)): ?>
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars((string) $formError) ?>
    </div>
<?php endif; ?>

<form method="POST" action="/warehouses/edit" class="space-y-6">
    <input type="hidden" name="id" value="<?= (int) ($warehouse['id'] ?? 0) ?>">

    <div class="grid gap-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Warehouse Name</label>
            <input
                type="text"
                name="name"
                value="<?= htmlspecialchars((string) ($warehouse['name'] ?? '')) ?>"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Address</label>
            <textarea
                name="address"
                rows="4"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            ><?= htmlspecialchars((string) ($warehouse['address'] ?? '')) ?></textarea>
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <a
            href="/warehouses"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Cancel
        </a>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
            Update Warehouse
        </button>
    </div>
</form>