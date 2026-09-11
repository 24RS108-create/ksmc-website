<?php

declare(strict_types=1);

namespace App\Core;

/**
 * カード一覧（作品ギャラリー・公式ブログ・タグ絞り込み結果・会員ページ）共通のページ分割処理。
 * 1ページあたりの件数は16件で固定とする。
 */
final class Paginator
{
    public const PER_PAGE = 16;

    /**
     * 要求されたページ番号を総件数に応じて有効な範囲（1〜最終ページ）に丸め、
     * 取得に必要なoffsetとあわせて返す。
     *
     * @return array{page: int, totalPages: int, offset: int, perPage: int, totalCount: int}
     */
    public static function resolve(int $requestedPage, int $totalCount, int $perPage = self::PER_PAGE): array
    {
        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        $page = max(1, min($requestedPage, $totalPages));

        return [
            'page' => $page,
            'totalPages' => $totalPages,
            'offset' => ($page - 1) * $perPage,
            'perPage' => $perPage,
            'totalCount' => $totalCount,
        ];
    }

    /**
     * ページ番号を含むURLを組み立てる。$extraQueryでタグ絞り込み（tag_ids[]）や
     * 会員ページのid等、維持したい他のクエリパラメータを指定する。
     *
     * @param array<string, mixed> $extraQuery
     */
    public static function buildUrl(string $base, array $extraQuery, int $page): string
    {
        $query = $extraQuery;
        $query['page'] = $page;

        return $base . '?' . http_build_query($query);
    }
}
