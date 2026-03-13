<?php

declare(strict_types=1);

namespace App\Controllers\Inventory;

use App\Controllers\BaseController;
use App\Repositories\InventoryRepository;
use App\Repositories\ItemRepository;
use App\Repositories\WarehouseRepository;
use App\Services\TransferService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Session;
use Throwable;

class TransferController extends BaseController
{
    private WarehouseRepository $warehouses;
    private InventoryRepository $inventory;
    private TransferService $transferService;
    private ItemRepository $items;

    public function __construct()
    {
        $this->warehouses = new WarehouseRepository();
        $this->inventory = new InventoryRepository();
        $this->transferService = new TransferService();
        $this->items = new ItemRepository();
    }

    public function create(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $old = Session::getFlash('old', []);

        return $this->view('inventory.transfer.create', [
            'title' => 'Transfer',
            'items' => $this->inventory->getTransferItemOptions(),
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

        $sourceWarehouse = (int) $request->input('source_warehouse', 0);
        $destinationWarehouse = (int) $request->input('destination_warehouse', 0);
        $items = $request->input('items', []);
        $items = is_array($items) ? array_values($items) : [];

        $normalizedItems = [];

        foreach ($items as $item) {
            $destMode = trim((string) ($item['dest_mode'] ?? 'existing'));
            $destPallet = $destMode === 'manual'
                ? trim((string) ($item['dest_pallet_manual'] ?? ''))
                : trim((string) ($item['dest_pallet_select'] ?? ''));

            $normalizedItems[] = [
                'item_id' => (int) ($item['item_id'] ?? 0),
                'source_pallet' => trim((string) ($item['source_pallet'] ?? '')),
                'dest_pallet' => $destPallet,
                'quantity' => (int) ($item['quantity'] ?? 0),
                'items_per_pc' => isset($item['items_per_pc']) && $item['items_per_pc'] !== ''
                    ? (int) ($item['items_per_pc'])
                    : 0,
                'dest_mode' => $destMode,
                'dest_pallet_select' => trim((string) ($item['dest_pallet_select'] ?? '')),
                'dest_pallet_manual' => trim((string) ($item['dest_pallet_manual'] ?? '')),
            ];
        }

        try {
            $this->transferService->process(
                $sourceWarehouse,
                $destinationWarehouse,
                $normalizedItems,
                Auth::username() ?? 'system'
            );

            Session::flash('success', 'Transfer completed successfully.');
            return $this->redirect('/inventory/transfer/history');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old', [
                'source_warehouse' => $sourceWarehouse,
                'destination_warehouse' => $destinationWarehouse,
                'items' => $normalizedItems,
            ]);

            return $this->redirect('/inventory/transfer');
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
            'source_warehouse' => (int) $request->input('source_warehouse', 0),
            'destination_warehouse' => (int) $request->input('destination_warehouse', 0),
            'start_date' => trim((string) $request->input('start_date', '')),
            'end_date' => trim((string) $request->input('end_date', '')),
            'limit' => trim((string) $request->input('limit', '20')),
        ];

        return $this->view('inventory.transfer.history', [
            'title' => 'Transfer History',
            'records' => $this->inventory->getTransferRecords($filters),
            'items' => $this->items->all(),
            'warehouses' => $this->warehouses->all(),
            'filters' => $filters,
        ]);
    }

    public function sourcePallets(Request $request)
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

    public function destinationPallets(Request $request)
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