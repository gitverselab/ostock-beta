<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Request;
use App\Support\Session;

class AuthController extends BaseController
{
    public function showLogin(Request $request)
    {
        return $this->view('auth.login', [
            'title' => 'Login',
            'error' => Session::getFlash('error')
        ], 'guest');
    }

    public function login(Request $request)
    {
        $username = trim((string) $request->input('username'));
        $password = (string) $request->input('password');

        if ($username === 'admin' && $password === 'admin123') {
            Session::put('user', [
                'id' => 1,
                'username' => 'admin',
                'role' => 1,
            ]);

            Session::flash('success', 'Welcome back.');
            return $this->redirect('/dashboard');
        }

        Session::flash('error', 'Invalid username or password.');
        return $this->redirect('/login');
    }

    public function logout(Request $request)
    {
        Session::forget('user');
        Session::flash('success', 'You have been logged out.');
        return $this->redirect('/login');
    }
}