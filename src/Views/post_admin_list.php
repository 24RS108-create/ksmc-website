<?php

use App\Core\View;

/**
 * @var array<int, array{id: int, title: string, post_type: string, status: string,
 *     user_id: int, author_name: string}> $posts
 * @var array{page: int, totalPages: int, offset: int, perPage: int, totalCount: int} $pagination
 * @var string $pageBaseUrl
 * @var array<string, mixed> $extraQuery
 */
?>
<h1>全投稿管理</h1>

<p class="hint">全会員の投稿（下書き含む）を一覧表示します。投稿の編集は各投稿者本人のみ行えます。</p>

<?php if (empty($posts)): ?>
<p>投稿がまだありません。</p>
<?php else: ?>
<ul class="post-list">
    <?php foreach ($posts as $post): ?>
    <li class="post-list-item">
        <span class="status-badge status-<?= View::e($post['status']) ?>">
            <?= $post['status'] === 'published' ? '公開中' : '下書き' ?>
        </span>
        <?php if ($post['post_type'] === 'official_blog'): ?>
        <span class="status-badge status-blog">公式ブログ</span>
        <?php endif; ?>
        <span class="post-list-title">
            <?= View::e($post['title']) ?>
            （<a href="/member.php?id=<?= $post['user_id'] ?>"><?= View::e($post['author_name']) ?></a>）
        </span>
        <span class="post-list-actions">
            <a href="/post_delete.php?id=<?= $post['id'] ?>">削除</a>
        </span>
    </li>
    <?php endforeach; ?>
</ul>
<?php include __DIR__ . '/partials/pagination.php'; ?>
<?php endif; ?>
