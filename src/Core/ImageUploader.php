<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Uploads;

final class ImageUploader
{
    /**
     * $_FILES['images'] 形式（multiple指定）の入力を検証する。
     *
     * @return array{files: array<int, array{name: string, tmp_name: string, size: int, extension: string}>, errors: array<int, string>}
     */
    public static function validate(array $rawFiles): array
    {
        $files = self::normalize($rawFiles);
        $errors = [];
        $validated = [];

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "画像「{$file['name']}」のアップロードに失敗しました。";
                continue;
            }

            if (!is_uploaded_file($file['tmp_name'])) {
                $errors[] = "画像「{$file['name']}」が不正なアップロードです。";
                continue;
            }

            $extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!array_key_exists($extension, Uploads::ALLOWED_EXTENSIONS)) {
                $errors[] = "画像「{$file['name']}」は対応していない形式です（jpg, png, gif, webpのみ）。";
                continue;
            }

            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false || $imageInfo['mime'] !== Uploads::ALLOWED_EXTENSIONS[$extension]) {
                $errors[] = "画像「{$file['name']}」は画像ファイルとして読み込めませんでした。";
                continue;
            }

            $validated[] = [
                'name' => $file['name'],
                'tmp_name' => $file['tmp_name'],
                'size' => $file['size'],
                'extension' => $extension,
            ];
        }

        return ['files' => $errors === [] ? $validated : [], 'errors' => $errors];
    }

    /**
     * 新規アップロード分と既存分を合わせた枚数・合計サイズが上限内かを確認する。
     * 投稿作成時は $existingCount / $existingBytes は 0 のままでよい。
     *
     * @param array<int, array{name: string, tmp_name: string, size: int, extension: string}> $validatedFiles
     * @return array<int, string>
     */
    public static function checkAggregateLimits(array $validatedFiles, int $existingCount = 0, int $existingBytes = 0): array
    {
        $errors = [];

        $newCount = count($validatedFiles);
        $newBytes = array_sum(array_column($validatedFiles, 'size'));

        if ($existingCount + $newCount > Uploads::MAX_FILE_COUNT) {
            $errors[] = '画像は' . Uploads::MAX_FILE_COUNT . '枚以内にしてください。';
        }

        if ($existingBytes + $newBytes > Uploads::MAX_TOTAL_BYTES) {
            $limitMb = (int) (Uploads::MAX_TOTAL_BYTES / 1024 / 1024);
            $errors[] = "画像の合計サイズが上限（{$limitMb}MB）を超えています。";
        }

        return $errors;
    }

    /**
     * 検証済みファイルを保存先に移動し、ロゴを合成した表示用画像を生成する（FR-19〜FR-21）。
     * 元画像（original_path）は上書きせず保存し、ロゴ合成後の画像を別ファイル（display_path）として保存する。
     * 合成は投稿の保存操作時に同期実行する（FR-20）。
     *
     * @param array<int, array{name: string, tmp_name: string, size: int, extension: string}> $validatedFiles
     * @param array<int, array{pos_x: float, pos_y: float, scale: float, opacity: float}> $logoSettings
     *     $validatedFilesと同じ順序・同じ件数であること。
     * @return array<int, array{original_path: string, display_path: string, file_size_kb: int,
     *     logo_pos_x: float, logo_pos_y: float, logo_scale: float, logo_opacity: float}>
     */
    public static function store(array $validatedFiles, int $postId, array $logoSettings): array
    {
        $targetDir = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, Uploads::UPLOAD_SUBDIR)
            . DIRECTORY_SEPARATOR . $postId;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('画像保存用ディレクトリの作成に失敗しました。');
        }

        $stored = [];
        foreach ($validatedFiles as $index => $file) {
            $settings = $logoSettings[$index] ?? [
                'pos_x' => Uploads::LOGO_DEFAULT_POS_X,
                'pos_y' => Uploads::LOGO_DEFAULT_POS_Y,
                'scale' => Uploads::LOGO_DEFAULT_SCALE,
                'opacity' => Uploads::LOGO_DEFAULT_OPACITY,
            ];

            $baseName = bin2hex(random_bytes(16));
            $originalFilename = 'original_' . $baseName . '.' . $file['extension'];
            $displayFilename = 'display_' . $baseName . '.' . $file['extension'];
            $originalTargetPath = $targetDir . DIRECTORY_SEPARATOR . $originalFilename;
            $displayTargetPath = $targetDir . DIRECTORY_SEPARATOR . $displayFilename;

            if (!move_uploaded_file($file['tmp_name'], $originalTargetPath)) {
                throw new \RuntimeException("画像「{$file['name']}」の保存に失敗しました。");
            }

            try {
                LogoCompositor::composite(
                    $originalTargetPath,
                    $displayTargetPath,
                    $settings['pos_x'],
                    $settings['pos_y'],
                    $settings['scale'],
                    $settings['opacity']
                );
            } catch (\Throwable $e) {
                @unlink($originalTargetPath);
                throw new \RuntimeException("画像「{$file['name']}」へのロゴ合成に失敗しました。", 0, $e);
            }

            $stored[] = [
                'original_path' => '/' . Uploads::UPLOAD_SUBDIR . '/' . $postId . '/' . $originalFilename,
                'display_path' => '/' . Uploads::UPLOAD_SUBDIR . '/' . $postId . '/' . $displayFilename,
                'file_size_kb' => (int) ceil($file['size'] / 1024),
                'logo_pos_x' => $settings['pos_x'],
                'logo_pos_y' => $settings['pos_y'],
                'logo_scale' => $settings['scale'],
                'logo_opacity' => $settings['opacity'],
            ];
        }

        return $stored;
    }

    /**
     * ロゴ調整フォーム（FR-19、JSにより画像ごとに動的生成）から送信された位置・サイズ・透過度の
     * 配列を、アップロードファイルと同じ並び順で$count件に揃えて返す。JS無効等で未送信の場合や
     * 数値として不正な場合は既定値を使う。値は許容範囲にクランプする。
     *
     * @param array<int, mixed> $posX
     * @param array<int, mixed> $posY
     * @param array<int, mixed> $scale
     * @param array<int, mixed> $opacity
     * @return array<int, array{pos_x: float, pos_y: float, scale: float, opacity: float}>
     */
    public static function parseLogoSettings(array $posX, array $posY, array $scale, array $opacity, int $count): array
    {
        $settings = [];

        for ($i = 0; $i < $count; $i++) {
            $settings[] = [
                'pos_x' => self::clampPercent($posX[$i] ?? null, Uploads::LOGO_DEFAULT_POS_X),
                'pos_y' => self::clampPercent($posY[$i] ?? null, Uploads::LOGO_DEFAULT_POS_Y),
                'scale' => self::clampScale($scale[$i] ?? null),
                'opacity' => self::clampPercent($opacity[$i] ?? null, Uploads::LOGO_DEFAULT_OPACITY),
            ];
        }

        return $settings;
    }

    private static function clampPercent(mixed $value, float $default): float
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max(0.0, min(100.0, (float) $value));
    }

    private static function clampScale(mixed $value): float
    {
        if (!is_numeric($value)) {
            return Uploads::LOGO_DEFAULT_SCALE;
        }

        return max(Uploads::LOGO_MIN_SCALE, min(Uploads::LOGO_MAX_SCALE, (float) $value));
    }

    /**
     * 投稿削除時に、保存済み画像ファイル一式をディスクから削除する。
     */
    public static function deletePostDirectory(int $postId): void
    {
        $targetDir = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, Uploads::UPLOAD_SUBDIR)
            . DIRECTORY_SEPARATOR . $postId;

        if (!is_dir($targetDir)) {
            return;
        }

        foreach (glob($targetDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($targetDir);
    }

    /**
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private static function normalize(array $rawFiles): array
    {
        if (!isset($rawFiles['name']) || !is_array($rawFiles['name'])) {
            return [];
        }

        $normalized = [];
        $count = count($rawFiles['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($rawFiles['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $normalized[] = [
                'name' => $rawFiles['name'][$i],
                'type' => $rawFiles['type'][$i],
                'tmp_name' => $rawFiles['tmp_name'][$i],
                'error' => $rawFiles['error'][$i],
                'size' => $rawFiles['size'][$i],
            ];
        }

        return $normalized;
    }
}
