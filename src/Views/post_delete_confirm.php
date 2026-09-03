<?php

use App\Core\Csrf;
use App\Core\View;
use App\Models\Post;

/**
 * @var Post $post
 * @var string|null $error
 */
?>
<h1>投稿の削除確認</h1>

<?php if (!empty($error)): ?>
<ul class="error-list">
    <li><?= View::e($error) ?></li>
</ul>
<?php endif; ?>

<p>以下の投稿を削除します。この操作は取り消せません。</p>
<p class="notice"><?= View::e($post->title) ?></p>

<form method="post" action="/post_delete.php">
    <input type="hidden" name="csrf_token" value="<?= View::e(Csrf::token()) ?>">
    <input type="hidden" name="id" value="<?= $post->id ?>">
    <div class="form-actions">
        <a class="button button-secondary" href="/post_edit.php?id=<?= $post->id ?>">キャンセル</a>
        <button type="submit">削除する</button>
    </div>
</form>
