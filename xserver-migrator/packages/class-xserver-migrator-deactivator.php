<?php

/**
 * Fired during plugin deactivation.
 */
class Xserver_Migrator_Deactivator {

	/**
	 * プラグイン無効化時に作業ディレクトリ削除
	 */
	public static function deactivate() {
		if ( file_exists( XSERVER_MIGRATOR_WORKSPACE_DIR ) ) {
			Xserver_Migrator_File::remove( XSERVER_MIGRATOR_WORKSPACE_DIR );
		}
	}
}
