<?php
/*
 * GitHub とのやりとり。記事ファイルと写真の読み書きだけを行います。
 * 使うのは Contents API だけで、トークンもこのリポジトリの Contents 権限に絞ります。
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

class GitHubError extends RuntimeException {}

function gh_request(string $method, string $path, ?array $body = null): array
{
    $c = config();
    $url = 'https://api.github.com' . $path;

    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $c['github_token'],
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
        'User-Agent: tomapufarm-admin',
    ];
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        throw new GitHubError('GitHub に接続できませんでした（' . $err . '）');
    }
    $json = json_decode((string)$res, true);
    if ($code === 404) {
        return ['_notfound' => true];
    }
    if ($code < 200 || $code >= 300) {
        $msg = is_array($json) ? ($json['message'] ?? '') : '';
        throw new GitHubError('GitHub がエラーを返しました（' . $code . ' ' . $msg . '）');
    }
    return is_array($json) ? $json : [];
}

function gh_path(string $path): string
{
    $c = config();
    return '/repos/' . rawurlencode($c['github_owner']) . '/' . rawurlencode($c['github_repo'])
         . '/contents/' . implode('/', array_map('rawurlencode', explode('/', $path)));
}

/** フォルダの中のファイル一覧。 */
function gh_list(string $dir): array
{
    $c = config();
    $res = gh_request('GET', gh_path($dir) . '?ref=' . rawurlencode($c['github_branch']));
    if (!empty($res['_notfound'])) {
        return [];
    }
    return $res;
}

/** ファイルを読む。無ければ null。戻り値は ['content' => 本文, 'sha' => …]。 */
function gh_get(string $path): ?array
{
    $c = config();
    $res = gh_request('GET', gh_path($path) . '?ref=' . rawurlencode($c['github_branch']));
    if (!empty($res['_notfound'])) {
        return null;
    }
    return [
        'content' => base64_decode(str_replace("\n", '', (string)($res['content'] ?? ''))),
        'sha' => (string)($res['sha'] ?? ''),
    ];
}

/** ファイルを書く（新規も更新も）。$raw はそのままの中身（テキストでも画像でも）。 */
function gh_put(string $path, string $raw, string $message, ?string $sha = null): array
{
    $c = config();
    $body = [
        'message' => $message,
        'content' => base64_encode($raw),
        'branch' => $c['github_branch'],
    ];
    if ($sha) {
        $body['sha'] = $sha;
    }
    return gh_request('PUT', gh_path($path), $body);
}

/** ファイルを消す。 */
function gh_delete(string $path, string $sha, string $message): array
{
    $c = config();
    return gh_request('DELETE', gh_path($path), [
        'message' => $message,
        'sha' => $sha,
        'branch' => $c['github_branch'],
    ]);
}

/** 公開の処理がいま動いているか（Actions の状況）。画面に出すために使います。 */
function gh_latest_run(): ?array
{
    $c = config();
    try {
        $res = gh_request('GET', '/repos/' . rawurlencode($c['github_owner']) . '/'
            . rawurlencode($c['github_repo']) . '/actions/runs?per_page=1');
    } catch (GitHubError) {
        return null;
    }
    $run = $res['workflow_runs'][0] ?? null;
    if (!$run) {
        return null;
    }
    return [
        'status' => (string)($run['status'] ?? ''),        // queued / in_progress / completed
        'conclusion' => (string)($run['conclusion'] ?? ''), // success / failure …
        'url' => (string)($run['html_url'] ?? ''),
        'name' => (string)($run['name'] ?? ''),
    ];
}

/**
 * 記事の一覧を速く作るための読み込み。
 * ファイルの中身は内容ごとに決まる番号（sha）で取れるので、一度読んだものは使い回します。
 */
function gh_cache_dir(): string
{
    $dir = sys_get_temp_dir() . '/tomapu_admin_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function gh_blob_cached(string $sha): ?string
{
    $f = gh_cache_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $sha);
    return is_readable($f) ? (string)file_get_contents($f) : null;
}

function gh_blob_store(string $sha, string $content): void
{
    $f = gh_cache_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $sha);
    @file_put_contents($f, $content, LOCK_EX);
}

/** 複数のファイルの中身を一度にまとめて取る。 */
function gh_blobs(array $shas): array
{
    $c = config();
    $out = [];
    $need = [];
    foreach ($shas as $sha) {
        $hit = gh_blob_cached($sha);
        if ($hit !== null) {
            $out[$sha] = $hit;
        } else {
            $need[] = $sha;
        }
    }
    if (!$need) {
        return $out;
    }

    $multi = curl_multi_init();
    $handles = [];
    foreach ($need as $sha) {
        $ch = curl_init('https://api.github.com/repos/'
            . rawurlencode($c['github_owner']) . '/' . rawurlencode($c['github_repo'])
            . '/git/blobs/' . rawurlencode($sha));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $c['github_token'],
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
                'User-Agent: tomapufarm-admin',
            ],
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[$sha] = $ch;
    }

    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    foreach ($handles as $sha => $ch) {
        $res = curl_multi_getcontent($ch);
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
        $json = json_decode((string)$res, true);
        $content = isset($json['content'])
            ? base64_decode(str_replace("\n", '', (string)$json['content']))
            : '';
        $out[$sha] = $content;
        if ($content !== '') {
            gh_blob_store($sha, $content);
        }
    }
    curl_multi_close($multi);

    return $out;
}
