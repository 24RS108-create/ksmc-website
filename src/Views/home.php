<?php

use App\Core\Auth;

/**
 * @var array<int, array{post: \App\Models\Post, authorName: string, thumbnail: ?string}> $individualPosts
 * @var array<int, array{post: \App\Models\Post, authorName: string, thumbnail: ?string}> $blogPosts
 */
?>
<h1>九産模型愛好会</h1>

<nav class="top-nav">
    <?php if (Auth::check()): ?>
    <a href="/my_posts.php">自分の投稿</a>
    <a href="/post_create.php">投稿する</a>
    <a href="/profile_edit.php">プロフィール</a>
    <a href="/logout.php">ログアウト</a>
    <?php else: ?>
    <a href="/login.php">ログイン</a>
    <?php endif; ?>
</nav>

<section>
    <h2>作品ギャラリー</h2>
    <?php if (empty($individualPosts)): ?>
    <p class="hint">まだ作品がありません。</p>
    <?php else: ?>
    <div class="post-card-grid">
        <?php foreach ($individualPosts as $item): ?>
        <?php include __DIR__ . '/partials/post_card.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p><a href="/gallery.php">作品ギャラリー一覧へ</a></p>
</section>

<section>
    <h2>公式ブログ</h2>
    <?php if (empty($blogPosts)): ?>
    <p class="hint">まだ記事がありません。</p>
    <?php else: ?>
    <div class="post-card-grid">
        <?php foreach ($blogPosts as $item): ?>
        <?php include __DIR__ . '/partials/post_card.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p><a href="/blog.php">公式ブログ一覧へ</a></p>
</section>
