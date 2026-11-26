#!/bin/bash
# tests/e2e のテストで共通に使う関数. 各テストのスクリプトから source する.
#
# 使う前に, 次の変数を決めておく.
#   ENV_DIR  wp-env の設定(.wp-env.json)がある作業ディレクトリ(setup.sh が作る <作業ディレクトリ>/env)
#   PORT     WordPress のポート

E2E_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PLUGIN_SLUG=l2d-disable-random-content-redirect
FAILS=0

# WP-CLI を cli コンテナで実行する. wp-env の案内の行と空行は捨てる.
W() { (cd "$ENV_DIR" && wp-env run cli -- wp "$@" 2>/dev/null | grep -v '^ℹ\|^✔' | grep -v '^$'); }

# URL の応答を調べる. 使い方: X <ラベル> <host:port> "<パス>::<期待>" ...
X() { node "$E2E_DIR/expect.mjs" "$@" || FAILS=$((FAILS + 1)); }

# 保存済みの random のルール数と記録(l2ddrcr_rules_state)を調べる.
# 使い方: ST <ラベル> <--url の値. シングルサイトは -> <期待ルール数> <期待の記録. 無ければ none>
# プラグインを読み込まずに調べる(--skip-plugins). 読み込むと wp_loaded でルールの作り直しが走り,
# 調べる操作で状態が変わってしまうため.
ST() {
	local url=() out n s r
	[ "$2" != "-" ] && url=(--url="$2")
	out=$(W "${url[@]}" --skip-plugins=$PLUGIN_SLUG eval '$n = 0; foreach ( (array) get_option( "rewrite_rules" ) as $v ) { if ( false !== strpos( (string) $v, "random=1" ) ) { $n++; } } echo "STATE $n ", get_option( "l2ddrcr_rules_state", "none" ), "\n";' | grep '^STATE')
	read -r _ n s <<<"$out"
	if [ "$n" = "$3" ] && [ "$s" = "$4" ]; then r=PASS; else r=FAIL; FAILS=$((FAILS + 1)); fi
	printf '%s %-12s %-46s 期待 ルール%s 記録%-9s 結果 ルール%s 記録%s\n' "$r" "$1" "(DB ${2})" "$3" "$4" "$n" "$s"
}

# 見出しを出す.
H() { echo; echo "## $*"; }

# debug.log を調べる. 無ければ PASS.
DEBUG_LOG() {
	local log
	log=$(cd "$ENV_DIR" && wp-env run cli -- bash -c 'cat wp-content/debug.log 2>/dev/null' 2>/dev/null | grep -v '^ℹ\|^✔')
	if [ -z "$log" ]; then
		echo "PASS debug.log なし"
	else
		echo "FAIL debug.log あり:"
		echo "$log" | tail -10
		FAILS=$((FAILS + 1))
	fi
}

# 最後に結果をまとめて出し, 失敗があれば終了コード 1 にする.
FINISH() {
	echo
	echo "$1: FAIL の数 = $FAILS"
	[ "$FAILS" -eq 0 ]
}
