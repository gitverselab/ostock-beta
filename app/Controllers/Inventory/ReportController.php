<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Repositories\InventoryRepository;
use App\Repositories\ItemRepository;
use App\Repositories\WarehouseRepository;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Session;

class ReportController extends BaseController
{
    private InventoryRepository $inventory;
    private ItemRepository $items;
    private WarehouseRepository $warehouses;

    public function __construct()
    {
        $this->inventory = new InventoryRepository();
        $this->items = new ItemRepository();
        $this->warehouses = new WarehouseRepository();
    }

    public function index(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $filters = [
            'item_id' => (int) $request->input('item_id', 0),
            'warehouse_id' => (int) $request->input('warehouse_id', 0),
            'start_date' => trim((string) $request->input('start_date', '')),
            'end_date' => trim((string) $request->input('end_date', '')),
            'limit' => trim((string) $request->input('limit', '20')),
        ];

        return $this->view('inventory.reports.index', [
            'title' => 'Inventory Report',
            'summary' => $this->inventory->getInventorySummary($filters),
            'records' => $this->inventory->getInventoryReportRecords($filters),
            'items' => $this->items->all(),
            'warehouses' => $this->warehouses->all(),
            'filters' => $filters,
        ]);
    }

    public function pallets(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $itemId = (int) $request->input('item_id', 0);
        $warehouseId = (int) $request->input('warehouse_id', 0);

        if ($itemId <= 0 || $warehouseId <= 0) {
            Session::flash('error', 'Invalid item or warehouse selected.');
            return $this->redirect('/inventory/report');
        }

        $pallets = $this->inventory->getPalletDetailsByItemAndWarehouse($itemId, $warehouseId);

        if (count($pallets) === 0) {
            Session::flash('error', 'No pallet records found for the selected item and warehouse.');
            return $this->redirect('/inventory/report');
        }

        return $this->view('inventory.reports.pallets', [
            'title' => 'Pallet Details',
            'pallets' => $pallets,
            'itemId' => $itemId,
            'warehouseId' => $warehouseId,
        ]);
    }

    public function history(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $filters = [
            'item_id' => (int) $request->input('item_id', 0),
            'warehouse_id' => (int) $request->input('warehouse_id', 0),
            'start_date' => trim((string) $request->input('start_date', '')),
            'end_date' => trim((string) $request->input('end_date', '')),
            'limit' => trim((string) $request->input('limit', '20')),
        ];

        return $this->view('inventory.reports.history', [
            'title' => 'Inventory History',
            'records' => $this->inventory->getInventoryHistoryRecords($filters),
            'items' => $this->items->all(),
            'warehouses' => $this->warehouses->all(),
            'filters' => $filters,
        ]);
    }
}