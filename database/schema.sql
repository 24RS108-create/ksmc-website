-- docs/db_schema_draft_1.md の users テーブル定義に基づく（FR-01〜FR-03 実装分）

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    login_id VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    display_name VARCHAR(100) NOT NULL,
    profile_note TEXT NULL,
    role ENUM('member', 'pr', 'admin', 'inactive') NOT NULL DEFAULT 'member',
    invited_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_users_login_id (login_id),
    CONSTRAINT fk_users_invited_by FOREIGN KEY (invited_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
