<?php

use App\Core\View;

/**
 * リンク集（FR-23）。更新はこのファイルを編集して再デプロイする。
 *
 * @var array<int, array{label: string, url: string, note: string}> $links
 */
$links = [
    ['label' => 'X（旧Twitter）', 'url' => 'https://x.com/93_KSMC', 'note' => ''],
    ['label' => 'Instagram', 'url' => 'https://www.instagram.com/93mokei/', 'note' => ''],
    ['label' => '九州産業大学', 'url' => 'https://www.kyusan-u.ac.jp/', 'note' => ''],
];
?>
<h1>リンク</h1>

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
