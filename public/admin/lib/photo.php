<?php
/*
 * iPhone から送られた写真を、サイトに載せられる形に整えます。
 *
 *  - 向きを直す（iPhone は横向き情報を写真の中に持たせるため、そのままだと倒れて見える）
 *  - 大きすぎる写真を縮める
 *  - JPEG に変換し直す（このとき撮影場所などの情報も落ちます）
 *
 * HEIC（iPhone の標準形式）は、Safari から送るときに JPEG へ変換されることがほとんどですが、
 * そのまま届いた場合は Imagick があれば変換し、無ければ分かる言葉で断ります。
 */
declare(strict_types=1);

const PHOTO_MAX_LONG_EDGE = 1600;
const PHOTO_THUMB_LONG_EDGE = 600;
const PHOTO_MAX_UPLOAD_BYTES = 25 * 1024 * 1024;

class PhotoError extends RuntimeException {}

/** 写真を読み込んで GD の画像にする。 */
function photo_load(string $tmpPath, string $originalName): \GdImage
{
    $bytes = file_get_contents($tmpPath);
    if ($bytes === false || $bytes === '') {
        throw new PhotoError('写真を読み取れませんでした。');
    }

    $img = @imagecreatefromstring($bytes);
    if ($img instanceof \GdImage) {
        return photo_fix_orientation($img, $tmpPath);
    }

    // HEIC など GD が扱えない形式は Imagick に任せる
    if (class_exists('Imagick')) {
        try {
            $im = new \Imagick();
            $im->readImageBlob($bytes);
            $im->setImageFormat('jpeg');
            $im->autoOrient();
            $converted = @imagecreatefromstring($im->getImageBlob());
            $im->clear();
            if ($converted instanceof \GdImage) {
                return $converted;
            }
        } catch (\Throwable) {
            // 下のメッセージに流す
        }
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'heic' || $ext === 'heif') {
        throw new PhotoError(
            'iPhone の写真の形式（HEIC）のままでは取り込めませんでした。'
            . 'iPhone の「設定 → カメラ → フォーマット」を「互換性優先」に変えると、'
            . 'これから撮る写真は取り込めるようになります。'
            . '今ある写真は、写真アプリで一度編集して保存し直すと送れることがあります。'
        );
    }
    throw new PhotoError('この形式の写真は取り込めません。JPEG か PNG でお願いします。');
}

/** 撮影時の向きに合わせて回す。 */
function photo_fix_orientation(\GdImage $img, string $path): \GdImage
{
    if (!function_exists('exif_read_data')) {
        return $img;
    }
    $exif = @exif_read_data($path);
    $orientation = (int)($exif['Orientation'] ?? 0);
    return match ($orientation) {
        3 => imagerotate($img, 180, 0) ?: $img,
        6 => imagerotate($img, -90, 0) ?: $img,
        8 => imagerotate($img, 90, 0) ?: $img,
        default => $img,
    };
}

/** 長い辺が $max に収まるように縮める（元より大きくはしない）。 */
function photo_resize(\GdImage $img, int $max): \GdImage
{
    $w = imagesx($img);
    $h = imagesy($img);
    $long = max($w, $h);
    if ($long <= $max) {
        return $img;
    }
    $scale = $max / $long;
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

/** JPEG のデータにする。 */
function photo_to_jpeg(\GdImage $img, int $quality = 82): string
{
    ob_start();
    imagejpeg($img, null, $quality);
    return (string)ob_get_clean();
}

/**
 * アップロードされた写真を、本体用とサムネイル用の2枚にする。
 * 戻り値: ['full' => JPEGデータ, 'thumb' => JPEGデータ]
 */
function photo_prepare(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE
        || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) {
        throw new PhotoError('写真が大きすぎます。別の写真でお試しください。');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new PhotoError('写真を受け取れませんでした。もう一度お試しください。');
    }
    if (($file['size'] ?? 0) > PHOTO_MAX_UPLOAD_BYTES) {
        throw new PhotoError('写真が大きすぎます（25MBまで）。');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new PhotoError('写真の受け取りに失敗しました。');
    }

    $img = photo_load($file['tmp_name'], (string)($file['name'] ?? ''));
    $full = photo_to_jpeg(photo_resize($img, PHOTO_MAX_LONG_EDGE));
    $thumb = photo_to_jpeg(photo_resize($img, PHOTO_THUMB_LONG_EDGE));

    return ['full' => $full, 'thumb' => $thumb];
}
