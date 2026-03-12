<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Repositories\InventoryRepository;
use App\Repositories\WarehouseRepository;
use App\Services\OutboundService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Session;
use Throwable;

class OutboundController extends BaseController
{
    private WarehouseRepository $warehouses;
    private InventoryRepository $inventory;
    private OutboundService $outboundService;

    public function __construct()
    {
        $this->warehouses = new WarehouseRepository();
        $this->inventory = new InventoryRepository();
        $this->outboundService = new OutboundService();
    }

    public function create(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $old = Session::getFlash('old', []);

        return $this->view('inventory.outbound.create', [
            'title' => 'Outbound',
            'items' => $this->inventory->getOutboundItemOptions(),
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
        $outboundType = trim((string) $request->input('outbound_type', ''));
        $items = $request->input('items', []);
        $items = is_array($items) ? array_values($items) : [];

        try {
            $this->outboundService->process(
                $warehouseId,
                $outboundType,
                $items,
                Auth::username() ?? 'system'
            );

            Session::flash('success', 'Outbound transaction successfully recorded.');
            return $this->redirect('/inventory/outbound');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', [
                'warehouse_id' => $warehouseId,
                'outbound_type' => $outboundType,
                'items' => $items,
            ]);

            return $this->redirect('/inventory/outbound');
        }
    }

    public function pallets(Request $request)
    {
        if (!Auth::check()) {
            return $this->json([
                'error' => 'Unauthorized.',
            ], 401);
        }

        $itemId = (int) $request->input('item_id', 0);
        $warehouseId = (int) $request->input('warehouse_id', 0);

        if ($itemId <= 0 || $warehouseId <= 0) {
            return $this->json([
                'error' => 'Invalid item or warehouse.',
            ], 422);
        }

        return $this->json([
            'pallets' => $this->inventory->getPalletsByItemAndWarehouse($itemId, $warehouseId),
        ]);
    }
}