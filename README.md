# tomapufarm.com（トマップファーム公式サイト）

WordPress から静的サイトへ入れ替えたもの。運営は株式会社北海道開拓使（代表 高橋ひかり）。
**代表者が Codex に日本語で頼んで更新できる**ことを目的に組んであります。

- 中身: Astro 5（静的書き出し）＋ 問い合わせフォームだけ PHP
- 公開先: さくらインターネット（`www463.sakura.ne.jp`）
- 公開の流れ: `main` に入る → GitHub Actions がビルド → FTPS でさくらへ転送
- 編集ルール: [AGENTS.md](AGENTS.md)（Codex / Claude Code が読む）

## ローカルで動かす

```bash
npm install
npm run dev        # http://localhost:4321/
```

フォームの送信まで確認するときは PHP が必要です。

```bash
npm run build
php -S localhost:4400 -t dist    # http://localhost:4400/contact/
```

## 移行にあたっての決定事項

| 項目 | 決めたこと |
|---|---|
| デザイン | 2020年版の既存デザインを忠実に移植。CSS・画像は元のものをそのまま使用 |
| ホスティング | さくらを維持（DNS・メールは一切触らない） |
| 公開の自動化 | GitHub Actions → FTPS でさくらへ。Codex から公開まで人手が入らない |
| フォーム | MW WP Form / Contact Form 7 を廃止し、PHP + Resend に置き換え |
| お知らせ | WordPress の投稿5本を Markdown に移行 |
| 記事URL | `お知らせテスト01` のような旧スラッグを読める名前に変更し、旧URLからの転送を用意 |

移植の正確さは、旧サイトと新サイトの各セクションの高さをブラウザ上で比較して確認しました
（トップ・about・company・service・transaction・english・blog・記事ページで PC/スマホともに一致）。

## サーバーについて調べたこと（2026-08-20）

| 調べたこと | 結果 |
|---|---|
| `.htaccess` は使えるか | **使える。** 現行サイトが WordPress のパーマリンクで動いており、存在しないURLに WordPress の404が返る＝`mod_rewrite` が動作している。さくらは前段 nginx／後段 Apache の構成 |
| `http` → `https` の転送 | **さくら側（nginx）が行っている**（PHPのヘッダーが付かない302）。WordPress を消しても残る |
| `www` あり → なしの転送 | **WordPress が行っていた**（`x-redirect-by: WordPress`）。消すと転送が失われ同じサイトが2つのURLで配られるため、`public/.htaccess` で引き継いだ |
| 外部リンクの生死 | BASE・Instagram・YouTube・Googleマップ・SnapWidget は正常。`twitter.com` は `x.com` へ転送される（リンク自体は有効） |
| 会社案内PDF | 2021年1月29日アップロード。内容が最新か要確認 |

## 公開後の確認

切り替えたら、本番URLを指定して確認スクリプトを流します。**メールは送りません**
（フォームには、わざと不備のある内容を送って入力チェックだけ動かします）。

```bash
./scripts/check-site.sh https://tomapufarm.com
```

全ページの表示（canonical が一致するかまで確認）、素材の有無、旧URLからの転送、404、
WordPress の入口が閉じているか、フォームの受け口、`www` の転送——をまとめて見ます。
手元のプレビューに対しても引数なしで実行できます。

## 公開前にやること

### 1. GitHub リポジトリ

`nihonshintatsu-byte/tomapufarm-web`（private）に push する。

### 2. Secrets を登録する

リポジトリの Settings → Secrets and variables → Actions に3つ登録します。
値はさくらのコントロールパネル（FTPアカウント）にあります。

| 名前 | 内容 | 例 |
|---|---|---|
| `SAKURA_FTP_SERVER` | FTPサーバー名 | `www463.sakura.ne.jp` |
| `SAKURA_FTP_USERNAME` | FTPアカウント | `tomapufarm` |
| `SAKURA_FTP_PASSWORD` | FTPパスワード | |
| `SAKURA_SERVER_DIR` | 公開ディレクトリ（末尾に `/`） | `/home/tomapufarm/www/` |

### 3. Resend の設定

1. https://resend.com で `tomapufarm.com` をドメイン登録し、指示される DNS レコード
   （TXT / CNAME）をさくらの DNS に追加する。**MX は触らない**
2. API キーを発行する
3. `public/api/config.sample.php` を写して `config.php` を作り、さくらの
   `www/api/config.php` に手で置く（このファイルは GitHub に入れない。自動転送でも上書きされない）

Resend を使わない場合、`config.php` が無ければさくらのメール送信に自動で切り替わります。
その場合の宛先は `contact.php` 内の既定値（`info@tomapufarm.com`）です。

### 4. 切り替え（WordPress の撤去）

順番を守ること。

1. **さくらのバックアップを取る**（`www/` 全体と MySQL のダンプ）。戻せる状態を必ず作る
2. 旧 WordPress のファイルを削除する
   （`wp-admin/` `wp-includes/` `wp-content/` `wp-*.php` `index.php` `xmlrpc.php` など）
   ※ `public/wp-content/uploads/` の PDF と OGP画像は新サイト側が同じパスで配り直すので消してよい
3. GitHub Actions を手動実行して転送する（Actions → 本番公開 → Run workflow）
4. 表示を確認する。特に `/contact/` の送信と `/form/` の送信
5. Google Search Console にサイトマップ `https://tomapufarm.com/sitemap.xml` を再送信する
6. さくらの WordPress とデータベースを削除する（1〜5 が問題なければ）

DNS とメールは変更しません。ドメインもさくらのままです。

## 残っている宿題

- Resend のドメイン認証と `api/config.php` の設置（上記 3）
- `/form/`（ギフト注文フォーム）は旧サイトのまま見出しが無く、デザインもサイト本体と揃っていない。
  必要なら別途整える
- お知らせ一覧で `TOMAPU FARM 公式サイトができました！` と
  `TOMAPU FARM ひまわりGARDEN　開催！！` にはサムネイルが無く、枠が空く（旧サイトと同じ状態）。
  `thumbnail:` を足せば埋まる
- 画像はすべて PNG のまま。**WebP に変換すると 30MB → 7MB（76%削減）** になることを実測した。
  ただし全ページのマークアップと CSS の書き換えが必要なため、今回は「見た目を変えない」ことを
  優先して手を付けていない
