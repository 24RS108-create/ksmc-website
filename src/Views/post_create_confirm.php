<?php

use App\Core\Csrf;
use App\Core\View;

/**
 * @var array{token: string, mode: string, user_id: int, post_id: ?int, post_type: string,
 *     title: string, body: string, status: string, selected_tag_ids: array<int, int>,
 *     new_tag_names: array<int, string>, delete_image_ids: array<int, int>,
 *     images: array<int, array{display_path: string}>} $pending
 * @var array{usesPlaceholders: bool, html: string, leadingImages: array<int, array{display_path: string}>,
 *     trailingImages: array<int, array{display_path: string}>, invalidTokens: array<int, int>} $rendered
 * @var string $postTypeLabel
 * @var string $statusLabel
 * @var array<int, string> $tagNames
 */
?>
<h1>投稿内容の確認</h1>

<p class="hint">この内容で保存してよろしいですか？画像は実際にロゴを合成した状態で表示しています。修正する場合は画像も含めて選び直しになります。</p>

<?php if (!empty($rendered['invalidTokens'])): ?>
<p class="error-list">本文中に存在しない画像番号への参照があります（[image:<?= implode('], [image:', $rendered['invalidTokens']) ?>]）。番号を見直すか、このまま保存すると文字列として表示されます。</p>
<?php endif; ?>

<?php if ($pending['post_type'] === 'official_blog'): ?>
<span class="status-badge status-blog"><?= View::e($postTypeLabel) ?></span>
<?php endif; ?>
<span class="status-badge status-<?= $pending['status'] === 'published' ? 'published' : 'draft' ?>"><?= View::e($statusLabel) ?></span>

<h2><?= View::e($pending['title']) ?></h2>

<?php if (!empty($rendered['leadingImages'])): ?>
<div class="post-image-list">
    <?php foreach ($rendered['leadingImages'] as $image): ?>
    <img class="post-image" src="<?= View::e($image['display_path']) ?>" alt="">
    <?php endforeach; ?>
</div>
<?php elseif (empty($pending['images'])): ?>
<p class="hint">画像はありません。</p>
<?php endif; ?>

<div class="post-body"><?= $rendered['html'] ?></div>

<?php if (!empty($rendered['trailingImages'])): ?>
<p class="hint">本文中で参照されていない画像</p>
<div class="post-image-list">
    <?php foreach ($rendered['trailingImages'] as $image): ?>
    <img class="post-image" src="<?= View::e($image['display_path']) ?>" alt="">
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($tagNames)): ?>
<p class="post-tags">
    <?php foreach ($tagNames as $name): ?>
    <span class="status-badge">#<?= View::e($name) ?></span>
    <?php endforeach; ?>
</p>
<?php endif; ?>

<form method="post" action="/post_create_confirm.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">
    <div class="form-actions">
        <button type="submit" name="confirm_action" value="cancel" class="button-secondary">修正する（この内容は破棄されます）</button>
        <button type="submit" name="confirm_action" value="commit"><?= $pending['status'] === 'published' ? '公開する' : '下書き保存する' ?></button>
    </div>
</form>
