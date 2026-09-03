<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

final class User
{
    public int $id;
    public string $loginId;
    public string $passwordHash;
    public bool $mustChangePassword;
    public string $displayName;
    public ?string $profileNote;
    public string $role;
    public ?int $invitedBy;

    private function __construct(array $row)
    {
        $this->id = (int) $row['id'];
        $this->loginId = $row['login_id'];
        $this->passwordHash = $row['password_hash'];
        $this->mustChangePassword = (bool) $row['must_change_password'];
        $this->displayName = $row['display_name'];
        $this->profileNote = $row['profile_note'];
        $this->role = $row['role'];
        $this->invitedBy = $row['invited_by'] !== null ? (int) $row['invited_by'] : null;
    }

    public static function findByLoginId(string $loginId): ?self
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE login_id = :login_id LIMIT 1'
        );
        $stmt->execute(['login_id' => $loginId]);
        $row = $stmt->fetch();

        return $row === false ? null : new self($row);
    }

    public static function findById(int $id): ?self
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : new self($row);
    }

    public static function loginIdExists(string $loginId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM users WHERE login_id = :login_id LIMIT 1'
        );
        $stmt->execute(['login_id' => $loginId]);

        return $stmt->fetch() !== false;
    }

    /**
     * 管理者による会員招待（FR-01）。初期パスワードは管理者が指定し、
     * 初回ログイン時の変更を強制する。
     */
    public static function createInvited(
        string $loginId,
        string $initialPassword,
        string $displayName,
        int $invitedBy
    ): self {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (login_id, password_hash, must_change_password, display_name, role, invited_by)
             VALUES (:login_id, :password_hash, 1, :display_name, \'member\', :invited_by)'
        );
        $stmt->execute([
            'login_id' => $loginId,
            'password_hash' => password_hash($initialPassword, PASSWORD_BCRYPT),
            'display_name' => $displayName,
            'invited_by' => $invitedBy,
        ]);

        $id = (int) Database::connection()->lastInsertId();

        $user = self::findById($id);
        if ($user === null) {
            throw new \RuntimeException('作成した会員の取得に失敗しました。');
        }

        return $user;
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function updatePassword(string $newPlainPassword, bool $mustChangePassword = false): void
    {
        $hash = password_hash($newPlainPassword, PASSWORD_BCRYPT);

        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET password_hash = :password_hash, must_change_password = :must_change_password
             WHERE id = :id'
        );
        $stmt->execute([
            'password_hash' => $hash,
            'must_change_password' => $mustChangePassword ? 1 : 0,
            'id' => $this->id,
        ]);

        $this->passwordHash = $hash;
        $this->mustChangePassword = $mustChangePassword;
    }

    public function updateProfile(string $displayName, ?string $profileNote): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET display_name = :display_name, profile_note = :profile_note
             WHERE id = :id'
        );
        $stmt->execute([
            'display_name' => $displayName,
            'profile_note' => $profileNote,
            'id' => $this->id,
        ]);

        $this->displayName = $displayName;
        $this->profileNote = $profileNote;
    }
}
