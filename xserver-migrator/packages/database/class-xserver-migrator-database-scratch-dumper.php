<?php
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'class-xserver-migrator-database-dumper-abstract.php';

class Xserver_Migrator_Database_Scratch_Dumper extends Xserver_Migrator_Database_Dumper_Abstract
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * @return bool
	 * @throws Xserver_Migrator_Database_Dump_Exception
	 */
	public function dump()
	{
		@set_error_handler( array( $this, 'error_handler' ) );

		try {
			$this->dump_header();
			$this->dump_main();
			$this->dump_footer();
		} catch ( Xserver_Migrator_Database_Dump_Exception $ex ) {
			@restore_error_handler();
			throw $ex;
		}
		@restore_error_handler();
		return true;
	}

	private function error_handler( $errno, $errstr, $errfile, $errline )
	{
		if ( false !== strpos( $errfile, '/xserver-migrator/' ) ) {
			Xserver_Migrator_File::writeLine( $this->error_dump_file_path, "$errstr [$errfile:$errline]" );
		}
	}

	/**
	 * @throws Xserver_Migrator_Database_Dump_Exception
	 */
	private function dump_header()
	{
		$dump_header  = "-- ------------------------------------------------------\n";
		$dump_header .= "-- Xserver Migrator\n\n";
		$dump_header .= "-- Host: " . DB_HOST . "\n";
		$dump_header .= "-- Database: " . DB_NAME . "\n";
		$dump_header .= "-- Server version " . $this->wpdb->db_version() . "\n";
		$dump_header .= "-- ------------------------------------------------------\n\n";
		$dump_header .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
		$dump_header .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
		$dump_header .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
		$dump_header .= "/*!40101 SET NAMES utf8 */;\n";
		$dump_header .= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n";
		$dump_header .= "/*!40103 SET TIME_ZONE='+00:00' */;\n";
		$dump_header .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n";
		$dump_header .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n";
		$dump_header .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n";
		$dump_header .= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n";

		$this->write( $dump_header );
	}

	/**
	 * @throws Xserver_Migrator_Database_Dump_Exception
	 */
	private function dump_footer()
	{
		$dump_footer  = "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n";
		$dump_footer .= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n";
		$dump_footer .= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n";
		$dump_footer .= "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n";
		$dump_footer .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
		$dump_footer .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
		$dump_footer .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
		$dump_footer .= "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n";
		$dump_footer .= "-- Dump completed on " . date( 'Y-m-d H:i:s' ) . "\n";

		$this->write( $dump_footer );
	}

	/**
	 * @throws Xserver_Migrator_Database_Dump_Exception
	 */
	private function dump_main()
	{
		$privileges = $this->get_privileges();

		$tables     = $this->get_tables();
		$views      = $this->get_views();
		$routines   = $this->get_stored_routines();
		$functions  = isset( $routines['function'] ) ? $routines['function'] : array();
		$procedures = isset( $routines['procedure'] ) ? $routines['procedure'] : array();
		$events     = $this->has_event_privilege( $privileges ) ? $this->get_events() : array();
		$triggers   = $this->has_trigger_privilege( $privileges ) ? $this->get_triggers() : array();

		if ( ! $tables ) {
			throw new Xserver_Migrator_Database_Dump_Exception( 'Tables does not found.' );
		}

		foreach ( $tables as $table ) {
			$dump_table = $this->table_structure( $table[0] );
			$dump_table .= $this->table_data( $table[0] );
			$this->write( $dump_table );
		}

		if ( $views ) {
			$dump_views = '';
			foreach ( $views as $view ) {
				$dump_views .= $this->view_structure( $view[0] );
			}
			$this->write( $dump_views );
		}

		if ( $functions ) {
			$dump_functions = '';
			foreach ( $functions as $function ) {
				$dump_functions .= $this->function_structure( $function );
			}
			$this->write( $dump_functions );
		}

		if ( $procedures ) {
			$dump_procedures = '';
			foreach ( $procedures as $procedure ) {
				$dump_procedures .= $this->procedure_structure( $procedure );
			}
			$this->write( $dump_procedures );
		}

		if ( $events ) {
			$dump_events = '';
			foreach ( $events as $event ) {
				$dump_events .= $this->event_structure( $event );
			}
			$this->write( $dump_events );
		}

		if ( $triggers ) {
			$dump_triggers = '';
			foreach ( $triggers as $trigger ) {
				$dump_triggers .= $this->trigger_structure( $trigger );
			}
			$this->write( $dump_triggers );
		}
	}

	/**
	 * @return array
	 */
	private function get_tables()
	{
		$tables = $this->wpdb->get_results( 'SHOW FULL TABLES', ARRAY_N );

		foreach ( $tables as $key => $table ) {
			if ( $table[1] !== 'BASE TABLE' ) {
				unset( $tables[$key] );
			}
		}

		return $tables;
	}

	/**
	 * @return array
	 */
	private function get_views()
	{
		$views = $this->wpdb->get_results( 'SHOW FULL TABLES', ARRAY_N );

		foreach ( $views as $key => $view ) {
			if ( $view[1] !== 'VIEW' ) {
				unset( $views[$key] );
			}
		}

		return $views;
	}

	/**
	 * @return array
	 */
	private function get_stored_routines()
	{
		$routines = $this->wpdb->get_results( 'SELECT * FROM information_schema.ROUTINES', ARRAY_A );
		$result = array();

		foreach ( $routines as $routine ) {
			if ( $routine['ROUTINE_TYPE'] === 'PROCEDURE' ) {
				$result['procedure'][] = $routine['SPECIFIC_NAME'];
			} elseif ( $routine['ROUTINE_TYPE'] === 'FUNCTION' ) {
				$result['function'][] = $routine['SPECIFIC_NAME'];
			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	private function get_triggers()
	{
		$triggers = $this->wpdb->get_results( 'SHOW TRIGGERS', ARRAY_A );
		$result = array();

		foreach ( $triggers as $trigger ) {
			$result[] = $trigger['Trigger'];
		}

		return $result;
	}

	/**
	 * @return array
	 */
	private function get_events()
	{
		$events = $this->wpdb->get_results( 'SHOW EVENTS', ARRAY_A );
		$result = array();

		foreach ( $events as $event ) {
			$result[] = $event['Name'];
		}

		return $result;
	}

	/**
	 * @param $table
	 * @return array|null|object
	 * @throws Xserver_Migrator_Database_Dump_Exception
	 */
	private function get_selected_data( $table )
	{
		$primary_keys = array(
			$this->wpdb->prefix . 'commentmeta'   => 'meta_ID',
			$this->wpdb->prefix . 'comments'      => 'comment_ID',
			$this->wpdb->prefix . 'links'         => 'link_id',
			$this->wpdb->prefix . 'options'       => 'option_id',
			$this->wpdb->prefix . 'postmeta'      => 'meta_id',
			$this->wpdb->prefix . 'posts'         => 'ID',
			$this->wpdb->prefix . 'termmeta'      => 'meta_id',
			$this->wpdb->prefix . 'terms'         => 'term_id',
			$this->wpdb->prefix . 'term_taxonomy' => 'term_taxonomy_id',
			$this->wpdb->prefix . 'usermeta'      => 'umeta_id',
			$this->wpdb->prefix . 'users'         => 'ID',
		);

		$limit = 1000;
		$offset = 0;

		$count = intval( $this->wpdb->get_var( "SELECT COUNT(*) FROM `$table`" ) );

		if ( $count === 0 ) {
			return $this->wpdb->get_results( "SELECT * FROM `$table`", ARRAY_A );
		}

		$id_max = ( isset( $primary_keys[$table] ) ) ? intval( $this->wpdb->get_var( "SELECT MAX(`$primary_keys[$table]`) FROM `$table`" ) ) : 0;
		$execution_count = ( $id_max !== 0 ) ? intval( ceil( $id_max / $limit ) ) : intval( ceil( $count / $limit ) );

		$all_selected_data = array();

		if ( isset( $primary_keys[$table] ) ) {
			$to = 0;
			$primary_col = $primary_keys[$table];

			for ( $i = 0; $i < $execution_count; $i++ ) {
				$from = $to + 1;
				$to = ( $i + 1 ) * $limit;
				$data = $this->wpdb->get_results( "SELECT * FROM `$table` WHERE $primary_col BETWEEN $from AND $to", ARRAY_A );

				foreach ( $data as $row ) {
					$all_selected_data[] = $row;
				}
			}
		} else {
			for ( $i = 0; $i < $execution_count; $i++ ) {
				$data = $this->wpdb->get_results( "SELECT * FROM `$table` LIMIT $limit OFFSET $offset", ARRAY_A );
				foreach ( $data as $row ) {
					$all_selected_data[] = $row;
				}
				$offset = $limit + $offset;
			}
		}

		$all_selected_data_count = count( $all_selected_data );

		if ( $count !== $all_selected_data_count ) {
			throw new Xserver_Migrator_Database_Dump_Exception( "Invalid $table record count: $all_selected_data_count records (expect $count records)." );
		}

		return $all_selected_data;
	}

	/**
	 * @param $table
	 * @return string
	 * @throws Xserver_Migrator_Database_Dump_Exception
	 */
	private function table_data( $table )
	{
		$selected_data = $this->get_selected_data( $table );

		$dumped_data  = "--\n";
		$dumped_data .= "-- Dumping data for table `$table`\n";
		$dumped_data .= "--\n\n";
		$dumped_data .= "LOCK TABLES `$table` WRITE;\n";
		$dumped_data .= "/*!40000 ALTER TABLE `$table` DISABLE KEYS */;\n";

		if ( count( $selected_data ) > 0 ) {
			$division_by = 10;
			foreach ( $selected_data as $key => $data ) {
				$head = ( $key % $division_by === 0 || $key === 0 ) ? "INSERT INTO `$table` VALUES " : '';
				$trailing = ( ( $key + 1 ) % $division_by === 0 || $data === end( $selected_data ) ) ? ";\n" : ',';
				$dumped_data .= $this->insert_into( $head, array_values( $data ), $trailing );
			}

			$dumped_data .= "\n";
		}

		$dumped_data .= "/*!40000 ALTER TABLE `$table` ENABLE KEYS */;\n";
		$dumped_data .= "UNLOCK TABLES;\n\n";

		return $dumped_data;
	}

	/**
	 * @param string $head
	 * @param array $selected_data
	 * @param string $trailing
	 * @return string
	 */
	private function insert_into( $head, $selected_data, $trailing )
	{
		$insert = "$head(";
		foreach ( array_values( $selected_data ) as $data ) {
			$insert .= is_null( $data ) ? 'NULL,' : "'{$this->add_slashes_to_string( $data )}',";
		}
		return rtrim($insert, ',') . ")$trailing";
	}

	/**
	 * @param $table
	 * @return string
	 * @throws Xserver_Migrator_Database_Dump_Exception
	 */
	private function table_structure( $table )
	{
		$create_table_query = $this->wpdb->get_row( "SHOW CREATE TABLE `$table`", ARRAY_N );

		if ( ! $create_table_query ) {
			throw new Xserver_Migrator_Database_Dump_Exception( "Can't get '$table' create table query." );
		}

		$table_structure  = "--\n";
		$table_structure .= "-- Table structure for table `$table`\n";
		$table_structure .= "--\n\n";
		$table_structure .= "DROP TABLE IF EXISTS `$table`;\n";
		$table_structure .= "/*!40101 SET @saved_cs_client    = @@character_set_client */;\n";
		$table_structure .= "/*!40101 SET character_set_client = utf8 */;\n";
		$table_structure .= $create_table_query[1].";\n";
		$table_structure .= "/*!40101 SET character_set_client = @saved_cs_client */;\n\n";

		return $table_structure;
	}

	/**
	 * @param $view
	 * @return string
	 */
	private function view_structure( $view )
	{
		$create_view_query = $this->wpdb->get_row( "SHOW CREATE VIEW `$view`", ARRAY_N );
		$replaced_query = preg_replace( '/CREATE\s.*\sVIEW/', 'CREATE VIEW', $create_view_query[1] );

		if ( $create_view_query[1] === $replaced_query || is_null( $replaced_query ) ) {
			return '';
		}

		$view_structure  = "--\n";
		$view_structure .= "-- View structure for view `$view`\n";
		$view_structure .= "--\n\n";
		$view_structure .= "DROP VIEW IF EXISTS `$view`;\n";
		$view_structure .= $replaced_query . ";\n\n";

		return $view_structure;
	}

	/**
	 * @param $function
	 * @return string
	 */
	private function function_structure( $function )
	{
		$create_function_query = $this->wpdb->get_row( "SHOW CREATE FUNCTION `$function`", ARRAY_A );
		$replaced_query = preg_replace( '/CREATE\s.*\sFUNCTION/', 'CREATE FUNCTION', $create_function_query['Create Function'] );

		if ( $create_function_query['Create Function'] === $replaced_query || is_null( $replaced_query ) ) {
			return '';
		}

		$function_structure  = "DROP FUNCTION IF EXISTS `$function`;\n";
		$function_structure .= "DELIMITER ;;\n";
		$function_structure .= "$replaced_query;;\n";
		$function_structure .= "DELIMITER ;\n\n";

		return $function_structure;
	}

	/**
	 * @param $procedure
	 * @return string
	 */
	private function procedure_structure( $procedure )
	{
		$create_procedure_query = $this->wpdb->get_row( "SHOW CREATE PROCEDURE `$procedure`", ARRAY_A );
		$replaced_query = preg_replace( '/CREATE\s.*\sPROCEDURE/', 'CREATE PROCEDURE', $create_procedure_query['Create Procedure'] );

		if ( $create_procedure_query['Create Procedure'] === $replaced_query || is_null( $replaced_query ) ) {
			return '';
		}

		$procedure_structure  = "DROP PROCEDURE IF EXISTS `$procedure`;\n";
		$procedure_structure .= "DELIMITER ;;\n";
		$procedure_structure .= "$replaced_query;;\n";
		$procedure_structure .= "DELIMITER ;\n\n";

		return $procedure_structure;
	}

	/**
	 * @param $event
	 * @return string
	 */
	private function event_structure( $event )
	{
		$create_event_query = $this->wpdb->get_row( "SHOW CREATE EVENT `$event`", ARRAY_A );
		$replaced_query = preg_replace( '/CREATE\s.*\sEVENT/', 'CREATE EVENT', $create_event_query['Create Event'] );

		if ( $create_event_query['Create Event'] === $replaced_query || is_null( $replaced_query ) ) {
			return '';
		}

		$event_structure  = "DROP EVENT IF EXISTS `$event`;\n";
		$event_structure .= "DELIMITER ;;\n";
		$event_structure .= "$replaced_query ;;\n";
		$event_structure .= "DELIMITER ;\n\n";

		return $event_structure;
	}

	/**
	 * @param $trigger
	 * @return string
	 */
	private function trigger_structure( $trigger )
	{
		$create_trigger_query = $this->wpdb->get_row( "SHOW CREATE TRIGGER $trigger", ARRAY_A );
		$replaced_query = preg_replace( '/CREATE\s.*\sTRIGGER/', 'CREATE TRIGGER', $create_trigger_query['SQL Original Statement'] );

		if ( $create_trigger_query['SQL Original Statement'] === $replaced_query || is_null( $replaced_query ) ) {
			return '';
		}

		$trigger_structure  = "DROP TRIGGER IF EXISTS `$trigger`;\n";
		$trigger_structure .= "DELIMITER ;;\n";
		$trigger_structure .= "$replaced_query ;;\n";
		$trigger_structure .= "DELIMITER ;\n\n";

		return $trigger_structure;
	}

	/**
	 * @param string $str
	 * @return mixed
	 */
	private function add_slashes_to_string( $str = '' )
	{
		return str_replace(
			array( '\\', "\0", "\n", "\r", "'", '"', "\xla" ),
			array( '\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\z' ),
			$str
		);
	}

	/**
	 * @param string $content
	 * @throws Xserver_Migrator_Database_Dump_Exception
	 */
	private function write( $content ) {
		$result = Xserver_Migrator_File::write( $this->dump_file_path, $content );
		if ( ! $result ) {
			Xserver_Migrator_File::remove( $this->dump_file_path );
			$error = Xserver_Migrator_File::read( $this->error_dump_file_path );
			throw new Xserver_Migrator_Database_Dump_Exception( $error );
		}
	}
}