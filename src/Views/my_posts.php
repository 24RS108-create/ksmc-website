<?php

use App\Core\View;
use App\Models\Post;

/**
 * @var array<int, Post> $posts
 */
?>
<h1>自分の投稿</h1>

<p><a class="button" href="/post_create.php">新しく投稿する</a></p>

<?php if (empty($posts)): ?>
<p>まだ投稿がありません。</p>
<?php else: ?>
<ul class="post-list">
    <?php foreach ($posts as $post): ?>
    <li class="post-list-item">
        <span class="status-badge status-<?= View::e($post->status) ?>">
            <?= $post->status === 'published' ? '公開中' : '下書き' ?>
        </span>
        <?php if ($post->postType === 'official_blog'): ?>
        <span class="status-badge status-blog">公式ブログ</span>
        <?php endif; ?>
        <span class="post-list-title"><?= View::e($post->title) ?></span>
        <span class="post-list-actions">
            <a href="/post_edit.php?id=<?= $post->id ?>">編集</a>
            <a href="/post_delete.php?id=<?= $post->id ?>">削除</a>
        </span>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
