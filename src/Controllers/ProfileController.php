<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Models\User;

final class ProfileController
{
    public function show(): void
    {
        $user = $this->requireActiveUser();

        View::render('profile_edit', [
            'title' => 'プロフィール編集',
            'errors' => [],
            'notice' => null,
            'displayName' => $user->displayName,
            'profileNote' => $user->profileNote,
        ]);
    }

    public function update(): void
    {
        $user = $this->requireActiveUser();

        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $profileNoteRaw = trim((string) ($_POST['profile_note'] ?? ''));
        $profileNote = $profileNoteRaw === '' ? null : $profileNoteRaw;
        $errors = [];

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $errors[] = '不正なリクエストです。もう一度お試しください。';
        } elseif ($displayName === '') {
            $errors[] = '表示名を入力してください。';
        } elseif (mb_strlen($displayName) > 100) {
            $errors[] = '表示名は100文字以内で入力してください。';
        } elseif ($profileNote !== null && mb_strlen($profileNote) > 2000) {
            $errors[] = 'メモは2000文字以内で入力してください。';
        }

        if (empty($errors)) {
            $user->updateProfile($displayName, $profileNote);

            View::render('profile_edit', [
                'title' => 'プロフィール編集',
                'errors' => [],
                'notice' => 'プロフィールを保存しました。',
                'displayName' => $user->displayName,
                'profileNote' => $user->profileNote,
            ]);
            return;
        }

        View::render('profile_edit', [
            'title' => 'プロフィール編集',
            'errors' => $errors,
            'notice' => null,
            'displayName' => $displayName,
            'profileNote' => $profileNote,
        ]);
    }

    /**
     * 休止会員はプロフィール編集不可（FR-11）。
     */
    private function requireActiveUser(): User
    {
        $user = Auth::requireLogin();

        if ($user->role === 'inactive') {
            header('Location: /my_posts.php');
            exit;
        }

        return $user;
    }
}
