<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_once __DIR__ . '/lib/article.php';
require_once __DIR__ . '/lib/photo.php';
require_once __DIR__ . '/lib/layout.php';

$user = require_login();
$c = config();

$error = null;
$path = (string)($_GET['path'] ?? $_POST['path'] ?? '');
$isNew = ($path === '');

// ---- いまの中身を読む ------------------------------------------------------
$d = ['title' => '', 'date' => date('Y-m-d'), 'dateLabel' => '', 'slug' => '',
      'thumbnail' => '', 'image' => '', 'draft' => true, 'body' => ''];
$sha = null;

if (!$isNew && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $file = gh_get($path);
        if ($file === null) {
            flash('そのお知らせは見つかりませんでした。');
            redirect('index.php');
        }
        $d = article_parse($file['content']);
        $sha = $file['sha'];
    } catch (GitHubError $e) {
        $error = $e->getMessage();
    }
}

// ---- 保存・公開・削除 ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? 'save');

    $d['title'] = trim((string)($_POST['title'] ?? ''));
    $d['date'] = trim((string)($_POST['date'] ?? date('Y-m-d')));
    $d['body'] = article_body_from_input((string)($_POST['body'] ?? ''));
    $d['slug'] = article_clean_slug((string)($_POST['slug'] ?? ''));
    $d['thumbnail'] = (string)($_POST['thumbnail'] ?? '');
    $d['image'] = (string)($_POST['image'] ?? '');
    $dateStyle = (string)($_POST['date_style'] ?? 'full');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['date'])) {
        $d['date'] = date('Y-m-d');
    }
    $ts = strtotime($d['date']) ?: time();
    $d['dateLabel'] = $dateStyle === 'month'
        ? date('Y年n月', $ts)
        : date('Y年n月j日', $ts);

    if ($d['slug'] === '') {
        $d['slug'] = 'news-' . date('Ymd', $ts) . '-' . substr(bin2hex(random_bytes(2)), 0, 3);
    }

    try {
        if ($action === 'delete') {
            if ($isNew) {
                redirect('index.php');
            }
            $cur = gh_get($path);
            if ($cur) {
                gh_delete($path, $cur['sha'], 'お知らせを削除: ' . $d['title']);
            }
            flash('お知らせを削除しました。');
            redirect('index.php');
        }

        if ($d['title'] === '') {
            throw new RuntimeException('タイトルを入れてください。');
        }

        // 写真が送られていたら、整えて保存する
        if (!empty($_FILES['photo']['name'] ?? '')) {
            $images = photo_prepare($_FILES['photo']);
            $base = $d['date'] . '-' . $d['slug'];
            $fullPath = PHOTO_DIR . '/' . $base . '.jpg';
            $thumbPath = PHOTO_DIR . '/' . $base . '-thumb.jpg';

            $existingFull = gh_get($fullPath);
            gh_put($fullPath, $images['full'], '写真を追加: ' . $base, $existingFull['sha'] ?? null);
            $existingThumb = gh_get($thumbPath);
            gh_put($thumbPath, $images['thumb'], '写真（一覧用）を追加: ' . $base, $existingThumb['sha'] ?? null);

            $d['image'] = '/assets/news/' . $base . '.jpg';
            $d['thumbnail'] = '/assets/news/' . $base . '-thumb.jpg';
        }

        if ($action === 'publish') {
            $d['draft'] = false;
        } elseif ($action === 'unpublish') {
            $d['draft'] = true;
        } else {
            $d['draft'] = true;
        }

        $newPath = article_filename($d['date'], $d['slug']);
        $content = article_build($d);

        if (!$isNew && $newPath !== $path) {
            // 日付かURLが変わったので、新しい名前で作って古いほうを消す
            $old = gh_get($path);
            gh_put($newPath, $content, 'お知らせを更新: ' . $d['title']);
            if ($old) {
                gh_delete($path, $old['sha'], '古いファイルを整理: ' . $d['title']);
            }
        } else {
            $cur = gh_get($newPath);
            gh_put($newPath, $content, ($isNew ? 'お知らせを追加: ' : 'お知らせを更新: ') . $d['title'], $cur['sha'] ?? null);
        }

        flash($action === 'publish'
            ? '公開しました。サイトに出るまで2〜3分かかります。'
            : ($action === 'unpublish' ? '公開を取り下げました。' : '下書きとして保存しました。'));
        redirect('index.php');

    } catch (PhotoError | GitHubError | RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$dateStyleNow = (!empty($d['dateLabel']) && !str_contains($d['dateLabel'], '日')) ? 'month' : 'full';

layout_head($isNew ? '新しいお知らせ' : 'お知らせを編集');
?>
<?php if ($error): ?><div class="notice err"><?= h($error) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card" id="f">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="path" value="<?= h($path) ?>">
  <input type="hidden" name="thumbnail" value="<?= h($d['thumbnail']) ?>">
  <input type="hidden" name="image" value="<?= h($d['image']) ?>">

  <label class="field">
    <span class="name">タイトル</span>
    <input type="text" name="title" value="<?= h($d['title']) ?>" required>
  </label>

  <label class="field">
    <span class="name">本文</span>
    <textarea name="body" placeholder="ここに本文を書きます。&#10;&#10;一行あけると、段落が変わります。&#10;ホームページのアドレスを貼ると、自動でリンクになります。"><?= h(article_body_to_input($d['body'])) ?></textarea>
  </label>

  <label class="field">
    <span class="name">掲載日</span>
    <input type="date" name="date" value="<?= h($d['date']) ?>">
  </label>

  <div class="check">
    <input type="radio" name="date_style" id="ds_full" value="full" <?= $dateStyleNow === 'full' ? 'checked' : '' ?>>
    <label for="ds_full">日にちまで出す（例 2026年6月1日）</label>
  </div>
  <div class="check">
    <input type="radio" name="date_style" id="ds_month" value="month" <?= $dateStyleNow === 'month' ? 'checked' : '' ?>>
    <label for="ds_month">年と月だけ出す（例 2026年6月）</label>
  </div>

  <p class="section-title">写真</p>
  <?php if (!empty($d['image'])): ?>
    <div class="photo-now">
      <img src="<?= h($d['image']) ?>" alt="">
      <span style="font-size:13.5px;color:var(--sub)">いまの写真です。<br>変えたいときは下から選び直してください。</span>
    </div>
  <?php endif; ?>
  <label class="field">
    <input type="file" name="photo" accept="image/*">
    <span class="hint">写真は1枚まで。向きや大きさはこちらで整えます。撮影場所などの情報は取り除いて載せます。</span>
  </label>

  <label class="field">
    <span class="name">URL に使う文字（空欄のままで大丈夫です）</span>
    <input type="text" name="slug" value="<?= h($d['slug']) ?>" autocapitalize="none" autocorrect="off" spellcheck="false">
    <span class="hint">半角の英数字とハイフンだけ使えます。<?php if (!$isNew): ?>すでに公開したお知らせでは、変えると前のアドレスが開けなくなります。<?php endif; ?></span>
  </label>

  <button class="btn" type="submit" formaction="preview.php" formtarget="_blank" name="action" value="preview">見た目を確認する</button>
  <button class="btn" type="submit" name="action" value="save">下書きとして保存</button>
  <button class="btn primary" type="submit" name="action" value="publish">公開する</button>
  <?php if (!$isNew && !$d['draft']): ?>
    <button class="btn quiet" type="submit" name="action" value="unpublish">公開を取り下げる</button>
  <?php endif; ?>
</form>

<?php if (!$isNew): ?>
  <p class="section-title">本物のページで確かめる</p>
  <a class="btn quiet" href="<?= h($c['preview_url']) ?>/blog/<?= h($d['slug']) ?>/" target="_blank" rel="noopener">プレビューのサイトで開く</a>
  <p class="foot" style="text-align:left">保存してから2〜3分たつと、ここに反映されます。</p>

  <form method="post" onsubmit="return confirm('このお知らせを削除します。元に戻せません。よろしいですか？');">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="path" value="<?= h($path) ?>">
    <input type="hidden" name="title" value="<?= h($d['title']) ?>">
    <button class="btn danger" type="submit" name="action" value="delete">このお知らせを削除する</button>
  </form>
<?php endif; ?>

<a class="btn quiet" href="index.php">一覧に戻る</a>
<?php layout_foot();
