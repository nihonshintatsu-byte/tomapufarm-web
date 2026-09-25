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

## 公開の仕組み（Mac mini のランナー）

`main` に push されると、**日本信達の Mac mini に常駐している GitHub Actions ランナー**が
ビルドし、FTPS でさくらの `www/site/` へ転送します。GitHub のサーバー（海外）で実行しないのは、
さくらの「国外IPアドレスフィルター」が有効で、海外からの FTP が拒否されるためです
（フィルターは FTP だけ外すことができず、外すとメール送信・SSH も海外に開くので、有効のまま残す判断をした）。

- 転送は Mac mini の `lftp`（`brew install lftp`）で行う。FTP-Deploy-Action はさくらの ProFTPD とデータ通信の暗号化の相性が悪く、切断されるため使っていない
- ランナーの場所: Mac mini の `~/actions-runner-tomapufarm`（名前 `macmini-tomapufarm`、ラベル `tomapufarm-deploy`）
- 状態の確認: GitHub の Settings → Actions → Runners で `Idle` なら正常
- **Mac mini が止まっている間は公開されません。** push は待ち行列に残り、Mac mini が戻れば実行されます（24時間を過ぎると失敗扱いになるので、Actions から Re-run する）
- 別のマシンへ移すとき: 新しいマシンで同じ手順でランナーを登録し、古いほうは
  `./svc.sh stop && ./svc.sh uninstall` のあと `./config.sh remove` で外す

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

登録が済むまで、公開ワークフローは**転送せずビルド確認だけで正常終了**します
（未登録のまま毎回赤くなると、本当の異常が埋もれるため）。登録後に「Re-run all jobs」で転送されます。

リポジトリの Settings → Secrets and variables → Actions に3つ登録します。
値はさくらのコントロールパネル（FTPアカウント）にあります。

| 名前 | 内容 | 例 |
|---|---|---|
| `SAKURA_FTP_SERVER` | FTPサーバー名 | `tomapufarm.sakura.ne.jp` |
| `SAKURA_FTP_USERNAME` | FTPアカウント | `tomapufarm` |
| `SAKURA_FTP_PASSWORD` | FTPパスワード | |
| `SAKURA_SERVER_DIR` | 新サイトの置き場所（末尾に `/`） | `/home/tomapufarm/www/site/` |

### 3. Resend の設定

1. https://resend.com で `tomapufarm.com` をドメイン登録し、指示される DNS レコード
   （TXT / CNAME）をさくらの DNS に追加する。**MX は触らない**
2. API キーを発行する
3. `public/api/config.sample.php` を写して `config.php` を作り、さくらの
   `www/api/config.php` に手で置く（このファイルは GitHub に入れない。自動転送でも上書きされない）

Resend を使わない場合、`config.php` が無ければさくらのメール送信に自動で切り替わります。
その場合の宛先は `contact.php` 内の既定値（`info@tomapufarm.com`）です。

### 4. 切り替え（WordPress の撤去）

順番を守ること。**手順1と2を飛ばさないこと。** 消したあとでは取り戻せません。

#### 手順1: WordPress の管理画面に入って中身を確認する

**バックアップだけでは「何が入っていたか」が分かりません。** ダンプは取れても、
中を読まないと気づけないものがあります。削除前に `https://tomapufarm.com/wp-admin/` に
ログインして、次を確認・保存します。ログイン情報が誰の手元にあるか（代表者か、当時の制作会社か）も
先に確かめておくこと。

| 確認するもの | なぜ |
|---|---|
| **MW WP Form の問い合わせ履歴** | このプラグインには問い合わせ内容をDBに保存する機能がある。有効なら過去の問い合わせが溜まっている。必要なら CSV で書き出す |
| **下書き・非公開の投稿** | 公開されていないので静的サイト側へ移せていない。残すものがあれば `src/content/news/` へ移す |
| **記事に紐づいていないメディア** | 公開ページから参照されていない画像は取得コピーに含まれていない。使う予定があれば控えておく |
| **固定ページの一覧** | 移行対象は8枚（＋`sample-page`）と把握しているが、非公開ページがないか念のため確認 |
| **ユーザー一覧** | 誰がアクセスできる状態だったかの記録として |

**2026-09-24 に DB ダンプを読んで確認した結果（管理画面に入らずに済んだ）:**

