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

    public function findLockedInventoryForOutbound(int $itemId, string $palletId, int $warehouseId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                quantity,
                items_per_pc,
                production_date,
                expiry_date,
                uom
            FROM inventory
            WHERE item_id = :item_id
              AND pallet_id = :pallet_id
              AND warehouse_id = :warehouse_id
            LIMIT 1
            FOR UPDATE
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

    public function updateInventoryStockOnly(int $inventoryId, int $newQuantity, int $newItemsPerPc): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE inventory
            SET
                quantity = :quantity,
                items_per_pc = :items_per_pc
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $inventoryId,
            'quantity' => $newQuantity,
            'items_per_pc' => $newItemsPerPc,
        ]);
    }

    public function deleteInventory(int $inventoryId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM inventory
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $inventoryId,
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

    public function insertOutbound(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO outbound_inventory (
                item_id,
                pallet_id,
                quantity_removed,
                warehouse_id,
                items_per_pc,
                outbound_type,
                date_removed,
                processed_by,
                production_date,
                expiry_date
            ) VALUES (
                :item_id,
                :pallet_id,
                :quantity_removed,
                :warehouse_id,
                :items_per_pc,
                :outbound_type,
                :date_removed,
                :processed_by,
                :production_date,
                :expiry_date
            )
        ");

        $stmt->execute([
            'item_id' => $data['item_id'],
            'pallet_id' => $data['pallet_id'],
            'quantity_removed' => $data['quantity_removed'],
            'warehouse_id' => $data['warehouse_id'],
            'items_per_pc' => $data['items_per_pc'],
            'outbound_type' => $data['outbound_type'],
            'date_removed' => $data['date_removed'],
            'processed_by' => $data['processed_by'],
            'production_date' => $data['production_date'],
            'expiry_date' => $data['expiry_date'],
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

    public function getInboundRecords(array $filters = []): array
    {
        $sql = "
            SELECT
                inventory.id,
                inventory.item_id,
                inventory.warehouse_id,
                inventory.pallet_id,
                inventory.quantity,
                inventory.uom,
                inventory.items_per_pc,
                inventory.production_date,
                inventory.expiry_date,
                inventory.date_received,
                inventory.processed_by,
                items.name AS item_name,
                warehouses.name AS warehouse_name
            FROM inventory
            INNER JOIN items ON inventory.item_id = items.id
            INNER JOIN warehouses ON inventory.warehouse_id = warehouses.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['item_id'])) {
            $sql .= " AND inventory.item_id = :item_id";
            $params['item_id'] = (int) $filters['item_id'];
        }

        if (!empty($filters['warehouse_id'])) {
            $sql .= " AND inventory.warehouse_id = :warehouse_id";
            $params['warehouse_id'] = (int) $filters['warehouse_id'];
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND DATE(inventory.date_received) BETWEEN :start_date AND :end_date";
            $params['start_date'] = $filters['start_date'];
            $params['end_date'] = $filters['end_date'];
        }

        $sql .= " ORDER BY inventory.date_received DESC";

        $limit = $filters['limit'] ?? '20';
        $allowedLimits = ['20', '50', '100', '500', 'ALL'];

        if (!in_array((string) $limit, $allowedLimits, true)) {
            $limit = '20';
        }

        if ($limit !== 'ALL') {
            $sql .= " LIMIT " . (int) $limit;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public function getOutboundItemOptions(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT
                items.id,
                items.name,
                items.item_code
            FROM inventory
            INNER JOIN items ON inventory.item_id = items.id
            ORDER BY items.name ASC
        ");

        return $stmt->fetchAll() ?: [];
    }

    public function getPalletsByItemAndWarehouse(int $itemId, int $warehouseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                pallet_id,
                quantity,
                items_per_pc,
                production_date,
                expiry_date,
                uom
            FROM inventory
            WHERE item_id = :item_id
              AND warehouse_id = :warehouse_id
            ORDER BY date_received ASC
        ");

        $stmt->execute([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
        ]);

        return $stmt->fetchAll() ?: [];
    }
}