<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Repositories\WarehouseRepository;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Session;
use Throwable;

class WarehouseController extends BaseController
{
    private WarehouseRepository $warehouses;

    public function __construct()
    {
        $this->warehouses = new WarehouseRepository();
    }

    public function index(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        return $this->view('warehouses.index', [
            'title' => 'Warehouses',
            'warehouses' => $this->warehouses->all(),
        ]);
    }

    public function create(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        return $this->view('warehouses.create', [
            'title' => 'Add Warehouse',
            'old' => Session::getFlash('old', []),
            'formError' => Session::getFlash('error'),
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $data = $this->normalizeFormData($request);

        $validationError = $this->validate($data);

        if ($validationError !== null) {
            Session::flash('error', $validationError);
            Session::flash('old', $data);
            return $this->redirect('/warehouses/create');
        }

        if ($this->warehouses->nameExists($data['name'])) {
            Session::flash('error', 'Warehouse name already exists.');
            Session::flash('old', $data);
            return $this->redirect('/warehouses/create');
        }

        try {
            $this->warehouses->create($data);
            Session::flash('success', 'Warehouse successfully created.');
            return $this->redirect('/warehouses');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to save warehouse.');
            Session::flash('old', $data);
            return $this->redirect('/warehouses/create');
        }
    }

    public function edit(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            Session::flash('error', 'Invalid warehouse selected.');
            return $this->redirect('/warehouses');
        }

        $warehouse = $this->warehouses->find($id);

        if (!$warehouse) {
            Session::flash('error', 'Warehouse not found.');
            return $this->redirect('/warehouses');
        }

        return $this->view('warehouses.edit', [
            'title' => 'Edit Warehouse',
            'warehouse' => $warehouse,
            'formError' => Session::getFlash('error'),
        ]);
    }

    public function update(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            Session::flash('error', 'Invalid warehouse selected.');
            return $this->redirect('/warehouses');
        }

        $existingWarehouse = $this->warehouses->find($id);

        if (!$existingWarehouse) {
            Session::flash('error', 'Warehouse not found.');
            return $this->redirect('/warehouses');
        }

        $data = $this->normalizeFormData($request);

        $validationError = $this->validate($data);

        if ($validationError !== null) {
            Session::flash('error', $validationError);
            return $this->redirect('/warehouses/edit?id=' . $id);
        }

        if ($this->warehouses->nameExists($data['name'], $id)) {
            Session::flash('error', 'Warehouse name already exists.');
            return $this->redirect('/warehouses/edit?id=' . $id);
        }

        try {
            $this->warehouses->update($id, $data);
            Session::flash('success', 'Warehouse successfully updated.');
            return $this->redirect('/warehouses');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update warehouse.');
            return $this->redirect('/warehouses/edit?id=' . $id);
        }
    }

    private function normalizeFormData(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name')),
            'address' => trim((string) $request->input('address')),
        ];
    }

    private function validate(array $data): ?string
    {
        if ($data['name'] === '') {
            return 'Warehouse name is required.';
        }

        if ($data['address'] === '') {
            return 'Warehouse address is required.';
        }

        return null;
    }
}