<?php

declare(strict_types=1);

namespace App\Core;

/**
 * 投稿本文中の画像プレースホルダー（[image:1] 等）を実画像へ差し込んで表示する（ブログ形式表示）。
 *
 * 本文はこれまで通りプレーンテキスト（posts.body）のまま変更しない。画像は表示順（sort_order昇順）
 * に1から番号を振り、投稿者が本文中に手入力した [image:N] トークンをその番号の画像に置き換える。
 * プレースホルダーを1つも含まない本文は従来通り「画像を上部にまとめて表示→本文」というレイアウトに
 * フォールバックする（既存投稿との後方互換）。プレースホルダーを使っていても参照されなかった画像が
 * あれば、本文の後ろにまとめて表示し、アップロードした画像が失われないようにする。
 */
final class PostBodyRenderer
{
    private const TOKEN_PATTERN = '/\[image:(\d+)\]/';

    public static function hasPlaceholders(string $body): bool
    {
        return preg_match(self::TOKEN_PATTERN, $body) === 1;
    }

    /**
     * @param array<int, array{display_path: string}> $images 表示順（sort_order昇順）に並んだ画像配列
     * @return array{
     *     usesPlaceholders: bool,
     *     html: string,
     *     leadingImages: array<int, array{display_path: string}>,
     *     trailingImages: array<int, array{display_path: string}>,
     *     invalidTokens: array<int, int>
     * }
     */
    public static function build(string $body, array $images): array
    {
        $usesPlaceholders = self::hasPlaceholders($body);

        if (!$usesPlaceholders) {
            return [
                'usesPlaceholders' => false,
                'html' => View::e($body),
                'leadingImages' => $images,
                'trailingImages' => [],
                'invalidTokens' => [],
            ];
        }

        $imageCount = count($images);
        $usedIndexes = [];
        $invalidTokens = [];

        $html = preg_replace_callback(
            self::TOKEN_PATTERN,
            static function (array $matches) use ($images, $imageCount, &$usedIndexes, &$invalidTokens): string {
                $number = (int) $matches[1];

                if ($number < 1 || $number > $imageCount) {
                    $invalidTokens[] = $number;
                    return View::e($matches[0]);
                }

                $usedIndexes[$number - 1] = true;

                return '<img class="post-image post-inline-image" src="' . View::e($images[$number - 1]['display_path']) . '" alt="">';
            },
            View::e($body)
        ) ?? View::e($body);

        $trailingImages = [];
        foreach ($images as $index => $image) {
            if (!isset($usedIndexes[$index])) {
                $trailingImages[] = $image;
            }
        }

        return [
            'usesPlaceholders' => true,
            'html' => $html,
            'leadingImages' => [],
            'trailingImages' => $trailingImages,
            'invalidTokens' => array_values(array_unique($invalidTokens)),
        ];
    }
}
