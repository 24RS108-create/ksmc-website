<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Models\User;

final class AccountController
{
    public function showCreate(): void
    {
        Auth::requireRole('admin');

        View::render('account_create', [
            'title' => 'アカウント発行',
            'errors' => [],
            'notice' => null,
            'loginId' => '',
            'displayName' => '',
        ]);
    }

    public function create(): void
    {
        $admin = Auth::requireRole('admin');

        $loginId = trim((string) ($_POST['login_id'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $initialPassword = (string) ($_POST['initial_password'] ?? '');
        $initialPasswordConfirm = (string) ($_POST['initial_password_confirm'] ?? '');
        $errors = [];

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $errors[] = '不正なリクエストです。もう一度お試しください。';
        }
        if ($loginId === '') {
            $errors[] = 'ログインIDを入力してください。';
        } elseif (User::loginIdExists($loginId)) {
            $errors[] = 'そのログインIDは既に使用されています。';
        }
        if ($displayName === '') {
            $errors[] = '表示名を入力してください。';
        } elseif (mb_strlen($displayName) > 100) {
            $errors[] = '表示名は100文字以内で入力してください。';
        }
        if (mb_strlen($initialPassword) < 8) {
            $errors[] = '初期パスワードは8文字以上で入力してください。';
        } elseif ($initialPassword !== $initialPasswordConfirm) {
            $errors[] = '初期パスワード（確認）が一致しません。';
        }

        if (empty($errors)) {
            $newUser = User::createInvited($loginId, $initialPassword, $displayName, $admin->id);

            View::render('account_create', [
                'title' => 'アカウント発行',
                'errors' => [],
                'notice' => "アカウント「{$newUser->loginId}」を発行しました。初期パスワードを本人へお伝えください。",
                'loginId' => '',
                'displayName' => '',
            ]);
            return;
        }

        View::render('account_create', [
            'title' => 'アカウント発行',
            'errors' => $errors,
            'notice' => null,
            'loginId' => $loginId,
            'displayName' => $displayName,
        ]);
    }
}
