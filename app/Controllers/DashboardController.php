<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Request;
use App\Support\Session;
use PDO;
use Throwable;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $stats = [
            'total_pieces' => 0,
            'total_pallets' => 0,
            'distinct_skus' => 0,
            'warehouses_count' => 0,
        ];

        $recentActivity = [];
        $dashboardError = null;

        try {
            $pdo = Database::connection();

            $finishedGoodCategoryId = $this->getFinishedGoodCategoryId($pdo);
            $stats = $this->getDashboardStats($pdo, $finishedGoodCategoryId);
            $recentActivity = $this->getRecentActivity($pdo, $finishedGoodCategoryId);
        } catch (Throwable $e) {
            $dashboardError = 'Dashboard widgets could not be fully loaded. Please check your data tables or database connection.';
        }

        return $this->view('dashboard.index', [
            'title' => 'Dashboard',
            'user' => Auth::user(),
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'dashboardError' => $dashboardError,
        ]);
    }

    private function getFinishedGoodCategoryId(PDO $pdo): int
    {
        $stmt = $pdo->prepare("
            SELECT id
            FROM item_categories
            WHERE category_name = :category_name
            LIMIT 1
        ");

        $stmt->execute([
            'category_name' => 'Finished Good',
        ]);

        $row = $stmt->fetch();

        return $row ? (int) $row['id'] : 0;
    }

    private function getDashboardStats(PDO $pdo, int $finishedGoodCategoryId): array
    {
        $statsStmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(inv.items_per_pc), 0) AS total_pieces,
                COUNT(DISTINCT inv.pallet_id) AS total_pallets,
                COUNT(DISTINCT inv.item_id) AS distinct_skus
            FROM inventory inv
            INNER JOIN items i ON inv.item_id = i.id
            WHERE i.category_id = :category_id
        ");

        $statsStmt->execute([
            'category_id' => $finishedGoodCategoryId,
        ]);

        $stats = $statsStmt->fetch() ?: [];

        $warehouseStmt = $pdo->query("
            SELECT COUNT(*) AS count
            FROM warehouses
        ");

        $warehouseRow = $warehouseStmt->fetch() ?: ['count' => 0];

        return [
            'total_pieces' => (int) ($stats['total_pieces'] ?? 0),
            'total_pallets' => (int) ($stats['total_pallets'] ?? 0),
            'distinct_skus' => (int) ($stats['distinct_skus'] ?? 0),
            'warehouses_count' => (int) ($warehouseRow['count'] ?? 0),
        ];
    }

    private function getRecentActivity(PDO $pdo, int $finishedGoodCategoryId): array
    {
        $stmt = $pdo->prepare("
            SELECT
                h.transaction_type,
                h.quantity,
                h.items_per_pc,
                h.transaction_date,
                i.name AS item_name
            FROM inventory_history h
            INNER JOIN items i ON h.item_id = i.id
            WHERE i.category_id = :category_id
            ORDER BY h.transaction_date DESC
            LIMIT 8
        ");

        $stmt->execute([
            'category_id' => $finishedGoodCategoryId,
        ]);

        return $stmt->fetchAll() ?: [];
    }
}