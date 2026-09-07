<?php

use App\Core\View;

/**
 * @var array<int, array{id: int, name: string, post_count: int}> $topTags
 * @var array<int, array{id: int, name: string, post_count: int}> $moreTags
 * @var bool $expandMoreTags
 * @var array<int, int> $selectedTagIds
 * @var array<int, array{post: \App\Models\Post, authorName: string, thumbnail: ?string}> $posts
 */
?>
<h1>タグから探す</h1>

<p><a href="/">トップページへ戻る</a></p>

<?php if (empty($topTags)): ?>
<p class="hint">登録されているタグはまだありません。</p>
<?php else: ?>

<form method="get" action="/tags.php">
    <div class="form-row">
        <label>タグを選択（すべて選んだタグを持つ投稿を表示します。件数の多い順）</label>
        <?php foreach ($topTags as $tag): ?>
        <label class="checkbox-label">
            <input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>" <?= in_array($tag['id'], $selectedTagIds, true) ? 'checked' : '' ?>>
            #<?= View::e($tag['name']) ?>（<?= (int) $tag['post_count'] ?>）
        </label>
        <?php endforeach; ?>

        <?php if (!empty($moreTags)): ?>
        <details class="tag-more" <?= $expandMoreTags ? 'open' : '' ?>>
            <summary>もっと見る（残り<?= count($moreTags) ?>件）</summary>
            <?php foreach ($moreTags as $tag): ?>
            <label class="checkbox-label">
                <input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>" <?= in_array($tag['id'], $selectedTagIds, true) ? 'checked' : '' ?>>
                #<?= View::e($tag['name']) ?>（<?= (int) $tag['post_count'] ?>）
            </label>
            <?php endforeach; ?>
        </details>
        <?php endif; ?>
    </div>
    <div class="form-actions">
        <button type="submit">絞り込む</button>
    </div>
</form>

<?php if (empty($selectedTagIds)): ?>
<p class="hint">タグを選択して「絞り込む」を押してください。</p>
<?php elseif (empty($posts)): ?>
<p class="hint">選択したタグをすべて持つ投稿は見つかりませんでした。</p>
<?php else: ?>
<div class="post-card-grid">
    <?php foreach ($posts as $item): ?>
    <?php include __DIR__ . '/partials/post_card.php'; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>
