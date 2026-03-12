<?php

declare(strict_types=1);

$items = is_array($items ?? null) ? $items : [];
$warehouses = is_array($warehouses ?? null) ? $warehouses : [];
$old = is_array($old ?? null) ? $old : [];

$oldWarehouseId = (int) ($old['warehouse_id'] ?? 0);
$oldOutboundType = (string) ($old['outbound_type'] ?? '');
$oldItems = is_array($old['items'] ?? null) ? array_values($old['items']) : [];

if (count($oldItems) === 0) {
    $oldItems = [[
        'item_id' => '',
        'pallet_id' => '',
        'quantity' => '',
        'items_per_pc' => '',
    ]];
}

$itemCatalog = array_map(static function (array $item): array {
    return [
        'id' => (int) ($item['id'] ?? 0),
        'name' => (string) ($item['name'] ?? ''),
        'item_code' => (string) ($item['item_code'] ?? ''),
    ];
}, $items);

$outboundTypes = [
    'Normal Outbound',
    'Return to Vendor',
    'Production Usage',
    'Spoilage',
    'Other',
];
?>

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Outbound Inventory</h2>
        <p class="mt-1 text-sm text-slate-500">
            Remove stock from a selected warehouse and record the outbound reason.
        </p>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars((string) $formError) ?>
    </div>
<?php endif; ?>

