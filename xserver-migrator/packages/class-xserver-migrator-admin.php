<?php

class Xserver_Migrator_Admin
{
	public $nonce_action = array(
		'Xserver Migrator Execute' => 'xserver_migrator_execute',
		'Xserver Migrator Get Versions' => 'xserver_migrator_get_versions',
		'Xserver Migrator Get Available' => 'xserver_migrator_get_available',
		'Xserver Migrator Get Prefix' => 'xserver_migrator_get_table_prefix',
		'Xserver Migrator Create Challenge' => 'xserver_migrator_create_challenge_token',
		'Xserver Migrator Delete Challenge' => 'xserver_migrator_delete_challenge_token',
	);

	public function activate()
	{
		add_action( 'admin_menu', array($this, 'add_admin_menu') );
		add_action( 'admin_head', array($this, 'add_admin_head') );
	}

	/**
	 * 管理画面にメタタグ追加
	 */
	public function add_admin_head()
	{
		global $pagenow;
		if ( $pagenow !== 'admin.php' || ! isset( $_GET['page'] ) ) {
			return;
		}
		if ( ! in_array( $_GET['page'], $this->nonce_action, true ) ) {
			return;
		}
		echo '<meta name="xserver-migrator-nonce" content="' . wp_create_nonce( esc_attr( $_GET['page'] ) ) . '">' . "\n";
	}

	/**
	 * 管理画面にメニュー追加
	 */
	public function add_admin_menu()
	{
		$current = current( $this->nonce_action );

		add_menu_page(
			'Xserver Migrator',
			'Xserver Migrator',
			'manage_options',
			$current
		);

		foreach ( $this->nonce_action as $title => $action ) {
			add_submenu_page(
				$current,
				$title,
				$title,
				'manage_options',
				$action,
				function(){}
			);
		}

		// サイドバーにメニューを表示させない
		remove_menu_page( $current );
	}

}
