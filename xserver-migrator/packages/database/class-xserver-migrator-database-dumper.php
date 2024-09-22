<?php

class Xserver_Migrator_Database_Dumper
{
	private $dumper;

	public function __construct()
	{
		if ( Xserver_Migrator_Server::is_available_mysqldump() ) {
			require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-database-mysqldump-dumper.php';
			$this->dumper = new Xserver_Migrator_Database_Mysqldump_Dumper();
		} else {
			require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-database-scratch-dumper.php';
			$this->dumper = new Xserver_Migrator_Database_Scratch_Dumper();
		}
	}

	/**
	 * データベース
	 */
	public function dump()
	{
		try {
			$dumper_class = get_class( $this->dumper );
			Xserver_Migrator_Log::info( 'start database dump...' );
			Xserver_Migrator_Log::info( 'dumper: ' . $dumper_class );
			$this->dumper->dump();
			Xserver_Migrator_Log::info( 'end database dump' );
		} catch ( Xserver_Migrator_Database_Dump_Exception $e ) {
			$error = $e->getMessage();
			if ( $dumper_class === 'Xserver_Migrator_Database_Scratch_Dumper' ) {
				Xserver_Migrator_Response::error( $error, 'dump' );
			}
			Xserver_Migrator_Log::error( $error );
		}

		if ( isset( $error ) && $dumper_class === 'Xserver_Migrator_Database_Mysqldump_Dumper' ) {
			require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-database-scratch-dumper.php';
			$this->dumper = new Xserver_Migrator_Database_Scratch_Dumper();

			try {
				$dumper_class = get_class( $this->dumper );
				Xserver_Migrator_Log::info( 'start database dump...' );
				Xserver_Migrator_Log::info( 'dumper: ' . $dumper_class );
				$this->dumper->dump();
				Xserver_Migrator_Log::info( 'end database dump' );
			} catch ( Xserver_Migrator_Database_Dump_Exception $e ) {
				$error = $e->getMessage();
				Xserver_Migrator_Response::error( $error, 'dump' );
			}
		}
	}

}