<form method="POST" action="/inventory/outbound" class="space-y-6">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Select Warehouse</label>
                <select
                    name="warehouse_id"
                    id="warehouse-select"
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

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Outbound Type</label>
                <select
                    name="outbound_type"
                    required
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    <option value="">-- Select outbound type --</option>
                    <?php foreach ($outboundTypes as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" <?= $oldOutboundType === $type ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div id="outbound-items-container" class="space-y-4">
        <?php foreach ($oldItems as $index => $row): ?>
            <div class="outbound-item-row rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-800">Outbound Item Row</h3>

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
                                    <?= (int) ($row['item_id'] ?? 0) === (int) ($item['id'] ?? 0) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars((string) ($item['name'] ?? '')) ?> (<?= htmlspecialchars((string) ($item['item_code'] ?? '')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Select Pallet / Batch ID</label>
                        <select
                            data-field="pallet_id"
                            name="items[<?= $index ?>][pallet_id]"
                            required
                            class="pallet-select w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            data-selected="<?= htmlspecialchars((string) ($row['pallet_id'] ?? '')) ?>"
                        >
                            <option value="">-- Select an item and warehouse first --</option>
                        </select>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Available Stock</div>
                        <div class="mt-2">
                            Crates: <span class="stock-qty font-semibold">0</span>
                        </div>
                        <div>
                            Pieces: <span class="stock-pieces font-semibold">0</span>
                        </div>
                        <div>
                            UOM: <span class="stock-uom font-semibold">-</span>
                        </div>
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
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <button
            type="button"
            id="add-outbound-row"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Add Another Item
        </button>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
            Process Outbound
        </button>
    </div>
</form>

<script>
const itemCatalog = <?= json_encode($itemCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const container = document.getElementById('outbound-items-container');
const addButton = document.getElementById('add-outbound-row');
const warehouseSelect = document.getElementById('warehouse-select');

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function itemOptionsHtml(selectedId = '') {
    const options = ['<option value="">-- Select an item --</option>'];

    itemCatalog.forEach((item) => {
        const selected = String(selectedId) === String(item.id) ? 'selected' : '';
        options.push(
            `<option value="${item.id}" ${selected}>${escapeHtml(item.name)} (${escapeHtml(item.item_code)})</option>`
        );
    });

    return options.join('');
}

function createRow(data = {}) {
    const row = document.createElement('div');
    row.className = 'outbound-item-row rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200';

    row.innerHTML = `
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-800">Outbound Item Row</h3>

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
                <label class="mb-2 block text-sm font-medium text-slate-700">Select Pallet / Batch ID</label>
                <select
                    data-field="pallet_id"
                    required
                    class="pallet-select w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    data-selected="${escapeHtml(data.pallet_id || '')}"
                >
                    <option value="">-- Select an item and warehouse first --</option>
                </select>
            </div>

            <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Available Stock</div>
                <div class="mt-2">
                    Crates: <span class="stock-qty font-semibold">0</span>
                </div>
                <div>
                    Pieces: <span class="stock-pieces font-semibold">0</span>
                </div>
                <div>
                    UOM: <span class="stock-uom font-semibold">-</span>
                </div>
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
        </div>
    `;

    container.appendChild(row);
    reindexRows();
    loadPalletOptionsForRow(row);
}

function reindexRows() {
    const rows = container.querySelectorAll('.outbound-item-row');

    rows.forEach((row, index) => {
        row.querySelectorAll('[data-field]').forEach((field) => {
            field.name = `items[${index}][${field.dataset.field}]`;
        });
    });
}

async function loadPalletOptionsForRow(row) {
    const itemSelect = row.querySelector('.item-select');
    const palletSelect = row.querySelector('.pallet-select');
    const stockQty = row.querySelector('.stock-qty');
    const stockPieces = row.querySelector('.stock-pieces');
    const stockUom = row.querySelector('.stock-uom');
    const warehouseId = warehouseSelect.value;

    stockQty.textContent = '0';
    stockPieces.textContent = '0';
    stockUom.textContent = '-';

    if (!warehouseId || !itemSelect.value) {
        palletSelect.innerHTML = '<option value="">-- Select an item and warehouse first --</option>';
        return;
    }

    palletSelect.innerHTML = '<option value="">Loading pallets...</option>';

    try {
        const response = await fetch(`/api/outbound/pallets?item_id=${encodeURIComponent(itemSelect.value)}&warehouse_id=${encodeURIComponent(warehouseId)}`);
        const data = await response.json();

        if (!response.ok || data.error) {
            palletSelect.innerHTML = '<option value="">No pallets available</option>';
            return;
        }

        const pallets = Array.isArray(data.pallets) ? data.pallets : [];

        if (pallets.length === 0) {
            palletSelect.innerHTML = '<option value="">No pallets available</option>';
            return;
        }

        const selectedPallet = palletSelect.dataset.selected || '';

        palletSelect.innerHTML = '<option value="">-- Select pallet --</option>' + pallets.map((pallet) => {
            const selected = String(selectedPallet) === String(pallet.pallet_id) ? 'selected' : '';
            return `<option
                value="${escapeHtml(pallet.pallet_id)}"
                data-qty="${escapeHtml(pallet.quantity)}"
                data-pieces="${escapeHtml(pallet.items_per_pc)}"
                data-uom="${escapeHtml(pallet.uom)}"
                ${selected}
            >${escapeHtml(pallet.pallet_id)}</option>`;
        }).join('');

        updateStockDisplay(row);
    } catch (error) {
        palletSelect.innerHTML = '<option value="">No pallets available</option>';
    }
}

function updateStockDisplay(row) {
    const palletSelect = row.querySelector('.pallet-select');
    const stockQty = row.querySelector('.stock-qty');
    const stockPieces = row.querySelector('.stock-pieces');
    const stockUom = row.querySelector('.stock-uom');

    const selected = palletSelect.options[palletSelect.selectedIndex];

    if (!selected || !selected.value) {
        stockQty.textContent = '0';
        stockPieces.textContent = '0';
        stockUom.textContent = '-';
        return;
    }

    stockQty.textContent = selected.dataset.qty || '0';
    stockPieces.textContent = selected.dataset.pieces || '0';
    stockUom.textContent = selected.dataset.uom || '-';
}

container.addEventListener('click', (event) => {
    const removeButton = event.target.closest('.remove-row');

    if (!removeButton) {
        return;
    }

    const rows = container.querySelectorAll('.outbound-item-row');

    if (rows.length === 1) {
        alert('At least one outbound item row is required.');
        return;
    }

    removeButton.closest('.outbound-item-row').remove();
    reindexRows();
});

container.addEventListener('change', async (event) => {
    const row = event.target.closest('.outbound-item-row');

    if (!row) {
        return;
    }

    if (event.target.classList.contains('item-select')) {
        const palletSelect = row.querySelector('.pallet-select');
        palletSelect.dataset.selected = '';
        await loadPalletOptionsForRow(row);
        return;
    }

    if (event.target.classList.contains('pallet-select')) {
        updateStockDisplay(row);
    }
});

warehouseSelect.addEventListener('change', async () => {
    const rows = container.querySelectorAll('.outbound-item-row');

    for (const row of rows) {
        const palletSelect = row.querySelector('.pallet-select');
        palletSelect.dataset.selected = '';
        await loadPalletOptionsForRow(row);
    }
});

addButton.addEventListener('click', () => {
    createRow({
        item_id: '',
        pallet_id: '',
        quantity: '',
        items_per_pc: '',
    });
});

document.querySelectorAll('.outbound-item-row').forEach((row) => {
    loadPalletOptionsForRow(row);
});

reindexRows();
</script>