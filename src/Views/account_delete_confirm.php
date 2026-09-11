<?php

use App\Core\Csrf;
use App\Core\View;
use App\Models\User;

/**
 * @var User $target
 * @var string|null $error
 */
?>
<h1>アカウント削除の確認</h1>

<?php if (!empty($error)): ?>
<ul class="error-list">
    <li><?= View::e($error) ?></li>
</ul>
<?php endif; ?>

<p><strong><?= View::e($target->displayName) ?></strong>（<?= View::e($target->loginId) ?>）のアカウントを削除します。</p>
<p class="hint">この会員の投稿・画像もすべて削除されます。この操作は取り消せません。本人からの削除の要望を確認した上で実行してください。</p>

<form method="post" action="/account_delete.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">
    <input type="hidden" name="login_id" value="<?= View::e($target->loginId) ?>">
    <div class="form-actions">
        <a class="button button-secondary" href="/account_manage.php">キャンセル</a>
        <button type="submit">削除する</button>
    </div>
</form>
