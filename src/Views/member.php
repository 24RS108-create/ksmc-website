<?php

use App\Core\View;

/**
 * 会員ページ（FR-28）。表示名・任意入力のプロフィール（profile_note）・公開済み投稿一覧を表示する。
 * プロフィールは任意入力かつ公開項目のため、未入力の会員には表示しない（FR-03）。
 * 5行以上に及ぶ場合は折りたたんで表示する（$foldProfileNote、GalleryController::showMember()参照）。
 *
 * @var \App\Models\User $member
 * @var bool $foldProfileNote
 * @var array<int, array{post: \App\Models\Post, authorId: ?int, authorName: string, thumbnail: ?string}> $posts
 * @var array{page: int, totalPages: int, offset: int, perPage: int, totalCount: int} $pagination
 * @var string $pageBaseUrl
 * @var array<string, mixed> $extraQuery
 */
?>
<p><a href="/gallery.php">作品ギャラリーへ戻る</a></p>

<h1><?= View::e($member->displayName) ?></h1>

<?php if (!empty($member->profileNote)): ?>
<div class="profile-note-section">
    <?php if ($foldProfileNote): ?>
    <details class="profile-note-fold">
        <summary>プロフィールを表示</summary>
        <p class="profile-note"><?= View::e($member->profileNote) ?></p>
    </details>
    <?php else: ?>
    <p class="profile-note"><?= View::e($member->profileNote) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($posts)): ?>
<p class="hint">公開済みの作品はまだありません。</p>
<?php else: ?>
<div class="post-card-grid">
    <?php foreach ($posts as $item): ?>
    <?php include __DIR__ . '/partials/post_card.php'; ?>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/partials/pagination.php'; ?>
<?php endif; ?>
