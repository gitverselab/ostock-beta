<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class WarehouseRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function all(): array
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM warehouses
            ORDER BY name ASC, id ASC
        ");

        return $stmt->fetchAll() ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM warehouses
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $warehouse = $stmt->fetch();

        return $warehouse ?: null;
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS count
                FROM warehouses
                WHERE name = :name
                  AND id != :except_id
            ");

            $stmt->execute([
                'name' => $name,
                'except_id' => $exceptId,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS count
                FROM warehouses
                WHERE name = :name
            ");

            $stmt->execute([
                'name' => $name,
            ]);
        }

        $row = $stmt->fetch();

        return ((int) ($row['count'] ?? 0)) > 0;
    }

    public function create(array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO warehouses (name, address)
            VALUES (:name, :address)
        ");

        return $stmt->execute([
            'name' => $data['name'],
            'address' => $data['address'],
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE warehouses
            SET
                name = :name,
                address = :address
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'address' => $data['address'],
        ]);
    }
}