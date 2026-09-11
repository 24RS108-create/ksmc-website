<?php

/**
 * @var array<int, array{post: \App\Models\Post, authorId: ?int, authorName: string, thumbnail: ?string}> $posts
 * @var array{page: int, totalPages: int, offset: int, perPage: int, totalCount: int} $pagination
 * @var string $pageBaseUrl
 * @var array<string, mixed> $extraQuery
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
<?php include __DIR__ . '/partials/pagination.php'; ?>
<?php endif; ?>
