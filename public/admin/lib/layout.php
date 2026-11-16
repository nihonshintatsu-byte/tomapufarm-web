<?php
/* 画面の外枠。全ページで同じものを使います。 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function layout_head(string $title, bool $showLogout = true): void
{
    $user = function_exists('current_user') ? current_user() : null;
    ?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#4a1c24">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="お知らせ投稿">
<link rel="apple-touch-icon" href="/assets/images/common/logo.png">
<title><?= h($title) ?>｜トマップファーム お知らせ投稿</title>
<link rel="stylesheet" href="style.css?v=1">
</head>
<body>
<div class="bar">
  <h1><?= h($title) ?></h1>
  <?php if ($showLogout && $user): ?>
    <a href="logout.php">ログアウト</a>
  <?php endif; ?>
</div>
<div class="wrap">
<?php
}

function layout_foot(): void
{
    ?>
  <p class="foot">トマップファーム お知らせ投稿<br>うまくいかないときは、この画面のまま日本信達にご連絡ください。</p>
</div>
<script>
// 二重送信を防ぐ。押した瞬間にボタンを効かなくする。
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (f.tagName === 'FORM') { f.classList.add('sending'); }
});
</script>
</body>
</html>
<?php
}
