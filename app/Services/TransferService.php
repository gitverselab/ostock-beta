<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InventoryRepository;
use App\Support\Database;
use RuntimeException;
use Throwable;

class TransferService
{
    private InventoryRepository $inventory;

    public function __construct()
    {
        $this->inventory = new InventoryRepository();
    }

    public function process(int $sourceWarehouse, int $destinationWarehouse, array $items, string $processedBy): void
    {
        if ($sourceWarehouse <= 0) {
            throw new RuntimeException('Please select a source warehouse.');
        }

        if ($destinationWarehouse <= 0) {
            throw new RuntimeException('Please select a destination warehouse.');
        }

        if ($sourceWarehouse === $destinationWarehouse) {
            throw new RuntimeException('Source and destination warehouse must be different.');
        }

        if (count($items) === 0) {
            throw new RuntimeException('Please add at least one transfer row.');
        }

        $pdo = Database::connection();
        $currentTime = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        try {
            foreach ($items as $item) {
                $itemId = (int) ($item['item_id'] ?? 0);
                $sourcePallet = trim((string) ($item['source_pallet'] ?? ''));
                $destPallet = trim((string) ($item['dest_pallet'] ?? ''));
                $transferCrates = (int) ($item['quantity'] ?? 0);
                $transferPieces = isset($item['items_per_pc']) && $item['items_per_pc'] !== ''
                    ? (int) ($item['items_per_pc'])
                    : 0;

                if ($itemId <= 0 || $sourcePallet === '' || $destPallet === '') {
                    throw new RuntimeException('Missing data for one of the transfer rows.');
                }

                if ($transferCrates <= 0) {
                    throw new RuntimeException('Primary quantity to transfer must be greater than zero.');
                }

                $inventoryRows = $this->inventory->getTransferSourceRowsLocked($itemId, $sourcePallet, $sourceWarehouse);

                if (count($inventoryRows) === 0) {
                    throw new RuntimeException("Source inventory not found for item ID {$itemId} on pallet {$sourcePallet}.");
                }

                $totalQuantity = 0;
                $totalItems = 0;

                foreach ($inventoryRows as $row) {
                    $totalQuantity += (int) ($row['quantity'] ?? 0);
                    $totalItems += (int) ($row['items_per_pc'] ?? 0);
                }

                if ($transferCrates > $totalQuantity || $transferPieces > $totalItems) {
                    throw new RuntimeException("Not enough stock available for item ID {$itemId} on pallet {$sourcePallet}.");
                }

                $remainingCratesToDeduct = $transferCrates;
                $remainingPiecesToDeduct = $transferPieces;

                foreach ($inventoryRows as $invRow) {
                    if ($remainingCratesToDeduct <= 0 && $remainingPiecesToDeduct <= 0) {
                        break;
                    }

                    $currentQty = (int) ($invRow['quantity'] ?? 0);
                    $currentPieces = (int) ($invRow['items_per_pc'] ?? 0);
                    $inventoryId = (int) ($invRow['id'] ?? 0);

                    $deductCrates = min($remainingCratesToDeduct, $currentQty);
                    $deductPieces = min($remainingPiecesToDeduct, $currentPieces);

                    $newQty = $currentQty - $deductCrates;
                    $newPieces = $currentPieces - $deductPieces;

                    if ($newQty <= 0 && $newPieces <= 0) {
                        $deleted = $this->inventory->deleteInventory($inventoryId);

                        if (!$deleted) {
                            throw new RuntimeException('Failed to delete depleted source inventory.');
                        }
                    } else {
                        $updated = $this->inventory->updateInventoryStockOnly($inventoryId, $newQty, $newPieces);

                        if (!$updated) {
                            throw new RuntimeException('Failed to update source inventory.');
                        }
                    }

                    $remainingCratesToDeduct -= $deductCrates;
                    $remainingPiecesToDeduct -= $deductPieces;
                }

                $firstRow = $inventoryRows[0];

                $transferId = $this->inventory->insertTransfer([
                    'item_id' => $itemId,
                    'source_warehouse' => $sourceWarehouse,
                    'destination_warehouse' => $destinationWarehouse,
                    'source_pallet' => $sourcePallet,
                    'dest_pallet' => $destPallet,
                    'quantity_transferred' => $transferCrates,
                    'pieces_transferred' => $transferPieces,
                    'date_transferred' => $currentTime,
                    'processed_by' => $processedBy,
                ]);

                $this->inventory->insertOutbound([
                    'item_id' => $itemId,
                    'pallet_id' => $sourcePallet,
                    'quantity_removed' => $transferCrates,
                    'warehouse_id' => $sourceWarehouse,
                    'items_per_pc' => $transferPieces,
                    'outbound_type' => 'Transfer_Outbound',
                    'date_removed' => $currentTime,
                    'transfer_id' => $transferId,
                    'processed_by' => $processedBy,
                    'production_date' => (string) ($firstRow['production_date'] ?? ''),
                    'expiry_date' => (string) ($firstRow['expiry_date'] ?? ''),
                ]);

                $destinationInventoryId = $this->inventory->insertInventory([
                    'item_id' => $itemId,
                    'quantity' => $transferCrates,
                    'uom' => (string) ($firstRow['uom'] ?? ''),
                    'expiry_date' => (string) ($firstRow['expiry_date'] ?? ''),
                    'production_date' => (string) ($firstRow['production_date'] ?? ''),
                    'pallet_id' => $destPallet,
                    'warehouse_id' => $destinationWarehouse,
                    'items_per_pc' => $transferPieces,
                    'date_received' => $currentTime,
                    'transfer_id' => $transferId,
                    'processed_by' => $processedBy,
                ]);

                $sourceHistoryDetails = json_encode([
                    'direction' => 'source_out',
                    'source_warehouse' => $sourceWarehouse,
                    'destination_warehouse' => $destinationWarehouse,
                    'source_pallet' => $sourcePallet,
                    'dest_pallet' => $destPallet,
                    'quantity_transferred' => $transferCrates,
                    'pieces_transferred' => $transferPieces,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $destHistoryDetails = json_encode([
                    'direction' => 'destination_in',
                    'source_warehouse' => $sourceWarehouse,
                    'destination_warehouse' => $destinationWarehouse,
                    'source_pallet' => $sourcePallet,
                    'dest_pallet' => $destPallet,
                    'quantity_transferred' => $transferCrates,
                    'pieces_transferred' => $transferPieces,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $sourceHistoryInserted = $this->inventory->insertHistory([
                    'transaction_type' => 'transfer_out',
                    'reference_id' => $transferId,
                    'item_id' => $itemId,
                    'pallet_id' => $sourcePallet,
                    'warehouse_id' => $sourceWarehouse,
                    'quantity' => $transferCrates,
                    'items_per_pc' => $transferPieces,
                    'uom' => (string) ($firstRow['uom'] ?? ''),
                    'production_date' => (string) ($firstRow['production_date'] ?? ''),
                    'expiry_date' => (string) ($firstRow['expiry_date'] ?? ''),
                    'processed_by' => $processedBy,
                    'details' => $sourceHistoryDetails,
                ]);

                $destHistoryInserted = $this->inventory->insertHistory([
                    'transaction_type' => 'transfer_in',
                    'reference_id' => $destinationInventoryId,
                    'item_id' => $itemId,
                    'pallet_id' => $destPallet,
                    'warehouse_id' => $destinationWarehouse,
                    'quantity' => $transferCrates,
                    'items_per_pc' => $transferPieces,
                    'uom' => (string) ($firstRow['uom'] ?? ''),
                    'production_date' => (string) ($firstRow['production_date'] ?? ''),
                    'expiry_date' => (string) ($firstRow['expiry_date'] ?? ''),
                    'processed_by' => $processedBy,
                    'details' => $destHistoryDetails,
                ]);

                if (!$sourceHistoryInserted || !$destHistoryInserted) {
                    throw new RuntimeException('Failed to log transfer history.');
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

    public function update(
        int $transferId,
        string $newSourcePallet,
        string $newDestPallet,
        int $newQty,
        int $newPieces,
        string $processedBy
    ): void {
        $transfer = $this->inventory->findTransferById($transferId);

        if (!$transfer) {
            throw new RuntimeException('Transfer record not found.');
        }

        if ($newSourcePallet === '' || $newDestPallet === '') {
            throw new RuntimeException('Source and destination pallet are required.');
        }

        if ($newQty <= 0) {
            throw new RuntimeException('Crates transferred must be greater than zero.');
        }

        if ($newPieces < 0) {
            throw new RuntimeException('Pieces transferred cannot be negative.');
        }

        $itemId = (int) $transfer['item_id'];
        $sourceWarehouse = (int) $transfer['source_warehouse'];
        $destinationWarehouse = (int) $transfer['destination_warehouse'];
        $origSourcePallet = (string) $transfer['source_pallet'];
        $origDestPallet = (string) $transfer['dest_pallet'];
        $origQty = (int) $transfer['quantity_transferred'];
        $origPieces = (int) $transfer['pieces_transferred'];

        $metadata = $this->inventory->getTransferOutboundMetadata($transferId) ?: [
            'production_date' => '',
            'expiry_date' => '',
            'uom' => '',
        ];

        $currentTime = date('Y-m-d H:i:s');
        $pdo = Database::connection();

        $pdo->beginTransaction();

        try {
            $sourceRow = $this->inventory->findLockedInventoryForOutbound($itemId, $origSourcePallet, $sourceWarehouse);

            if ($sourceRow) {
                $reversedQty = (int) $sourceRow['quantity'] + $origQty;
                $reversedPieces = (int) $sourceRow['items_per_pc'] + $origPieces;

                $ok = $this->inventory->updateInventoryStockOnly(
                    (int) $sourceRow['id'],
                    $reversedQty,
                    $reversedPieces
                );

                if (!$ok) {
                    throw new RuntimeException('Failed to reverse source inventory.');
                }
            } else {
                $this->inventory->insertInventory([
                    'item_id' => $itemId,
                    'quantity' => $origQty,
                    'items_per_pc' => $origPieces,
                    'pallet_id' => $origSourcePallet,
                    'warehouse_id' => $sourceWarehouse,
                    'date_received' => $currentTime,
                    'uom' => (string) ($metadata['uom'] ?? ''),
                    'production_date' => (string) ($metadata['production_date'] ?? ''),
                    'expiry_date' => (string) ($metadata['expiry_date'] ?? ''),
                    'processed_by' => $processedBy,
                ]);
            }

            $destRow = $this->inventory->findDestinationInventoryByTransferId($itemId, $destinationWarehouse, $transferId);

            if (!$destRow) {
                throw new RuntimeException('Destination inventory record not found during reversal.');
            }

            $destNewQty = (int) $destRow['quantity'] - $origQty;
            $destNewPieces = (int) $destRow['items_per_pc'] - $origPieces;

            if ($destNewQty < 0 || $destNewPieces < 0) {
                throw new RuntimeException('Reversing original transfer would lead to negative stock at destination.');
            }

            if ($destNewQty === 0 && $destNewPieces === 0) {
                $deleted = $this->inventory->deleteInventory((int) $destRow['id']);

                if (!$deleted) {
                    throw new RuntimeException('Failed to delete destination inventory record during reversal.');
                }
            } else {
                $updated = $this->inventory->updateInventoryStockOnly(
                    (int) $destRow['id'],
                    $destNewQty,
                    $destNewPieces
                );

                if (!$updated) {
                    throw new RuntimeException('Failed to update destination inventory during reversal.');
                }
            }

            $this->inventory->updateOutboundTypeByTransferId($transferId, 'Outbound Overridden (Transfer)');

            $newSourceRow = $this->inventory->findLockedInventoryForOutbound($itemId, $newSourcePallet, $sourceWarehouse);

            if (!$newSourceRow) {
                throw new RuntimeException('Source inventory record not found for new transfer.');
            }

            if ($newQty > (int) $newSourceRow['quantity'] || $newPieces > (int) $newSourceRow['items_per_pc']) {
                throw new RuntimeException('Not enough stock in source for new transfer.');
            }

            $sourceAfterQty = (int) $newSourceRow['quantity'] - $newQty;
            $sourceAfterPieces = (int) $newSourceRow['items_per_pc'] - $newPieces;

            if ($sourceAfterQty <= 0 && $sourceAfterPieces <= 0) {
                $deleted = $this->inventory->deleteInventory((int) $newSourceRow['id']);

                if (!$deleted) {
                    throw new RuntimeException('Failed to apply new transfer to source inventory.');
                }
            } else {
                $updated = $this->inventory->updateInventoryStockOnly(
                    (int) $newSourceRow['id'],
                    $sourceAfterQty,
                    $sourceAfterPieces
                );

                if (!$updated) {
                    throw new RuntimeException('Failed to apply new transfer to source inventory.');
                }
            }

            $this->inventory->insertOutbound([
                'item_id' => $itemId,
                'pallet_id' => $newSourcePallet,
                'quantity_removed' => $newQty,
                'warehouse_id' => $sourceWarehouse,
                'items_per_pc' => $newPieces,
                'outbound_type' => 'Transfer_Outbound(edited)',
                'date_removed' => $currentTime,
                'transfer_id' => $transferId,
                'processed_by' => $processedBy,
                'production_date' => (string) ($metadata['production_date'] ?? ''),
                'expiry_date' => (string) ($metadata['expiry_date'] ?? ''),
            ]);

            $this->inventory->insertInventory([
                'item_id' => $itemId,
                'quantity' => $newQty,
                'uom' => (string) ($metadata['uom'] ?? ''),
                'expiry_date' => (string) ($metadata['expiry_date'] ?? ''),
                'production_date' => (string) ($metadata['production_date'] ?? ''),
                'pallet_id' => $newDestPallet,
                'warehouse_id' => $destinationWarehouse,
                'items_per_pc' => $newPieces,
                'date_received' => $currentTime,
                'transfer_id' => $transferId,
                'processed_by' => $processedBy,
            ]);

            $transferUpdated = $this->inventory->updateTransferRecord(
                $transferId,
                $newSourcePallet,
                $newDestPallet,
                $newQty,
                $newPieces,
                $currentTime
            );

            if (!$transferUpdated) {
                throw new RuntimeException('Failed to update transfer record.');
            }

            $historyDetails = json_encode([
                'original' => [
                    'source_pallet' => $origSourcePallet,
                    'dest_pallet' => $origDestPallet,
                    'quantity_transferred' => $origQty,
                    'pieces_transferred' => $origPieces,
                ],
                'new' => [
                    'source_pallet' => $newSourcePallet,
                    'dest_pallet' => $newDestPallet,
                    'quantity_transferred' => $newQty,
                    'pieces_transferred' => $newPieces,
                    'date_transferred' => $currentTime,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $historyInserted = $this->inventory->insertHistory([
                'transaction_type' => 'transfer_edit',
                'reference_id' => $transferId,
                'item_id' => $itemId,
                'pallet_id' => $newSourcePallet,
                'warehouse_id' => $sourceWarehouse,
                'quantity' => $newQty,
                'items_per_pc' => $newPieces,
                'uom' => (string) ($metadata['uom'] ?? ''),
                'production_date' => (string) ($metadata['production_date'] ?? ''),
                'expiry_date' => (string) ($metadata['expiry_date'] ?? ''),
                'processed_by' => $processedBy,
                'details' => $historyDetails,
            ]);

            if (!$historyInserted) {
                throw new RuntimeException('Failed to log transfer edit.');
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