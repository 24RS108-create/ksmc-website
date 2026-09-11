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
    CONSTRAINT fk_users_invited_by FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- docs/db_schema_draft_1.md の posts/images/tags/post_tags テーブル定義に基づく（FR-04 実装分）
-- post_type は個人作品のみ（'official_blog' は FR-13/FR-14 で別途対応）

-- 会員アカウント削除時（FR-11）、投稿・画像・タグ紐付けも連鎖して削除する方針のため CASCADE とする。
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
    CONSTRAINT fk_posts_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ロゴ合成機能（FR-19〜FR-21）実装分。original_pathには合成前の元画像、display_pathには
-- ロゴ合成後の表示用画像のパスをそれぞれ保存する（元画像は上書きしない、FR-21）。
-- logo_pos_x/pos_y はロゴ中心位置（画像幅/高さに対する割合%、0-100）、logo_scale はロゴ幅の
-- 画像幅に対する割合(%)、logo_opacity は不透明度(%)。いずれもFR-19実装以降は必ず値が入るが、
-- 実装前に作成された既存行との互換性のためNULL許容のままとする。
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

-- お問い合わせ（FR-22）。訪問者が送信し、閲覧・対応管理は管理者のみ。
-- email は任意入力（管理者が本人へ返信する際の連絡先）。handled_at が NULL なら未対応。
CREATE TABLE IF NOT EXISTS inquiries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NULL,
    body TEXT NOT NULL,
    handled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inquiries_handled_at (handled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
