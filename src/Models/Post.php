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
     * 個人の作品投稿を作成する（FR-04）。承認フローは無く即時公開する（FR-07）。
     */
    public static function createPublishedIndividual(int $userId, string $title, string $body): self
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO posts (user_id, post_type, title, body, status, published_at)
             VALUES (:user_id, \'individual\', :title, :body, \'published\', NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
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
}
