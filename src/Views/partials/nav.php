<?php

use App\Core\Auth;

$navUser = Auth::user();
?>
<nav class="top-nav">
    <a href="/">トップページ</a>
    <?php if ($navUser === null): ?>
    <a href="/login.php">ログイン</a>
    <?php else: ?>
    <a href="/my_posts.php">自分の投稿</a>
    <?php if ($navUser->role !== 'inactive'): ?>
    <a href="/post_create.php">投稿する</a>
    <a href="/profile_edit.php">プロフィール</a>
    <?php endif; ?>
    <a href="/password_edit.php">パスワード再設定</a>
    <?php if ($navUser->role === 'admin'): ?>
    <a href="/account_create.php">アカウント発行</a>
    <a href="/account_manage.php">アカウント管理</a>
    <?php endif; ?>
    <a href="/logout.php">ログアウト</a>
    <?php endif; ?>
</nav>
