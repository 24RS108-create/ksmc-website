<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

final class Post
{
    public int $id;
    public int $userId;
    public string $postType;
    public string $title;
    public string $body;
    public string $status;
    public ?string $publishedAt;

    private function __construct(array $row)
    {
        $this->id = (int) $row['id'];
        $this->userId = (int) $row['user_id'];
        $this->postType = $row['post_type'];
        $this->title = $row['title'];
        $this->body = $row['body'];
        $this->status = $row['status'];
        $this->publishedAt = $row['published_at'];
    }

    /**
     * 投稿を作成する（FR-04, FR-13, FR-14）。$postType は 'individual' か 'official_blog'、
     * $status は 'draft' か 'published' のみを受け付ける。公式ブログ投稿権限のチェックは
     * 呼び出し側（コントローラー）の責務とする。
     * 公開時は承認フローを介さず即時公開する（FR-05, FR-07）。
     */
    public static function create(int $userId, string $postType, string $title, string $body, string $status): self
    {
        if (!in_array($postType, ['individual', 'official_blog'], true)) {
            throw new \InvalidArgumentException('不正な投稿種別です。');
        }
        if (!in_array($status, ['draft', 'published'], true)) {
            throw new \InvalidArgumentException('不正な投稿状態です。');
        }

        $publishedAtExpr = $status === 'published' ? 'NOW()' : 'NULL';

        $stmt = Database::connection()->prepare(
            "INSERT INTO posts (user_id, post_type, title, body, status, published_at)
             VALUES (:user_id, :post_type, :title, :body, :status, {$publishedAtExpr})"
        );
        $stmt->execute([
            'user_id' => $userId,
            'post_type' => $postType,
            'title' => $title,
            'body' => $body,
            'status' => $status,
        ]);

        $id = (int) Database::connection()->lastInsertId();

        $post = self::findById($id);
        if ($post === null) {
            throw new \RuntimeException('作成した投稿の取得に失敗しました。');
        }

        return $post;
    }

    public static function findById(int $id): ?self
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM posts WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : new self($row);
    }

    /**
     * @return array<int, self>
     */
    public static function findByUserId(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM posts WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        $posts = [];
        foreach ($stmt->fetchAll() as $row) {
            $posts[] = new self($row);
        }

        return $posts;
    }

    /**
     * 投稿の編集（FR-06）。$status は 'draft' か 'published' のみを受け付ける。
     */
    public function update(string $title, string $body, string $status): void
    {
        if (!in_array($status, ['draft', 'published'], true)) {
            throw new \InvalidArgumentException('不正な投稿状態です。');
        }

        // 下書き→公開に変わる時だけ公開日時を設定する。既に公開済みなら日時は保持する。
        if ($status === 'published' && $this->publishedAt === null) {
            $publishedAtExpr = 'NOW()';
        } elseif ($status === 'draft') {
            $publishedAtExpr = 'NULL';
        } else {
            $publishedAtExpr = 'published_at';
        }

        $stmt = Database::connection()->prepare(
            "UPDATE posts
             SET title = :title, body = :body, status = :status, published_at = {$publishedAtExpr}
             WHERE id = :id"
        );
        $stmt->execute([
            'title' => $title,
            'body' => $body,
            'status' => $status,
            'id' => $this->id,
        ]);

        $refreshed = self::findById($this->id);
        if ($refreshed !== null) {
            $this->title = $refreshed->title;
            $this->body = $refreshed->body;
            $this->status = $refreshed->status;
            $this->publishedAt = $refreshed->publishedAt;
        }
    }

    /**
     * トップページ・一覧ページ表示用（FR-15）。公開済みの投稿のみを種別ごとに取得する。
     * $offsetはページ分割（FR-29）用。$limitを指定しない場合は無視される。
     *
     * @return array<int, self>
     */
    public static function findPublishedByType(string $postType, ?int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT * FROM posts WHERE post_type = :post_type AND status = 'published'
                ORDER BY published_at DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['post_type' => $postType]);

        $posts = [];
        foreach ($stmt->fetchAll() as $row) {
            $posts[] = new self($row);
        }

        return $posts;
    }

    /**
     * ページ分割（FR-29）用の総件数取得。findPublishedByType()と対になる。
     */
    public static function countPublishedByType(string $postType): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM posts WHERE post_type = :post_type AND status = 'published'"
        );
        $stmt->execute(['post_type' => $postType]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * タグ別一覧・複数タグ絞り込み用（FR-18）。選択された全タグを持つ公開済み投稿のみを返す
     * （AND条件）。個人作品・公式ブログの区別なく対象とする。
     * $offsetはページ分割（FR-29）用。$limitを指定しない場合は無視される。
     *
     * @param array<int, int> $tagIds
     * @return array<int, self>
     */
    public static function findPublishedByTagIds(array $tagIds, ?int $limit = null, int $offset = 0): array
    {
        if (empty($tagIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $sql = "SELECT p.* FROM posts p
                JOIN post_tags pt ON pt.post_id = p.id
                WHERE p.status = 'published' AND pt.tag_id IN ({$placeholders})
                GROUP BY p.id
                HAVING COUNT(DISTINCT pt.tag_id) = ?
                ORDER BY p.published_at DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([...$tagIds, count($tagIds)]);

        $posts = [];
        foreach ($stmt->fetchAll() as $row) {
            $posts[] = new self($row);
        }

        return $posts;
    }

    /**
     * ページ分割（FR-29）用の総件数取得。findPublishedByTagIds()と対になる。
     *
     * @param array<int, int> $tagIds
     */
    public static function countPublishedByTagIds(array $tagIds): int
    {
        if (empty($tagIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $sql = "SELECT COUNT(*) FROM (
                    SELECT p.id FROM posts p
                    JOIN post_tags pt ON pt.post_id = p.id
                    WHERE p.status = 'published' AND pt.tag_id IN ({$placeholders})
                    GROUP BY p.id
                    HAVING COUNT(DISTINCT pt.tag_id) = ?
                ) AS matched";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([...$tagIds, count($tagIds)]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * 会員ページ（FR-28）表示用。指定した会員の公開済み投稿のみを取得する（下書きは表示しない）。
     * $offsetはページ分割（FR-29）用。$limitを指定しない場合は無視される。
     *
     * @return array<int, self>
     */
    public static function findPublishedByUserId(int $userId, ?int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT * FROM posts WHERE user_id = :user_id AND status = 'published'
                ORDER BY published_at DESC";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        $posts = [];
        foreach ($stmt->fetchAll() as $row) {
            $posts[] = new self($row);
        }

        return $posts;
    }

    /**
     * ページ分割（FR-29）用の総件数取得。findPublishedByUserId()と対になる。
     */
    public static function countPublishedByUserId(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM posts WHERE user_id = :user_id AND status = 'published'"
        );
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * 公開済みの投稿を1件取得する（未公開の投稿は訪問者に見せない、FR-15）。
     */
    public static function findPublishedById(int $id): ?self
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM posts WHERE id = :id AND status = 'published' LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : new self($row);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM posts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
