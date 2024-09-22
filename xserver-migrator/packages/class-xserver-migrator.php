<?php

class Xserver_Migrator
{
	private static $instance;

	private $database_dumper;

	private $archiver;

	private $ssl;

	private $admin;

	/**
	 * Xserver_Migrator constructor.
	 */
	private function __construct()
	{
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		};

		// 依存クラス読み込み
		$this->load_dependencies();

		// アクション追加
		$this->bind_actions();

		if ( ! file_exists( XSERVER_MIGRATOR_WORKSPACE_DIR ) ) {
			@mkdir( XSERVER_MIGRATOR_WORKSPACE_DIR );
		}

		if ( ! file_exists( XSERVER_MIGRATOR_LOG_FILE_PATH ) ) {
			@touch( XSERVER_MIGRATOR_LOG_FILE_PATH );
		}

		@set_error_handler( function ( $errno, $errstr, $errfile, $errline ) {
			if ( false !== strpos( $errfile, '/xserver-migrator/' ) ) {
				Xserver_Migrator_Log::error( "$errstr [$errfile:$errline]" );
			}
		} );

		@register_shutdown_function( function () {
			$error = error_get_last();

			if ( ! is_null( $error ) && strpos( $error['file'], XSERVER_MIGRATOR_PLUGIN_NAME ) !== false ) {
				Xserver_Migrator_File::remove( XSERVER_MIGRATOR_WORKSPACE_DIR );
				$message = $error['line'] . ': ' . $error['message'];
				Xserver_Migrator_Response::error( $message, 'general', 500, false );
			}
		});

		// .htaccessを生成
		if ( ! file_exists( XSERVER_MIGRATOR_WORKSPACE_DIR . '.htaccess' ) ) {
			Xserver_Migrator_File::create_htaccess_file( XSERVER_MIGRATOR_WORKSPACE_DIR );
		}

		// index.phpを生成
		if ( ! file_exists( XSERVER_MIGRATOR_WORKSPACE_DIR . 'index.php' ) ) {
			Xserver_Migrator_File::create_index_file( XSERVER_MIGRATOR_WORKSPACE_DIR );
		}