| 確認するもの | 結果 |
|---|---|
| MW WP Form の問い合わせ履歴 | **保存されていない**（DB保存の投稿が0件）。Contact Form 7 も保存プラグイン（Flamingo）なし |
| 下書き・非公開の投稿 | 中身のある下書きなし。2026-09-19〜24 に空の「自動下書き」が12件＝誰かが新規投稿画面を開いて保存せず閉じている |
| ゴミ箱 | 「コールドプレスジュースプロジェクト」が 2026-09-19 にゴミ箱へ移されていた → 新サイトからも削除した |
| 固定ページ | 公開10枚＋下書き1枚（プライバシーポリシー＝WordPress 既定の雛形のまま。移行不要） |
| メディア | 21件。未参照のものはデモ画像と 2021/03 の写真3枚のみ（`backups/.../wp.zip` に残っている） |
| ユーザー | `tomapufarm_admin` の1人。管理者メールは制作会社（by-push.com）のまま |
| フォームの宛先 | お問い合わせ＝`info@tomapufarm.com`、ご注文（`/form/`）＝`info@hokkaido-kaitakushi.co.jp` → 新フォームも同じ宛先にした |

#### 手順2〜

2026-09-24 の確認で、`tomapufarm.com` の公開フォルダは **`/home/tomapufarm/www/wp`**
（WordPress が入っているフォルダ）だと分かった。そこで、新サイトは別フォルダ `www/site/` に置き、
ドメインの公開フォルダを切り替える。WordPress を消さずに切り替えられ、戻すときは公開フォルダを
`www/wp` に戻すだけで済む。

1. **さくらのバックアップを取る**（`www/wp` 全体と MySQL のダンプ）。
   2026-09-24 取得分がこのMac miniの `~/tomapufarm-migration/backups/2026-09-24/` にある（sha256 付き）
2. Secrets を登録し、GitHub Actions で `www/site/` に転送する（この時点では公開中のサイトは変わらない）
   → 2026-09-24 済み。`https://tomapufarm.sakura.ne.jp/site/` で届いていることを確認できる（絶対パスの素材は崩れて見える）
3. `www/` 直下に `.htaccess` が無いか確認する。あれば親フォルダの設定として `www/site/` にも効くため、
   中身を読んで新サイトに影響しないか見る
4. さくらのコントロールパネル → ドメイン/SSL → `tomapufarm.com` の公開フォルダを `/www/wp` から `/www/site` に変更する
   → **2026-09-24 18:20 切り替え済み。** 確認スクリプトは全項目 OK。フォームは両方テスト送信し、受信を確認済み（2026-09-25）
5. **確認スクリプトを流す**

   ```bash
   ./scripts/check-site.sh https://tomapufarm.com
   ```

   加えて `/contact/` と `/form/` から実際に1件ずつ送信して、メールが届くことを見る
   （お問い合わせ → `info@tomapufarm.com`、ご注文 → `info@hokkaido-kaitakushi.co.jp`。どちらも旧サイトの設定値）
6. Google Search Console にサイトマップ `https://tomapufarm.com/sitemap.xml` を再送信する
7. しばらく（2週間程度、**2026-10-08 以降**）問題がなければ、`www/wp` とデータベースを削除する
8. さくらのコントロールパネルで PHP を 8系に上げる（7.4 はサポート終了済み。
   撤去後は `api/contact.php` しか動かず、8系でも問題なく動作する）

**戻し方:** 手順4の公開フォルダを `/www/wp` に戻す。WordPress を消す（手順7）までは即座に戻せる。

DNS とメールは変更しません。ドメインもさくらのままです。

**削除後に消えるもの:** `/wp-admin/` のログイン画面はなくなります（`.htaccess` で404にしてある）。
静的サイトに管理画面はありません。更新は Codex 経由になります。もし「ログインして書く」形が
必要になったら、`nihon-shintatsu-static` の `admin/`（PHP + SQLite の自前CMS）を移植できます。

## 残っている宿題

- 部品の脆弱性警告が3件残っている（2026-09-25、`npm audit`）。いずれもビルド時の道具（Astro 本体・sharp・esbuild）で、公開中の静的ファイルには影響しない。解消には Astro 5 → 7 の大きな更新が必要なので、見た目が変わらないことを確かめながら別作業で行う

- Resend のドメイン認証と `api/config.php` の設置（上記 3）
- `/form/`（ギフト注文フォーム）は旧サイトのまま見出しが無く、デザインもサイト本体と揃っていない。
  必要なら別途整える
- お知らせ一覧で `TOMAPU FARM 公式サイトができました！` と
  `TOMAPU FARM ひまわりGARDEN　開催！！` にはサムネイルが無く、枠が空く（旧サイトと同じ状態）。
  `thumbnail:` を足せば埋まる
- 画像はすべて PNG のまま。**WebP に変換すると 30MB → 7MB（76%削減）** になることを実測した。
  ただし全ページのマークアップと CSS の書き換えが必要なため、今回は「見た目を変えない」ことを
  優先して手を付けていない
