import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

// お知らせ（BLOG）。src/content/news/*.md を1本＝1記事として扱います。
const news = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/news' }),
  schema: z.object({
    title: z.string(),              // 記事タイトル
    date: z.string(),               // 'YYYY-MM-DD'（表示は YYYY.MM.DD に変換）
    slug: z.string(),               // URL に使う文字列（/blog/<slug>/）
    thumbnail: z.string().optional(),// 一覧に出すサムネイル画像
    image: z.string().optional(),   // 記事の右側に大きく出る画像
    draft: z.boolean().default(false), // true の間はサイトに出ません
  }),
});

export const collections = { news };
