#!/bin/bash
# シングルサイトの HTTP の通しテスト.
#
# 使い方: tests/e2e/test-single.sh <作業ディレクトリ> <ポート>
#   作業ディレクトリは setup.sh single で作ったもの. 何度流しても同じ結果になるように,
#   最初にプラグインを止めてテストデータを片付ける.
WORK=${1:?作業ディレクトリを指定してください}
PORT=${2:?ポートを指定してください}
ENV_DIR="$WORK/env"
source "$(dirname "$0")/lib.sh"
T="localhost:$PORT"

# --- 準備
W plugin deactivate $PLUGIN_SLUG >/dev/null
for o in l2ddrcr_e2e_disable l2ddrcr_e2e_core_filter l2ddrcr_e2e_cpt l2ddrcr_rules_state; do W option delete $o >/dev/null; done
# 前回の実行で残ったテスト用の投稿(スラッグが random で始まるもの)を完全に消す.
for id in $(W post list --post_type=post,page,random --post_status=any,trash --name=random --field=ID) \
	$(W post list --post_type=post --post_status=any,trash --name=random-notes --field=ID); do
	W post delete "$id" --force >/dev/null
done
W rewrite flush >/dev/null
ABOUT=$(W post list --post_type=page --name=about --field=ID)
rm_debug_log() { (cd "$ENV_DIR" && wp-env run cli -- bash -c 'rm -f wp-content/debug.log' >/dev/null 2>&1); }
rm_debug_log

H "S1 プラグインなし(コアの挙動)"
X S1 $T "/random/::302" "/random/category/news/::302" "/random/author/admin/::302" "/category/news/?random::302" "/?s=hello&random::301>/?s=hello"
ST S1 - 5 none

H "S2 有効化"
W plugin activate $PLUGIN_SLUG
ST S2-直後 - 0 none
X S2 $T "/random/::404" "/random/category/news/::404" "/random/author/admin/::404" "/category/news/?random::200#category-news" "/?s=hello&random::200#search" "/hello2/::200#single" "/::200#home"
ST S2-後 - 0 disabled

H "S3 無効化"
W plugin deactivate $PLUGIN_SLUG
ST S3-直後 - 0 none
X S3 $T "/random/::302" "/random/category/news/::302" "/random/author/admin/::302" "/category/news/?random::302"
ST S3-後 - 5 none

H "S4 再び有効化"
W plugin activate $PLUGIN_SLUG
X S4 $T "/random/::404" "/random/category/news/::404"
ST S4 - 0 disabled

H "S5 独自フィルターを false に(パーマリンク設定の保存なしで, 次のアクセスで戻る)"
W option update l2ddrcr_e2e_disable 0 >/dev/null
X S5 $T "/random/::302" "/random/category/news/::302" "/category/news/?random::302"
ST S5 - 5 enabled

H "S6 独自フィルターを true に"
W option update l2ddrcr_e2e_disable 1 >/dev/null
X S6 $T "/random/::404" "/random/category/news/::404" "/category/news/?random::200#category-news"
ST S6 - 0 disabled
W option delete l2ddrcr_e2e_disable >/dev/null

H "S7 ほかのプラグインが先に false を返す → 外す(レビューの指摘 1)"
W option update l2ddrcr_e2e_disable 0 >/dev/null
W option update l2ddrcr_e2e_core_filter 'false@5' >/dev/null
X S7 $T "/random/::404" "/category/news/?random::200#category-news"
ST S7 - 0 disabled
W option delete l2ddrcr_e2e_core_filter >/dev/null
X S7-外す $T "/random/::302" "/category/news/?random::302"
ST S7-外す - 5 enabled
W option delete l2ddrcr_e2e_disable >/dev/null
X S7-戻す $T "/random/::404"
ST S7-戻す - 0 disabled

H "S8 ほかのプラグインが後で true を返す → 外す(レビューの指摘 1)"
W option update l2ddrcr_e2e_core_filter 'true@20' >/dev/null
X S8 $T "/random/::302" "/random/category/news/::302"
ST S8 - 5 enabled
W option delete l2ddrcr_e2e_core_filter >/dev/null
X S8-外す $T "/random/::404" "/random/category/news/::404"
ST S8-外す - 0 disabled

H "S9 トップレベルの固定ページ random と子ページ about/random"
PAGE=$(W post create --post_type=page --post_title=Random --post_name=random --post_status=publish --porcelain)
CHILD=$(W post create --post_type=page --post_title=ChildRandom --post_name=random --post_parent="$ABOUT" --post_status=publish --porcelain)
X S9 $T "/random/::200#page-id-$PAGE" "/random/category/news/::404" "/about/random/::200#page-id-$CHILD"
W plugin deactivate $PLUGIN_SLUG >/dev/null
X S9-コア $T "/random/::200#page-id-$PAGE" "/random/category/news/::302" "/about/random/::200#page-id-$CHILD"
W plugin activate $PLUGIN_SLUG >/dev/null
W post delete "$PAGE" --force >/dev/null

H "S10 404 の推測リダイレクト(スラッグが random で始まる投稿)"
X S10-子ページ $T "/random/::301>/about/random/"
W post delete "$CHILD" --force >/dev/null
MINE=$(W post create --post_title=MyRandom --post_name=my-random-post --post_status=publish --porcelain)
X S10-含むだけ $T "/random/::404"
NOTES=$(W post create --post_title=RandomNotes --post_name=random-notes --post_status=publish --porcelain)
X S10-始まる $T "/random/::301>/random-notes/"
W post delete "$MINE" "$NOTES" --force >/dev/null

H "S11 投稿 random(パーマリンク /%postname%/)"
POST=$(W post create --post_title=RandomPost --post_name=random --post_status=publish --porcelain)
X S11 $T "/random/::200#postid-$POST" "/random/category/news/::404"
W post delete "$POST" --force >/dev/null

H "S12 カスタム投稿タイプ random(has_archive)"
W option update l2ddrcr_e2e_cpt 1 >/dev/null
CPT=$(W post create --post_type=random --post_title=Foo --post_name=foo --post_status=publish --porcelain)
W rewrite flush >/dev/null # 投稿タイプを登録したので, サイト側の通常の手順としてルールを作り直す.
X S12 $T "/random/::200#post-type-archive-random" "/random/foo/::200#single-random" "/random/category/news/::404"
W plugin deactivate $PLUGIN_SLUG >/dev/null
X S12-コア $T "/random/::200#post-type-archive-random" "/random/foo/::200#single-random" "/category/news/?random::302"
W plugin activate $PLUGIN_SLUG >/dev/null
W post delete "$CPT" --force >/dev/null
W option delete l2ddrcr_e2e_cpt >/dev/null
W rewrite flush >/dev/null
X S12-後片付け $T "/random/::404"

H "S13 debug.log"
DEBUG_LOG

FINISH シングルサイト
