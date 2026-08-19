<?php
/*
 * このファイルを config.php という名前でコピーし、サーバー上（さくら）の
 * /api/config.php に置いてください。config.php は GitHub には入れません。
 * （API キーを公開リポジトリに置かないため／自動デプロイでも上書きされません）
 */
return [
    // Resend の API キー（https://resend.com のダッシュボードで発行）
    'resend_api_key' => 're_xxxxxxxxxxxxxxxxxxxxxxxx',

    // 送信元。Resend で認証（ドメイン検証）したドメインのアドレスにしてください
    'from' => 'TOMAPU FARM <no-reply@tomapufarm.com>',

    // 受信するアドレス（複数可）
    'to' => ['info@tomapufarm.com'],

    // Resend が使えないときに使う、さくらのメール送信のFrom
    'fallback_from' => 'no-reply@tomapufarm.com',
];
