#!/bin/bash
# マルチサイト(サブディレクトリ型・サブドメイン型)の HTTP の通しテスト.
#
# 使い方: tests/e2e/test-multisite.sh <subdir|subdomain> <作業ディレクトリ> <ポート>
#   作業ディレクトリは setup.sh で同じ型を指定して作ったもの. サイトは main・site2・site3 の 3 つで,
#   テストの中で site4 を作る(最初に消してから作るので, 何度流しても同じ結果になる).
#
# サイトの URL
#   サブディレクトリ型: localhost:<ポート>/site2/ など. メインサイトのアーカイブには /blog/ が付く.
#   サブドメイン型:     site2.localhost:<ポート>/ など.
MODE=${1:?subdir / subdomain のどちらかを指定してください}
WORK=${2:?作業ディレクトリを指定してください}
PORT=${3:?ポートを指定してください}
ENV_DIR="$WORK/env"
source "$(dirname "$0")/lib.sh"

# サイトの名前から, WP-CLI の --url と, HTTP で調べるときのホストとパスの先頭を返す.
URL() {
	if [ "$1" = main ]; then echo "localhost:$PORT"
	elif [ "$MODE" = subdir ]; then echo "localhost:$PORT/$1"
	else echo "$1.localhost:$PORT"; fi
}
HOST() { if [ "$MODE" = subdir ] || [ "$1" = main ]; then echo "localhost:$PORT"; else echo "$1.localhost:$PORT"; fi; }
BASE() { if [ "$MODE" = subdir ] && [ "$1" != main ]; then echo "/$1/"; else echo "/"; fi; }

# 1 つのサイトの /random/ 系の URL をまとめて調べる.
# 使い方: SITE <ラベル> <main|site2|...> <random の期待> <?random の期待>
SITE() {
	local b cat
	b=$(BASE "$2")
	cat="${b}category/uncategorized/"
	[ "$MODE" = subdir ] && [ "$2" = main ] && cat="/blog/category/uncategorized/"
	X "$1" "$(HOST "$2")" "${b}random/::$3" "${b}random/author/admin/::$3" "${cat}?random::$4"
}

# --- 準備: プラグインを止め, site4 を消し, 全サイトのルールをコアだけで作り直す.
W plugin deactivate $PLUGIN_SLUG --network >/dev/null
W --url="$(URL site2)" plugin deactivate $PLUGIN_SLUG >/dev/null
ID4=$(W site list --field=blog_id --url="http://$(URL site4)/")
[ -n "$ID4" ] && W site delete "$ID4" --yes >/dev/null
for s in main site2 site3; do
	W --url="$(URL $s)" option delete l2ddrcr_e2e_disable >/dev/null
	W --url="$(URL $s)" option delete l2ddrcr_rules_state >/dev/null
	W --url="$(URL $s)" rewrite flush >/dev/null
done
(cd "$ENV_DIR" && wp-env run cli -- bash -c 'rm -f wp-content/debug.log' >/dev/null 2>&1)

H "M1 プラグインなし(コアの挙動)"
for s in main site2 site3; do SITE "M1-$s" $s 302 302; done
for s in main site2 site3; do ST "M1 $s" "$(URL $s)" 5 none; done

H "M2 ネットワークで有効化"
W plugin activate $PLUGIN_SLUG --network
for s in main site2 site3; do ST "M2-直後 $s" "$(URL $s)" 0 none; done
for s in main site2 site3; do SITE "M2-$s" $s 404 200; done
for s in main site2 site3; do ST "M2-後 $s" "$(URL $s)" 0 disabled; done

H "M3 ネットワーク有効化中に新しいサイトを作る"
W site create --slug=site4 --title="Site 4" >/dev/null
SITE M3-site4 site4 404 200
ST "M3 site4" "$(URL site4)" 0 disabled

H "M4 site2 だけ独自フィルターを false に(次のアクセスでそのサイトだけ戻る)"
W --url="$(URL site2)" option update l2ddrcr_e2e_disable 0 >/dev/null
SITE M4-site2 site2 302 302
SITE M4-main main 404 200
ST "M4 site2" "$(URL site2)" 5 enabled
W --url="$(URL site2)" option delete l2ddrcr_e2e_disable >/dev/null
SITE M4-site2戻す site2 404 200
ST "M4-戻す site2" "$(URL site2)" 0 disabled

H "M5 site3 だけ, ほかのプラグインが後で true を返す(レビューの指摘 1)"
W --url="$(URL site3)" option update l2ddrcr_e2e_core_filter 'true@20' >/dev/null
SITE M5-site3 site3 302 302
SITE M5-main main 404 200
ST "M5 site3" "$(URL site3)" 5 enabled
W --url="$(URL site3)" option delete l2ddrcr_e2e_core_filter >/dev/null
SITE M5-site3外す site3 404 200
ST "M5-外す site3" "$(URL site3)" 0 disabled

H "M6 ネットワークで無効化"
W plugin deactivate $PLUGIN_SLUG --network
for s in main site2 site3 site4; do ST "M6-直後 $s" "$(URL $s)" 0 none; done
for s in main site2 site3 site4; do SITE "M6-$s" $s 302 302; done
for s in main site2 site3 site4; do ST "M6-後 $s" "$(URL $s)" 5 none; done

H "M7 site2 から有効化しようとしても, ネットワーク単位の有効化になる(ヘッダーの Network: true)"
W --url="$(URL site2)" plugin activate $PLUGIN_SLUG
STATUS=$(W plugin list --name=$PLUGIN_SLUG --field=status)
if [ "$STATUS" = "active-network" ]; then
	echo "PASS M7          プラグインの状態 期待 active-network 結果 $STATUS"
else
	echo "FAIL M7          プラグインの状態 期待 active-network 結果 $STATUS"
	FAILS=$((FAILS + 1))
fi
SITE M7-site2 site2 404 200
SITE M7-main main 404 200
SITE M7-site3 site3 404 200
W plugin deactivate $PLUGIN_SLUG --network
SITE M7-無効化 site2 302 302
SITE M7-無効化 main 302 302

H "M8 debug.log"
DEBUG_LOG

if [ "$MODE" = subdir ]; then FINISH "マルチサイト(サブディレクトリ型)"; else FINISH "マルチサイト(サブドメイン型)"; fi
