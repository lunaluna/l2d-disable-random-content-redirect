#!/bin/bash
# HTTP の通しテスト用の wp-env の環境を作る.
#
# 使い方: tests/e2e/setup.sh <single|subdir|subdomain> <WordPress の zip> <作業ディレクトリ> <ポート>
#   例: tests/e2e/setup.sh subdomain ~/wordpress-7.2-RC3.zip /tmp/l2ddrcr-subdomain 8904
#
# 作業ディレクトリの中に core/(zip を展開した WordPress)と env/(.wp-env.json)を作る.
# 作業ディレクトリは新しく作る. 既に core/ があれば止まる.
#
# 決めていること(2026-11 に wp-env 8.4.0 で確かめた内容):
# - コアは zip を展開したものを使う. wp-env は core のディレクトリに wp-config.php を書き込むので,
#   手元の参照用のソースを直接指定しない.
# - プラグインは mappings でマウントし, 自動では有効化しない(有効化する前の挙動も確かめるため).
# - .htaccess は wp-env が作らないので, ここで置く. 無いとパーマリンクの URL がすべて 404 になる.
#   マルチサイトの .htaccess は wp-admin/includes/network.php が出す内容に合わせる.
# - マルチサイトは, シングルサイトで起動してから wp core multisite-convert で変える.
#   変えたあとに wp-env start をし直すと wp-config.php が作り直されるおそれがあるので, し直さない.
set -euo pipefail

MODE=${1:?single / subdir / subdomain のどれかを指定してください}
ZIP=${2:?WordPress の zip を指定してください}
WORK=${3:?作業ディレクトリを指定してください}
PORT=${4:?ポートを指定してください}
REPO=$(cd "$(dirname "$0")/../.." && pwd)

case "$MODE" in single | subdir | subdomain) ;; *)
	echo "MODE は single / subdir / subdomain のどれかです: $MODE"
	exit 1
	;;
esac

mkdir -p "$WORK"
WORK=$(cd "$WORK" && pwd)
if [ -e "$WORK/core" ]; then
	echo "既にあります: $WORK/core(作り直すときは, wp-env destroy をしてから作業ディレクトリを消してください)"
	exit 1
fi
mkdir -p "$WORK/core" "$WORK/env"
unzip -q "$ZIP" -d "$WORK/core"
CORE="$WORK/core/wordpress"
grep "wp_version = " "$CORE/wp-includes/version.php"

cat >"$WORK/env/.wp-env.json" <<EOF
{
	"core": "../core/wordpress",
	"port": $PORT,
	"testsPort": $((PORT + 1)),
	"config": {
		"WP_DEBUG": true,
		"WP_DEBUG_LOG": true,
		"WP_DEBUG_DISPLAY": false
	},
	"mappings": {
		"wp-content/plugins/l2d-disable-random-content-redirect": "$REPO",
		"wp-content/mu-plugins": "$REPO/tests/e2e/mu-plugins"
	}
}
EOF

cd "$WORK/env"
wp-env start
W() { wp-env run cli -- wp "$@" 2>/dev/null | grep -v '^ℹ\|^✔' | grep -v '^$' || true; }

# .htaccess を置く.
if [ "$MODE" = single ]; then
	cat >"$CORE/.htaccess" <<'EOF'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
EOF
else
	# サブディレクトリ型は先頭にサイトのパス([_0-9a-zA-Z-]+/)が付く. サブドメイン型は付かない.
	if [ "$MODE" = subdir ]; then
		M='([_0-9a-zA-Z-]+/)?'
		R1='$1'
		R2='$2'
	else
		M=''
		R1=''
		R2='$1'
	fi
	cat >"$CORE/.htaccess" <<EOF
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]

# add a trailing slash to /wp-admin
RewriteRule ^${M}wp-admin$ ${R1}wp-admin/ [R=301,L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^${M}(wp-(content|admin|includes).*) ${R2} [L]
RewriteRule ^${M}(.*\.php)$ ${R2} [L]
RewriteRule . index.php [L]
EOF
	if [ "$MODE" = subdomain ]; then
		W core multisite-convert --subdomains --title="L2DDRCR Subdomain"
	else
		W core multisite-convert --title="L2DDRCR Subdirectory"
	fi
	W site create --slug=site2 --title="Site 2"
	W site create --slug=site3 --title="Site 3"
fi

# テストデータ. パーマリンクは /%postname%/. マルチサイトの site3 だけは既定の日付形式のまま残す
# (パーマリンクの構造が違うサイトでも確かめるため).
if [ "$MODE" = single ]; then
	URLS=("localhost:$PORT")
elif [ "$MODE" = subdir ]; then
	URLS=("localhost:$PORT" "localhost:$PORT/site2")
else
	URLS=("localhost:$PORT" "site2.localhost:$PORT")
fi
for u in "${URLS[@]}"; do
	W --url="$u" rewrite structure '/%postname%/'
done
if [ "$MODE" = single ]; then
	W term create category news
	W post create --post_title=Hello2 --post_name=hello2 --post_status=publish --post_category=news
	W post create --post_type=page --post_title=About --post_name=about --post_status=publish
fi

W site list --fields=blog_id,url 2>/dev/null || true
echo "作成しました: $MODE($WORK, http://localhost:$PORT)"
