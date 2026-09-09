<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

final class Image
{
    /**
     * ロゴ合成済みの画像を1件登録する（FR-19〜FR-21）。$originalPathには合成前の元画像、
     * $displayPathにはロゴ合成後の表示用画像のパスを保存する（元画像は上書きしない）。
     */
    public static function create(
        int $postId,
        string $originalPath,
        string $displayPath,
        int $fileSizeKb,
        int $sortOrder,
        float $logoPosX,
        float $logoPosY,
        float $logoScale,
        float $logoOpacity
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO images
                (post_id, original_path, display_path, file_size_kb, sort_order,
                 logo_pos_x, logo_pos_y, logo_scale, logo_opacity)
             VALUES
                (:post_id, :original_path, :display_path, :file_size_kb, :sort_order,
                 :logo_pos_x, :logo_pos_y, :logo_scale, :logo_opacity)'
        );
        $stmt->execute([
            'post_id' => $postId,
            'original_path' => $originalPath,
            'display_path' => $displayPath,
            'file_size_kb' => $fileSizeKb,
            'sort_order' => $sortOrder,
            'logo_pos_x' => $logoPosX,
            'logo_pos_y' => $logoPosY,
            'logo_scale' => $logoScale,
            'logo_opacity' => $logoOpacity,
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

    /**
     * 画像削除（投稿編集画面での既存画像削除用）のため、ファイルパスを含めて1件取得する。
     *
     * @return array{id: int, post_id: int, original_path: string, display_path: string}|null
     */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, post_id, original_path, display_path FROM images WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'post_id' => (int) $row['post_id'],
            'original_path' => $row['original_path'] ?? '',
            'display_path' => $row['display_path'],
        ];
    }

    public static function deleteById(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM images WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
