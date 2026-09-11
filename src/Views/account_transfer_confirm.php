<?php

use App\Core\Csrf;
use App\Core\View;
use App\Models\User;

/**
 * @var User $target
 * @var string|null $error
 */
?>
<h1>管理者権限の委譲確認</h1>

<?php if (!empty($error)): ?>
<ul class="error-list">
    <li><?= View::e($error) ?></li>
</ul>
<?php endif; ?>

<p><strong><?= View::e($target->displayName) ?></strong>（<?= View::e($target->loginId) ?>）に管理者権限を委譲します。</p>
<p class="hint">実行すると、あなた自身のロールは「会員」になります。この操作は取り消せません。</p>

<form method="post" action="/account_transfer.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">
    <input type="hidden" name="login_id" value="<?= View::e($target->loginId) ?>">
    <div class="form-actions">
        <a class="button button-secondary" href="/account_manage.php">キャンセル</a>
        <button type="submit">委譲を実行する</button>
    </div>
</form>
