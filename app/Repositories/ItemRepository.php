<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class ItemRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function all(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                i.*,
                ic.category_name
            FROM items i
            LEFT JOIN item_categories ic ON i.category_id = ic.id
            ORDER BY i.name ASC
        ");

        return $stmt->fetchAll() ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM items
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $item = $stmt->fetch();

        return $item ?: null;
    }

    public function getCategories(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, category_name
            FROM item_categories
            ORDER BY category_name ASC
        ");

        return $stmt->fetchAll() ?: [];
    }

    public function itemCodeExists(string $itemCode, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS count
                FROM items
                WHERE item_code = :item_code
                  AND id != :except_id
            ");

            $stmt->execute([
                'item_code' => $itemCode,
                'except_id' => $exceptId,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS count
                FROM items
                WHERE item_code = :item_code
            ");

            $stmt->execute([
                'item_code' => $itemCode,
            ]);
        }

        $row = $stmt->fetch();

        return ((int) ($row['count'] ?? 0)) > 0;
    }

    public function create(array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO items (
                name,
                item_code,
                uom,
                category_id,
                cost,
                is_calendar_item,
                primary_uom_label,
                secondary_uom_label
            ) VALUES (
                :name,
                :item_code,
                :uom,
                :category_id,
                :cost,
                :is_calendar_item,
                :primary_uom_label,
                :secondary_uom_label
            )
        ");

        return $stmt->execute([
            'name' => $data['name'],
            'item_code' => $data['item_code'],
            'uom' => $data['uom'],
            'category_id' => $data['category_id'],
            'cost' => $data['cost'],
            'is_calendar_item' => $data['is_calendar_item'],
            'primary_uom_label' => $data['primary_uom_label'],
            'secondary_uom_label' => $data['secondary_uom_label'],
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE items
            SET
                name = :name,
                item_code = :item_code,
                uom = :uom,
                category_id = :category_id,
                cost = :cost,
                is_calendar_item = :is_calendar_item,
                primary_uom_label = :primary_uom_label,
                secondary_uom_label = :secondary_uom_label
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'item_code' => $data['item_code'],
            'uom' => $data['uom'],
            'category_id' => $data['category_id'],
            'cost' => $data['cost'],
            'is_calendar_item' => $data['is_calendar_item'],
            'primary_uom_label' => $data['primary_uom_label'],
            'secondary_uom_label' => $data['secondary_uom_label'],
        ]);
    }
}