<?php

/**
 * @var array<int, array{post: \App\Models\Post, authorName: string, thumbnail: ?string}> $posts
 */
?>
<h1>作品ギャラリー</h1>

<p><a href="/">トップページへ戻る</a></p>

<?php if (empty($posts)): ?>
<p class="hint">まだ作品がありません。</p>
<?php else: ?>
<div class="post-card-grid">
    <?php foreach ($posts as $item): ?>
    <?php include __DIR__ . '/partials/post_card.php'; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
