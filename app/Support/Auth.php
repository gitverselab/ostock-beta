<?php

declare(strict_types=1);

namespace App\Support;

class Auth
{
    public static function user(): ?array
    {
        $user = Session::get('user');

        return is_array($user) ? $user : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return isset($user['id']) ? (int) $user['id'] : null;
    }

    public static function username(): ?string
    {
        $user = self::user();

        return isset($user['username']) ? (string) $user['username'] : null;
    }

    public static function role(): int|string|null
    {
        $user = self::user();

        return $user['role'] ?? null;
    }

    public static function login(array $user): void
    {
        Session::put('user', [
            'id' => (int) ($user['id'] ?? 0),
            'username' => (string) ($user['username'] ?? ''),
            'role' => $user['role'] ?? null,
        ]);
    }

    public static function logout(): void
    {
        Session::forget('user');
    }

    public static function guest(): bool
    {
        return !self::check();
    }
}