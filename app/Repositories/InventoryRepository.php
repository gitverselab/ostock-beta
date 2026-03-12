<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class InventoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findExistingInventory(int $itemId, string $palletId, int $warehouseId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, quantity, items_per_pc
            FROM inventory
            WHERE item_id = :item_id
              AND pallet_id = :pallet_id
              AND warehouse_id = :warehouse_id
            LIMIT 1
        ");

        $stmt->execute([
            'item_id' => $itemId,
            'pallet_id' => $palletId,
            'warehouse_id' => $warehouseId,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateInventoryTotals(
        int $inventoryId,
        int $newQuantity,
        int $newItemsPerPc,
        string $dateReceived,
        string $processedBy
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE inventory
            SET
                quantity = :quantity,
                items_per_pc = :items_per_pc,
                date_received = :date_received,
                processed_by = :processed_by
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $inventoryId,
            'quantity' => $newQuantity,
            'items_per_pc' => $newItemsPerPc,
            'date_received' => $dateReceived,
            'processed_by' => $processedBy,
        ]);
    }

    public function insertInventory(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO inventory (
                item_id,
                quantity,
                uom,
                expiry_date,
                production_date,
                pallet_id,
                warehouse_id,
                items_per_pc,
                date_received,
                processed_by
            ) VALUES (
                :item_id,
                :quantity,
                :uom,
                :expiry_date,
                :production_date,
                :pallet_id,
                :warehouse_id,
                :items_per_pc,
                :date_received,
                :processed_by
            )
        ");

        $stmt->execute([
            'item_id' => $data['item_id'],
            'quantity' => $data['quantity'],
            'uom' => $data['uom'],
            'expiry_date' => $data['expiry_date'],
            'production_date' => $data['production_date'],
            'pallet_id' => $data['pallet_id'],
            'warehouse_id' => $data['warehouse_id'],
            'items_per_pc' => $data['items_per_pc'],
            'date_received' => $data['date_received'],
            'processed_by' => $data['processed_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function insertHistory(array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO inventory_history (
                transaction_type,
                reference_id,
                item_id,
                pallet_id,
                warehouse_id,
                quantity,
                items_per_pc,
                uom,
                production_date,
                expiry_date,
                processed_by,
                details
            ) VALUES (
                :transaction_type,
                :reference_id,
                :item_id,
                :pallet_id,
                :warehouse_id,
                :quantity,
                :items_per_pc,
                :uom,
                :production_date,
                :expiry_date,
                :processed_by,
                :details
            )
        ");

        return $stmt->execute([
            'transaction_type' => $data['transaction_type'],
            'reference_id' => $data['reference_id'],
            'item_id' => $data['item_id'],
            'pallet_id' => $data['pallet_id'],
            'warehouse_id' => $data['warehouse_id'],
            'quantity' => $data['quantity'],
            'items_per_pc' => $data['items_per_pc'],
            'uom' => $data['uom'],
            'production_date' => $data['production_date'],
            'expiry_date' => $data['expiry_date'],
            'processed_by' => $data['processed_by'],
            'details' => $data['details'],
        ]);
    }

    public function palletExists(string $palletId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS count
            FROM inventory
            WHERE pallet_id = :pallet_id
        ");

        $stmt->execute([
            'pallet_id' => $palletId,
        ]);

        $row = $stmt->fetch();

        return ((int) ($row['count'] ?? 0)) > 0;
    }
}