		$this->database_dumper = new Xserver_Migrator_Database_Dumper();
		$this->archiver = new Xserver_Migrator_Archiver();
		$this->ssl = new Xserver_Migrator_SSL();
		if ( is_admin() ) {
			$this->admin = new Xserver_Migrator_Admin();
			$this->admin->activate();
		}
	}

	/**
	 * インスタンス取得
	 *
	 * @return Xserver_Migrator
	 */
	public static function get_instance()
	{
		if ( ! self::$instance ) {
			return new Xserver_Migrator();
		}
		return self::$instance;
	}

	/**
	 * 依存クラス読み込み
	 */
	private function load_dependencies()
	{
		// exceptions
		require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-exceptions.php';
		// File
		require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-file.php';
		// Log
		require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-log.php';
		// Response
		require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-response.php';
		// Server
		require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-server.php';
		// SSL
		require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-ssl.php';
		// Admin menu
		require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-admin.php';
		// DB dumper
		require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-database-dumper.php';
		// Archiver
		require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'archiver' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-archiver.php';
	}

	/**
	 * アクションとメソッドを紐づける
	 */
	private function bind_actions()
	{
		// administratorのみに制限
		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		add_action( 'wp_ajax_xserver_migrator_execute', array( $this, 'execute' ) );
		add_action( 'wp_ajax_xserver_migrator_get_versions_and_db_size', array( $this, 'get_versions_and_db_size' ) );
		add_action( 'wp_ajax_xserver_migrator_get_available_archive_methods', array( $this, 'get_available_archive_methods' ) );
		add_action( 'wp_ajax_xserver_migrator_create_challenge_token', array( $this, 'create_challenge_token' ) );
		add_action( 'wp_ajax_xserver_migrator_delete_challenge_token', array( $this, 'delete_challenge_token' ) );
		add_action( 'wp_ajax_xserver_migrator_get_table_prefix', array( $this, 'get_table_prefix' ) );
	}

	/**
	 * 検証
	 */
	private function validate()
	{
		// PHPバージョン確認
		if ( version_compare( Xserver_Migrator_Server::php_version(), '5.3', '<' ) ) {
			$error = 'PHP5.3 or higher is required to use this plugin (PHP' . Xserver_Migrator_Server::php_version() . ')';
			Xserver_Migrator_Response::error( $error, 'php_version' );
		};

		// WordPressバージョン確認
		if ( version_compare( Xserver_Migrator_Server::wordpress_version(), '4.0', '<' ) ) {
			$error = 'WordPress4.0 or higher is required to use this plugin (WordPress' . Xserver_Migrator_Server::wordpress_version() . ')';
			Xserver_Migrator_Response::error( $error, 'wp_version' );
		}

		Xserver_Migrator_Log::info(
			'php version=' . Xserver_Migrator_Server::php_version() . ', wordpress version=' . Xserver_Migrator_Server::wordpress_version()
		);
	}

	/**
	 * データベースダンプ、wp-contentアーカイブ
	 */
	public function execute()
	{
		if ( ! check_ajax_referer( 'xserver_migrator_execute', '_secure', false ) ) {
			Xserver_Migrator_Response::error( 'Invalid access', 'archive', 403 );
		}

		// 検証
		$this->validate();

		@ini_set( 'memory_limit', '1024M' );
		@set_time_limit( 0 );

		Xserver_Migrator_Log::info( $this->get_migration_spec() );

		// dumpはエラーハンドリングが内部で行われているためtryの外におく
		$this->database_dumper->dump();
		try {
			$archive_file_info = $this->archiver->archive();
			Xserver_Migrator_File::remove( XSERVER_MIGRATOR_WORKSPACE_DIR . 'dump.sql' );
		} catch ( Xserver_Migrator_Archive_Exception $e ) {
			$error = $e->getMessage();
			Xserver_Migrator_File::remove( XSERVER_MIGRATOR_WORKSPACE_DIR . 'dump.sql' );
			Xserver_Migrator_Response::error( $error, 'archive' );
		}

		Xserver_Migrator_Response::success( $archive_file_info );
	}

	/**
	 * WordPressバージョン取得
	 */
	public function get_versions_and_db_size()
	{
		if ( ! check_ajax_referer( 'xserver_migrator_get_versions', '_secure', false ) ) {
			Xserver_Migrator_Response::error( 'Invalid access', 'version', 403 );
		}

		$versions = array(
			'php' => Xserver_Migrator_Server::php_version(),
			'wordpress' => Xserver_Migrator_Server::wordpress_version(),
			'db_size' => Xserver_Migrator_Server::wpdb_size()
		);

		Xserver_Migrator_Response::success( $versions );
	}

	/**
	 * アーカイブ作成に必要なコマンドやモジュールの利用可否を取得
	 */
	public function get_available_archive_methods()
	{
		if ( ! check_ajax_referer( 'xserver_migrator_get_available', '_secure', false ) ) {
			Xserver_Migrator_Response::error( 'Invalid access', 'methods', 403 );
		}

		$methods = array(
			'zip_command' => false !== Xserver_Migrator_Server::is_available_zip_command(),
			'tar_command' => false !== Xserver_Migrator_Server::is_available_tar_command(),
			'zip_extension' => Xserver_Migrator_Server::is_loaded_zip_extension(),
		);

		Xserver_Migrator_Response::success( $methods );
	}

	/**
	 * WordPressテーブルプレフィックス取得
	 */
	public function get_table_prefix()
	{
		if ( ! check_ajax_referer( 'xserver_migrator_get_table_prefix', '_secure', false ) ) {
			Xserver_Migrator_Response::error( 'Invalid access', 'prefix', 403 );
		}

		Xserver_Migrator_Response::success(Xserver_Migrator_Server::wordpress_table_prefix());
	}

	/**
	 * チャレンジトークンのファイル生成
	 */
	public function create_challenge_token()
	{
		if ( ! check_ajax_referer( 'xserver_migrator_create_challenge_token', '_secure', false ) ) {
			Xserver_Migrator_Response::error( 'Invalid access', 'challenge_token', 403 );
		}
		if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'xserver_migrator_create_challenge_token' ) {
			Xserver_Migrator_Response::error( 'Invalid parameter: action =' . esc_attr($_POST['action']), 'challenge_token' );
		}

		$response = $this->ssl->create_file( esc_attr($_POST['file_name']), esc_attr($_POST['contents']) );

		Xserver_Migrator_Response::success( $response );
	}

	/**
	 * チャレンジトークンのファイル削除
	 */
	public function delete_challenge_token()
	{
		if ( ! check_ajax_referer( 'xserver_migrator_delete_challenge_token', '_secure', false ) ) {
			Xserver_Migrator_Response::error( 'Invalid access', 'challenge_token', 403 );
		}
		if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'xserver_migrator_delete_challenge_token' ) {
			Xserver_Migrator_Response::error( 'Invalid parameter: action=' . esc_attr($_POST['action']), 'challenge_token' );
		}

		foreach ( $_POST['token_info'] as $token_info ) {
			$info = $this->ssl->delete_file( $token_info['file_path'], $token_info['is_created_dir_1'], $token_info['is_created_dir_2'] );
		}

		Xserver_Migrator_Response::success( $info );
	}

	/**
	 * 移行情報取得
	 *
	 * @return array
	 */
	private function get_migration_spec()
	{
		return array(
			'php_os'				=> Xserver_Migrator_Server::os(),
			'memory_limit'			=> Xserver_Migrator_Server::memory_limit(),
			'document_root'			=> Xserver_Migrator_Server::document_root(),
			'php_version'			=> Xserver_Migrator_Server::php_version(),
			'wordpress_version'		=> Xserver_Migrator_Server::wordpress_version(),
		);
	}
}
