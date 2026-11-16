<?php
/*
 * ログインの管理。総当たりを防ぐため、失敗が続いたら少しのあいだ受け付けません。
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const LOGIN_MAX_TRIES = 8;        // この回数続けて失敗したら
const LOGIN_LOCK_SECONDS = 600;   // 10分待ってもらう

function login_attempts_file(): string
{
    return sys_get_temp_dir() . '/tomapu_admin_attempts.json';
}

function login_attempts(): array
{
    $f = login_attempts_file();
    if (!is_readable($f)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($f), true);
    return is_array($data) ? $data : [];
}

function login_locked_for(): int
{
    $all = login_attempts();
    $key = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rec = $all[$key] ?? null;
    if (!$rec || ($rec['count'] ?? 0) < LOGIN_MAX_TRIES) {
        return 0;
    }
    $left = (int)$rec['last'] + LOGIN_LOCK_SECONDS - time();
    return max(0, $left);
}

function login_record_failure(): void
{
    $all = login_attempts();
    $key = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rec = $all[$key] ?? ['count' => 0, 'last' => 0];
    if (time() - (int)$rec['last'] > LOGIN_LOCK_SECONDS) {
        $rec['count'] = 0;
    }
    $rec['count'] = (int)$rec['count'] + 1;
    $rec['last'] = time();
    $all[$key] = $rec;
    @file_put_contents(login_attempts_file(), json_encode($all), LOCK_EX);
}

function login_clear_failures(): void
{
    $all = login_attempts();
    unset($all[$_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    @file_put_contents(login_attempts_file(), json_encode($all), LOCK_EX);
}

/** ログインできたら true。 */
function login_attempt(string $id, string $password): bool
{
    $users = config()['users'] ?? [];
    $user = $users[$id] ?? null;
    // 利用者がいない場合も同じだけ時間をかけて、IDの存在を悟られないようにする
    $hash = $user['hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
    if (!password_verify($password, $hash) || !$user) {
        login_record_failure();
        return false;
    }
    login_clear_failures();
    start_session();
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => $id, 'name' => $user['name'] ?? $id];
    return true;
}

function current_user(): ?array
{
    start_session();
    return $_SESSION['user'] ?? null;
}

/** ログインしていなければログイン画面へ送る。 */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}
