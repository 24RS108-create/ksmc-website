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

        if (count($files) > Uploads::MAX_FILE_COUNT) {
            $errors[] = '画像は' . Uploads::MAX_FILE_COUNT . '枚以内にしてください。';
        }

        $validated = [];
        $totalBytes = 0;

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

            $totalBytes += $file['size'];

            $validated[] = [
                'name' => $file['name'],
                'tmp_name' => $file['tmp_name'],
                'size' => $file['size'],
                'extension' => $extension,
            ];
        }

        if ($totalBytes > Uploads::MAX_TOTAL_BYTES) {
            $limitMb = (int) (Uploads::MAX_TOTAL_BYTES / 1024 / 1024);
            $errors[] = "画像の合計サイズが上限（{$limitMb}MB）を超えています。";
        }

        return ['files' => $errors === [] ? $validated : [], 'errors' => $errors];
    }

    /**
     * 検証済みファイルを保存先に移動する。
     *
     * @param array<int, array{name: string, tmp_name: string, size: int, extension: string}> $validatedFiles
     * @return array<int, array{display_path: string, file_size_kb: int}>
     */
    public static function store(array $validatedFiles, int $postId): array
    {
        $targetDir = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, Uploads::UPLOAD_SUBDIR)
            . DIRECTORY_SEPARATOR . $postId;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('画像保存用ディレクトリの作成に失敗しました。');
        }

        $stored = [];
        foreach ($validatedFiles as $file) {
            $filename = bin2hex(random_bytes(16)) . '.' . $file['extension'];
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new \RuntimeException("画像「{$file['name']}」の保存に失敗しました。");
            }

            $stored[] = [
                'display_path' => '/' . Uploads::UPLOAD_SUBDIR . '/' . $postId . '/' . $filename,
                'file_size_kb' => (int) ceil($file['size'] / 1024),
            ];
        }

        return $stored;
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
