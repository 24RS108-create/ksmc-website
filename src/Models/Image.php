<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

final class Image
{
    public static function create(int $postId, string $displayPath, int $fileSizeKb, int $sortOrder): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO images (post_id, display_path, file_size_kb, sort_order)
             VALUES (:post_id, :display_path, :file_size_kb, :sort_order)'
        );
        $stmt->execute([
            'post_id' => $postId,
            'display_path' => $displayPath,
            'file_size_kb' => $fileSizeKb,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @return array<int, array{id: int, display_path: string, sort_order: int, file_size_kb: int}>
     */
    public static function findByPostId(int $postId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, display_path, sort_order, file_size_kb FROM images WHERE post_id = :post_id ORDER BY sort_order ASC'
        );
        $stmt->execute(['post_id' => $postId]);

        $images = [];
        foreach ($stmt->fetchAll() as $row) {
            $images[] = [
                'id' => (int) $row['id'],
                'display_path' => $row['display_path'],
                'sort_order' => (int) $row['sort_order'],
                'file_size_kb' => (int) $row['file_size_kb'],
            ];
        }

        return $images;
    }

    /**
     * 次に追加する画像の表示順（既存の最大値+1）を返す。
     */
    public static function nextSortOrder(int $postId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order FROM images WHERE post_id = :post_id'
        );
        $stmt->execute(['post_id' => $postId]);

        return (int) $stmt->fetchColumn();
    }
}
