<?php

use App\Core\Csrf;
use App\Core\View;

/**
 * @var array<int, string> $errors
 * @var string|null $notice
 * @var string $loginId
 * @var string $displayName
 */
?>
<h1>アカウント発行</h1>

<p class="hint">会員登録は招待制です。ログインIDと初期パスワードを決めて発行し、本人へ直接お伝えください（初回ログイン時にパスワードの変更が求められます）。</p>

<?php if (!empty($notice)): ?>
<p class="notice"><?= View::e($notice) ?></p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<ul class="error-list">
    <?php foreach ($errors as $error): ?>
    <li><?= View::e($error) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/account_create.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">

    <div class="form-row">
        <label for="login_id">ログインID</label>
        <input type="text" id="login_id" name="login_id" value="<?= View::e($loginId) ?>" required maxlength="64" autofocus>
    </div>

    <div class="form-row">
        <label for="display_name">表示名</label>
        <input type="text" id="display_name" name="display_name" value="<?= View::e($displayName) ?>" required maxlength="100">
        <p class="hint">本人が後からプロフィール編集画面で変更できます。</p>
    </div>

    <div class="form-row">
        <label for="initial_password">初期パスワード</label>
        <input type="password" id="initial_password" name="initial_password" required minlength="8">
    </div>

    <div class="form-row">
        <label for="initial_password_confirm">初期パスワード（確認）</label>
        <input type="password" id="initial_password_confirm" name="initial_password_confirm" required minlength="8">
    </div>

    <div class="form-actions">
        <button type="submit">発行</button>
    </div>
</form>
