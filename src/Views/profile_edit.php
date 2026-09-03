<?php

use App\Core\Csrf;
use App\Core\View;

/**
 * @var array<int, string> $errors
 * @var string|null $notice
 * @var string $displayName
 * @var string|null $profileNote
 */
?>
<h1>プロフィール編集</h1>

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

<form method="post" action="/profile_edit.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">

    <div class="form-row">
        <label for="display_name">表示名（必須・サイト上に公開されます）</label>
        <input type="text" id="display_name" name="display_name" value="<?= View::e($displayName) ?>" required maxlength="100" autofocus>
    </div>

    <div class="form-row">
        <label for="profile_note">メモ（任意・サイト上には公開されません）</label>
        <textarea id="profile_note" name="profile_note" maxlength="2000"><?= View::e($profileNote) ?></textarea>
        <p class="hint">学年・専攻など、公開したくない情報はここに書いても外部には表示されません。</p>
    </div>

    <div class="form-actions">
        <button type="submit">保存</button>
    </div>
</form>
