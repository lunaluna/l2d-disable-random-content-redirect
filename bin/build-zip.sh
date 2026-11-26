#!/usr/bin/env bash
#
# 配布用の ZIP を作る.
#
# 使い方: プラグインのルートで bash bin/build-zip.sh
#   {スラッグ}.{バージョン}.zip をプラグインのルートに作る. ZIP の中は {スラッグ}/ の下にファイルが入る.
#
# リリースのワークフロー(.github/workflows/release.yml)が呼ぶ再利用ワークフロー
# lunaluna/l2d-wp-github-update-lib の build-zip(1.3.1)は, このスクリプトを実行してから,
# 開発用のファイル(.git・.github・tests など)が ZIP に入っていないかを検査する.
#
# 処理は l2d-wp-github-update-lib の汎用のビルド処理(dist/bin/build-zip.sh・1.3.1)と同じ.
# このプラグインはライブラリ(lib/l2d-updater)を同梱しないので, ここに同じ処理を置いている.
#
# - スラッグはカレントディレクトリの名前から決める(GitHub Actions ではリポジトリ名になる).
# - バージョンはプラグインのヘッダーの Version: から読む.
# - 除外するファイルは .distignore に書く.

set -euo pipefail

SLUG="$(basename "$(pwd)")"
PLUGIN_FILE="${SLUG}.php"

if [ ! -f "$PLUGIN_FILE" ]; then
	echo "Error: $PLUGIN_FILE が見つかりません. プラグインのルートで実行してください." >&2
	exit 1
fi

VERSION=$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' "$PLUGIN_FILE" |
	head -n1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r')

if [ -z "$VERSION" ]; then
	echo "Error: $PLUGIN_FILE から Version を読めませんでした." >&2
	exit 1
fi

if [ ! -f ".distignore" ]; then
	echo "Error: .distignore が見つかりません. プラグインのルートで実行してください." >&2
	exit 1
fi

echo "Building: ${SLUG} ${VERSION}"

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "${STAGE}/${SLUG}"

# rsync の --exclude-from に渡す前に, コメント行と空行を取り除く.
grep -vE '^[[:space:]]*(#|$)' .distignore >"${STAGE}/excludes.txt"

# 前に作った ZIP が次の ZIP に入らないよう, .distignore の内容に関係なく必ず外す.
# 先頭の / はプラグインのルートを指す.
rsync -a --exclude-from="${STAGE}/excludes.txt" --exclude="/${SLUG}.*.zip" ./ "${STAGE}/${SLUG}/"

ZIP_NAME="${SLUG}.${VERSION}.zip"

(cd "$STAGE" && zip -rq "$ZIP_NAME" "$SLUG")
mv "${STAGE}/${ZIP_NAME}" "./${ZIP_NAME}"

echo "Built: ${ZIP_NAME}"
