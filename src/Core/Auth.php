<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private const SESSION_KEY = 'user_id';

    public static function login(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user->id;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]);
    }

    public static function user(): ?User
    {
        if (!self::check()) {
            return null;
        }

        return User::findById((int) $_SESSION[self::SESSION_KEY]);
    }

    /**
     * 未ログインなら指定先へリダイレクトして処理を終了する。
     */
    public static function requireLogin(string $redirectTo = '/login.php'): User
    {
        $user = self::user();
        if ($user === null) {
            header('Location: ' . $redirectTo);
            exit;
        }

        return $user;
    }

    /**
     * 未ログイン、または指定ロールでなければリダイレクトして処理を終了する。
     */
    public static function requireRole(string $role, string $redirectTo = '/login.php'): User
    {
        $user = self::requireLogin($redirectTo);
        if ($user->role !== $role) {
            header('Location: /profile_edit.php');
            exit;
        }

        return $user;
    }
}
