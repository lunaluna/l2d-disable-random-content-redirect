<?php
/**
 * Plugin Name:       L2D disable random content redirect
 * Plugin URI:        https://github.com/lunaluna/l2d-disable-random-content-redirect
 * Description:       WordPress 7.2 で追加された /random/ などのランダムな投稿へのリダイレクトを無効化します.
 * Version:           1.0.0
 * Tested up to:      7.2
 * Author:            lunaluna_dev
 * Author URI:        https://profiles.wordpress.org/lunaluna_dev/
 * Update URI:        false
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
 * どの状態でリライトルールを作ったかを記録するオプションの名前.
 *
 * 値は 'disabled'(ランダムな投稿へのリダイレクトが無効な状態で作った)か
 * 'enabled'(有効な状態で作った). どちらも, このプラグインの設定ではなく,
 * コアの wp_is_random_content_redirect_enabled() の最終結果で決める.
 * サイトごとに持つ. l2ddrcr_maybe_refresh_rules() が今の状態と比べて,
 * 違えばルールを作り直す.
 */
const L2DDRCR_STATE_OPTION = 'l2ddrcr_rules_state';

/**
 * このプラグインで無効化するかを返す.
 *
 * 独自フィルター l2ddrcr_disable_random_redirect の値で決める.
 * これは「このプラグインが無効化しようとするか」であり, 最終的に無効になるかではない.
 * 最終的な値は, ほかのプラグインも参加するコアのフィルターで決まる(l2ddrcr_maybe_refresh_rules() を参照).
 *
 * @return bool 無効化するなら true.
 */
function l2ddrcr_should_disable() {
	/**
	 * ランダムな投稿へのリダイレクトを無効化するかを絞り込む.
	 *
	 * サイト側で false を返すと, このプラグインは何もせず, コアの挙動に戻る.
	 * 値を変えると, 次のアクセスでリライトルールが自動で作り直される
	 * (l2ddrcr_maybe_refresh_rules()).
	 *
	 * なお, このプラグインより後の優先度でほかのプラグインが wp_enable_random_content_redirect に
	 * true を返すと, そちらが勝つ(このプラグインは優先度 10 で登録している).
	 *
	 * リクエストによって変わる値(ログインしているか, 管理画面か など)を返してはいけない.
	 * 値が変わるたびにルールを作り直すので, 毎回のアクセスで作り直しが起きてしまう.
	 *
	 * @param bool $disable 無効化するなら true. 既定は true.
	 */
	return (bool) apply_filters( 'l2ddrcr_disable_random_redirect', true );
}

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
	if ( l2ddrcr_should_disable() ) {
		return false;
	}

	// 無効化しないときは, 受け取った値をそのまま返す. 他のプラグインが付けた値を壊さないため.
	return (bool) $enabled;
}
add_filter( 'wp_enable_random_content_redirect', 'l2ddrcr_filter_enable' );

/**
 * この WordPress にランダムな投稿へのリダイレクトの機能があるかを返す.
 *
 * バージョン番号では判定しない. version_compare( '7.2-RC3', '7.2', '>=' ) は false になり,
 * RC や beta で機能があっても「ない」と判定してしまうため.
 * 代わりに, 7.2 で追加された関数があるかを見る.
 *
 * @return bool 機能があるなら true.
 */
function l2ddrcr_is_supported() {
	return function_exists( 'wp_is_random_content_redirect_enabled' );
}

/**
 * 必要なときだけ, リライトルールを作り直す.
 *
 * /random/ のルールは rewrite_rules オプションに保存されているので,
 * フィルターの値が変わっただけでは消えたり戻ったりしない.
 * そこで, どの状態でルールを作ったかを L2DDRCR_STATE_OPTION に記録しておき,
 * 今の状態と違えば作り直す. 次のような場面で効く.
 *
 * - 有効化した直後(記録が無い).
 * - マルチサイトでネットワーク有効化したあとの各サイト(記録が無い).
 * - 独自フィルター l2ddrcr_disable_random_redirect の値を変えたとき.
 * - ほかのプラグインが wp_enable_random_content_redirect の値を変えたとき
 *   (そのプラグインを入れた・外した・設定を変えた など).
 * - WordPress を 7.2 に上げたとき(7.2 未満では記録しないので, 記録が無い).
 *
 * 状態は, 独自フィルターの値ではなく wp_is_random_content_redirect_enabled() の最終結果で決める.
 * ルールを作るとき(class-wp-rewrite.php の random_rewrite_rules())も同じ関数を見るので,
 * 記録とルールが必ず同じ値にもとづく. 独自フィルターの値で決めていたときは,
 * ほかのプラグインがコアのフィルターで false を返していると, 実際は無効なのに 'enabled' と記録し,
 * そのプラグインを外したあとも作り直しが起きなかった(2026-11-26 のレビューの指摘 1).
 *
 * ルールの中身(random=1 のルールがあるか)では判定しない. 他のプラグインが
 * random_rewrite_rules フィルターで random のルールを空にしていると,
 * 作り直しても食い違ったままになり, 毎回作り直してしまうため.
 *
 * wp_loaded で動かすのは, 投稿タイプやタクソノミーが init で登録し終わってから
 * 作り直すため. flush_rules() も wp_loaded 前だと wp_loaded まで待つ作りになっている
 * (class-wp-rewrite.php の flush_rules()). 同じリクエストのルーティング
 * (WP::parse_request())はこのあとなので, 作り直したルールがすぐ使われる.
 *
 * @return void
 */
