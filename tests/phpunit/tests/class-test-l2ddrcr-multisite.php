<?php
/**
 * マルチサイトでの有効化・無効化のテスト.
 *
 * このテストは phpunit-multisite.xml.dist で動かす. シングルサイトではスキップする.
 * コアのテスト用ライブラリはマルチサイトをサブディレクトリ型で作る(includes/install.php).
 * サブドメイン型は tests/e2e の HTTP の通しテストで確かめる.
 *
 * @package L2D_Disable_Random_Content_Redirect
 */

/**
 * ネットワーク単位とサイト単位で, 対象のサイトが変わることを確かめる.
 *
 * @group ms-required
 */
class Test_L2DDRCR_Multisite extends Test_L2DDRCR_Base {

	/**
	 * テスト用に作るサイトの ID.
	 *
	 * @var int[]
	 */
	protected $site_ids = array();

	/**
	 * マルチサイトでなければスキップし, サイトを 2 つ作って, 全サイトにルールと記録を入れる.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイト用のテスト. phpunit-multisite.xml.dist で実行してください.' );
		}

		$this->site_ids = self::factory()->blog->create_many( 2 );

		foreach ( $this->all_site_ids() as $site_id ) {
			switch_to_blog( $site_id );
			// 中身は何でもよい. 消えたかどうかだけを見る.
			update_option( 'rewrite_rules', array( 'dummy/?$' => 'index.php' ) );
			update_option( L2DDRCR_STATE_OPTION, 'disabled' );
			restore_current_blog();
		}
	}

	/**
	 * メインサイトとテスト用のサイトの ID を返す.
	 *
	 * @return int[] サイトの ID.
	 */
	protected function all_site_ids() {
		return array_merge( array( get_main_site_id() ), $this->site_ids );
	}

	/**
	 * サイトのルールと記録が消えているかを返す.
	 *
	 * @param int $site_id サイトの ID.
	 * @return bool 両方消えていれば true.
	 */
	protected function is_reset( $site_id ) {
		switch_to_blog( $site_id );
		$reset = empty( get_option( 'rewrite_rules' ) ) && false === get_option( L2DDRCR_STATE_OPTION );
		restore_current_blog();
		return $reset;
	}

	/**
	 * ネットワーク有効化では, すべてのサイトのルールと記録を消す.
	 *
	 * @return void
	 */
	public function test_network_activate_resets_all_sites() {
		l2ddrcr_activate( true );

		foreach ( $this->all_site_ids() as $site_id ) {
			$this->assertTrue( $this->is_reset( $site_id ), "サイト {$site_id} が消えている" );
		}
	}

	/**
	 * サイト単位の有効化では, 今のサイトだけを対象にする.
	 *
	 * @return void
	 */
	public function test_site_activate_resets_only_current_site() {
		l2ddrcr_activate( false );

		$this->assertTrue( $this->is_reset( get_current_blog_id() ) );
		foreach ( $this->site_ids as $site_id ) {
			$this->assertFalse( $this->is_reset( $site_id ), "サイト {$site_id} はそのまま" );
		}
	}

	/**
	 * ネットワーク無効化では, すべてのサイトのルールと記録を消す.
	 *
	 * @return void
	 */
	public function test_network_deactivate_resets_all_sites() {
		l2ddrcr_deactivate( true );

		foreach ( $this->all_site_ids() as $site_id ) {
			$this->assertTrue( $this->is_reset( $site_id ), "サイト {$site_id} が消えている" );
		}
	}

	/**
	 * サイト単位の無効化では, 今のサイトだけを対象にする.
	 *
	 * @return void
	 */
	public function test_site_deactivate_resets_only_current_site() {
		l2ddrcr_deactivate( false );

		$this->assertTrue( $this->is_reset( get_current_blog_id() ) );
		foreach ( $this->site_ids as $site_id ) {
			$this->assertFalse( $this->is_reset( $site_id ), "サイト {$site_id} はそのまま" );
		}
	}

	/**
	 * ネットワーク単位の処理のあと, 元のサイトに戻っている.
	 *
	 * @return void
	 */
	public function test_returns_to_original_site_after_network_reset() {
		$original = get_current_blog_id();

		l2ddrcr_activate( true );
		$this->assertSame( $original, get_current_blog_id() );
		$this->assertFalse( ms_is_switched() );

		l2ddrcr_deactivate( true );
		$this->assertSame( $original, get_current_blog_id() );
		$this->assertFalse( ms_is_switched() );
	}

	/**
	 * 別のサイトから操作しても, ネットワーク有効化は全サイトを対象にし, 操作したサイトに戻る.
	 *
	 * @return void
	 */
	public function test_network_activate_from_sub_site() {
		switch_to_blog( $this->site_ids[0] );
		l2ddrcr_activate( true );
		$current = get_current_blog_id();
		restore_current_blog();

		$this->assertSame( $this->site_ids[0], $current, '操作したサイトに戻る' );
		foreach ( $this->all_site_ids() as $site_id ) {
			$this->assertTrue( $this->is_reset( $site_id ), "サイト {$site_id} が消えている" );
		}
	}
}
