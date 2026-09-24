<?php
/*
 * お問い合わせ／注文フォームの受け口。
 *
 * 動き:
 *   1. POST された内容を検証する（必須項目・メール形式・迷惑メール対策）
 *   2. Resend の API でメールを送る（api/config.php に設定がある場合）
 *   3. Resend が使えない場合は、さくらのメール送信（mb_send_mail）に切り替える
 *   4. 結果を JSON で返す（{"ok":true} / {"ok":false,"error":"…"}）
 *
 * 設定は同じフォルダの config.php に置きます（config.sample.php を参照）。
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding('UTF-8');
mb_language('uni');

function respond(bool $ok, string $error = '', int $status = 200): void
{
    http_response_code($status);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'このURLには直接アクセスできません。', 405);
}

// ---- 迷惑メール対策 -------------------------------------------------------
// 人には見えない項目に何か入っていたら機械とみなす
if (trim((string)($_POST['your-website'] ?? '')) !== '') {
    respond(true); // 送ったふりをして黙って捨てる
}
// フォームを開いてから3秒未満の送信は機械とみなす
$openedAt = (int)($_POST['form-opened-at'] ?? 0);
if ($openedAt > 0 && (microtime(true) * 1000 - $openedAt) < 3000) {
    respond(false, '送信が早すぎます。もう一度お試しください。');
}

// ---- 項目の定義 -----------------------------------------------------------
// お問い合わせフォーム（/contact/）
$CONTACT_FIELDS = [
    'your-name'    => ['label' => 'お名前',           'required' => true],
    'your-subject' => ['label' => '法人・個人',        'required' => false],
    'your-company' => ['label' => '会社名',           'required' => false],
    'your-email'   => ['label' => 'Eメールアドレス',   'required' => true, 'email' => true],
    'your-check'   => ['label' => 'お問い合わせ内容',  'required' => false],
    'your-message' => ['label' => 'お問い合わせ詳細',  'required' => true],
    'your-pra'     => ['label' => 'プライバシーポリシー', 'required' => true],
];
// 注文フォーム（/form/）
$ORDER_FIELDS = [
    'sender-name'          => ['label' => '送り主 氏名',      'required' => true],
    'sender-address'       => ['label' => '送り主 住所',      'required' => true],
    'sender-phone'         => ['label' => '送り主 電話番号',   'required' => true],
    'sender-email'         => ['label' => '送り主 メール',     'required' => true, 'email' => true],
    'noshi'                => ['label' => 'のし有無',         'required' => false],
    'noshi-type'           => ['label' => '表書き',           'required' => false],
    'recipient-name'       => ['label' => 'お届け先 氏名',     'required' => true],
    'recipient-address'    => ['label' => 'お届け先 住所',     'required' => true],
    'recipient-phone'      => ['label' => 'お届け先 電話番号',  'required' => true],
    'product-type'         => ['label' => '商品',             'required' => false],
    'item-quantity'        => ['label' => '商品個数',          'required' => false],
    'delivery-date-option' => ['label' => '配達日指定',        'required' => false],
    'delivery-time'        => ['label' => '配達時間帯',        'required' => false],
];

$isOrder = isset($_POST['sender-name']) || isset($_POST['recipient-name']);
$fields  = $isOrder ? $ORDER_FIELDS : $CONTACT_FIELDS;
$formName = $isOrder ? 'ご注文フォーム' : 'お問い合わせフォーム';

// ---- 値の取り出しと検証 ---------------------------------------------------
function value(string $name): string
{
    // チェックボックスは name[] で届くこともあるので両方見る
    $raw = $_POST[$name] ?? $_POST[$name . '[]'] ?? '';
    if (is_array($raw)) {
        $raw = implode('、', array_map('strval', $raw));
    }
    $raw = str_replace(["\r\n", "\r"], "\n", (string)$raw);
    // ヘッダーインジェクション対策
    return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $raw));
}

$values = [];
foreach ($fields as $name => $def) {
    $v = value($name);
    if ($def['required'] && $v === '') {
        respond(false, $def['label'] . 'が入力されていません。');
    }
    if (!empty($def['email']) && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
        respond(false, 'メールアドレスの形式をご確認ください。');
    }
    if (mb_strlen($v) > 5000) {
        respond(false, $def['label'] . 'が長すぎます。');
    }
    $values[$name] = $v;
}

$replyTo = $isOrder ? $values['sender-email'] : $values['your-email'];

// ---- メール本文を組む -----------------------------------------------------
$lines = [];
$lines[] = 'トマップファーム公式サイトの' . $formName . 'から送信がありました。';
$lines[] = '';
$lines[] = '----------------------------------------';
foreach ($fields as $name => $def) {
    $lines[] = $def['label'] . '：' . ($values[$name] !== '' ? $values[$name] : '（未入力）');
}
$lines[] = '----------------------------------------';
$lines[] = '';
$lines[] = '送信日時：' . date('Y-m-d H:i:s');
$lines[] = '送信ページ：' . value('page-url');
$body = implode("\n", $lines);
$subject = '【tomapufarm.com】' . $formName . 'より（' . ($isOrder ? $values['sender-name'] : $values['your-name']) . '様）';

// ---- 送信 -----------------------------------------------------------------
$configPath = __DIR__ . '/config.php';
$config = is_readable($configPath) ? require $configPath : null;

// 宛先は旧サイトの設定を引き継ぐ（お問い合わせ＝MW WP Form、ご注文＝Contact Form 7 の設定値）
$to = $isOrder
    ? (array)($config['order_to'] ?? 'info@hokkaido-kaitakushi.co.jp')
    : (array)($config['to'] ?? 'info@tomapufarm.com');

function sendWithResend(array $config, array $to, string $subject, string $body, string $replyTo): array
{
    $payload = [
        'from'     => $config['from'],
        'to'       => $to,
        'subject'  => $subject,
        'text'     => $body,
        'reply_to' => $replyTo,
    ];
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $config['resend_api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$code >= 200 && $code < 300, $code, $err, (string)$res];
}

function sendWithSakuraMail(?array $config, array $to, string $subject, string $body, string $replyTo): bool
{
    $from = $config['fallback_from'] ?? 'no-reply@tomapufarm.com';
    $headers = "From: " . $from . "\r\nReply-To: " . $replyTo;
    return mb_send_mail(implode(',', (array)$to), $subject, $body, $headers);
}

// ログはさくらの PHP エラーログへ（Web からは見えない場所）
$logLine = $formName . "\t" . $replyTo . "\t";

if ($config && !empty($config['resend_api_key']) && function_exists('curl_init')) {
    [$ok, $code, $err, $res] = sendWithResend($config, $to, $subject, $body, $replyTo);
    if ($ok) {
        @error_log('[tomapufarm-form] ' . $logLine . "resend ok\n");
        respond(true);
    }
    @error_log('[tomapufarm-form] ' . $logLine . "resend NG code={$code} err={$err} res={$res}\n");
    // Resend が落ちていてもお客様の送信を捨てないよう、さくらのメールに切り替える
    if (sendWithSakuraMail($config, $to, $subject, $body, $replyTo)) {
        @error_log('[tomapufarm-form] ' . $logLine . "fallback ok\n");
        respond(true);
    }
    respond(false, 'メールの送信に失敗しました。');
}

if (sendWithSakuraMail($config, $to, $subject, $body, $replyTo)) {
    @error_log('[tomapufarm-form] ' . $logLine . "sakura ok\n");
    respond(true);
}
@error_log('[tomapufarm-form] ' . $logLine . "sakura NG\n");
respond(false, 'メールの送信に失敗しました。');
