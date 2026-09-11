<?php

use App\Core\Csrf;
use App\Core\View;
use App\Models\User;

/**
 * @var User $target
 * @var string|null $error
 */
?>
<h1>アカウントのロック確認</h1>

<?php if (!empty($error)): ?>
<ul class="error-list">
    <li><?= View::e($error) ?></li>
</ul>
<?php endif; ?>

<p><strong><?= View::e($target->displayName) ?></strong>（<?= View::e($target->loginId) ?>）を休止会員にします。</p>
<p class="hint">休止会員はログイン・パスワード再設定・自分の既存投稿の編集/削除は引き続き可能ですが、新規投稿とプロフィール編集はできなくなります。</p>

<form method="post" action="/account_lock.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">
    <input type="hidden" name="login_id" value="<?= View::e($target->loginId) ?>">
    <div class="form-actions">
        <a class="button button-secondary" href="/account_manage.php">キャンセル</a>
        <button type="submit">ロックする</button>
    </div>
</form>
