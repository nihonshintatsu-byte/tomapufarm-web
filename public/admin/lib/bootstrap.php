<?php
/*
 * 管理画面の共通の下ごしらえ。設定の読み込み、セッション、よく使う小道具。
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding('UTF-8');

const ADMIN_DIR = __DIR__ . '/..';

/** 設定を読む。無ければ案内を出して止める。 */
function config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $path = ADMIN_DIR . '/config.php';
    if (!is_readable($path)) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="ja"><meta charset="UTF-8">'
           . '<body style="font-family:sans-serif;padding:2em;line-height:1.8">'
           . '<h1>設定がまだです</h1>'
           . '<p>サーバーの <code>admin/config.php</code> が見つかりません。'
           . '<code>config.sample.php</code> を写して作成してください。</p></body></html>';
        exit;
    }
    $config = require $path;
    return $config;
}

/** セッションを安全めな設定で開始する。 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('tomapu_admin');
    session_start();
}

/** HTML に出すときのエスケープ。 */
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 画面を移動する。 */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** なりすまし投稿を防ぐための合い言葉。 */
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    start_session();
    $sent = (string)($_POST['_csrf'] ?? '');
    if (!$sent || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(400);
        exit('フォームの有効期限が切れています。前の画面に戻ってやり直してください。');
    }
}

/** 画面に1回だけ出すお知らせ。 */
function flash(?string $message = null): ?string
{
    start_session();
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}
