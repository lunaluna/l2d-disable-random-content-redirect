<?php
/**
 * フィルター・ルールの自動の作り直し・有効化と無効化のテスト.
 *
 * シングルサイトとマルチサイトのどちらでも動く(マルチサイトでは, 今のサイトだけを対象に確かめる).
 *
 * @package L2D_Disable_Random_Content_Redirect
 */

/**
 * プラグインの基本の動作を確かめる.
 */
class Test_L2DDRCR_Rules extends Test_L2DDRCR_Base {

	/**
	 * 既定では, ランダムな投稿へのリダイレクトが無効になる.
	 *
	 * @return void
	 */
	public function test_disabled_by_default() {
		$this->assertFalse( wp_is_random_content_redirect_enabled() );
		$this->assertSame( 0, $this->flush_and_count_random_rules() );
	}

	/**
	 * 独自フィルターで false を返すと, コアの挙動に戻る.
	 *
	 * @return void
	 */
	public function test_custom_filter_false_restores_core_behavior() {
		add_filter( 'l2ddrcr_disable_random_redirect', '__return_false' );

		$this->assertTrue( wp_is_random_content_redirect_enabled() );
		$this->assertGreaterThan( 0, $this->flush_and_count_random_rules() );
	}

	/**
	 * 無効化しないときは, 先に付いたほかのプラグインの false を true に変えない.
	 *
	 * @return void
	 */
	public function test_custom_filter_false_keeps_earlier_false() {
		add_filter( 'l2ddrcr_disable_random_redirect', '__return_false' );
		add_filter( 'wp_enable_random_content_redirect', '__return_false', 5 );

		$this->assertFalse( wp_is_random_content_redirect_enabled() );
	}

	/**
	 * 記録が無いときは, ルールを作り直して記録する.
	 *
	 * @return void
	 */
	public function test_refresh_when_state_is_missing() {
		l2ddrcr_maybe_refresh_rules();

		$this->assertSame( 1, $this->generated, 'ルールを 1 回作り直す' );
		$this->assertSame( 'disabled', get_option( L2DDRCR_STATE_OPTION ) );
		$this->assertSame( 0, $this->count_random_rules() );
	}

	/**
	 * 記録が今の状態と同じなら, ルールを作り直さない.
	 *
	 * @return void
	 */
	public function test_no_refresh_when_state_matches() {
		update_option( L2DDRCR_STATE_OPTION, 'disabled' );

		l2ddrcr_maybe_refresh_rules();
		l2ddrcr_maybe_refresh_rules();

		$this->assertSame( 0, $this->generated, 'ルールを作り直さない' );
		$this->assertSame( 'disabled', get_option( L2DDRCR_STATE_OPTION ) );
	}

	/**
	 * 独自フィルターの値が変わったら, ルールを作り直して記録を更新する. 2 回目は作り直さない.
	 *
	 * @return void
	 */
	public function test_refresh_when_custom_filter_changes() {
		update_option( L2DDRCR_STATE_OPTION, 'disabled' );
		add_filter( 'l2ddrcr_disable_random_redirect', '__return_false' );

		l2ddrcr_maybe_refresh_rules();

		$this->assertSame( 1, $this->generated );
		$this->assertSame( 'enabled', get_option( L2DDRCR_STATE_OPTION ) );
		$this->assertGreaterThan( 0, $this->count_random_rules() );

		l2ddrcr_maybe_refresh_rules();
		$this->assertSame( 1, $this->generated, '2 回目は作り直さない' );
	}

	/**
	 * ほかのプラグインが先にコアのフィルターで false を返す場合(レビューの指摘 1).
	 *
	 * 記録は実際の状態(無効)にそろい, そのプラグインを外すと次のアクセスで作り直される.
	 *
	 * @return void
	 */
	public function test_other_plugin_disables_then_is_removed() {
		add_filter( 'l2ddrcr_disable_random_redirect', '__return_false' );
		add_filter( 'wp_enable_random_content_redirect', '__return_false', 5 );

		l2ddrcr_maybe_refresh_rules();
		$this->assertSame( 'disabled', get_option( L2DDRCR_STATE_OPTION ), '実際は無効なので disabled と記録する' );
		$this->assertSame( 0, $this->count_random_rules() );

		// ほかのプラグインを外す.
		remove_filter( 'wp_enable_random_content_redirect', '__return_false', 5 );
		$this->generated = 0;

		l2ddrcr_maybe_refresh_rules();
		$this->assertSame( 1, $this->generated, '外したら作り直す' );
		$this->assertSame( 'enabled', get_option( L2DDRCR_STATE_OPTION ) );
		$this->assertGreaterThan( 0, $this->count_random_rules() );
	}

