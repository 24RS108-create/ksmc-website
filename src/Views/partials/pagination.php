<?php

use App\Core\Paginator;
use App\Core\View;

/**
 * カード一覧共通のページ分割ナビゲーション（FR-29）。1ページあたりPaginator::PER_PAGE件。
 *
 * @var array{page: int, totalPages: int, offset: int, perPage: int, totalCount: int} $pagination
 * @var string $pageBaseUrl
 * @var array<string, mixed> $extraQuery タグ絞り込み（tag_ids）や会員ページのid等、維持したいクエリパラメータ
 */
?>
<?php if ($pagination['totalPages'] > 1): ?>
<nav class="pagination">
    <?php if ($pagination['page'] > 1): ?>
    <a href="<?= View::e(Paginator::buildUrl($pageBaseUrl, $extraQuery, $pagination['page'] - 1)) ?>">&laquo; 前へ</a>
    <?php endif; ?>

    <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
    <?php if ($p === $pagination['page']): ?>
    <span class="pagination-current"><?= $p ?></span>
    <?php else: ?>
    <a href="<?= View::e(Paginator::buildUrl($pageBaseUrl, $extraQuery, $p)) ?>"><?= $p ?></a>
    <?php endif; ?>
    <?php endfor; ?>

    <?php if ($pagination['page'] < $pagination['totalPages']): ?>
    <a href="<?= View::e(Paginator::buildUrl($pageBaseUrl, $extraQuery, $pagination['page'] + 1)) ?>">次へ &raquo;</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
