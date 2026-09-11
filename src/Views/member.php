<?php

use App\Core\View;

/**
 * 会員ページ（FR-28）。表示名と公開済み投稿一覧のみを表示する。プロフィールメモは非公開項目
 * のためここには表示しない（profile_edit.phpの「サイト上には公開されません」に対応）。
 *
 * @var \App\Models\User $member
 * @var array<int, array{post: \App\Models\Post, authorId: ?int, authorName: string, thumbnail: ?string}> $posts
 */
?>
<p><a href="/gallery.php">作品ギャラリーへ戻る</a></p>

<h1><?= View::e($member->displayName) ?></h1>

<?php if (empty($posts)): ?>
<p class="hint">公開済みの作品はまだありません。</p>
<?php else: ?>
<div class="post-card-grid">
    <?php foreach ($posts as $item): ?>
    <?php include __DIR__ . '/partials/post_card.php'; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
