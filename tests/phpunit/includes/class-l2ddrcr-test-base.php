<?php
/**
 * テストの共通部分.
 *
 * @package L2D_Disable_Random_Content_Redirect
 */

/**
 * テストの共通の準備と, ルールを調べるための関数をまとめる.
 */
abstract class L2DDRCR_Test_Base extends WP_UnitTestCase {

	/**
	 * ルールを作った回数. generate_rewrite_rules アクションで数える.
	 *
	 * @var int
	 */
	protected $generated = 0;

	/**
	 * 各テストの前に, パーマリンクを /%postname%/ にして, 記録を消す.
	 *
	 * パーマリンクが既定(?p=123)だと rewrite_rules が空になり, ルールの有無を確かめられないため.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// 7.2 未満では, このプラグインは何もしない. このテストは 7.2 以降の動作を確かめるもの.
		if ( ! l2ddrcr_is_supported() ) {
			$this->markTestSkipped( 'WordPress 7.2 以降(wp_is_random_content_redirect_enabled() がある環境)で実行してください.' );
		}

		$this->set_permalink_structure( '/%postname%/' );
		delete_option( L2DDRCR_STATE_OPTION );

		$this->generated = 0;
		add_action( 'generate_rewrite_rules', array( $this, 'count_generated' ) );
	}

	/**
	 * ルールを作った回数を数える.
	 *
	 * WP_Rewrite::rewrite_rules() の中で呼ばれる(class-wp-rewrite.php).
	 *
	 * @return void
	 */
	public function count_generated() {
		++$this->generated;
	}

	/**
	 * 保存されているルールのうち, ランダムな投稿へのリダイレクトのルールの数を返す.
	 *
	 * ランダム用のルールは, 行き先のクエリに random=1 を含む(class-wp-rewrite.php の random_rewrite_rules()).
	 *
	 * @return int ルールの数.
	 */
	protected function count_random_rules() {
		$count = 0;
		foreach ( (array) get_option( 'rewrite_rules' ) as $query ) {
			if ( false !== strpos( (string) $query, 'random=1' ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * ルールを作り直してから, ランダム用のルールの数を返す.
	 *
	 * @return int ルールの数.
	 */
	protected function flush_and_count_random_rules() {
		flush_rewrite_rules( false );
		return $this->count_random_rules();
	}
}
