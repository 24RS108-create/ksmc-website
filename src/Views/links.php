<?php

use App\Core\View;

/**
 * リンク集（FR-23）。内容はダミー。更新はこのファイルを編集して再デプロイする。
 *
 * @var array<int, array{label: string, url: string, note: string}> $links
 */
$links = [
    ['label' => 'X（旧Twitter）', 'url' => 'https://x.com/', 'note' => '※ダミーリンク（本番のアカウントURLに差し替え予定）'],
    ['label' => 'Instagram', 'url' => 'https://www.instagram.com/', 'note' => '※ダミーリンク（本番のアカウントURLに差し替え予定）'],
    ['label' => '九州産業大学', 'url' => 'https://www.kyusan-u.ac.jp/', 'note' => ''],
];
?>
<h1>リンク</h1>

<p class="hint">現在掲載しているリンクはダミーです。正式なSNSアカウント等のURLが決まり次第、差し替えます。</p>

<ul class="link-list">
    <?php foreach ($links as $link): ?>
    <li>
        <a href="<?= View::e($link['url']) ?>" target="_blank" rel="noopener noreferrer external"><?= View::e($link['label']) ?></a>
        <?php if ($link['note'] !== ''): ?>
        <span class="hint"><?= View::e($link['note']) ?></span>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
