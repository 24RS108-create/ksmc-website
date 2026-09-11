<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Models\User;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: /');
            exit;
        }

        View::render('login', [
            'title' => 'ログイン',
            'errors' => [],
            'loginId' => '',
        ]);
    }

    public function login(): void
    {
        $loginId = trim((string) ($_POST['login_id'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $errors[] = '不正なリクエストです。もう一度お試しください。';
        } elseif ($loginId === '' || $password === '') {
            $errors[] = 'ログインIDとパスワードを入力してください。';
        } else {
            $user = User::findByLoginId($loginId);

            if ($user === null || !$user->verifyPassword($password)) {
                $errors[] = 'ログインIDまたはパスワードが正しくありません。';
            } else {
                Auth::login($user);

                if ($user->mustChangePassword) {
                    header('Location: /password_edit.php');
                } else {
                    header('Location: /');
                }
                exit;
            }
        }

        View::render('login', [
            'title' => 'ログイン',
            'errors' => $errors,
            'loginId' => $loginId,
        ]);
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: /login.php');
        exit;
    }

    public function showPasswordEdit(): void
    {
        $user = Auth::requireLogin();

        View::render('password_edit', [
            'title' => 'パスワード再設定',
            'errors' => [],
            'notice' => null,
            'forced' => $user->mustChangePassword,
        ]);
    }

    public function updatePassword(): void
    {
        $user = Auth::requireLogin();

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');
        $errors = [];

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $errors[] = '不正なリクエストです。もう一度お試しください。';
        } elseif (!$user->verifyPassword($currentPassword)) {
            $errors[] = '現在のパスワードが正しくありません。';
        } elseif (mb_strlen($newPassword) < 8) {
            $errors[] = '新しいパスワードは8文字以上で入力してください。';
        } elseif ($newPassword !== $newPasswordConfirm) {
            $errors[] = '新しいパスワード（確認）が一致しません。';
        }

        if (empty($errors)) {
            $user->updatePassword($newPassword, false);

            View::render('password_edit', [
                'title' => 'パスワード再設定',
                'errors' => [],
                'notice' => 'パスワードを変更しました。',
                'forced' => false,
            ]);
            return;
        }

        View::render('password_edit', [
            'title' => 'パスワード再設定',
            'errors' => $errors,
            'notice' => null,
            'forced' => $user->mustChangePassword,
        ]);
    }
}
