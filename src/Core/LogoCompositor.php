<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Uploads;

/**
 * アップロード画像へのロゴ合成（FR-19）。GD拡張のみを用いる（標準機能、Imagick等の追加拡張は使用しない）。
 * 合成は投稿の保存操作時に同期実行する（FR-20）。元画像はこのクラスでは変更せず、
 * 呼び出し側が別ファイルとして保存する（FR-21）。
 */
final class LogoCompositor
{
    /**
     * $sourcePath の画像にロゴを合成し、$destPath へ保存する。
     *
     * @param float $posXPercent ロゴ中心のX位置（画像幅に対する割合、0-100）
     * @param float $posYPercent ロゴ中心のY位置（画像高さに対する割合、0-100）
     * @param float $scalePercent ロゴ幅の画像幅に対する割合（%）
     * @param float $opacityPercent ロゴの不透明度（%、0=完全透明、100=不透明）
     */
    public static function composite(
        string $sourcePath,
        string $destPath,
        float $posXPercent,
        float $posYPercent,
        float $scalePercent,
        float $opacityPercent
    ): void {
        $logoPath = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, Uploads::LOGO_ASSET_PATH);

        $source = self::loadImage($sourcePath);
        $logo = self::loadImage($logoPath);

        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);

        $resizedLogo = self::resizeLogo($logo, $srcWidth, $scalePercent);
        self::applyOpacity($resizedLogo, $opacityPercent);

        $logoWidth = imagesx($resizedLogo);
        $logoHeight = imagesy($resizedLogo);
        $destX = (int) round($srcWidth * $posXPercent / 100) - intdiv($logoWidth, 2);
        $destY = (int) round($srcHeight * $posYPercent / 100) - intdiv($logoHeight, 2);

        imagesavealpha($source, true);
        imagealphablending($source, false);
        self::blendOver($source, $resizedLogo, $destX, $destY);

        self::saveImage($source, $destPath);

        imagedestroy($source);
        imagedestroy($logo);
        imagedestroy($resizedLogo);
    }

    private static function loadImage(string $path): \GdImage
    {
        $info = @getimagesize($path);
        if ($info === false) {
            throw new \RuntimeException("画像を読み込めませんでした: {$path}");
        }

        $image = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/gif' => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default => throw new \RuntimeException("未対応の画像形式です: {$info['mime']}"),
        };

        if ($image === false) {
            throw new \RuntimeException("画像の読み込みに失敗しました: {$path}");
        }

        return $image;
    }

    /**
     * ロゴを、元画像の横幅に対する指定割合の幅になるよう縦横比を保って縮小する。
     * アルファチャンネルを保持した状態でリサイズする。
     */
    private static function resizeLogo(\GdImage $logo, int $srcWidth, float $scalePercent): \GdImage
    {
        $scalePercent = max(Uploads::LOGO_MIN_SCALE, min(Uploads::LOGO_MAX_SCALE, $scalePercent));

        $logoWidth0 = imagesx($logo);
        $logoHeight0 = imagesy($logo);

        $targetWidth = max(1, (int) round($srcWidth * $scalePercent / 100));
        $targetHeight = max(1, (int) round($logoHeight0 * ($targetWidth / $logoWidth0)));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);

        imagecopyresampled($resized, $logo, 0, 0, 0, 0, $targetWidth, $targetHeight, $logoWidth0, $logoHeight0);

        return $resized;
    }

    /**
     * ロゴ画像（アルファチャンネル付き）の各ピクセルの透過度に、指定した不透明度（%）を掛け合わせる。
     * GDのimagecopymergeはアルファチャンネル付きPNGを正しく扱えないため、ピクセル単位で調整する。
     */
    private static function applyOpacity(\GdImage $logo, float $opacityPercent): void
    {
        $opacityPercent = max(0.0, min(100.0, $opacityPercent));
        $factor = $opacityPercent / 100;

        $width = imagesx($logo);
        $height = imagesy($logo);

        imagealphablending($logo, false);
        imagesavealpha($logo, true);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($logo, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                // GDのアルファは0(不透明)〜127(完全透明)。不透明度factorを掛けて透明側へ寄せる。
                $newAlpha = (int) round(127 - (127 - $alpha) * $factor);
                $newAlpha = max(0, min(127, $newAlpha));

                if ($newAlpha === $alpha) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $color = imagecolorallocatealpha($logo, $r, $g, $b, $newAlpha);
                imagesetpixel($logo, $x, $y, $color);
            }
        }
    }

    /**
     * ロゴ（$logo）を元画像（$base）へ、両者のアルファ値を正しく考慮した「over」合成で描画する。
     * GDのimagecopy()はimagealphablending(true)を指定しても合成先（$base）側のアルファ値を
     * ブレンド計算に正しく使わず、特に元画像の透明部分にロゴが重なる箇所で
     *   - 透過度（オパシティ）の設定が反映されない
     *   - 元画像の透明部分がロゴ合成後に不透明になってしまう
     * という不具合を起こすため、ピクセル単位で手動合成する。
     */
    private static function blendOver(\GdImage $base, \GdImage $logo, int $offsetX, int $offsetY): void
    {
        $baseWidth = imagesx($base);
        $baseHeight = imagesy($base);
        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);

        for ($ly = 0; $ly < $logoHeight; $ly++) {
            $by = $offsetY + $ly;
            if ($by < 0 || $by >= $baseHeight) {
                continue;
            }

            for ($lx = 0; $lx < $logoWidth; $lx++) {
                $bx = $offsetX + $lx;
                if ($bx < 0 || $bx >= $baseWidth) {
                    continue;
                }

                $srcRgba = imagecolorat($logo, $lx, $ly);
                $srcAlphaGd = ($srcRgba >> 24) & 0x7F;
                if ($srcAlphaGd === 127) {
                    // ロゴ側が完全透明な部分は何もしない（元画像のピクセルをそのまま残す）。
                    continue;
                }

                $dstRgba = imagecolorat($base, $bx, $by);
                $dstAlphaGd = ($dstRgba >> 24) & 0x7F;

                // GDのアルファ（0=不透明・127=透明）を、通常の不透明度（0=透明・1=不透明）に変換する。
                $srcA = (127 - $srcAlphaGd) / 127;
                $dstA = (127 - $dstAlphaGd) / 127;
                $outA = $srcA + $dstA * (1 - $srcA);

                if ($outA <= 0.0) {
                    $color = imagecolorallocatealpha($base, 0, 0, 0, 127);
                    imagesetpixel($base, $bx, $by, $color);
                    continue;
                }

                $srcR = ($srcRgba >> 16) & 0xFF;
                $srcG = ($srcRgba >> 8) & 0xFF;
                $srcB = $srcRgba & 0xFF;
                $dstR = ($dstRgba >> 16) & 0xFF;
                $dstG = ($dstRgba >> 8) & 0xFF;
                $dstB = $dstRgba & 0xFF;

                // 標準的な「over」合成式（Porter-Duff）。出力アルファで正規化する。
                $outR = ($srcR * $srcA + $dstR * $dstA * (1 - $srcA)) / $outA;
                $outG = ($srcG * $srcA + $dstG * $dstA * (1 - $srcA)) / $outA;
                $outB = ($srcB * $srcA + $dstB * $dstA * (1 - $srcA)) / $outA;
                $outAlphaGd = max(0, min(127, (int) round(127 * (1 - $outA))));

                $color = imagecolorallocatealpha(
                    $base,
                    max(0, min(255, (int) round($outR))),
                    max(0, min(255, (int) round($outG))),
                    max(0, min(255, (int) round($outB))),
                    $outAlphaGd
                );
                imagesetpixel($base, $bx, $by, $color);
            }
        }
    }

    private static function saveImage(\GdImage $image, string $path): void
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        $result = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $path, 85),
            'png' => imagepng($image, $path),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path, 85),
            default => throw new \RuntimeException("未対応の保存形式です: {$extension}"),
        };

        if ($result === false) {
            throw new \RuntimeException("画像の保存に失敗しました: {$path}");
        }
    }
}
