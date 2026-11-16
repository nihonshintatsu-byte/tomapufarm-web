<?php
/*
 * 記事ファイル（Markdown）の読み書きと、プレビュー用の組み立て。
 *
 * 記事の形:
 *   ---
 *   title: "..."        タイトル
 *   date: "YYYY-MM-DD"  並び順に使う日付
 *   dateLabel: "..."    画面に出す日付（「2026年6月」のように月だけにしたいとき）
 *   slug: "..."         URL に使う文字
 *   thumbnail: "..."    一覧に出す写真
 *   image: "..."        記事の右に出る写真
 *   draft: true/false   true のあいだは公開されない
 *   ---
 *   本文
 */
declare(strict_types=1);

const NEWS_DIR = 'src/content/news';
const PHOTO_DIR = 'public/assets/news';

/** 記事ファイルの中身を、項目ごとに分解する。 */
function article_parse(string $raw): array
{
    $data = ['title' => '', 'date' => '', 'dateLabel' => '', 'slug' => '',
             'thumbnail' => '', 'image' => '', 'draft' => false, 'body' => ''];

    if (!preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $raw, $m)) {
        $data['body'] = $raw;
        return $data;
    }
    $data['body'] = ltrim($m[2], "\r\n");

    foreach (preg_split('/\R/', $m[1]) as $line) {
        if (!preg_match('/^([a-zA-Z]+):\s*(.*)$/', $line, $kv)) {
            continue;
        }
        $key = $kv[1];
        $val = trim($kv[2]);
        // 前後の引用符を外す
        if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[-1] === $val[0]) {
            $val = substr($val, 1, -1);
            $val = str_replace('\\"', '"', $val);
        }
        if ($key === 'draft') {
            $data['draft'] = ($val === 'true');
        } elseif (array_key_exists($key, $data)) {
            $data[$key] = $val;
        }
    }
    return $data;
}

/** 項目から記事ファイルの中身を組み立てる。 */
function article_build(array $d): string
{
    $q = static fn(string $s): string => '"' . str_replace('"', '\\"', $s) . '"';

    $lines = ['---'];
    $lines[] = 'title: ' . $q($d['title']);
    $lines[] = 'date: ' . $q($d['date']);
    if (!empty($d['dateLabel'])) {
        $lines[] = 'dateLabel: ' . $q($d['dateLabel']);
    }
    $lines[] = 'slug: ' . $q($d['slug']);
    if (!empty($d['thumbnail'])) {
        $lines[] = 'thumbnail: ' . $q($d['thumbnail']);
    }
    if (!empty($d['image'])) {
        $lines[] = 'image: ' . $q($d['image']);
    }
    $lines[] = 'draft: ' . (!empty($d['draft']) ? 'true' : 'false');
    $lines[] = '---';
    $lines[] = '';
    $lines[] = rtrim($d['body']);
    $lines[] = '';

    return implode("\n", $lines);
}

/**
 * 入力欄に書かれた文章を、記事ファイル用に整える。
 * 「1行の改行はそのまま改行、空行で段落」という、ふつうの書き方ができるようにする。
 */
function article_body_from_input(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    // 裸のURLはリンクにする
    $text = preg_replace_callback(
        '~(?<![\(\[\w])(https?://[^\s<>\[\]()]+)~u',
        static fn(array $m): string => '[' . $m[1] . '](' . $m[1] . ')',
        $text
    ) ?? $text;

    $paragraphs = preg_split('/\n{2,}/', $text) ?: [];
    $out = [];
    foreach ($paragraphs as $p) {
        $lines = explode("\n", rtrim($p));
        $out[] = implode("<br />\n", $lines);
    }
    return implode("\n\n", $out);
}

/** 記事ファイルの本文を、入力欄に戻す（上の逆）。 */
function article_body_to_input(string $body): string
{
    $body = preg_replace('/<br\s*\/?>\s*\n?/', "\n", $body) ?? $body;
    // [URL](URL) のように同じものが並んでいたら、裸のURLに戻す
    $body = preg_replace('~\[(https?://[^\]]+)\]\(\1\)~', '$1', $body) ?? $body;
    return trim($body);
}

/** 本文を、サイトと同じ見た目のHTMLにする（管理画面のプレビュー用）。 */
function article_body_to_html(string $body): string
{
    $paragraphs = preg_split('/\n{2,}/', trim($body)) ?: [];
    $out = [];
    foreach ($paragraphs as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        // 画像だけの段落は、そのまま通す
        $html = $p;
        // リンク
        $html = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            static fn(array $m): string =>
                '<a href="' . htmlspecialchars($m[2], ENT_QUOTES) . '">'
                . htmlspecialchars($m[1], ENT_QUOTES) . '</a>',
            $html
        ) ?? $html;
        $out[] = '<p>' . $html . '</p>';
    }
    return implode("\n", $out);
}

/** 日付の表示（サイトと同じ変換）。 */
function article_display_date(array $d): string
{
    if (!empty($d['dateLabel'])) {
        return $d['dateLabel'];
    }
    return str_replace('-', '.', $d['date']);
}

/** URL に使える文字だけにする。 */
function article_clean_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';
    $slug = trim(preg_replace('/-+/', '-', $slug) ?? '', '-');
    return $slug;
}

/** 記事のファイル名。 */
function article_filename(string $date, string $slug): string
{
    return NEWS_DIR . '/' . $date . '-' . $slug . '.md';
}
