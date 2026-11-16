<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/github.php';
require_once __DIR__ . '/lib/article.php';
require_once __DIR__ . '/lib/layout.php';

$user = require_login();
$c = config();

$error = null;
$items = [];
try {
    $entries = gh_list(NEWS_DIR);
    $shas = [];
    foreach ($entries as $e) {
        if (($e['type'] ?? '') === 'file' && str_ends_with((string)$e['name'], '.md')) {
            $shas[(string)$e['path']] = (string)$e['sha'];
        }
    }
    $blobs = gh_blobs(array_values($shas));
    foreach ($shas as $path => $sha) {
        $d = article_parse($blobs[$sha] ?? '');
        $d['path'] = $path;
        $items[] = $d;
    }
    // 下書きを先に、そのあと日付の新しい順
    usort($items, static function (array $a, array $b): int {
        if ($a['draft'] !== $b['draft']) {
            return $a['draft'] ? -1 : 1;
        }
        return strcmp($b['date'], $a['date']);
    });
} catch (GitHubError $e) {
    $error = $e->getMessage();
}

$run = gh_latest_run();
$building = $run && in_array($run['status'], ['queued', 'in_progress'], true);
$failed = $run && $run['status'] === 'completed' && $run['conclusion'] !== 'success';

layout_head('お知らせ');
?>
<?php if ($m = flash()): ?><div class="notice"><?= h($m) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice err"><?= h($error) ?></div><?php endif; ?>

<?php if ($building): ?>
  <div class="notice">いま公開の作業をしています。サイトに出るまで2〜3分お待ちください。</div>
<?php elseif ($failed): ?>
  <div class="notice err">
    さいごの公開の作業が最後まで進みませんでした。日本信達にご連絡ください。
  </div>
<?php endif; ?>

<a class="btn primary" href="edit.php">＋ 新しいお知らせを書く</a>

<p class="section-title">お知らせ一覧</p>
<div class="card">
  <?php if (!$items): ?>
    <p style="margin:0;color:var(--sub)">まだお知らせがありません。</p>
  <?php else: ?>
    <ul class="list">
      <?php foreach ($items as $it): ?>
        <li>
          <a href="edit.php?path=<?= h(urlencode($it['path'])) ?>">
            <?php if (!empty($it['thumbnail'])): ?>
              <img class="thumb" src="<?= h($it['thumbnail']) ?>" alt="">
            <?php elseif (!empty($it['image'])): ?>
              <img class="thumb" src="<?= h($it['image']) ?>" alt="">
            <?php else: ?>
              <span class="thumb"></span>
            <?php endif; ?>
            <span class="meta">
              <span class="t"><?= h($it['title'] !== '' ? $it['title'] : '（タイトルなし）') ?></span>
              <span class="d">
                <?php if ($it['draft']): ?>
                  <span class="tag draft">下書き</span>
                <?php else: ?>
                  <span class="tag live">公開中</span>
                <?php endif; ?>
                <?= h(article_display_date($it)) ?>
              </span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<p class="section-title">サイトを見る</p>
<a class="btn quiet" href="<?= h($c['site_url']) ?>/blog/" target="_blank" rel="noopener">公開中のお知らせページ</a>
<a class="btn quiet" href="<?= h($c['preview_url']) ?>/blog/" target="_blank" rel="noopener">下書きも見えるプレビュー</a>
<?php layout_foot();
