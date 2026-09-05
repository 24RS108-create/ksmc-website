<?php

use App\Core\Csrf;
use App\Core\View;
use App\Models\User;

/**
 * @var string|null $notice
 * @var string|null $error
 * @var string $searchLoginId
 * @var User|null $found
 */

$roleLabels = [
    'member' => '会員',
    'pr' => '広報担当',
    'admin' => '管理者',
    'inactive' => '休止会員',
];
?>
<h1>アカウント管理</h1>

<?php if (!empty($notice)): ?>
<p class="notice"><?= View::e($notice) ?></p>
<?php endif; ?>

<?php if (!empty($error)): ?>
<ul class="error-list">
    <li><?= View::e($error) ?></li>
</ul>
<?php endif; ?>

<form method="get" action="/account_manage.php">
    <div class="form-row">
        <label for="login_id">ログインIDで検索</label>
        <input type="text" id="login_id" name="login_id" value="<?= View::e($searchLoginId) ?>" required>
    </div>
    <div class="form-actions">
        <button type="submit">検索</button>
    </div>
</form>

<?php if ($found !== null): ?>
<div class="account-card">
    <p><strong><?= View::e($found->displayName) ?></strong>（<?= View::e($found->loginId) ?>）</p>
    <p>現在のロール：<?= View::e($roleLabels[$found->role] ?? $found->role) ?></p>

    <?php if ($found->role !== 'admin'): ?>
    <p><a class="button" href="/account_transfer.php?login_id=<?= urlencode($found->loginId) ?>">管理者権限を委譲する</a></p>
    <?php endif; ?>

    <?php if ($found->role === 'member'): ?>
    <form method="post" action="/account_pr.php" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">
        <input type="hidden" name="login_id" value="<?= View::e($found->loginId) ?>">
        <input type="hidden" name="pr_action" value="grant">
        <button type="submit" class="button-secondary">広報担当にする</button>
    </form>
    <?php elseif ($found->role === 'pr'): ?>
    <form method="post" action="/account_pr.php" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">
        <input type="hidden" name="login_id" value="<?= View::e($found->loginId) ?>">
        <input type="hidden" name="pr_action" value="revoke">
        <button type="submit" class="button-secondary">広報担当を解除する</button>
    </form>
    <?php endif; ?>

    <?php if ($found->role !== 'inactive'): ?>
    <p><a class="button button-secondary" href="/account_lock.php?login_id=<?= urlencode($found->loginId) ?>">休止会員にする（ロック）</a></p>
    <?php endif; ?>

    <p><a class="button button-danger" href="/account_delete.php?login_id=<?= urlencode($found->loginId) ?>">アカウントを削除する</a></p>
</div>
<?php endif; ?>
