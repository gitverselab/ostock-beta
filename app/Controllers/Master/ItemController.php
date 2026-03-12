<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Repositories\ItemRepository;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Session;
use Throwable;

class ItemController extends BaseController
{
    private ItemRepository $items;

    public function __construct()
    {
        $this->items = new ItemRepository();
    }

    public function index(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        return $this->view('items.index', [
            'title' => 'Items',
            'items' => $this->items->all(),
        ]);
    }

    public function create(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        return $this->view('items.create', [
            'title' => 'Add Item',
            'categories' => $this->items->getCategories(),
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
            return $this->redirect('/items/create');
        }

        if ($this->items->itemCodeExists($data['item_code'])) {
            Session::flash('error', 'Item code already exists.');
            Session::flash('old', $data);
            return $this->redirect('/items/create');
        }

        try {
            $this->items->create($data);
            Session::flash('success', 'Item successfully created.');
            return $this->redirect('/items');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to save item.');
            Session::flash('old', $data);
            return $this->redirect('/items/create');
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
            Session::flash('error', 'Invalid item selected.');
            return $this->redirect('/items');
        }

        $item = $this->items->find($id);

        if (!$item) {
            Session::flash('error', 'Item not found.');
            return $this->redirect('/items');
        }

        return $this->view('items.edit', [
            'title' => 'Edit Item',
            'item' => $item,
            'categories' => $this->items->getCategories(),
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
            Session::flash('error', 'Invalid item selected.');
            return $this->redirect('/items');
        }

        $existingItem = $this->items->find($id);

        if (!$existingItem) {
            Session::flash('error', 'Item not found.');
            return $this->redirect('/items');
        }

        $data = $this->normalizeFormData($request);

        $validationError = $this->validate($data);

        if ($validationError !== null) {
            Session::flash('error', $validationError);
            return $this->redirect('/items/edit?id=' . $id);
        }

        if ($this->items->itemCodeExists($data['item_code'], $id)) {
            Session::flash('error', 'Item code already exists.');
            return $this->redirect('/items/edit?id=' . $id);
        }

        try {
            $this->items->update($id, $data);
            Session::flash('success', 'Item successfully updated.');
            return $this->redirect('/items');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update item.');
            return $this->redirect('/items/edit?id=' . $id);
        }
    }

    public function delete(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            Session::flash('error', 'Invalid item selected.');
            return $this->redirect('/items');
        }

        $item = $this->items->find($id);

        if (!$item) {
            Session::flash('error', 'Item not found.');
            return $this->redirect('/items');
        }

        return $this->view('items.delete', [
            'title' => 'Delete Item',
            'item' => $item,
        ]);
    }

    public function destroy(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            Session::flash('error', 'Invalid item selected.');
            return $this->redirect('/items');
        }

        $item = $this->items->find($id);

        if (!$item) {
            Session::flash('error', 'Item not found.');
            return $this->redirect('/items');
        }

        try {
            $this->items->delete($id);
            Session::flash('success', 'Item successfully deleted.');
            return $this->redirect('/items');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to delete item. It may still be referenced by inventory or transaction records.');
            return $this->redirect('/items');
        }
    }

    private function normalizeFormData(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name')),
            'item_code' => trim((string) $request->input('item_code')),
            'uom' => trim((string) $request->input('uom')),
            'category_id' => (int) $request->input('category_id', 0),
            'cost' => (float) $request->input('cost', 0),
            'is_calendar_item' => $request->input('is_calendar_item') ? 1 : 0,
            'primary_uom_label' => trim((string) $request->input('primary_uom_label')),
            'secondary_uom_label' => trim((string) $request->input('secondary_uom_label')),
        ];
    }

    private function validate(array $data): ?string
    {
        if ($data['name'] === '') {
            return 'Item name is required.';
        }

        if ($data['item_code'] === '') {
            return 'Item code is required.';
        }

        if ($data['category_id'] <= 0) {
            return 'Please select a category.';
        }

        if ($data['primary_uom_label'] === '') {
            return 'Primary unit label is required.';
        }

        if ($data['secondary_uom_label'] === '') {
            return 'Secondary unit label is required.';
        }

        if ($data['uom'] === '') {
            return 'Base UOM is required.';
        }

        if ($data['cost'] < 0) {
            return 'Cost cannot be negative.';
        }

        return null;
    }
}