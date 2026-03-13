<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Repositories\DeletedTransactionRepository;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Session;

class DeletedTransactionController extends BaseController
{
    private DeletedTransactionRepository $deletedTransactions;

    public function __construct()
    {
        $this->deletedTransactions = new DeletedTransactionRepository();
    }

    public function index(Request $request)
    {
        if (!Auth::check()) {
            Session::flash('error', 'Please sign in first.');
            return $this->redirect('/login');
        }

        $filters = [
            'transaction_type' => trim((string) $request->input('transaction_type', '')),
            'deleted_by' => trim((string) $request->input('deleted_by', '')),
            'start_date' => trim((string) $request->input('start_date', '')),
            'end_date' => trim((string) $request->input('end_date', '')),
        ];

        return $this->view('admin.deleted_history.index', [
            'title' => 'Deleted Transaction History',
            'records' => $this->deletedTransactions->getRecords($filters),
            'transactionTypes' => $this->deletedTransactions->getTransactionTypes(),
            'filters' => $filters,
        ]);
    }
}