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

-- docs/db_schema_draft_1.md の posts/images/tags/post_tags テーブル定義に基づく（FR-04 実装分）
-- post_type は個人作品のみ（'official_blog' は FR-13/FR-14 で別途対応）

CREATE TABLE IF NOT EXISTS posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    post_type ENUM('individual', 'official_blog') NOT NULL DEFAULT 'individual',
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_posts_user_id (user_id),
    CONSTRAINT fk_posts_user_id FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- logo_pos_x/y, logo_scale, logo_opacity はロゴ合成機能（FR-19、操作項目は要決定）が
-- 未実装のため NULL 許容とする。実装時に値の意味・NOT NULL化を再検討する。
CREATE TABLE IF NOT EXISTS images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id INT UNSIGNED NOT NULL,
    original_path VARCHAR(255) NULL,
    display_path VARCHAR(255) NOT NULL,
    logo_pos_x FLOAT NULL,
    logo_pos_y FLOAT NULL,
    logo_scale FLOAT NULL,
    logo_opacity FLOAT NULL,
    file_size_kb INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_images_post_id (post_id),
    CONSTRAINT fk_images_post_id FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    CONSTRAINT fk_post_tags_post_id FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_post_tags_tag_id FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
