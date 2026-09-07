<?php

use App\Core\Csrf;
use App\Core\View;

/**
 * @var string|null $notice
 * @var string|null $error
 * @var array<int, array{id: int, name: string, post_count: int}> $tags
 */
?>
<h1>タグ管理</h1>

<p class="hint">表記ゆれのタグをリネーム、または複数のタグを1つに統合できます（例：「戦車」と「戦車模型」を統合）。</p>

<?php if (!empty($notice)): ?>
<p class="notice"><?= View::e($notice) ?></p>
<?php endif; ?>

<?php if (!empty($error)): ?>
<ul class="error-list">
    <li><?= View::e($error) ?></li>
</ul>
<?php endif; ?>

<?php if (empty($tags)): ?>
<p class="hint">登録されているタグはまだありません。</p>
<?php else: ?>

<h2>リネーム</h2>
<ul class="post-list">
    <?php foreach ($tags as $tag): ?>
    <li class="post-list-item">
        <span class="post-list-title">#<?= View::e($tag['name']) ?>（<?= (int) $tag['post_count'] ?>件）</span>
        <form method="post" action="/tag_rename.php" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="tag_id" value="<?= (int) $tag['id'] ?>">
            <input type="text" name="name" value="<?= View::e($tag['name']) ?>" maxlength="50" required>
            <button type="submit" class="button-secondary">名前を変更</button>
        </form>
    </li>
    <?php endforeach; ?>
</ul>

<h2>統合</h2>
<p class="hint">統合元に選んだタグは削除され、投稿の紐付けは統合先のタグへ引き継がれます。この操作は取り消せません。</p>
<form method="post" action="/tag_merge.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">

    <div class="form-row">
        <label>統合元タグ（複数選択可）</label>
        <?php foreach ($tags as $tag): ?>
        <label class="checkbox-label">
            <input type="checkbox" name="source_tag_ids[]" value="<?= (int) $tag['id'] ?>">
            #<?= View::e($tag['name']) ?>
        </label>
        <?php endforeach; ?>
    </div>

    <div class="form-row">
        <label for="target_tag_id">統合先タグ</label>
        <select id="target_tag_id" name="target_tag_id" required>
            <option value="">選択してください</option>
            <?php foreach ($tags as $tag): ?>
            <option value="<?= (int) $tag['id'] ?>">#<?= View::e($tag['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-actions">
        <button type="submit">統合を実行する</button>
    </div>
</form>

<?php endif; ?>
