import { getCollection, type CollectionEntry } from 'astro:content';

export type News = CollectionEntry<'news'>;

/** 公開中のお知らせを新しい順に返します（draft: true のものは除外）。 */
export async function getNews(): Promise<News[]> {
  // NEWS_PREVIEW=1 のときは下書きも含めます。
  // 手元の `npm run dev` と、プレビュー用サイトのビルドで使います。
  const preview = process.env.NEWS_PREVIEW === '1';
  const all = await getCollection('news', ({ data }) => preview || data.draft !== true);
  return all.sort((a, b) => (a.data.date < b.data.date ? 1 : -1));
}

/** 記事のURL */
export function newsUrl(entry: News): string {
  return `/blog/${entry.data.slug}/`;
}

/** '2023-08-08' → '2023.08.08' */
export function newsDate(entry: News): string {
  return entry.data.dateLabel ?? entry.data.date.replaceAll('-', '.');
}

/** 1ページあたりの記事数（現行サイトと同じ4本） */
export const PER_PAGE = 4;
