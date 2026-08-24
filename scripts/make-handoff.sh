#!/bin/bash
# 高橋社長へ渡す一式（zip）を作ります。
#
#   ./scripts/make-handoff.sh
#
# 中身:
#   START-HERE.html   … 社長が最初に開く案内（このリポジトリの同名ファイルを複製）
#   tomapufarm-web/   … リポジトリ本体（.git 込み。node_modules と dist は含まない）
#
# 渡す側は HTTPS で GitHub に繋ぐため、複製した側の remote は HTTPS に差し替えます。
set -euo pipefail
cd "$(dirname "$0")/.."
REPO="$(pwd)"

DATE="$(date +%Y%m%d)"
NAME="tomapufarm-handoff-${DATE}"
STAGE="$(mktemp -d)/${NAME}"
OUT="${REPO}/../${NAME}.zip"

if [ -n "$(git status --porcelain)" ]; then
  echo "! コミットされていない変更があります。先に commit してください。" >&2
  git status --short >&2
  exit 1
fi

mkdir -p "$STAGE"
cp START-HERE.html "$STAGE/"
git clone -q "$REPO" "$STAGE/tomapufarm-web"
cd "$STAGE/tomapufarm-web"
git remote set-url origin https://github.com/nihonshintatsu-byte/tomapufarm-web.git
git config user.name "Nihon Shintatsu"
git config user.email "nihon.shintatsu@gmail.com"

rm -f "$OUT"
cd "$STAGE/.."
zip -qr "$OUT" "$NAME" -x "*.DS_Store"

echo "できました: $OUT"
ls -lh "$OUT"
echo
echo "中身の確認:"
unzip -l "$OUT" | grep -E "START-HERE.html|HANDOFF.md|AGENTS.md|/\.git/HEAD" || true
