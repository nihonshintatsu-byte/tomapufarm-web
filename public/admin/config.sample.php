<?php
/*
 * このファイルを config.php という名前でコピーし、さくらのサーバーの
 * admin/config.php に置いてください。GitHub には入れません。
 * （トークンを公開リポジトリに置かないため／自動転送でも上書きされません）
 */
return [
    // ログインする人。パスワードは password_hash() で作ったものを入れます。
    // 作り方: php -r 'echo password_hash("ここにパスワード", PASSWORD_DEFAULT), "\n";'
    'users' => [
        'hikari' => [
            'name' => '高橋 ひかり',
            'hash' => '$2y$10$ここにpassword_hashの結果を貼る',
        ],
    ],

    // GitHub の細かい権限付きトークン（Fine-grained personal access token）。
    // 対象リポジトリ: nihonshintatsu-byte/tomapufarm-web のみ
    // 権限: Contents = Read and write ／ Actions = Read-only （この2つだけ）
    //       Actions は、公開の作業の状況を画面に出すためだけに使います。
    'github_token' => 'github_pat_xxxxxxxxxxxxxxxx',
    'github_owner' => 'nihonshintatsu-byte',
    'github_repo'  => 'tomapufarm-web',
    'github_branch' => 'main',

    // 本物プレビューのアドレス（さくらのサブドメイン）
    'preview_url' => 'https://preview.tomapufarm.com',

    // 公開サイトのアドレス
    'site_url' => 'https://tomapufarm.com',
];
