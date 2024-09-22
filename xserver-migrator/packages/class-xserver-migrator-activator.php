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
		if ( ! file_exists( XSERVER_MIGRATOR_WORKSPACE_DIR ) ) {
			@mkdir( XSERVER_MIGRATOR_WORKSPACE_DIR );
		}
		if ( ! file_exists( XSERVER_MIGRATOR_WORKSPACE_DIR . 'migrator.log' ) ) {
			@touch( XSERVER_MIGRATOR_WORKSPACE_DIR . 'migrator.log' );
		}
	}
}
