<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Repositories\InventoryRepository;
use App\Repositories\ItemRepository;
use App\Repositories\WarehouseRepository;
use App\Services\InboundService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Session;
use Throwable;

class InboundController extends BaseController
{
    private ItemRepository $items;
    private WarehouseRepository $warehouses;
    private InventoryRepository $inventory;
    private InboundService $inboundService;

    public function __construct()
    {
        $this->items = new ItemRepository();
        $this->warehouses = new WarehouseRepository();
        $this->inventory = new InventoryRepository();
        $this->inboundService = new InboundService();
    }

    public function create(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $old = Session::getFlash('old', []);

        return $this->view('inventory.inbound.create', [
            'title' => 'Inbound',
            'items' => $this->items->all(),
            'warehouses' => $this->warehouses->all(),
            'old' => is_array($old) ? $old : [],
            'formError' => Session::getFlash('error'),
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $warehouseId = (int) $request->input('warehouse_id', 0);
        $items = $request->input('items', []);
        $items = is_array($items) ? array_values($items) : [];

        try {
            $this->inboundService->process(
                $warehouseId,
                $items,
                Auth::username() ?? 'system'
            );

            Session::flash('success', 'Inbound transaction successfully recorded.');
            return $this->redirect('/inventory/inbound/history');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', [
                'warehouse_id' => $warehouseId,
                'items' => $items,
            ]);

            return $this->redirect('/inventory/inbound');
        }
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

        $records = $this->inventory->getInboundRecords($filters);

        return $this->view('inventory.inbound.history', [
            'title' => 'Inbound History',
            'records' => $records,
            'items' => $this->items->all(),
            'warehouses' => $this->warehouses->all(),
            'filters' => $filters,
        ]);
    }

    public function generatePallet(Request $request)
    {
        if (!Auth::check()) {
            return $this->json([
                'error' => 'Unauthorized.',
            ], 401);
        }

        $itemId = (int) $request->input('item_id', 0);

        if ($itemId <= 0) {
            return $this->json([
                'error' => 'Invalid item selected.',
            ], 422);
        }

        $item = $this->items->find($itemId);

        if (!$item) {
            return $this->json([
                'error' => 'Item not found.',
            ], 404);
        }

        $itemCode = trim((string) ($item['item_code'] ?? ''));

        if ($itemCode === '') {
            return $this->json([
                'error' => 'Selected item has no item code.',
            ], 422);
        }

        $date = date('Ymd');

        for ($unique = 1; $unique <= 9999; $unique++) {
            $candidate = $itemCode . '-' . $date . '-' . str_pad((string) $unique, 4, '0', STR_PAD_LEFT);

            if (!$this->inventory->palletExists($candidate)) {
                return $this->json([
                    'pallet_id' => $candidate,
                ]);
            }
        }

        return $this->json([
            'error' => 'Unable to generate unique pallet ID.',
        ], 500);
    }
}