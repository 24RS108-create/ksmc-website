<?php

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
</div>
<?php endif; ?>
