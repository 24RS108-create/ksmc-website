<?php

use App\Core\Csrf;
use App\Core\View;

/**
 * @var array<int, string> $errors
 * @var string|null $notice
 * @var bool $forced
 */
?>
<h1>パスワード再設定</h1>

<?php if (!empty($forced)): ?>
<p class="hint">初期パスワードでのログインのため、続行する前にパスワードの変更が必要です。</p>
<?php endif; ?>

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

<form method="post" action="/password_edit.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">

    <div class="form-row">
        <label for="current_password">現在のパスワード</label>
        <input type="password" id="current_password" name="current_password" required autofocus>
    </div>

    <div class="form-row">
        <label for="new_password">新しいパスワード</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">
    </div>

    <div class="form-row">
        <label for="new_password_confirm">新しいパスワード（確認）</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8">
    </div>

    <div class="form-actions">
        <button type="submit">パスワードを変更</button>
    </div>
</form>
