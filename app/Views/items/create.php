<?php

declare(strict_types=1);

$categories = is_array($categories ?? null) ? $categories : [];
$old = is_array($old ?? null) ? $old : [];
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Add New Item</h2>
    <p class="mt-1 text-sm text-slate-500">
        Create a new master item record.
    </p>
</div>

<?php if (!empty($formError)): ?>
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars((string) $formError) ?>
    </div>
<?php endif; ?>

<form method="POST" action="/items/create" class="space-y-6">
    <div class="grid gap-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:grid-cols-2">
        <div class="md:col-span-2">
            <label class="mb-2 block text-sm font-medium text-slate-700">Item Name</label>
            <input
                type="text"
                name="name"
                value="<?= htmlspecialchars((string) ($old['name'] ?? '')) ?>"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Item Code</label>
            <input
                type="text"
                name="item_code"
                value="<?= htmlspecialchars((string) ($old['item_code'] ?? '')) ?>"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Item Category</label>
            <select
                name="category_id"
                required
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
                <option value="">-- Select a Category --</option>
                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= (int) $category['id'] ?>"
                        <?= ((int) ($old['category_id'] ?? 0) === (int) $category['id']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars((string) $category['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Cost / Value (per secondary unit)</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-500">₱</span>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="cost"
                    value="<?= htmlspecialchars((string) ($old['cost'] ?? '0')) ?>"
                    class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-4 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Primary Unit Label</label>
            <input
                type="text"
                name="primary_uom_label"
                value="<?= htmlspecialchars((string) ($old['primary_uom_label'] ?? '')) ?>"
                required
                placeholder="Example: Crate"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Secondary Unit Label</label>
            <input
                type="text"
                name="secondary_uom_label"
                value="<?= htmlspecialchars((string) ($old['secondary_uom_label'] ?? '')) ?>"
                required
                placeholder="Example: Piece"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div class="md:col-span-2">
            <label class="mb-2 block text-sm font-medium text-slate-700">Base UOM (for records)</label>
            <input
                type="text"
                name="uom"
                value="<?= htmlspecialchars((string) ($old['uom'] ?? 'pc')) ?>"
                required
                placeholder="Example: pc"
                class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
            >
        </div>

        <div class="md:col-span-2">
            <label class="inline-flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <input
                    type="checkbox"
                    name="is_calendar_item"
                    value="1"
                    <?= ((int) ($old['is_calendar_item'] ?? 0) === 1) ? 'checked' : '' ?>
                    class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400"
                >
                Show on Delivery Calendar
            </label>
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <a
            href="/items"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Cancel
        </a>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
            Save Item
        </button>
    </div>
</form>