	/**
	 * ほかのプラグインが後の優先度でコアのフィルターに true を返す場合(レビューの指摘 1).
	 *
	 * 最終結果は有効になり, 記録もルールも有効の状態にそろう.
	 *
	 * @return void
	 */
	public function test_other_plugin_enables_with_later_priority() {
		add_filter( 'wp_enable_random_content_redirect', '__return_true', 20 );

		l2ddrcr_maybe_refresh_rules();

		$this->assertTrue( wp_is_random_content_redirect_enabled() );
		$this->assertSame( 'enabled', get_option( L2DDRCR_STATE_OPTION ) );
		$this->assertGreaterThan( 0, $this->count_random_rules(), 'ルールも有効の状態で作られる' );

		// そのプラグインを外すと, 次のアクセスで無効の状態に作り直される.
		remove_filter( 'wp_enable_random_content_redirect', '__return_true', 20 );
		l2ddrcr_maybe_refresh_rules();

		$this->assertSame( 'disabled', get_option( L2DDRCR_STATE_OPTION ) );
		$this->assertSame( 0, $this->count_random_rules() );
	}

	/**
	 * 有効化すると, 今のサイトのルールと記録を消す.
	 *
	 * @return void
	 */
	public function test_activate_resets_current_site() {
		flush_rewrite_rules( false );
		update_option( L2DDRCR_STATE_OPTION, 'enabled' );

		l2ddrcr_activate( false );

		$this->assertEmpty( get_option( 'rewrite_rules' ) );
		$this->assertFalse( get_option( L2DDRCR_STATE_OPTION ) );
	}

	/**
	 * シングルサイトで $network_wide が true で渡っても, 今のサイトだけを対象にする.
	 *
	 * @return void
	 */
	public function test_activate_network_wide_on_single_site() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'シングルサイト用のテスト.' );
		}

		flush_rewrite_rules( false );
		update_option( L2DDRCR_STATE_OPTION, 'disabled' );

		l2ddrcr_activate( true );

		$this->assertEmpty( get_option( 'rewrite_rules' ) );
		$this->assertFalse( get_option( L2DDRCR_STATE_OPTION ) );
	}

	/**
	 * 無効化すると, フィルターとアクションを外し, ルールと記録を消す.
	 *
	 * そのあと作り直したルールには, ランダム用のルールが戻る.
	 *
	 * @return void
	 */
	public function test_deactivate_removes_hooks_and_resets() {
		flush_rewrite_rules( false );
		update_option( L2DDRCR_STATE_OPTION, 'disabled' );

		l2ddrcr_deactivate( false );

		$this->assertFalse( has_filter( 'wp_enable_random_content_redirect', 'l2ddrcr_filter_enable' ) );
		$this->assertFalse( has_action( 'wp_loaded', 'l2ddrcr_maybe_refresh_rules' ) );
		$this->assertEmpty( get_option( 'rewrite_rules' ) );
		$this->assertFalse( get_option( L2DDRCR_STATE_OPTION ) );

		$this->assertTrue( wp_is_random_content_redirect_enabled() );
		$this->assertGreaterThan( 0, $this->flush_and_count_random_rules() );
	}

	/**
	 * プラグインを読み込むと, フィルターとアクションが登録されている.
	 *
	 * @return void
	 */
	public function test_hooks_are_registered() {
		$this->assertSame( 10, has_filter( 'wp_enable_random_content_redirect', 'l2ddrcr_filter_enable' ) );
		$this->assertSame( 10, has_action( 'wp_loaded', 'l2ddrcr_maybe_refresh_rules' ) );
	}
}
