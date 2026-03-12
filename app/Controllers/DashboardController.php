<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Request;
use App\Support\Session;

class DashboardController extends BaseController
{
    public function index(Request $request)
    {
        if (!Session::get('user')) {
            return $this->redirect('/login');
        }

        return $this->view('dashboard.index', [
            'title' => 'Dashboard',
            'user' => Session::get('user')
        ]);
    }
}