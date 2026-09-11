<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

/**
 * お問い合わせ（FR-22）。訪問者が送信し、閲覧・対応管理は管理者のみ行う。
 */
final class Inquiry
{
    public static function create(?string $email, string $body): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO inquiries (email, body) VALUES (:email, :body)'
        );
        $stmt->execute([
            'email' => $email !== '' ? $email : null,
            'body' => $body,
        ]);
    }

    /**
     * @return array<int, array{id: int, email: ?string, body: string, handled_at: ?string, created_at: string}>
     */
    public static function findAll(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, email, body, handled_at, created_at FROM inquiries ORDER BY created_at DESC'
        );

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'email' => $row['email'],
                'body' => $row['body'],
                'handled_at' => $row['handled_at'],
                'created_at' => $row['created_at'],
            ];
        }

        return $rows;
    }

    public static function exists(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM inquiries WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() !== false;
    }

    /**
     * 対応状態を切り替える（未対応 ⇔ 対応済み）。
     */
    public static function toggleHandled(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE inquiries
             SET handled_at = CASE WHEN handled_at IS NULL THEN NOW() ELSE NULL END
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function unhandledCount(): int
    {
        $stmt = Database::connection()->query('SELECT COUNT(*) FROM inquiries WHERE handled_at IS NULL');

        return (int) $stmt->fetchColumn();
    }
}
