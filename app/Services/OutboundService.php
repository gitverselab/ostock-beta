<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InventoryRepository;
use App\Support\Database;
use RuntimeException;
use Throwable;

class OutboundService
{
    private InventoryRepository $inventory;

    public function __construct()
    {
        $this->inventory = new InventoryRepository();
    }

    public function process(int $warehouseId, string $outboundType, array $items, string $processedBy): void
    {
        $allowedTypes = [
            'Normal Outbound',
            'Return to Vendor',
            'Production Usage',
            'Spoilage',
            'Other',
        ];

        if ($warehouseId <= 0) {
            throw new RuntimeException('Please select a warehouse.');
        }

        if (!in_array($outboundType, $allowedTypes, true)) {
            throw new RuntimeException('Please select a valid outbound type.');
        }

        if (count($items) === 0) {
            throw new RuntimeException('Please add at least one outbound item.');
        }

        $pdo = Database::connection();
        $dateRemoved = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            foreach ($items as $item) {
                $itemId = (int) ($item['item_id'] ?? 0);
                $palletId = trim((string) ($item['pallet_id'] ?? ''));
                $quantity = (int) ($item['quantity'] ?? 0);
                $itemsPerPc = isset($item['items_per_pc']) && $item['items_per_pc'] !== ''
                    ? (int) ($item['items_per_pc'])
                    : 0;

                if ($itemId <= 0 || $palletId === '') {
                    throw new RuntimeException('Invalid data for one of the outbound rows.');
                }

                if ($quantity <= 0) {
                    throw new RuntimeException('Primary quantity must be greater than zero for all outbound rows.');
                }

                $row = $this->inventory->findLockedInventoryForOutbound($itemId, $palletId, $warehouseId);

                if (!$row) {
                    throw new RuntimeException("Stock not found for item ID {$itemId} on pallet {$palletId}.");
                }

                if ($quantity > (int) $row['quantity'] || $itemsPerPc > (int) $row['items_per_pc']) {
                    throw new RuntimeException("Not enough stock available for item ID {$itemId} on pallet {$palletId}.");
                }

                $newQuantity = (int) $row['quantity'] - $quantity;
                $newItemsPerPc = (int) $row['items_per_pc'] - $itemsPerPc;
                $inventoryId = (int) $row['id'];

                if ($newQuantity <= 0 && $newItemsPerPc <= 0) {
                    $deleted = $this->inventory->deleteInventory($inventoryId);

                    if (!$deleted) {
                        throw new RuntimeException('Failed to delete depleted inventory record.');
                    }
                } else {
                    $updated = $this->inventory->updateInventoryStockOnly($inventoryId, $newQuantity, $newItemsPerPc);

                    if (!$updated) {
                        throw new RuntimeException('Failed to update inventory record.');
                    }
                }

                $productionDate = (string) ($row['production_date'] ?? '');
                $expiryDate = (string) ($row['expiry_date'] ?? '');
                $uom = (string) ($row['uom'] ?? '');

                $outboundId = $this->inventory->insertOutbound([
                    'item_id' => $itemId,
                    'pallet_id' => $palletId,
                    'quantity_removed' => $quantity,
                    'warehouse_id' => $warehouseId,
                    'items_per_pc' => $itemsPerPc,
                    'outbound_type' => $outboundType,
                    'date_removed' => $dateRemoved,
                    'processed_by' => $processedBy,
                    'production_date' => $productionDate,
                    'expiry_date' => $expiryDate,
                ]);

                $historyDetails = json_encode([
                    'pallet_id' => $palletId,
                    'warehouse_id' => $warehouseId,
                    'quantity_removed' => $quantity,
                    'pieces_removed' => $itemsPerPc,
                    'outbound_type' => $outboundType,
                    'uom' => $uom,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $historyInserted = $this->inventory->insertHistory([
                    'transaction_type' => 'outbound',
                    'reference_id' => $outboundId,
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
                    throw new RuntimeException('Failed to log outbound transaction history.');
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

    public function update(int $outboundId, int $newCrates, int $newPieces, string $processedBy): void
    {
        $original = $this->inventory->findOutboundRecordById($outboundId);

        if (!$original) {
            throw new RuntimeException('Outbound record not found.');
        }

        if ($newCrates <= 0) {
            throw new RuntimeException('Crates removed must be greater than zero.');
        }

        if ($newPieces < 0) {
            throw new RuntimeException('Pieces removed cannot be negative.');
        }

        $pdo = Database::connection();
        $dateRemoved = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            $restoredInventory = $this->inventory->findLockedInventoryForOutbound(
                (int) $original['item_id'],
                (string) $original['pallet_id'],
                (int) $original['warehouse_id']
            );

            if ($restoredInventory) {
                $restoredQty = (int) $restoredInventory['quantity'] + (int) $original['quantity_removed'];
                $restoredPieces = (int) $restoredInventory['items_per_pc'] + (int) $original['items_per_pc'];

                $ok = $this->inventory->updateInventoryStockOnly(
                    (int) $restoredInventory['id'],
                    $restoredQty,
                    $restoredPieces
                );

                if (!$ok) {
                    throw new RuntimeException('Failed to reverse original outbound.');
                }
            } else {
                $this->inventory->insertInventory([
                    'item_id' => (int) $original['item_id'],
                    'quantity' => (int) $original['quantity_removed'],
                    'items_per_pc' => (int) $original['items_per_pc'],
                    'warehouse_id' => (int) $original['warehouse_id'],
                    'pallet_id' => (string) $original['pallet_id'],
                    'production_date' => (string) ($original['production_date'] ?? ''),
                    'expiry_date' => (string) ($original['expiry_date'] ?? ''),
                    'date_received' => $dateRemoved,
                    'uom' => (string) ($original['item_uom'] ?? ''),
                    'processed_by' => $processedBy,
                ]);
            }

            $inventoryRow = $this->inventory->findLockedInventoryForOutbound(
                (int) $original['item_id'],
                (string) $original['pallet_id'],
                (int) $original['warehouse_id']
            );

            if (!$inventoryRow) {
                throw new RuntimeException('Source inventory not found after reversal.');
            }

            if ($newCrates > (int) $inventoryRow['quantity'] || $newPieces > (int) $inventoryRow['items_per_pc']) {
                throw new RuntimeException('Not enough stock available for new outbound values.');
            }

            $updatedQty = (int) $inventoryRow['quantity'] - $newCrates;
            $updatedPieces = (int) $inventoryRow['items_per_pc'] - $newPieces;

            if ($updatedQty <= 0 && $updatedPieces <= 0) {
                $deleted = $this->inventory->deleteInventory((int) $inventoryRow['id']);

                if (!$deleted) {
                    throw new RuntimeException('Failed to update inventory with new outbound values.');
                }
            } else {
                $updated = $this->inventory->updateInventoryStockOnly(
                    (int) $inventoryRow['id'],
                    $updatedQty,
                    $updatedPieces
                );

                if (!$updated) {
                    throw new RuntimeException('Failed to update inventory with new outbound values.');
                }
            }

            $recordUpdated = $this->inventory->updateOutboundRecord(
                $outboundId,
                $newCrates,
                $newPieces,
                $dateRemoved,
                $processedBy
            );

            if (!$recordUpdated) {
                throw new RuntimeException('Failed to update outbound record.');
            }

            $historyDetails = json_encode([
                'original' => [
                    'quantity_removed' => (int) ($original['quantity_removed'] ?? 0),
                    'items_per_pc' => (int) ($original['items_per_pc'] ?? 0),
                    'pallet_id' => (string) ($original['pallet_id'] ?? ''),
                    'production_date' => (string) ($original['production_date'] ?? ''),
                    'expiry_date' => (string) ($original['expiry_date'] ?? ''),
                ],
                'new' => [
                    'quantity_removed' => $newCrates,
                    'items_per_pc' => $newPieces,
                    'date_removed' => $dateRemoved,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $historyInserted = $this->inventory->insertHistory([
                'transaction_type' => 'outbound_edit',
                'reference_id' => $outboundId,
                'item_id' => (int) $original['item_id'],
                'pallet_id' => (string) $original['pallet_id'],
                'warehouse_id' => (int) $original['warehouse_id'],
                'quantity' => $newCrates,
                'items_per_pc' => $newPieces,
                'uom' => (string) ($original['item_uom'] ?? ''),
                'production_date' => (string) ($original['production_date'] ?? ''),
                'expiry_date' => (string) ($original['expiry_date'] ?? ''),
                'processed_by' => $processedBy,
                'details' => $historyDetails,
            ]);

            if (!$historyInserted) {
                throw new RuntimeException('Failed to log outbound edit in inventory history.');
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