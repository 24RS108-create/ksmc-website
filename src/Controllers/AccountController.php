<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\ImageUploader;
use App\Core\View;
use App\Models\Post;
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

    public function showManage(): void
    {
        Auth::requireRole('admin');

        $searchLoginId = trim((string) ($_GET['login_id'] ?? ''));
        $found = $searchLoginId !== '' ? User::findByLoginId($searchLoginId) : null;

        View::render('account_manage', [
            'title' => 'アカウント管理',
            'notice' => null,
            'error' => $searchLoginId !== '' && $found === null ? '該当するアカウントが見つかりませんでした。' : null,
            'searchLoginId' => $searchLoginId,
            'found' => $found,
        ]);
    }

    public function showTransferConfirm(): void
    {
        $admin = Auth::requireRole('admin');
        $target = $this->findTransferTargetOrRedirect($admin);

        View::render('account_transfer_confirm', [
            'title' => '管理者権限の委譲確認',
            'error' => null,
            'target' => $target,
        ]);
    }

    public function transferAdmin(): void
    {
        $admin = Auth::requireRole('admin');
        $target = $this->findTransferTargetOrRedirect($admin, (string) ($_POST['login_id'] ?? ''));

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            View::render('account_transfer_confirm', [
                'title' => '管理者権限の委譲確認',
                'error' => '不正なリクエストです。もう一度お試しください。',
                'target' => $target,
            ]);
            return;
        }

        $connection = Database::connection();
        $connection->beginTransaction();
        try {
            $target->updateRole('admin');
            $admin->updateRole('member');
            $connection->commit();
        } catch (\Throwable) {
            $connection->rollBack();

            View::render('account_transfer_confirm', [
                'title' => '管理者権限の委譲確認',
                'error' => '権限の委譲に失敗しました。もう一度お試しください。',
                'target' => $target,
            ]);
            return;
        }

        View::render('account_manage', [
            'title' => 'アカウント管理',
            'notice' => "管理者権限を「{$target->loginId}」に委譲しました。あなたのロールは「会員」になりました。",
            'error' => null,
            'searchLoginId' => '',
            'found' => null,
        ]);
    }

    public function showLockConfirm(): void
    {
        $admin = Auth::requireRole('admin');
        $target = $this->findLockTargetOrRedirect($admin);

        View::render('account_lock_confirm', [
            'title' => 'アカウントのロック確認',
            'error' => null,
            'target' => $target,
        ]);
    }

    public function lock(): void
    {
        $admin = Auth::requireRole('admin');
        $target = $this->findLockTargetOrRedirect($admin, (string) ($_POST['login_id'] ?? ''));

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            View::render('account_lock_confirm', [
                'title' => 'アカウントのロック確認',
                'error' => '不正なリクエストです。もう一度お試しください。',
                'target' => $target,
            ]);
            return;
        }

        $target->updateRole('inactive');

        View::render('account_manage', [
            'title' => 'アカウント管理',
            'notice' => "「{$target->loginId}」を休止会員にしました。",
            'error' => null,
            'searchLoginId' => '',
            'found' => null,
        ]);
    }

    public function showDeleteConfirm(): void
    {
        $admin = Auth::requireRole('admin');
        $target = $this->findManagedTargetOrRedirect($admin);

        View::render('account_delete_confirm', [
            'title' => 'アカウント削除の確認',
            'error' => null,
            'target' => $target,
        ]);
    }

    public function delete(): void
    {
        $admin = Auth::requireRole('admin');
        $target = $this->findManagedTargetOrRedirect($admin, (string) ($_POST['login_id'] ?? ''));

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            View::render('account_delete_confirm', [
                'title' => 'アカウント削除の確認',
                'error' => '不正なリクエストです。もう一度お試しください。',
                'target' => $target,
            ]);
            return;
        }

        // 投稿ごとアップロード済み画像も削除する（FR-11、DB側は外部キーのCASCADEで連鎖削除）。
        foreach (Post::findByUserId($target->id) as $post) {
            ImageUploader::deletePostDirectory($post->id);
        }
        User::delete($target->id);

        View::render('account_manage', [
            'title' => 'アカウント管理',
            'notice' => "「{$target->loginId}」のアカウントを削除しました。",
            'error' => null,
            'searchLoginId' => '',
            'found' => null,
        ]);
    }

    /**
     * 広報担当ロールの付与・解除（FR-14）。委譲・削除と異なり容易に元へ戻せる操作のため、
     * 確認画面を挟まず1クリックで実行する。
     */
    public function updatePr(): void
    {
        $admin = Auth::requireRole('admin');
        $target = $this->findManagedTargetOrRedirect($admin, (string) ($_POST['login_id'] ?? ''));
        $action = (string) ($_POST['pr_action'] ?? '');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            header('Location: /account_manage.php');
            exit;
        }

        if ($action === 'grant' && $target->role === 'member') {
            $target->updateRole('pr');
            $notice = "「{$target->loginId}」を広報担当にしました。";
        } elseif ($action === 'revoke' && $target->role === 'pr') {
            $target->updateRole('member');
            $notice = "「{$target->loginId}」の広報担当を解除しました。";
        } else {
            header('Location: /account_manage.php');
            exit;
        }

        View::render('account_manage', [
            'title' => 'アカウント管理',
            'notice' => $notice,
            'error' => null,
            'searchLoginId' => '',
            'found' => null,
        ]);
    }

    /**
     * 管理対象のログインIDを検証し、対象ユーザーを返す（自分自身は対象外）。
     * 不正な場合は一覧へリダイレクトして終了する。
     */
    private function findManagedTargetOrRedirect(User $admin, ?string $loginId = null): User
    {
        $loginId = $loginId ?? (string) ($_GET['login_id'] ?? '');
        $loginId = trim($loginId);
        $target = $loginId !== '' ? User::findByLoginId($loginId) : null;

        if ($target === null || $target->id === $admin->id) {
            header('Location: /account_manage.php');
            exit;
        }

        return $target;
    }

    /**
     * 委譲先ログインIDを検証し、対象ユーザーを返す。不正な場合は一覧へリダイレクトして終了する。
     */
    private function findTransferTargetOrRedirect(User $admin, ?string $loginId = null): User
    {
        $target = $this->findManagedTargetOrRedirect($admin, $loginId);

        if ($target->role === 'admin') {
            header('Location: /account_manage.php');
            exit;
        }

        return $target;
    }

    /**
     * ロック対象のログインIDを検証し、対象ユーザーを返す。不正な場合は一覧へリダイレクトして終了する。
     */
    private function findLockTargetOrRedirect(User $admin, ?string $loginId = null): User
    {
        $target = $this->findManagedTargetOrRedirect($admin, $loginId);

        if ($target->role === 'inactive') {
            header('Location: /account_manage.php');
            exit;
        }

        return $target;
    }
}
