<?php

use App\Config\Uploads;
use App\Core\Csrf;
use App\Core\View;
use App\Models\Post;

/**
 * @var Post $post
 * @var array<int, string> $errors
 * @var string|null $notice
 * @var string $formTitle
 * @var string $formBody
 * @var string $formNewTags
 * @var array<int, int> $selectedTagIds
 * @var array<int, array{id: int, name: string}> $tags
 * @var array<int, array{id: int, display_path: string, sort_order: int, file_size_kb: int}> $existingImages
 */

$maxTotalMb = (int) (Uploads::MAX_TOTAL_BYTES / 1024 / 1024);
?>
<h1>投稿を編集</h1>

<p><a href="/my_posts.php">自分の投稿一覧へ戻る</a></p>

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

<form method="post" action="/post_edit.php?id=<?= $post->id ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">
    <input type="hidden" name="id" value="<?= $post->id ?>">

    <div class="form-row">
        <label for="title">タイトル</label>
        <input type="text" id="title" name="title" value="<?= View::e($formTitle) ?>" required maxlength="200" autofocus>
    </div>

    <div class="form-row">
        <label for="body">本文</label>
        <textarea id="body" name="body"><?= View::e($formBody) ?></textarea>
        <p class="hint">公開するには入力が必要です（下書き保存は未入力でも可）。</p>
    </div>

    <?php if (!empty($existingImages)): ?>
    <div class="form-row">
        <label>登録済みの画像</label>
        <ul class="image-list">
            <?php foreach ($existingImages as $image): ?>
            <li><?= View::e($image['display_path']) ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="hint">既存の画像はこの画面では削除できません。削除したい場合は投稿自体を削除してください。</p>
    </div>
    <?php endif; ?>

    <div class="form-row">
        <label for="images">画像を追加</label>
        <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
        <p class="hint">jpg / png / gif / webp、合計<?= $maxTotalMb ?>MBまで、最大<?= Uploads::MAX_FILE_COUNT ?>枚（既存分を含む）。</p>
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

<p><a href="/post_delete.php?id=<?= $post->id ?>">この投稿を削除する</a></p>
