<?php

declare(strict_types=1);

namespace App\Config;

final class Uploads
{
    // 1投稿あたりの画像合計サイズ上限（FR-08）。
    public const MAX_TOTAL_BYTES = 16 * 1024 * 1024;

    // 1投稿あたりの最大枚数（要件定義書に明記はないが、乱用防止のための技術的な上限）。
    public const MAX_FILE_COUNT = 10;

    // 許可する画像形式（拡張子とMIMEタイプの対応）。
    public const ALLOWED_EXTENSIONS = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public const UPLOAD_SUBDIR = 'uploads/posts';
}
