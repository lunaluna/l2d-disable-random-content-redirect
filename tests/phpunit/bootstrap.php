<?php
/**
 * PHPUnit の起動処理.
 *
 * テストは wp-env の tests-cli コンテナで動かす前提.
 * コンテナには PHPUnit 9.6, WordPress のテスト用ライブラリ(環境変数 WP_TESTS_DIR),
 * Yoast PHPUnit Polyfills(~/.composer/vendor の下)が入っているので,
 * このリポジトリに Composer の依存を足さずに動く.
 *
 * @package L2D_Disable_Random_Content_Redirect
 */

$l2ddrcr_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $l2ddrcr_tests_dir ) {
	// wp-env の tests-cli コンテナでの既定の場所.
	$l2ddrcr_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $l2ddrcr_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- コマンドラインへの出力.
	echo "WordPress のテスト用ライブラリが見つかりません: {$l2ddrcr_tests_dir}" . PHP_EOL;
	exit( 1 );
}

// WordPress のテストの起動処理は Polyfills の場所を求めるので, 先に定数で渡す.
// 環境変数で指定されていればそれを使い, 無ければ wp-env のコンテナでの場所を使う.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	$l2ddrcr_polyfills = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
	if ( ! $l2ddrcr_polyfills ) {
		$l2ddrcr_polyfills = getenv( 'HOME' ) . '/.composer/vendor/yoast/phpunit-polyfills';
	}
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $l2ddrcr_polyfills );
}

require_once $l2ddrcr_tests_dir . '/includes/functions.php';

/**
 * プラグインを読み込む.
 *
 * 有効化(activate)の処理は通さず, ファイルを読み込むだけにする.
 * 有効化・無効化の処理は, テストの中で関数を直接呼んで確かめる.
 *
 * @return void
 */
function l2ddrcr_tests_load_plugin() {
	require dirname( __DIR__, 2 ) . '/l2d-disable-random-content-redirect.php';
}
tests_add_filter( 'muplugins_loaded', 'l2ddrcr_tests_load_plugin' );

require $l2ddrcr_tests_dir . '/includes/bootstrap.php';

// テストの共通部分. WP_UnitTestCase を継承するので, WordPress のテストの起動処理のあとに読む.
require __DIR__ . '/includes/class-test-l2ddrcr-base.php';
