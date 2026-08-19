import type { APIRoute } from 'astro';
import { getNews, newsUrl } from '../lib/news';

// 固定ページの一覧（新しいページを作ったらここに足してください）
const STATIC_PATHS = [
  '/', '/about/', '/company/', '/service/', '/transaction/',
  '/english/', '/blog/', '/contact/',
];

export const GET: APIRoute = async () => {
  const base = 'https://tomapufarm.com';
  // 日本語を含むURLはパーセントエンコードする（sitemap の仕様）
  const enc = (path: string) => base + path.split('/').map(encodeURIComponent).join('/');
  const news = await getNews();
  const urls = [
    ...STATIC_PATHS.map((p) => ({ loc: enc(p), lastmod: undefined as string | undefined })),
    ...news.map((n) => ({ loc: enc(newsUrl(n)), lastmod: n.data.date })),
  ];
  const body = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls.map((u) => `  <url><loc>${u.loc}</loc>${u.lastmod ? `<lastmod>${u.lastmod}</lastmod>` : ''}</url>`).join('\n')}
</urlset>
`;
  return new Response(body, { headers: { 'Content-Type': 'application/xml; charset=UTF-8' } });
};
