<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\InventoryRepository;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Session;
use Throwable;

class DashboardController extends BaseController
{
    private InventoryRepository $inventory;

    public function __construct()
    {
        $this->inventory = new InventoryRepository();
    }

    public function index(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $lowStockThreshold = (int) $request->input('low_stock_threshold', 100);
        if ($lowStockThreshold <= 0) {
            $lowStockThreshold = 100;
        }

        $dashboardError = null;
        $kpis = [
            'total_pieces' => 0,
            'total_pallets' => 0,
            'distinct_skus' => 0,
            'warehouses_count' => 0,
        ];
        $lowStockItems = [];
        $expiringSoonItems = [];
        $recentInbound = [];
        $recentOutbound = [];
        $warehouseStock = [];
        $storageTracker = [];

        try {
            $finishedGoodCategoryId = $this->inventory->getFinishedGoodCategoryId();

            if ($finishedGoodCategoryId > 0) {
                $kpis = $this->inventory->getFinishedGoodsKpis($finishedGoodCategoryId);
                $lowStockItems = $this->inventory->getLowStockItems($finishedGoodCategoryId, $lowStockThreshold);
                $expiringSoonItems = $this->inventory->getExpiringSoonItems($finishedGoodCategoryId, 14);
                $recentInbound = $this->inventory->getRecentInboundActivities($finishedGoodCategoryId, 5);
                $recentOutbound = $this->inventory->getRecentOutboundActivities($finishedGoodCategoryId, 5);
                $warehouseStock = $this->inventory->getFinishedGoodsStockByWarehouse($finishedGoodCategoryId);
                $storageTracker = $this->inventory->getFinishedGoodsStorageTracker($finishedGoodCategoryId);
            } else {
                $dashboardError = 'Finished Good category was not found. Dashboard widgets may be incomplete.';
            }
        } catch (Throwable $e) {
            $dashboardError = 'Dashboard widgets could not be fully loaded.';
        }

        return $this->view('dashboard.index', [
            'title' => 'Dashboard',
            'kpis' => $kpis,
            'lowStockThreshold' => $lowStockThreshold,
            'lowStockItems' => $lowStockItems,
            'expiringSoonItems' => $expiringSoonItems,
            'recentInbound' => $recentInbound,
            'recentOutbound' => $recentOutbound,
            'warehouseStock' => $warehouseStock,
            'storageTracker' => $storageTracker,
            'dashboardError' => $dashboardError,
        ]);
    }
}