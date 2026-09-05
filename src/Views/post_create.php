<?php

use App\Config\Uploads;
use App\Core\Csrf;
use App\Core\View;

/**
 * @var array<int, string> $errors
 * @var string|null $notice
 * @var string $formTitle
 * @var string $formBody
 * @var string $formNewTags
 * @var array<int, int> $selectedTagIds
 * @var array<int, array{id: int, name: string}> $tags
 * @var bool $canPostOfficialBlog
 * @var string $formPostType
 */

$maxTotalMb = (int) (Uploads::MAX_TOTAL_BYTES / 1024 / 1024);
?>
<h1>作品投稿</h1>

<?php if (!empty($notice)): ?>
<p class="notice"><?= View::e($notice) ?></p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<ul class="error-list">
    <?php foreach ($errors as $error): ?>
    <li><?= View::e($error) ?></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<form method="post" action="/post_create.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">

    <?php if ($canPostOfficialBlog): ?>
    <div class="form-row">
        <label>投稿区分</label>
        <label class="checkbox-label">
            <input type="radio" name="post_type" value="individual" <?= $formPostType !== 'official_blog' ? 'checked' : '' ?>>
            個人の作品記事
        </label>
        <label class="checkbox-label">
            <input type="radio" name="post_type" value="official_blog" <?= $formPostType === 'official_blog' ? 'checked' : '' ?>>
            サークル公式ブログ
        </label>
    </div>
    <?php endif; ?>

    <div class="form-row">
        <label for="title">タイトル</label>
        <input type="text" id="title" name="title" value="<?= View::e($formTitle) ?>" required maxlength="200" autofocus>
    </div>

    <div class="form-row">
        <label for="body">本文</label>
        <textarea id="body" name="body"><?= View::e($formBody) ?></textarea>
        <p class="hint">公開するには入力が必要です（下書き保存は未入力でも可）。</p>
    </div>

    <div class="form-row">
        <label for="images">画像</label>
        <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
        <p class="hint">jpg / png / gif / webp、合計<?= $maxTotalMb ?>MBまで、最大<?= Uploads::MAX_FILE_COUNT ?>枚。公開するには1枚以上必要です（下書き保存は未選択でも可）。</p>
    </div>

    <?php if (!empty($tags)): ?>
    <div class="form-row">
        <label>既存タグから選ぶ</label>
        <?php foreach ($tags as $tag): ?>
        <label class="checkbox-label">
            <input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>" <?= in_array($tag['id'], $selectedTagIds, true) ? 'checked' : '' ?>>
            <?= View::e($tag['name']) ?>
        </label>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="form-row">
        <label for="new_tags">新しいタグ</label>
        <input type="text" id="new_tags" name="new_tags" value="<?= View::e($formNewTags) ?>">
        <p class="hint">カンマ区切りで複数入力できます（例: 戦車, ガンプラ）。</p>
    </div>

    <div class="form-actions">
        <button type="submit" name="action" value="draft" class="button-secondary">下書き保存</button>
        <button type="submit" name="action" value="publish">公開する</button>
    </div>
</form>
