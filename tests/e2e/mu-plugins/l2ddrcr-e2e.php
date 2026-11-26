<?php
/**
 * Plugin Name: L2DDRCR E2E helper
 * Description: tests/e2e の HTTP の通しテスト専用. 本番のサイトには入れない.
 *
 * テストの中でオプションを変えて, 次の状態を切り替える. オプションはサイトごとにある.
 *
 * - l2ddrcr_e2e_disable: 独自フィルター l2ddrcr_disable_random_redirect の値('1' / '0'). 無ければ既定のまま.
 * - l2ddrcr_e2e_core_filter: ほかのプラグインの代わりに, コアのフィルター wp_enable_random_content_redirect を変える.
 *   'false@5' なら優先度 5 で false, 'true@20' なら優先度 20 で true を返す. 無ければ何もしない.
 * - l2ddrcr_e2e_cpt: '1' ならカスタム投稿タイプ random(has_archive あり)を登録する.
 *
 * @package L2D_Disable_Random_Content_Redirect
 */

defined( 'ABSPATH' ) || exit;

/**
 * 独自フィルターの値を, オプション l2ddrcr_e2e_disable で上書きする.
 *
 * @param bool $disable 無効化するか.
 * @return bool 無効化するか.
 */
function l2ddrcr_e2e_disable( $disable ) {
	$value = get_option( 'l2ddrcr_e2e_disable' );
	if ( false === $value ) {
		return $disable;
	}
	return '1' === (string) $value;
}
add_filter( 'l2ddrcr_disable_random_redirect', 'l2ddrcr_e2e_disable' );

/**
 * ほかのプラグインの代わりに, 優先度 5(このプラグインより先)で false を返す.
 *
 * @param bool $enabled 有効にするか.
 * @return bool 有効にするか.
 */
function l2ddrcr_e2e_core_filter_early( $enabled ) {
	return 'false@5' === get_option( 'l2ddrcr_e2e_core_filter' ) ? false : $enabled;
}
add_filter( 'wp_enable_random_content_redirect', 'l2ddrcr_e2e_core_filter_early', 5 );

/**
 * ほかのプラグインの代わりに, 優先度 20(このプラグインより後)で true を返す.
 *
 * @param bool $enabled 有効にするか.
 * @return bool 有効にするか.
 */
function l2ddrcr_e2e_core_filter_late( $enabled ) {
	return 'true@20' === get_option( 'l2ddrcr_e2e_core_filter' ) ? true : $enabled;
}
add_filter( 'wp_enable_random_content_redirect', 'l2ddrcr_e2e_core_filter_late', 20 );

/**
 * オプション l2ddrcr_e2e_cpt が '1' なら, カスタム投稿タイプ random を登録する.
 *
 * @return void
 */
function l2ddrcr_e2e_register_cpt() {
	if ( '1' === get_option( 'l2ddrcr_e2e_cpt' ) ) {
		register_post_type(
			'random',
			array(
				'public'      => true,
				'has_archive' => true,
				'label'       => 'Random CPT',
			)
		);
	}
}
add_action( 'init', 'l2ddrcr_e2e_register_cpt' );
