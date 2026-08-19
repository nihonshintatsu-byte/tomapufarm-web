import { defineConfig } from 'astro/config';

// トマップファーム公式サイト（静的サイト）
// 出力は dist/ 。GitHub Actions がこの dist/ をさくらサーバーへ転送します。
export default defineConfig({
  site: 'https://tomapufarm.com',
  trailingSlash: 'always',
  build: {
    format: 'directory',   // /about/index.html の形で書き出す（現行URLと同じ）
  },
  compressHTML: false,
});
