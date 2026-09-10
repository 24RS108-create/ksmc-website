<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

/**
 * 内容が固定的で、管理者による頻繁な更新を想定しないページ（FR-23 リンク集 / FR-24 利用規約）。
 * 更新はビューファイルを編集して再デプロイする運用とする（管理者フォームは設けない）。
 * 現段階の掲載内容はいずれもダミー。
 */
final class PageController
{
    public function showLinks(): void
    {
        View::render('links', [
            'title' => 'リンク',
        ]);
    }

    public function showTerms(): void
    {
        View::render('tos', [
            'title' => '利用規約',
        ]);
    }
}
