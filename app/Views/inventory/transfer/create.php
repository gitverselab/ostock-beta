<?php

declare(strict_types=1);

$items = is_array($items ?? null) ? $items : [];
$warehouses = is_array($warehouses ?? null) ? $warehouses : [];
$old = is_array($old ?? null) ? $old : [];

$oldSourceWarehouse = (int) ($old['source_warehouse'] ?? 0);
$oldDestinationWarehouse = (int) ($old['destination_warehouse'] ?? 0);
$oldItems = is_array($old['items'] ?? null) ? array_values($old['items']) : [];

if (count($oldItems) === 0) {
    $oldItems = [[
        'item_id' => '',
        'source_pallet' => '',
        'dest_mode' => 'manual',
        'dest_pallet_select' => '',
        'dest_pallet_manual' => '',
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
?>

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Transfer Items</h2>
        <p class="mt-1 text-sm text-slate-500">
            Move stock from one warehouse to another while preserving pallet-level tracking.
        </p>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars((string) $formError) ?>
    </div>
<?php endif; ?>

<form method="POST" action="/inventory/transfer" class="space-y-6">
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Source Warehouse</label>
                <select
                    name="source_warehouse"
                    id="source-warehouse-select"
                    required
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    <option value="">-- Select source warehouse --</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option
                            value="<?= (int) ($warehouse['id'] ?? 0) ?>"
                            <?= $oldSourceWarehouse === (int) ($warehouse['id'] ?? 0) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars((string) ($warehouse['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Destination Warehouse</label>
                <select
                    name="destination_warehouse"
                    id="destination-warehouse-select"
                    required
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    <option value="">-- Select destination warehouse --</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option
                            value="<?= (int) ($warehouse['id'] ?? 0) ?>"
                            <?= $oldDestinationWarehouse === (int) ($warehouse['id'] ?? 0) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars((string) ($warehouse['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div id="transfer-items-container" class="space-y-4">
        <?php foreach ($oldItems as $index => $row): ?>
            <div class="transfer-item-row rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-800">Transfer Item Row</h3>

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
                        <label class="mb-2 block text-sm font-medium text-slate-700">Select Source Pallet / Batch</label>
                        <select
                            data-field="source_pallet"
                            name="items[<?= $index ?>][source_pallet]"
                            required
                            class="source-pallet-select w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            data-selected="<?= htmlspecialchars((string) ($row['source_pallet'] ?? '')) ?>"
                        >
                            <option value="">-- Select an item and source warehouse first --</option>
                        </select>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Available Source Stock</div>
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
                        <label class="mb-2 block text-sm font-medium text-slate-700">Destination Pallet Mode</label>
                        <select
                            data-field="dest_mode"
                            name="items[<?= $index ?>][dest_mode]"
                            class="dest-mode-select w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                            <option value="existing" <?= (($row['dest_mode'] ?? '') === 'existing') ? 'selected' : '' ?>>Use Existing Pallet</option>
                            <option value="manual" <?= (($row['dest_mode'] ?? 'manual') === 'manual') ? 'selected' : '' ?>>Enter Manually</option>
                        </select>
                    </div>

                    <div class="dest-existing-wrap">
                        <label class="mb-2 block text-sm font-medium text-slate-700">Destination Existing Pallet</label>
                        <select
                            data-field="dest_pallet_select"
                            name="items[<?= $index ?>][dest_pallet_select]"
                            class="dest-pallet-select w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            data-selected="<?= htmlspecialchars((string) ($row['dest_pallet_select'] ?? '')) ?>"
                        >
                            <option value="">-- Select an item and destination warehouse first --</option>
                        </select>
                    </div>

                    <div class="dest-manual-wrap">
                        <label class="mb-2 block text-sm font-medium text-slate-700">Destination Pallet / Batch ID</label>
                        <input
                            type="text"
                            data-field="dest_pallet_manual"
                            name="items[<?= $index ?>][dest_pallet_manual]"
                            value="<?= htmlspecialchars((string) ($row['dest_pallet_manual'] ?? '')) ?>"
                            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Primary Quantity to Transfer</label>
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
                        <label class="mb-2 block text-sm font-medium text-slate-700">Secondary Quantity to Transfer</label>
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
            id="add-transfer-row"
            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Add Another Item
        </button>

        <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
        >
            Process Transfer
        </button>
    </div>
</form>

<script>
const itemCatalog = <?= json_encode($itemCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const container = document.getElementById('transfer-items-container');
const addButton = document.getElementById('add-transfer-row');
const sourceWarehouseSelect = document.getElementById('source-warehouse-select');
const destinationWarehouseSelect = document.getElementById('destination-warehouse-select');

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
    row.className = 'transfer-item-row rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200';

    row.innerHTML = `
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-800">Transfer Item Row</h3>

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
                <label class="mb-2 block text-sm font-medium text-slate-700">Select Source Pallet / Batch</label>
                <select
                    data-field="source_pallet"
                    required
                    class="source-pallet-select w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    data-selected="${escapeHtml(data.source_pallet || '')}"
                >
                    <option value="">-- Select an item and source warehouse first --</option>
                </select>
            </div>

            <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Available Source Stock</div>
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
                <label class="mb-2 block text-sm font-medium text-slate-700">Destination Pallet Mode</label>
                <select
                    data-field="dest_mode"
                    class="dest-mode-select w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
                    <option value="existing" ${(data.dest_mode === 'existing') ? 'selected' : ''}>Use Existing Pallet</option>
                    <option value="manual" ${(data.dest_mode !== 'existing') ? 'selected' : ''}>Enter Manually</option>
                </select>
            </div>

            <div class="dest-existing-wrap">
                <label class="mb-2 block text-sm font-medium text-slate-700">Destination Existing Pallet</label>
                <select
                    data-field="dest_pallet_select"
                    class="dest-pallet-select w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                    data-selected="${escapeHtml(data.dest_pallet_select || '')}"
                >
                    <option value="">-- Select an item and destination warehouse first --</option>
                </select>
            </div>

            <div class="dest-manual-wrap">
                <label class="mb-2 block text-sm font-medium text-slate-700">Destination Pallet / Batch ID</label>
                <input
                    type="text"
                    data-field="dest_pallet_manual"
                    value="${escapeHtml(data.dest_pallet_manual || '')}"
                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                >
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Primary Quantity to Transfer</label>
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
                <label class="mb-2 block text-sm font-medium text-slate-700">Secondary Quantity to Transfer</label>
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
    toggleDestinationMode(row);
    loadSourcePalletsForRow(row);
    loadDestinationPalletsForRow(row);
}

function reindexRows() {
    const rows = container.querySelectorAll('.transfer-item-row');

    rows.forEach((row, index) => {
        row.querySelectorAll('[data-field]').forEach((field) => {
            field.name = `items[${index}][${field.dataset.field}]`;
        });
    });
}

function toggleDestinationMode(row) {
    const modeSelect = row.querySelector('.dest-mode-select');
    const existingWrap = row.querySelector('.dest-existing-wrap');
    const manualWrap = row.querySelector('.dest-manual-wrap');

    if (modeSelect.value === 'existing') {
        existingWrap.style.display = '';
        manualWrap.style.display = 'none';
    } else {
        existingWrap.style.display = 'none';
        manualWrap.style.display = '';
    }
}

async function loadSourcePalletsForRow(row) {
    const itemSelect = row.querySelector('.item-select');
    const sourcePalletSelect = row.querySelector('.source-pallet-select');
    const stockQty = row.querySelector('.stock-qty');
    const stockPieces = row.querySelector('.stock-pieces');
    const stockUom = row.querySelector('.stock-uom');

    stockQty.textContent = '0';
    stockPieces.textContent = '0';
    stockUom.textContent = '-';

    if (!sourceWarehouseSelect.value || !itemSelect.value) {
        sourcePalletSelect.innerHTML = '<option value="">-- Select an item and source warehouse first --</option>';
        return;
    }

    sourcePalletSelect.innerHTML = '<option value="">Loading pallets...</option>';

    try {
        const response = await fetch(`/api/transfer/source-pallets?item_id=${encodeURIComponent(itemSelect.value)}&warehouse_id=${encodeURIComponent(sourceWarehouseSelect.value)}`);
        const data = await response.json();

        if (!response.ok || data.error) {
            sourcePalletSelect.innerHTML = '<option value="">No source pallets available</option>';
            return;
        }

        const pallets = Array.isArray(data.pallets) ? data.pallets : [];
        const selectedPallet = sourcePalletSelect.dataset.selected || '';

        if (pallets.length === 0) {
            sourcePalletSelect.innerHTML = '<option value="">No source pallets available</option>';
            return;
        }

        sourcePalletSelect.innerHTML = '<option value="">-- Select source pallet --</option>' + pallets.map((pallet) => {
            const selected = String(selectedPallet) === String(pallet.pallet_id) ? 'selected' : '';
            return `<option
                value="${escapeHtml(pallet.pallet_id)}"
                data-qty="${escapeHtml(pallet.quantity)}"
                data-pieces="${escapeHtml(pallet.items_per_pc)}"
                data-uom="${escapeHtml(pallet.uom)}"
                ${selected}
            >${escapeHtml(pallet.pallet_id)}</option>`;
        }).join('');

        updateSourceStockDisplay(row);
    } catch (error) {
        sourcePalletSelect.innerHTML = '<option value="">No source pallets available</option>';
    }
}

async function loadDestinationPalletsForRow(row) {
    const itemSelect = row.querySelector('.item-select');
    const destPalletSelect = row.querySelector('.dest-pallet-select');

    if (!destinationWarehouseSelect.value || !itemSelect.value) {
        destPalletSelect.innerHTML = '<option value="">-- Select an item and destination warehouse first --</option>';
        return;
    }

    destPalletSelect.innerHTML = '<option value="">Loading pallets...</option>';

    try {
        const response = await fetch(`/api/transfer/destination-pallets?item_id=${encodeURIComponent(itemSelect.value)}&warehouse_id=${encodeURIComponent(destinationWarehouseSelect.value)}`);
        const data = await response.json();

        if (!response.ok || data.error) {
            destPalletSelect.innerHTML = '<option value="">No destination pallets available</option>';
            return;
        }

        const pallets = Array.isArray(data.pallets) ? data.pallets : [];
        const selectedPallet = destPalletSelect.dataset.selected || '';

        if (pallets.length === 0) {
            destPalletSelect.innerHTML = '<option value="">No destination pallets available</option>';
            return;
        }

        destPalletSelect.innerHTML = '<option value="">-- Select destination pallet --</option>' + pallets.map((pallet) => {
            const selected = String(selectedPallet) === String(pallet.pallet_id) ? 'selected' : '';
            return `<option value="${escapeHtml(pallet.pallet_id)}" ${selected}>${escapeHtml(pallet.pallet_id)}</option>`;
        }).join('');
    } catch (error) {
        destPalletSelect.innerHTML = '<option value="">No destination pallets available</option>';
    }
}

function updateSourceStockDisplay(row) {
    const sourcePalletSelect = row.querySelector('.source-pallet-select');
    const stockQty = row.querySelector('.stock-qty');
    const stockPieces = row.querySelector('.stock-pieces');
    const stockUom = row.querySelector('.stock-uom');

    const selected = sourcePalletSelect.options[sourcePalletSelect.selectedIndex];

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

    const rows = container.querySelectorAll('.transfer-item-row');

    if (rows.length === 1) {
        alert('At least one transfer row is required.');
        return;
    }

    removeButton.closest('.transfer-item-row').remove();
    reindexRows();
});

container.addEventListener('change', async (event) => {
    const row = event.target.closest('.transfer-item-row');

    if (!row) {
        return;
    }

    if (event.target.classList.contains('item-select')) {
        row.querySelector('.source-pallet-select').dataset.selected = '';
        row.querySelector('.dest-pallet-select').dataset.selected = '';
        await loadSourcePalletsForRow(row);
        await loadDestinationPalletsForRow(row);
        return;
    }

    if (event.target.classList.contains('source-pallet-select')) {
        updateSourceStockDisplay(row);
        return;
    }

    if (event.target.classList.contains('dest-mode-select')) {
        toggleDestinationMode(row);
    }
});

sourceWarehouseSelect.addEventListener('change', async () => {
    const rows = container.querySelectorAll('.transfer-item-row');

    for (const row of rows) {
        row.querySelector('.source-pallet-select').dataset.selected = '';
        await loadSourcePalletsForRow(row);
    }
});

destinationWarehouseSelect.addEventListener('change', async () => {
    const rows = container.querySelectorAll('.transfer-item-row');

    for (const row of rows) {
        row.querySelector('.dest-pallet-select').dataset.selected = '';
        await loadDestinationPalletsForRow(row);
    }
});

addButton.addEventListener('click', () => {
    createRow({
        item_id: '',
        source_pallet: '',
        dest_mode: 'manual',
        dest_pallet_select: '',
        dest_pallet_manual: '',
        quantity: '',
        items_per_pc: '',
    });
});

document.querySelectorAll('.transfer-item-row').forEach((row) => {
    toggleDestinationMode(row);
    loadSourcePalletsForRow(row);
    loadDestinationPalletsForRow(row);
});

reindexRows();
</script>