<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class DeletedTransactionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function getTransactionTypes(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT transaction_type
            FROM deleted_transactions
            ORDER BY transaction_type ASC
        ");

        return $stmt->fetchAll() ?: [];
    }

    public function getRecords(array $filters = []): array
    {
        $sql = "
            SELECT
                id,
                transaction_type,
                original_id,
                deleted_by,
                deleted_date,
                details
            FROM deleted_transactions
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['transaction_type'])) {
            $sql .= " AND transaction_type = :transaction_type";
            $params['transaction_type'] = $filters['transaction_type'];
        }

        if (!empty($filters['deleted_by'])) {
            $sql .= " AND deleted_by LIKE :deleted_by";
            $params['deleted_by'] = '%' . $filters['deleted_by'] . '%';
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND deleted_date >= :start_date";
            $params['start_date'] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND deleted_date <= :end_date";
            $params['end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        $sql .= " ORDER BY deleted_date DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }
}