function l2ddrcr_maybe_refresh_rules() {
	// 7.2 未満には /random/ のルールが無いので, 何もしない.
	if ( ! l2ddrcr_is_supported() ) {
		return;
	}

	$state = wp_is_random_content_redirect_enabled() ? 'enabled' : 'disabled';

	if ( get_option( L2DDRCR_STATE_OPTION ) === $state ) {
		return;
	}

	// .htaccess は変わらないので, ソフトフラッシュ(false)でよい.
	flush_rewrite_rules( false );

	// 作り直したあとで記録する. 記録はルールを作り直したかどうかに関係なく更新するので,
	// ルールの中身が変わらない場合でも, 次のアクセスで作り直しが繰り返されることはない.
	update_option( L2DDRCR_STATE_OPTION, $state );
}
add_action( 'wp_loaded', 'l2ddrcr_maybe_refresh_rules' );

/**
 * 1 つのサイトのリライトルールと記録を消す.
 *
 * ルールは, そのサイトへの次のアクセスで作り直される
 * (class-wp-rewrite.php の wp_rewrite_rules() は, rewrite_rules が空なら作り直す).
 *
 * その場で flush_rewrite_rules() しないのは, switch_to_blog() で別のサイトに切り替えても
 * $wp_rewrite(パーマリンク構造や, 登録済みの投稿タイプのルール)は切り替わらないため
 * (ms-blogs.php の switch_to_blog() は $wp_rewrite を作り直さない).
 * その場で作ると, 今のサイトの設定で別のサイトのルールを作ってしまう.
 *
 * @return void
 */
function l2ddrcr_reset_site_rules() {
	delete_option( 'rewrite_rules' );
	delete_option( L2DDRCR_STATE_OPTION );
}

/**
 * 有効化・無効化の対象になったサイトで, リライトルールと記録を消す.
 *
 * ネットワーク有効化・無効化のときは, ネットワーク内のすべてのサイトが対象.
 * 有効化・無効化のフックは操作したサイトでしか呼ばれないので, ここで全サイトを回る.
 * 回らないと, 他のサイトには古いルールが残る
 * (2026-11-26, wp-env のマルチサイトで確認. 有効化後も /site2/random/ がホームを表示した).
 *
 * サイト数がとても多いネットワークでの処理時間は未実測.
 *
 * @param bool $network_wide ネットワーク有効化・無効化なら true.
 * @return void
 */
function l2ddrcr_reset_rules( $network_wide ) {
	if ( ! is_multisite() || ! $network_wide ) {
		l2ddrcr_reset_site_rules();
		return;
	}

	// number を 0 にすると件数の上限が外れる(class-wp-site-query.php. 既定は 100 件).
	$site_ids = get_sites(
		array(
			'fields'     => 'ids',
			'number'     => 0,
			'network_id' => get_current_network_id(),
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		l2ddrcr_reset_site_rules();
		restore_current_blog();
	}
}

/**
 * 有効化したときに, リライトルールを消して, 作り直させる.
 *
 * 消したルールは, 各サイトへの次のアクセスで作り直される.
 * そのときはこのプラグインが読み込まれているので, /random/ のルールが無い状態で作られる.
 * 記録も消すので, l2ddrcr_maybe_refresh_rules() も一度だけ作り直して記録する.
 *
 * @param bool $network_wide ネットワーク有効化なら true.
 * @return void
 */
function l2ddrcr_activate( $network_wide = false ) {
	if ( ! l2ddrcr_is_supported() ) {
		return;
	}

	l2ddrcr_reset_rules( (bool) $network_wide );
}
register_activation_hook( __FILE__, 'l2ddrcr_activate' );

/**
 * 無効化したときに, リライトルールを消して, 作り直させる.
 *
 * 次のアクセスからはこのプラグインが読み込まれないので, コアの /random/ のルールが戻る.
 * 記録も消す. 消さないと, また有効化したときに「記録どおり」と判断されて,
 * コアのルールが残ったままになる.
 *
 * 無効化のフックが呼ばれる時点では, まだ l2ddrcr_filter_enable() が登録されている.
 * このリクエストの中でルールが作り直された場合に, 無効化したルールが保存されないよう,
 * フィルターも外しておく.
 *
 * @param bool $network_wide ネットワーク無効化なら true.
 * @return void
 */
function l2ddrcr_deactivate( $network_wide = false ) {
	if ( ! l2ddrcr_is_supported() ) {
		return;
	}

	remove_filter( 'wp_enable_random_content_redirect', 'l2ddrcr_filter_enable' );
	remove_action( 'wp_loaded', 'l2ddrcr_maybe_refresh_rules' );
	l2ddrcr_reset_rules( (bool) $network_wide );
}
register_deactivation_hook( __FILE__, 'l2ddrcr_deactivate' );
