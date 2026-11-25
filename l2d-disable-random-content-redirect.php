<?php
/**
 * Plugin Name:       L2D disable random content redirect
 * Description:       WordPress 7.2 で追加された /random/ などのランダムな投稿へのリダイレクトを無効化します.
 * Version:           0.1.0
 * Author:            lunaluna
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       l2d-disable-random-content-redirect
 *
 * WordPress 7.2 では /random/ や /random/category/{term}/ にアクセスすると,
 * ランダムに選ばれた投稿へリダイレクトされる. この機能は既定で有効なので,
 * 意図しないアクセスが起きる. このプラグインは, コアのフィルター
 * wp_enable_random_content_redirect に false を返して, 機能をまるごと止める.
 *
 * Requires at least はあえて付けない. 7.2 未満ではコアのフィルターが呼ばれないので,
 * 有効化しても何も起きない. 7.2 に上げたときに, そのまま効くようにするため.
 *
 * @package L2D_Disable_Random_Content_Redirect
 */

defined( 'ABSPATH' ) || exit;

/**
 * ランダムな投稿へのリダイレクトを有効にするかを返す.
 *
 * コアのフィルター wp_enable_random_content_redirect に登録する.
 * このフィルターの値は, リライトルールの生成(class-wp-rewrite.php の random_rewrite_rules()),
 * リクエストの処理(class-wp.php の WP::handle_random()),
 * wp_is_random_content_query() のすべてで使われる. なので false を返せば,
 * /random/ だけでなく /random/category/... や /random/author/... などもまとめて止まる.
 *
 * random スラッグの固定ページ・投稿・カスタム投稿タイプがあっても無効化する.
 * 無効化しても, それらは自分のリライトルールで表示されるので壊れない
 * (7.2-RC3 のソースで確認済み. 詳しくは README).
 *
 * @param bool $enabled ランダムな投稿へのリダイレクトを有効にするか. コアの既定は true.
 * @return bool 有効にするなら true.
 */
function l2ddrcr_filter_enable( $enabled ) {
	/**
	 * ランダムな投稿へのリダイレクトを無効化するかを絞り込む.
	 *
	 * サイト側で false を返すと, このプラグインは何もせず, コアの挙動に戻る.
	 * 値を変えたあとは, 管理画面の「設定 > パーマリンク」で保存して,
	 * リライトルールを作り直す必要がある(ルールは rewrite_rules オプションに保存されているため).
	 *
	 * @param bool $disable 無効化するなら true. 既定は true.
	 */
	$disable = (bool) apply_filters( 'l2ddrcr_disable_random_redirect', true );

	if ( $disable ) {
		return false;
	}

	// 無効化しないときは, 受け取った値をそのまま返す. 他のプラグインが付けた値を壊さないため.
	return (bool) $enabled;
}
add_filter( 'wp_enable_random_content_redirect', 'l2ddrcr_filter_enable' );
