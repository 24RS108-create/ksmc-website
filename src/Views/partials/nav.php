<?php

use App\Core\Auth;

$navUser = Auth::user();
?>
<aside class="sidebar">
    <a href="/" class="sidebar-logo">
        <img src="/assets/images/ksmc-logo-white.png" alt="KSMC KSU MODEL CLUB">
    </a>
    <nav class="sidebar-nav">
        <a href="/">トップ</a>
        <a href="/about.php">サークルについて</a>
        <a href="/gallery.php">模型ギャラリー</a>
        <a href="/blog.php">公式ブログ</a>
        <a href="/tags.php">タグから探す</a>
        <a href="/contact.php">お問い合わせ</a>
        <a href="/links.php">リンク</a>
        <a href="/tos.php">利用規約</a>
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
        <a href="/tag_manage.php">タグ管理</a>
        <a href="/contact_manage.php">お問い合わせ管理</a>
        <?php endif; ?>
        <a href="/logout.php">ログアウト</a>
        <?php endif; ?>
    </nav>
</aside>
