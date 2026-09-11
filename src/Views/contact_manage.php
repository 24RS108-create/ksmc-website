<?php

use App\Core\Csrf;
use App\Core\View;

/**
 * @var string|null $notice
 * @var array<int, array{id: int, email: ?string, body: string, handled_at: ?string, created_at: string}> $inquiries
 */
?>
<h1>お問い合わせ管理</h1>

<?php if (!empty($notice)): ?>
<p class="notice"><?= View::e($notice) ?></p>
<?php endif; ?>

<?php if (empty($inquiries)): ?>
<p class="hint">お問い合わせはまだありません。</p>
<?php else: ?>

<?php foreach ($inquiries as $inquiry): ?>
<div class="account-card">
    <p>
        <span class="status-badge status-<?= $inquiry['handled_at'] === null ? 'draft' : 'published' ?>">
            <?= $inquiry['handled_at'] === null ? '未対応' : '対応済み' ?>
        </span>
        <span class="hint"><?= View::e(substr($inquiry['created_at'], 0, 16)) ?></span>
    </p>
    <p>
        <strong>メール：</strong>
        <?php if ($inquiry['email'] !== null && $inquiry['email'] !== ''): ?>
        <a href="mailto:<?= View::e($inquiry['email']) ?>"><?= View::e($inquiry['email']) ?></a>
        <?php else: ?>
        （記載なし）
        <?php endif; ?>
    </p>
    <div class="post-body"><?= View::e($inquiry['body']) ?></div>

    <form method="post" action="/contact_status.php" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $inquiry['id'] ?>">
        <button type="submit" class="button-secondary">
            <?= $inquiry['handled_at'] === null ? '対応済みにする' : '未対応に戻す' ?>
        </button>
    </form>
</div>
<?php endforeach; ?>

<?php endif; ?>
