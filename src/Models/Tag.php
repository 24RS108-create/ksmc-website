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

    /**
     * 投稿詳細表示用（FR-15）。
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function findByPostId(int $postId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.id, t.name FROM tags t
             JOIN post_tags pt ON pt.tag_id = t.id
             WHERE pt.post_id = :post_id
             ORDER BY t.name ASC'
        );
        $stmt->execute(['post_id' => $postId]);

        $tags = [];
        foreach ($stmt->fetchAll() as $row) {
            $tags[] = ['id' => (int) $row['id'], 'name' => $row['name']];
        }

        return $tags;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, name FROM tags WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : ['id' => (int) $row['id'], 'name' => $row['name']];
    }

    /**
     * タグ管理画面用（FR-17）。紐付く投稿数（下書き含む）を併せて返す。
     *
     * @return array<int, array{id: int, name: string, post_count: int}>
     */
    public static function findAllWithPostCount(): array
    {
        $stmt = Database::connection()->query(
            'SELECT t.id, t.name, COUNT(pt.post_id) AS post_count
             FROM tags t
             LEFT JOIN post_tags pt ON pt.tag_id = t.id
             GROUP BY t.id, t.name
             ORDER BY t.name ASC'
        );

        $tags = [];
        foreach ($stmt->fetchAll() as $row) {
            $tags[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'post_count' => (int) $row['post_count'],
            ];
        }

        return $tags;
    }

    /**
     * タグ別一覧ページ用（FR-18）。公開済み投稿の件数のみをカウントする。
     *
     * @return array<int, array{id: int, name: string, post_count: int}>
     */
    public static function findAllWithPublishedPostCount(): array
    {
        $stmt = Database::connection()->query(
            "SELECT t.id, t.name,
                    COUNT(DISTINCT CASE WHEN p.status = 'published' THEN p.id END) AS post_count
             FROM tags t
             LEFT JOIN post_tags pt ON pt.tag_id = t.id
             LEFT JOIN posts p ON p.id = pt.post_id
             GROUP BY t.id, t.name
             ORDER BY t.name ASC"
        );

        $tags = [];
        foreach ($stmt->fetchAll() as $row) {
            $tags[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'post_count' => (int) $row['post_count'],
            ];
        }

        return $tags;
    }

    /**
     * 表記ゆれ対策のリネーム（FR-17）。呼び出し側で重複チェック済みであること。
     */
    public static function rename(int $id, string $name): void
    {
        $stmt = Database::connection()->prepare('UPDATE tags SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM tags WHERE name = :name AND id != :exclude_id LIMIT 1'
            );
            $stmt->execute(['name' => $name, 'exclude_id' => $excludeId]);
        } else {
            $stmt = Database::connection()->prepare('SELECT 1 FROM tags WHERE name = :name LIMIT 1');
            $stmt->execute(['name' => $name]);
        }

        return $stmt->fetch() !== false;
    }

    /**
     * 表記ゆれの統合（FR-17）。$sourceIds に紐づく投稿を $targetId へ付け替えたうえで、
     * $sourceIds のタグ自体を削除する（$targetId と同じIDは無視する）。
     *
     * @param array<int, int> $sourceIds
     */
    public static function mergeInto(array $sourceIds, int $targetId): void
    {
        $connection = Database::connection();
        $reattach = $connection->prepare(
            'INSERT IGNORE INTO post_tags (post_id, tag_id) SELECT post_id, :target_id FROM post_tags WHERE tag_id = :source_id'
        );
        $detach = $connection->prepare('DELETE FROM post_tags WHERE tag_id = :source_id');
        $deleteTag = $connection->prepare('DELETE FROM tags WHERE id = :source_id');

        foreach (array_unique($sourceIds) as $sourceId) {
            if ($sourceId === $targetId) {
                continue;
            }

            $reattach->execute(['target_id' => $targetId, 'source_id' => $sourceId]);
            $detach->execute(['source_id' => $sourceId]);
            $deleteTag->execute(['source_id' => $sourceId]);
        }
    }
}
