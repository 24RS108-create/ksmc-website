<?php

use App\Core\View;

/** @var array{post: \App\Models\Post, authorName: string, thumbnail: ?string} $item */
$post = $item['post'];
?>
<a class="post-card" href="/post_view.php?id=<?= $post->id ?>">
    <?php if ($item['thumbnail'] !== null): ?>
    <img class="post-card-thumb" src="<?= View::e($item['thumbnail']) ?>" alt="">
    <?php else: ?>
    <span class="post-card-thumb post-card-thumb-empty"></span>
    <?php endif; ?>
    <?php if ($post->postType === 'official_blog'): ?>
    <span class="status-badge status-blog">公式ブログ</span>
    <?php endif; ?>
    <span class="post-card-title"><?= View::e($post->title) ?></span>
    <span class="post-card-meta"><?= View::e($item['authorName']) ?>・<?= View::e($post->publishedAt !== null ? substr($post->publishedAt, 0, 10) : '') ?></span>
</a>
