<?php

use App\Core\Csrf;
use App\Core\View;

/**
 * @var array<int, string> $errors
 * @var string $loginId
 */
?>
<h1>ログイン</h1>

<?php if (!empty($errors)): ?>
<ul class="error-list">
    <?php foreach ($errors as $error): ?>
    <li><?= View::e($error) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/login.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">

    <div class="form-row">
        <label for="login_id">ログインID</label>
        <input type="text" id="login_id" name="login_id" value="<?= View::e($loginId) ?>" required autofocus>
    </div>

    <div class="form-row">
        <label for="password">パスワード</label>
        <input type="password" id="password" name="password" required>
    </div>

    <div class="form-actions">
        <button type="submit">ログイン</button>
    </div>
</form>
