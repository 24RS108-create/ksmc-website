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
 * @var bool $canDeleteImages
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
        <p class="hint">公開するには入力が必要です（下書き保存は未入力でも可）。本文中に <code>[image:1]</code> のように入力すると、その位置に画像を挿入できます（番号は下の画像一覧・選択欄で確認できます）。使わない場合は従来通り画像が本文の上にまとめて表示されます。</p>
    </div>

    <?php if (!empty($existingImages)): ?>
    <div class="form-row">
        <label>登録済みの画像</label>
        <div class="post-image-list">
            <?php foreach ($existingImages as $index => $image): ?>
            <?php if ($canDeleteImages): ?>
            <label class="existing-image-item">
                <img class="post-image" src="<?= View::e($image['display_path']) ?>" alt="">
                <span class="hint">画像<?= $index + 1 ?></span>
                <span class="checkbox-label">
                    <input type="checkbox" name="delete_image_ids[]" value="<?= (int) $image['id'] ?>">
                    この画像を削除する
                </span>
            </label>
            <?php else: ?>
            <span class="existing-image-item">
                <img class="post-image" src="<?= View::e($image['display_path']) ?>" alt="">
                <span class="hint">画像<?= $index + 1 ?></span>
            </span>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php if ($canDeleteImages): ?>
        <p class="hint">削除する画像にチェックを入れて保存すると、確認画面で削除内容を確認できます。本文の [image:N] 番号は、上の並び順（削除した画像を除く）＋新しく追加する画像の順で決まります。削除の組み合わせによって番号がずれる場合があるため、最終的な番号は確認画面で確かめてください。</p>
        <?php else: ?>
        <p class="hint">休止会員は既存の画像を削除できません。削除したい場合は投稿自体を削除してください。</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="form-row">
        <label for="images">画像を追加</label>
        <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple data-logo-adjust-target="logo-adjust-list" data-image-number-offset="<?= count($existingImages) ?>">
        <p class="hint">jpg / png / gif / webp、合計<?= $maxTotalMb ?>MBまで、最大<?= Uploads::MAX_FILE_COUNT ?>枚（既存分を含む）。各画像には九産模型愛好会のロゴが合成されます。選択すると、画像ごとにプレビュー上でロゴの位置・大きさ・濃さを調整できます。</p>
        <div id="logo-adjust-list" class="logo-adjust-list"></div>
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

<script src="/assets/js/logo-adjust.js" defer></script>

<p><a href="/post_delete.php?id=<?= $post->id ?>">この投稿を削除する</a></p>
