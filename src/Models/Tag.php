<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

final class Tag
{
    /**
     * 既存タグを流用し、無ければ新規作成してIDを返す（FR-16）。
     */
    public static function findOrCreateByName(string $name): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO tags (name) VALUES (:name)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $stmt->execute(['name' => $name]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public static function findAll(): array
    {
        $stmt = Database::connection()->query('SELECT id, name FROM tags ORDER BY name ASC');

        $tags = [];
        foreach ($stmt->fetchAll() as $row) {
            $tags[] = ['id' => (int) $row['id'], 'name' => $row['name']];
        }

        return $tags;
    }

    /**
     * @param array<int, int> $tagIds
     */
    public static function attachToPost(int $postId, array $tagIds): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)'
        );

        foreach (array_unique($tagIds) as $tagId) {
            $stmt->execute(['post_id' => $postId, 'tag_id' => $tagId]);
        }
    }

    public static function detachAllFromPost(int $postId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM post_tags WHERE post_id = :post_id');
        $stmt->execute(['post_id' => $postId]);
    }

    /**
     * @return array<int, int>
     */
    public static function findIdsByPostId(int $postId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tag_id FROM post_tags WHERE post_id = :post_id'
        );
        $stmt->execute(['post_id' => $postId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
