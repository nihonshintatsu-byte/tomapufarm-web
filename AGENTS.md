# このリポジトリの扱い方（Codex / Claude Code 共通）

トマップファーム公式サイト **https://tomapufarm.com** の中身です。
2026年8月に WordPress から静的サイト（Astro）へ入れ替えました。

このファイルは、AI エージェントが作業するときのルールです。
**依頼者は農業の人で、コードは書きません。専門用語で返さず、何をどう変えたかを日本語で説明してください。**

---

## 1. まず知っておくこと

| 項目 | 内容 |
|---|---|
| 中身 | Astro（静的サイト生成）。サーバー側で動くのは問い合わせフォームの PHP だけ |
| 公開先 | さくらインターネットのレンタルサーバー |
| 公開の流れ | `main` に入ると GitHub Actions が自動でビルドしてさくらへ転送する |
| デザイン | 2020年に作られた既存デザインをそのまま移植した。**勝手に変えない** |
| 運営会社 | 株式会社北海道開拓使（代表 高橋ひかり） |

**デザイン・レイアウト・配色・フォントは変更しない。** 依頼が「文章を直す」「お知らせを足す」
「写真を差し替える」なら、CSS には触らないこと。見た目を変えたいという明確な依頼があったときだけ
`public/assets/css/` を触る。

---

## 2. どのファイルを触るか

```
src/content/news/          お知らせの記事（1本＝1ファイル）  ← いちばんよく触る
src/data/site.json         電話番号・住所・SNS のリンクなど
src/pages/                 各ページの中身（about, company, service, transaction, english, contact, form）
src/components/            全ページ共通のヘッダー・フッター
src/layouts/Base.astro     <head> の中身（タイトル、説明文、Googleアナリティクス）
public/assets/images/      デザイン用の画像（既存デザインの部品。基本は触らない）
public/assets/news/        お知らせに載せる写真
public/assets/css/         デザインの定義（原則そのまま）
public/api/contact.php     問い合わせフォームの受け口
```

触ってはいけないもの：

- `dist/` … ビルドで作られる出力。手で編集しても次のビルドで消える
- `public/wp-content/` … 旧サイトのURLで配られていた PDF と OGP画像の置き場（外部リンク対策）
- `public/api/config.php` … サーバー上にだけ置く設定（APIキー）。リポジトリには入れない

---

## 3. お知らせを1本追加する

`src/content/news/` に `YYYY-MM-DD-英数字の名前.md` というファイルを作ります。

```markdown
---
title: "さくらんぼの予約受付をはじめました"
date: "2026-06-01"
slug: "cherry-2026"
thumbnail: "/assets/news/2026-06-01-cherry-thumb.jpg"
image: "/assets/news/2026-06-01-cherry.jpg"
draft: false
---

本文をここに書きます。段落を分けるときは1行空けます。
行の途中で改行したいときは行末に `<br />` を置きます。
```

| 項目 | 説明 |
|---|---|
| `title` | 一覧とページに出る見出し |
| `date` | `YYYY-MM-DD`。この順で新しいものから並ぶ |
| `slug` | URL に使う文字。`/blog/cherry-2026/` になる。**英数字とハイフンだけ**にする |
| `thumbnail` | 一覧に出る小さい写真。無くてもよい |
| `image` | 記事の右側に大きく出る写真。無くてもよい |
| `draft` | `true` の間はサイトに出ない。下書きに使う |

- 写真は `public/assets/news/` に置き、`/assets/news/ファイル名` の形で書く
- ファイル名は日付＋内容（例 `2026-06-01-cherry.jpg`）。日本語のファイル名は使わない
- 一覧は1ページ4本で自動的にページ送りされる。トップページには最新3本が自動で出る
- **既存の記事の `slug` は変えない。** 変えると外部からのリンクや検索結果が切れる

---

## 4. 会社情報や電話番号を変える

`src/data/site.json` を直します。ここを直すと、フッター・会社情報ページ・
プライバシーポリシーなど、その値を使っている全部の場所が一度に変わります。

## 5. ページの文章を直す

`src/pages/` の該当ファイルを直します。HTML のタグ（`<p class="txt">` など）は
デザインに直結しているので**構造は変えず、文字だけ**差し替えてください。

## 6. 新しいページを増やす

`src/pages/新しい名前.astro` を作り、既存ページ（例 `transaction.astro`）を写して中身を変えます。
できたら次の2つも忘れないこと。

1. `src/pages/sitemap.xml.ts` の `STATIC_PATHS` にパスを足す
2. ナビゲーションに載せるなら `src/components/Header.astro` と `Footer.astro` に足す

## 7. URLを変えたとき

古いURLからの転送を必ず用意します。`src/data/redirects.json` に
`{ "from": "/古いURL/", "to": "/新しいURL/" }` を足すと、転送用のページが自動で作られます。

---

## 8. 作業の終わり方（必ず守る）

1. `npm run build` が最後まで通ることを確認する。**通らない状態でコミットしない**
2. 変更を1つの Pull Request にまとめる。PR の説明は「何をどう変えたか」を日本語で
3. `main` に直接 push しない。PR のビルド確認が緑になってからマージする
4. マージすると数分で本番サイトに出る。反映されない場合は GitHub の Actions タブを見る

ローカルで見た目を確認するとき：

```bash
npm install
npm run dev
```

`http://localhost:4321/` で開きます。ただし問い合わせフォームの送信は PHP が必要なので、
そこだけは `npm run build` してから `php -S localhost:4400 -t dist` で確認します。

---

## 9. 問い合わせフォームについて

- `/contact/` … 入力 → 確認画面 → 送信 → `/contact/thanks/`
- `/form/` … ギフトの注文フォーム。確認画面はなく、そのまま送信
- 受け口はどちらも `public/api/contact.php`
- メールは Resend（外部のメール送信サービス）経由で送る。設定はサーバー上の
  `api/config.php` にある（リポジトリには無い）。Resend が使えないときは
  さくらのメール送信に自動で切り替わる
- 項目を増やす・減らすときは、**ページ側と `contact.php` の項目定義の両方**を直す。
  片方だけ直すとメールに出ない

---

## 10. 全社共通のルール

日本信達グループ共通のルールは `nihonshintatsu-byte/shintatsu-docs` の
`AGENTS.md` と `WORKSPACE.md` にあります。端末をまたぐ引き継ぎは `HANDOFF.md` を使います。
