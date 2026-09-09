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

    // ロゴ合成機能（FR-19）。九産模型愛好会のエンブレムを固定のロゴ素材として使用する。
    public const LOGO_ASSET_PATH = 'assets/images/ksmc-emblem.png';

    // 位置（画像内でのロゴ中心のX/Y、画像幅・高さに対する割合%）、サイズ（ロゴ幅の画像幅に対する割合%）、
    // 透過度（%、0=完全透明、100=不透明）の既定値。JS無効時など未指定の場合に使用する。
    public const LOGO_DEFAULT_POS_X = 90.0;
    public const LOGO_DEFAULT_POS_Y = 90.0;
    public const LOGO_DEFAULT_SCALE = 15.0;
    public const LOGO_DEFAULT_OPACITY = 70.0;

    public const LOGO_MIN_SCALE = 1.0;
    public const LOGO_MAX_SCALE = 50.0;
}
