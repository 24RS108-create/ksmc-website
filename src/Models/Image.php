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
}
