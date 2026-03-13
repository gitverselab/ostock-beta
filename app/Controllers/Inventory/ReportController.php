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
}