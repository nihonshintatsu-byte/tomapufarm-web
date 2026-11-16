<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/layout.php';

if (current_user()) {
    redirect('index.php');
}

$error = null;
$locked = login_locked_for();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($locked > 0) {
        $error = 'しばらく時間をおいてからお試しください（あと約' . (int)ceil($locked / 60) . '分）。';
    } elseif (login_attempt((string)($_POST['id'] ?? ''), (string)($_POST['password'] ?? ''))) {
        redirect('index.php');
    } else {
        $error = 'IDかパスワードが違います。';
        $locked = login_locked_for();
    }
}

layout_head('ログイン', false);
?>
<?php if ($error): ?><div class="notice err"><?= h($error) ?></div><?php endif; ?>

<form method="post" class="card">
  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
  <label class="field">
    <span class="name">ID</span>
    <input type="text" name="id" autocomplete="username" autocapitalize="none" autocorrect="off" required>
  </label>
  <label class="field">
    <span class="name">パスワード</span>
    <input type="password" name="password" autocomplete="current-password" required>
  </label>
  <button class="btn primary" type="submit">ログイン</button>
</form>

<p class="foot">このページはトマップファームの関係者だけが使います。</p>
<?php layout_foot();
