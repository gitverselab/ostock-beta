<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InventoryRepository;
use App\Support\Database;
use RuntimeException;
use Throwable;

class InboundService
{
    private InventoryRepository $inventory;

    public function __construct()
    {
        $this->inventory = new InventoryRepository();
    }

    public function process(int $warehouseId, array $items, string $processedBy): void
    {
        if ($warehouseId <= 0) {
            throw new RuntimeException('Please select a warehouse.');
        }

        if (count($items) === 0) {
            throw new RuntimeException('Please add at least one inbound item.');
        }

        $pdo = Database::connection();
        $dateReceived = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            foreach ($items as $item) {
                $itemId = (int) ($item['item_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);
                $uom = trim((string) ($item['uom'] ?? ''));
                $itemsPerPc = isset($item['items_per_pc']) && $item['items_per_pc'] !== ''
                    ? (int) ($item['items_per_pc'])
                    : 0;
                $palletId = trim((string) ($item['pallet_id'] ?? ''));
                $productionDateInput = trim((string) ($item['production_date'] ?? ''));
                $expiryDateInput = trim((string) ($item['expiry_date'] ?? ''));

                if ($itemId <= 0) {
                    throw new RuntimeException('One of the selected items is invalid.');
                }

                if ($quantity <= 0) {
                    throw new RuntimeException('Primary quantity must be greater than zero for all inbound rows.');
                }

                if ($uom === '') {
                    throw new RuntimeException('UOM is required for all inbound rows.');
                }

                if ($palletId === '') {
                    throw new RuntimeException('Pallet / Batch ID is required for all inbound rows.');
                }

                $productionTimestamp = strtotime($productionDateInput);
                $expiryTimestamp = strtotime($expiryDateInput);

                if ($productionTimestamp === false) {
                    throw new RuntimeException('Invalid production date detected.');
                }

                if ($expiryTimestamp === false) {
                    throw new RuntimeException('Invalid expiry date detected.');
                }

                $productionDate = date('Y-m-d H:i:s', $productionTimestamp);
                $expiryDate = date('Y-m-d H:i:s', $expiryTimestamp);

                $existing = $this->inventory->findExistingInventory($itemId, $palletId, $warehouseId);

                if ($existing) {
                    $inventoryId = (int) $existing['id'];
                    $newQuantity = (int) $existing['quantity'] + $quantity;
                    $newItemsPerPc = (int) $existing['items_per_pc'] + $itemsPerPc;

                    $updated = $this->inventory->updateInventoryTotals(
                        $inventoryId,
                        $newQuantity,
                        $newItemsPerPc,
                        $dateReceived,
                        $processedBy
                    );

                    if (!$updated) {
                        throw new RuntimeException('Failed to update existing inventory record.');
                    }
                } else {
                    $inventoryId = $this->inventory->insertInventory([
                        'item_id' => $itemId,
                        'quantity' => $quantity,
                        'uom' => $uom,
                        'expiry_date' => $expiryDate,
                        'production_date' => $productionDate,
                        'pallet_id' => $palletId,
                        'warehouse_id' => $warehouseId,
                        'items_per_pc' => $itemsPerPc,
                        'date_received' => $dateReceived,
                        'processed_by' => $processedBy,
                    ]);
                }

                $historyDetails = json_encode([
                    'pallet_id' => $palletId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'items_per_pc' => $itemsPerPc,
                    'uom' => $uom,
                    'expiry_date' => $expiryDate,
                    'production_date' => $productionDate,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $historyInserted = $this->inventory->insertHistory([
                    'transaction_type' => 'inbound',
                    'reference_id' => $inventoryId,
                    'item_id' => $itemId,
                    'pallet_id' => $palletId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'items_per_pc' => $itemsPerPc,
                    'uom' => $uom,
                    'production_date' => $productionDate,
                    'expiry_date' => $expiryDate,
                    'processed_by' => $processedBy,
                    'details' => $historyDetails,
                ]);

                if (!$historyInserted) {
                    throw new RuntimeException('Failed to log inbound transaction history.');
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function update(int $inboundId, array $data, string $processedBy): void
    {
        $original = $this->inventory->findInboundRecordById($inboundId);

        if (!$original) {
            throw new RuntimeException('Inbound record not found.');
        }

        $newQuantity = (int) ($data['quantity'] ?? 0);
        $newItemsPerPc = (int) ($data['items_per_pc'] ?? 0);
        $newUom = trim((string) ($data['uom'] ?? ''));
        $newPalletId = trim((string) ($data['pallet_id'] ?? ''));
        $productionDateInput = trim((string) ($data['production_date'] ?? ''));
        $expiryDateInput = trim((string) ($data['expiry_date'] ?? ''));

        if ($newQuantity <= 0) {
            throw new RuntimeException('Quantity must be greater than zero.');
        }

        if ($newItemsPerPc < 0) {
            throw new RuntimeException('Items per PC cannot be negative.');
        }

        if ($newUom === '') {
            throw new RuntimeException('UOM is required.');
        }

        if ($newPalletId === '') {
            throw new RuntimeException('Pallet ID is required.');
        }

        $productionTimestamp = strtotime($productionDateInput);
        $expiryTimestamp = strtotime($expiryDateInput);

        if ($productionTimestamp === false || $expiryTimestamp === false) {
            throw new RuntimeException('Invalid production or expiry date.');
        }

        $newProductionDate = date('Y-m-d H:i:s', $productionTimestamp);
        $newExpiryDate = date('Y-m-d H:i:s', $expiryTimestamp);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ((int) $original['quantity'] !== $newQuantity || (int) $original['items_per_pc'] !== $newItemsPerPc) {
                throw new RuntimeException('This inbound transaction has been partially consumed and cannot be edited.');
            }

            $updated = $this->inventory->updateInboundRecord(
                $inboundId,
                $newQuantity,
                $newItemsPerPc,
                $newUom,
                $newExpiryDate,
                $newProductionDate,
                $newPalletId,
                $processedBy
            );

            if (!$updated) {
                throw new RuntimeException('Failed to update inbound record.');
            }

            $historyDetails = json_encode([
                'old' => [
                    'quantity' => (int) ($original['quantity'] ?? 0),
                    'items_per_pc' => (int) ($original['items_per_pc'] ?? 0),
                    'uom' => (string) ($original['uom'] ?? ''),
                    'expiry_date' => (string) ($original['expiry_date'] ?? ''),
                    'production_date' => (string) ($original['production_date'] ?? ''),
                    'pallet_id' => (string) ($original['pallet_id'] ?? ''),
                ],
                'new' => [
                    'quantity' => $newQuantity,
                    'items_per_pc' => $newItemsPerPc,
                    'uom' => $newUom,
                    'expiry_date' => $newExpiryDate,
                    'production_date' => $newProductionDate,
                    'pallet_id' => $newPalletId,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $historyInserted = $this->inventory->insertHistory([
                'transaction_type' => 'inbound_edit',
                'reference_id' => $inboundId,
                'item_id' => (int) $original['item_id'],
                'pallet_id' => $newPalletId,
                'warehouse_id' => (int) $original['warehouse_id'],
                'quantity' => $newQuantity,
                'items_per_pc' => $newItemsPerPc,
                'uom' => $newUom,
                'production_date' => $newProductionDate,
                'expiry_date' => $newExpiryDate,
                'processed_by' => $processedBy,
                'details' => $historyDetails,
            ]);

            if (!$historyInserted) {
                throw new RuntimeException('Failed to log inbound edit.');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}