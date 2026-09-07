<?php

use App\Core\View;

/**
 * @var \App\Models\Post $post
 * @var string $authorName
 * @var array<int, array{id: int, display_path: string, sort_order: int, file_size_kb: int}> $images
 * @var array<int, array{id: int, name: string}> $tags
 */

$backHref = $post->postType === 'official_blog' ? '/blog.php' : '/gallery.php';
$backLabel = $post->postType === 'official_blog' ? '公式ブログ一覧へ戻る' : '作品ギャラリーへ戻る';
?>
<p><a href="<?= $backHref ?>"><?= $backLabel ?></a></p>

<?php if ($post->postType === 'official_blog'): ?>
<span class="status-badge status-blog">公式ブログ</span>
<?php endif; ?>

<h1><?= View::e($post->title) ?></h1>
<p class="hint">
    <?= View::e($authorName) ?>・<?= View::e($post->publishedAt !== null ? substr($post->publishedAt, 0, 10) : '') ?>
</p>

<?php if (!empty($images)): ?>
<div class="post-image-list">
    <?php foreach ($images as $image): ?>
    <img class="post-image" src="<?= View::e($image['display_path']) ?>" alt="">
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="post-body"><?= View::e($post->body) ?></div>

<?php if (!empty($tags)): ?>
<p class="post-tags">
    <?php foreach ($tags as $tag): ?>
    <a class="status-badge" href="/tags.php?tag_ids%5B%5D=<?= (int) $tag['id'] ?>">#<?= View::e($tag['name']) ?></a>
    <?php endforeach; ?>
</p>
<?php endif; ?>
