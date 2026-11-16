<?php
/*
 * 書いている途中の見た目を、その場で確かめる画面。
 * サイト本体の CSS をそのまま読み込み、記事部分の作りも本番と同じにしています。
 *
 * ※ 本番のページと同じ作りを、ここにもう一組持っています。
 *    src/pages/blog/[...slug].astro の記事まわりを直したときは、この画面も合わせてください。
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/article.php';
require_once __DIR__ . '/lib/photo.php';

require_login();
$c = config();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();

$title = trim((string)($_POST['title'] ?? ''));
$date = trim((string)($_POST['date'] ?? date('Y-m-d')));
$body = article_body_from_input((string)($_POST['body'] ?? ''));
$dateStyle = (string)($_POST['date_style'] ?? 'full');
$image = (string)($_POST['image'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$ts = strtotime($date) ?: time();
$dateLabel = $dateStyle === 'month' ? date('Y年n月', $ts) : date('Y年n月j日', $ts);

// この場で選ばれた写真があれば、保存前でも見えるように画面に埋め込む
$photoNote = null;
if (!empty($_FILES['photo']['name'] ?? '')) {
    try {
        $images = photo_prepare($_FILES['photo']);
        $image = 'data:image/jpeg;base64,' . base64_encode($images['full']);
    } catch (PhotoError $e) {
        $photoNote = $e->getMessage();
    }
}

$bodyHtml = article_body_to_html($body);
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>見た目の確認｜<?= h($title) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/news.css">
<style>
  .preview-bar {
    position: sticky; top: 0; z-index: 99;
    background: #4a1c24; color: #fff;
    font-family: "Hiragino Kaku Gothic ProN", system-ui, sans-serif;
    font-size: 13px; line-height: 1.6;
    padding: 10px 14px; display: flex; gap: 12px; align-items: center; justify-content: space-between;
  }
  .preview-bar button {
    font: inherit; color: #4a1c24; background: #fff; border: 0;
    border-radius: 6px; padding: 7px 14px; cursor: pointer;
  }
  .preview-note {
    font-family: "Hiragino Kaku Gothic ProN", system-ui, sans-serif;
    font-size: 13px; line-height: 1.8; color: #6b625e;
    background: #f7f4f0; border-top: 1px solid #ded7cf;
    padding: 16px 20px; margin: 0;
  }
  .preview-note.err { background: #fdf4f2; color: #9d3b2b; }
</style>
</head>
<body>
<div class="preview-bar">
  <span>記事部分の見た目の確認です</span>
  <button type="button" onclick="window.close()">閉じる</button>
</div>

<?php if ($photoNote): ?>
  <p class="preview-note err"><?= h($photoNote) ?></p>
<?php endif; ?>

<div id="Page">
<div id="Content" class="under">
<main id="Main" class="news_page" role="main">
	<section id="Sec01">
		<div class="inner">
			<h2 class="top_en_ttl">NEWS</h2>
			<p class="date"><?= h($dateLabel) ?></p>
			<h3 class="ttl"><?= h($title) ?></h3>
			<div class="article">
				<div class="flex">
					<div class="txt_box">
<?= $bodyHtml ?>
					</div>
					<?php if ($image !== ''): ?>
					<div class="img_box">
						<p class="img">
							<img src="<?= h($image) ?>" width="100%" height="auto" alt="">
						</p>
					</div>
					<?php endif; ?>
				</div>
			</div>
			<p class="back_list"><a href="#" onclick="window.close();return false;">一覧へ戻る</a></p>
		</div><!-- // .inner -->
	</section><!-- // #Sec01 -->
</main><!-- // #Main -->
</div>
</div>

<p class="preview-note">
  ここで見えているのは記事の部分だけです。ヘッダーやフッターまで入った本物の見た目は、
  いったん保存してから「プレビューのサイトで開く」で確かめられます（反映まで2〜3分かかります）。
</p>
</body>
</html>
