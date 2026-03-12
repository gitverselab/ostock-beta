<?php

declare(strict_types=1);

$items = is_array($items ?? null) ? $items : [];
$warehouses = is_array($warehouses ?? null) ? $warehouses : [];
$old = is_array($old ?? null) ? $old : [];

$oldWarehouseId = (int) ($old['warehouse_id'] ?? 0);
$oldItems = is_array($old['items'] ?? null) ? array_values($old['items']) : [];

if (count($oldItems) === 0) {
    $oldItems = [[
        'item_id' => '',
        'pallet_id' => '',
        'quantity' => '',
        'items_per_pc' => '',
        'uom' => 'pc',
        'production_date' => '',
        'expiry_date' => '',
    ]];
}

$itemCatalog = array_map(static function (array $item): array {
    return [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'item_code' => (string) ($item['item_code'] ?? ''),
        'uom' => (string) ($item['uom'] ?? 'pc'),
    ];
}, $items);
?>

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Inbound Inventory</h2>
        <p class="mt-1 text-sm text-slate-500">
            Receive finished goods into a selected warehouse.
        </p>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars((string) $formError) ?>
    </div>
<?php endif; ?>

<form method="POST" action="/inventory/inbound" class="space-y-6">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Select Warehouse</label>
                <select
                    name="warehouse_id"
                    required
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    <option value="">-- Select a warehouse --</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option
                            value="<?= (int) ($warehouse['id'] ?? 0) ?>"
                            <?= $oldWarehouseId === (int) ($warehouse['id'] ?? 0) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars((string) ($warehouse['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div id="inbound-items-container" class="space-y-4">
        <?php foreach ($oldItems as $index => $row): ?>
            <div class="inbound-item-row rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-800">Inbound Item Row</h3>

                    <button
                        type="button"
                        class="remove-row rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100"
                    >
                        Remove This Item
                    </button>
                </div>

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Select Item</label>
                        <select
                            data-field="item_id"
                            name="items[<?= $index ?>][item_id]"
                            required
                            class="item-select w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                            <option value="">-- Select an item --</option>
                            <?php foreach ($items as $item): ?>
                                <option
                                    value="<?= (int) ($item['id'] ?? 0) ?>"
                                    data-uom="<?= htmlspecialchars((string) ($item['uom'] ?? 'pc')) ?>"
                                    <?= (int) ($row['item_id'] ?? 0) === (int) ($item['id'] ?? 0) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars((string) ($item['name'] ?? '')) ?> (<?= htmlspecialchars((string) ($item['item_code'] ?? '')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Pallet / Batch ID</label>
                        <div class="flex gap-2">
                            <input
                                type="text"
                                data-field="pallet_id"
                                name="items[<?= $index ?>][pallet_id]"
                                value="<?= htmlspecialchars((string) ($row['pallet_id'] ?? '')) ?>"
                                required
                                class="pallet-input w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            >

                            <button
                                type="button"
                                class="generate-pallet whitespace-nowrap rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-800"
                            >
                                Generate
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">UOM</label>
                        <input
                            type="text"
                            data-field="uom"
                            name="items[<?= $index ?>][uom]"
                            value="<?= htmlspecialchars((string) ($row['uom'] ?? 'pc')) ?>"
                            required
                            class="uom-input w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Primary Quantity</label>
                        <input
                            type="number"
                            min="1"
                            data-field="quantity"
                            name="items[<?= $index ?>][quantity]"
                            value="<?= htmlspecialchars((string) ($row['quantity'] ?? '')) ?>"
                            required
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Secondary Quantity</label>
                        <input
                            type="number"
                            min="0"
                            data-field="items_per_pc"
                            name="items[<?= $index ?>][items_per_pc]"
                            value="<?= htmlspecialchars((string) ($row['items_per_pc'] ?? '')) ?>"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Production Date</label>
                        <input
                            type="datetime-local"
                            data-field="production_date"
                            name="items[<?= $index ?>][production_date]"
                            value="<?= htmlspecialchars((string) ($row['production_date'] ?? '')) ?>"
                            required
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Expiry Date</label>
                        <input
                            type="datetime-local"
                            data-field="expiry_date"
                            name="items[<?= $index ?>][expiry_date]"
                            value="<?= htmlspecialchars((string) ($row['expiry_date'] ?? '')) ?>"
                            required
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <button
            type="button"
            id="add-item-row"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Add Another Item
        </button>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
            Submit Inbound
        </button>
    </div>
</form>

<script>
const itemCatalog = <?= json_encode($itemCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const container = document.getElementById('inbound-items-container');
const addButton = document.getElementById('add-item-row');

function itemOptionsHtml(selectedId = '') {
    const options = ['<option value="">-- Select an item --</option>'];

    itemCatalog.forEach((item) => {
        const selected = String(selectedId) === String(item.id) ? 'selected' : '';
        options.push(
            `<option value="${item.id}" data-uom="${escapeHtml(item.uom)}" ${selected}>${escapeHtml(item.name)} (${escapeHtml(item.item_code)})</option>`
        );
    });

    return options.join('');
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function createRow(data = {}) {
    const row = document.createElement('div');
    row.className = 'inbound-item-row rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200';

    row.innerHTML = `
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-800">Inbound Item Row</h3>

            <button
                type="button"
                class="remove-row rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100"
            >
                Remove This Item
            </button>
        </div>

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Select Item</label>
                <select
                    data-field="item_id"
                    required
                    class="item-select w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    ${itemOptionsHtml(data.item_id || '')}
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Pallet / Batch ID</label>
                <div class="flex gap-2">
                    <input
                        type="text"
                        data-field="pallet_id"
                        value="${escapeHtml(data.pallet_id || '')}"
                        required
                        class="pallet-input w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    >

                    <button
                        type="button"
                        class="generate-pallet whitespace-nowrap rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white hover:bg-slate-800"
                    >
                        Generate
                    </button>
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">UOM</label>
                <input
                    type="text"
                    data-field="uom"
                    value="${escapeHtml(data.uom || 'pc')}"
                    required
                    class="uom-input w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Primary Quantity</label>
                <input
                    type="number"
                    min="1"
                    data-field="quantity"
                    value="${escapeHtml(data.quantity || '')}"
                    required
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Secondary Quantity</label>
                <input
                    type="number"
                    min="0"
                    data-field="items_per_pc"
                    value="${escapeHtml(data.items_per_pc || '')}"
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Production Date</label>
                <input
                    type="datetime-local"
                    data-field="production_date"
                    value="${escapeHtml(data.production_date || '')}"
                    required
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Expiry Date</label>
                <input
                    type="datetime-local"
                    data-field="expiry_date"
                    value="${escapeHtml(data.expiry_date || '')}"
                    required
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>
        </div>
    `;

    container.appendChild(row);
    reindexRows();
}

function reindexRows() {
    const rows = container.querySelectorAll('.inbound-item-row');

    rows.forEach((row, index) => {
        row.querySelectorAll('[data-field]').forEach((field) => {
            field.name = `items[${index}][${field.dataset.field}]`;
        });
    });
}

function bindEvents() {
    container.addEventListener('click', async (event) => {
        const removeButton = event.target.closest('.remove-row');
        const generateButton = event.target.closest('.generate-pallet');

        if (removeButton) {
            const rows = container.querySelectorAll('.inbound-item-row');

            if (rows.length === 1) {
                alert('At least one inbound item row is required.');
                return;
            }

            removeButton.closest('.inbound-item-row').remove();
            reindexRows();
            return;
        }

        if (generateButton) {
            const row = generateButton.closest('.inbound-item-row');
            const itemSelect = row.querySelector('.item-select');
            const palletInput = row.querySelector('.pallet-input');

            if (!itemSelect.value) {
                alert('Please select an item first.');
                return;
            }

            generateButton.disabled = true;
            generateButton.textContent = 'Generating...';

            try {
                const response = await fetch(`/api/inbound/generate-pallet?item_id=${encodeURIComponent(itemSelect.value)}`);
                const data = await response.json();

                if (!response.ok || data.error) {
                    alert(data.error || 'Failed to generate pallet ID.');
                } else {
                    palletInput.value = data.pallet_id || '';
                }
            } catch (error) {
                alert('Failed to generate pallet ID.');
            } finally {
                generateButton.disabled = false;
                generateButton.textContent = 'Generate';
            }
        }
    });

    container.addEventListener('change', (event) => {
        const select = event.target.closest('.item-select');

        if (!select) {
            return;
        }

        const selectedOption = select.options[select.selectedIndex];
        const row = select.closest('.inbound-item-row');
        const uomInput = row.querySelector('.uom-input');

        if (selectedOption && selectedOption.dataset.uom && uomInput) {
            uomInput.value = selectedOption.dataset.uom;
        }
    });
}

addButton.addEventListener('click', () => {
    createRow({
        item_id: '',
        pallet_id: '',
        quantity: '',
        items_per_pc: '',
        uom: 'pc',
        production_date: '',
        expiry_date: ''
    });
});

bindEvents();
reindexRows();
</script>