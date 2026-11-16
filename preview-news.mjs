import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';

const root = process.cwd();
const directory = path.join(root, 'editorial/news-drafts');
if (process.argv[2]) {
  const source = fs.readFileSync(process.argv[2], 'utf8').replace(/\r/g, '');
  const chunks = source.split(/─+\n/).filter(s => /^\s*\d+\./.test(s));
  fs.mkdirSync(directory, { recursive: true });
  const posts = chunks.map(chunk => {
    const match = chunk.trim().match(/^(\d+)\. (.*?)｜([^\n]+)/);
    const [, number, dateLabel, title] = match;
    const body = chunk.split('WordPress投稿本文\n')[1].split('\n掲載元・URL')[0].trim();
    const tail = chunk.split('\n掲載元・URL')[1];
    const links = [...tail.matchAll(/https?:\/\/[^\s\u2028]+/g)].map(m => m[0]);
    const date = /^\d{4}\.\d{2}\.\d{2}$/.test(dateLabel) ? dateLabel.replaceAll('.', '-') : null;
    const post = { number: Number(number), title, date, dateLabel, body, links, image: null, draft: true };
    fs.writeFileSync(path.join(directory, `${number.padStart(2, '0')}.md`), `# ${title}\n\n掲載日：${dateLabel}${date ? '' : '（要確認）'}\n状態：未公開・画像は後日追加\n\n${body.split('\n').join('\n\n')}\n\n${links.map(u => `[掲載元の記事・資料](${u})`).join('\n\n')}\n`, 'utf8');
    return post;
  });
  fs.writeFileSync(path.join(directory, 'posts.json'), JSON.stringify(posts, null, 2) + '\n');
  console.log(`下書き ${posts.length} 件、日付確定 ${posts.filter(p => p.date).length} 件`);
}
const escape = s => String(s).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
http.createServer((req, res) => {
  const url = new URL(req.url, 'http://127.0.0.1:4322');
  if (url.pathname.startsWith('/assets/')) {
    const target = path.resolve(root, 'public', '.' + decodeURIComponent(url.pathname));
    const assets = path.resolve(root, 'public/assets') + path.sep;
    if (!target.startsWith(assets) || !fs.existsSync(target) || !fs.statSync(target).isFile()) { res.writeHead(404).end(); return; }
    res.setHeader('Content-Type', target.endsWith('.css') ? 'text/css; charset=utf-8' : target.endsWith('.png') ? 'image/png' : 'application/octet-stream');
    fs.createReadStream(target).pipe(res); return;
  }
  const posts = JSON.parse(fs.readFileSync(path.join(directory, 'posts.json'), 'utf8'));
  const selected = posts.find(p => String(p.number) === url.searchParams.get('article'));
  const content = selected ? `<main id="Main" class="news_page"><section id="Sec01"><div class="inner"><h2 class="top_en_ttl">NEWS</h2><p class="date">${escape(selected.dateLabel)}${selected.date ? '' : '（日付要確認）'}</p><h3 class="ttl">${escape(selected.title)}</h3><div class="article"><div class="flex"><div class="txt_box">${selected.body.split('\n').map(p => `<p>${escape(p)}</p>`).join('')}${selected.links.map((u,i) => `<p><a href="${escape(u)}" target="_blank" rel="noopener">${selected.links.length > 1 ? `掲載元の記事・資料 ${i+1}` : '掲載元の記事はこちら'}</a></p>`).join('')}</div></div></div><p class="back_list"><a href="/">確認一覧へ戻る</a></p></div></section></main>` : `<main style="max-width:1000px;margin:40px auto;padding:24px"><h1>お知らせのプレビュー（11件・未公開）</h1><p>画像は後日追加します。日付が未確定の記事は、元の表記のままです。</p><ol>${posts.map(p => `<li style="margin:24px 0"><p>${escape(p.dateLabel)}${p.date ? '' : ' 【日付要確認】'}</p><a href="/?article=${p.number}">${escape(p.title)}</a></li>`).join('')}</ol></main>`;
  res.setHeader('Content-Type', 'text/html; charset=utf-8');
  res.end(`<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>お知らせ・公開前プレビュー</title><link rel="stylesheet" href="/assets/css/style.css"><link rel="stylesheet" href="/assets/css/news.css"></head><body><div style="padding:16px;background:#fff5ce;color:#333;text-align:center">公開前の確認用／画像は後日追加　<a href="/">11件の一覧</a></div><div id="Content" class="under">${content}</div></body></html>`);
}).listen(4322, '127.0.0.1', () => console.log('Preview: http://127.0.0.1:4322/'));
