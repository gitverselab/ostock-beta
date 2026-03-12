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
                    ? (int) $item['items_per_pc']
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
}