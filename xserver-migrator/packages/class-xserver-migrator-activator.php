<?php

/**
 * Fired during plugin activation.
 */
class Xserver_Migrator_Activator
{
	/**
	 * プラグイン有効化時に作業ディレクトリ生成
	 */
	public static function activate()
	{
		if ( ! function_exists( 'exec' ) && version_compare( PHP_VERSION, '8.0.0', '>=' ) ) {
			wp_die(
				'exec関数が使用できないためプラグインを有効化できません。',
				'',
				array(
					'link_text' => __( 'Go Back' ),
					'link_url'  => admin_url( 'plugins.php' ),
				)
			);
		}
		if ( ! file_exists( XSERVER_MIGRATOR_WORKSPACE_DIR ) ) {
			@mkdir( XSERVER_MIGRATOR_WORKSPACE_DIR );
		}
		if ( ! file_exists( XSERVER_MIGRATOR_WORKSPACE_DIR . 'migrator.log' ) ) {
			@touch( XSERVER_MIGRATOR_WORKSPACE_DIR . 'migrator.log' );
		}
	}
}
