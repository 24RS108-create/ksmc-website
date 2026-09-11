<?php

use App\Core\Csrf;
use App\Core\View;

/**
 * @var array<int, string> $errors
 * @var string|null $notice
 * @var string $formEmail
 * @var string $formBody
 */
?>
<h1>お問い合わせ</h1>

<p class="hint">サークルへのお問い合わせはこちらのフォームからお送りください。返信をご希望の場合はメールアドレスをご記入ください（任意）。</p>

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

<form method="post" action="/contact.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">

    <div class="honeypot" aria-hidden="true">
        <label for="website">Webサイト（この欄は入力しないでください）</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <div class="form-row">
        <label for="email">メールアドレス（任意）</label>
        <input type="email" id="email" name="email" value="<?= View::e($formEmail) ?>" maxlength="255">
    </div>

    <div class="form-row">
        <label for="body">お問い合わせ内容</label>
        <textarea id="body" name="body" required maxlength="2000"><?= View::e($formBody) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit">送信する</button>
    </div>
</form>
