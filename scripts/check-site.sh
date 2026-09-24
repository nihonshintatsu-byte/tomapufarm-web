#!/bin/bash
# サイトが正しく配られているか確認するスクリプト。
#
#   ./scripts/check-site.sh                          … 手元のプレビュー（http://localhost:4400）を確認
#   ./scripts/check-site.sh https://tomapufarm.com   … 本番を確認
#
# 切り替え（WordPress 撤去）の直後に本番へ向けて実行することを想定しています。
# メールは送りません（フォームは、わざと不備のある内容を送って検証だけ動かします）。

BASE="${1:-http://localhost:4400}"
BASE="${BASE%/}"
UA='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120 Safari/537.36'
NG=0

ok()   { printf '  \033[32m OK \033[0m %s\n' "$1"; }
ng()   { printf '  \033[31m NG \033[0m %s\n' "$1"; NG=$((NG+1)); }
head2() { printf '\n\033[1m%s\033[0m\n' "$1"; }

code() { curl -s -o /dev/null -m 30 -A "$UA" -w '%{http_code}' "$1"; }
body() { curl -s -m 30 -A "$UA" "$1"; }

echo "確認先: $BASE"

head2 "1. ページが表示できるか"
# 状態コードだけでは足りません（php -S は存在しないパスにも 200 と index.html を返す）。
# 各ページが自分の canonical を持っていることまで確かめて、中身のすり替わりを検出します。
for p in / /about/ /company/ /service/ /transaction/ /english/ /blog/ \
         /contact/ /contact/thanks/ /form/ \
         /blog/website-open/ /blog/seika-yoyaku-hanbai/ \
         /blog/kinkyo-houkoku-supporter/ /blog/himawari-garden-2023/ ; do
  c=$(code "$BASE$p")
  if [ "$c" != "200" ]; then
    ng "$p （$c が返りました）"
    continue
  fi
  b=$(body "$BASE$p")
  case "$b" in
    *"rel=\"canonical\" href=\"https://tomapufarm.com$p\""*) ok "$p" ;;
    *) ng "$p は 200 ですが、そのページの中身になっていません（別のページが返っている可能性）" ;;
  esac
done

head2 "2. 素材が置かれているか"
for a in /assets/css/style.css /assets/css/top.css /assets/js/common.js /assets/js/jquery-2.1.4.min.js \
         /assets/js/contact-form.js /assets/images/common/logo.png /assets/images/top/movie.mp4 \
         /assets/ogp.png /assets/pdf/tomapu_A4_PDF_JPN_fin.pdf /assets/pdf/tomapu_A4_PDF_ENG_fin.pdf \
         /robots.txt /sitemap.xml ; do
  c=$(code "$BASE$a")
  [ "$c" = "200" ] && ok "$a" || ng "$a （$c が返りました）"
done

head2 "3. 旧サイトのURLから転送されるか"
check_redirect() {
  local from="$1" to="$2"
  local c loc b
  c=$(curl -s -o /dev/null -m 30 -A "$UA" -w '%{http_code}' "$BASE$from")
  loc=$(curl -s -o /dev/null -m 30 -A "$UA" -w '%{redirect_url}' "$BASE$from")
  if [ "$c" = "301" ] || [ "$c" = "302" ]; then
    case "$loc" in *"$to") ok "$from → $to （$c）" ;; *) ng "$from の転送先が $loc になっています" ;; esac
  elif [ "$c" = "200" ]; then
    b=$(body "$BASE$from")
    case "$b" in *"url=$to"*) ok "$from → $to （HTMLで転送）" ;; *) ng "$from が転送されていません" ;; esac
  else
    ng "$from （$c が返りました）"
  fi
}
check_redirect "/blog/%e3%81%8a%e7%9f%a5%e3%82%89%e3%81%9b%e3%83%86%e3%82%b9%e3%83%8801/" "/blog/website-open/"
check_redirect "/blog/%e3%81%8a%e7%9f%a5%e3%82%89%e3%81%9b%e3%83%86%e3%82%b9%e3%83%8803/" "/blog/seika-yoyaku-hanbai/"
check_redirect "/comingsoon/" "/"
check_redirect "/page/2/" "/blog/"

# 4 と 5 は .htaccess が効くサーバー（さくら）でのみ意味があります。
# 手元の `php -S` は .htaccess を読まず、存在しないパスにも 200 を返すため飛ばします。
if [ "${BASE#http://localhost}" = "$BASE" ] && [ "${BASE#http://127.0.0.1}" = "$BASE" ]; then
  head2 "4. 見つからないページの扱い"
  c=$(code "$BASE/zzz-not-exist-check/")
  [ "$c" = "404" ] && ok "存在しないURLは 404 を返す" || ng "存在しないURLで $c が返りました（404 が正しい）"

  head2 "5. WordPress の入口が閉じているか"
  for p in /wp-admin/ /wp-login.php /wp-json/ /xmlrpc.php ; do
    c=$(code "$BASE$p")
    case "$c" in 404|403) ok "$p は閉じている（$c）" ;; *) ng "$p が $c を返しています。WordPress が残っている可能性があります" ;; esac
  done
else
  head2 "4-5. 404 と WordPress の入口（手元では確認できないため飛ばします）"
  printf '  \033[33m --  \033[0m .htaccess を読まない php -S のため。本番URLを指定して実行してください\n'
fi

head2 "6. 問い合わせフォームの受け口（メールは送りません）"
r=$(curl -s -m 30 -A "$UA" -X POST "$BASE/api/contact.php" -d "your-name=" -d "your-email=")
case "$r" in
  *'"ok":false'*) ok "PHP が動作し、入力チェックが返っている: $r" ;;
  *'"ok":true'*)  ng "不備のある内容が通ってしまいました: $r" ;;
  *)              ng "JSON が返りません。PHP が動いていない可能性があります: $(echo "$r" | head -c 120)" ;;
esac

if [ "${BASE#https://tomapufarm.com}" != "$BASE" ]; then
  head2 "7. www ありでのアクセス"
  loc=$(curl -s -o /dev/null -m 30 -A "$UA" -w '%{redirect_url}' https://www.tomapufarm.com/)
  case "$loc" in https://tomapufarm.com/*) ok "www なしへ転送される" ;; *) ng "www ありが転送されていません（転送先: ${loc:-なし}）" ;; esac
fi

printf '\n'
if [ "$NG" -eq 0 ]; then
  printf '\033[32m問題は見つかりませんでした。\033[0m\n'
else
  printf '\033[31m%d 件、確認が必要です。\033[0m\n' "$NG"
fi
exit "$NG"
