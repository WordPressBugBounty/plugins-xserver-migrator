<?php

abstract class Xserver_Migrator_Database_Dumper_Abstract
{
	protected $wpdb;
	protected $error_dump_file_path;
	protected $dump_file_path;

	protected function __construct()
	{
		if ( ! file_exists(XSERVER_MIGRATOR_WORKSPACE_DIR ) ) {
			@mkdir( XSERVER_MIGRATOR_WORKSPACE_DIR );
		}

		global $wpdb;

		$this->wpdb = $wpdb;
		$this->dump_file_path = XSERVER_MIGRATOR_WORKSPACE_DIR . 'dump.sql';
		$this->error_dump_file_path = XSERVER_MIGRATOR_WORKSPACE_DIR . 'error_dump.log';
	}

	/**
	 * データベースダンプ
	 *
	 * @return mixed
	 */
	abstract public function dump();

	/**
	 * 権限確認
	 */
	protected function get_privileges()
	{
		$grants = $this->wpdb->get_results( 'SHOW GRANTS FOR CURRENT_USER', ARRAY_N );

		$grants_flatten = array();

		array_walk_recursive( $grants, function( $val ) use ( &$grants_flatten ) {
			$grants_flatten[] = $val;
		} );

		$privileges = array();

		foreach ( $grants_flatten as $grant ) {
			preg_match( '/^GRANT (?<privileges>.*) ON/', $grant, $matches );
			$privileges[] = $matches['privileges'];
		}

		return $privileges;
	}

	/**
	 * Eventの参照権限があるか
	 */
	protected function has_event_privilege( $privileges )
	{
		foreach ( $privileges as $privilege ) {
			if ( strpos( $privilege, 'ALL PRIVILEGES' ) !== false || strpos( $privilege, 'EVENT' ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Triggerの参照権限があるか
	 */
	protected function has_trigger_privilege( $privileges )
	{
		foreach ( $privileges as $privilege ) {
			if ( strpos( $privilege, 'ALL PRIVILEGES' ) !== false || strpos( $privilege, 'TRIGGER' ) !== false ) {
				return true;
			}
		}
		return false;
	}
}