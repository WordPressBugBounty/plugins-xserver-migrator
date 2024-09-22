<?php
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'class-xserver-migrator-database-dumper-abstract.php';

class Xserver_Migrator_Database_Mysqldump_Dumper extends Xserver_Migrator_Database_Dumper_Abstract
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * データベースダンプ
	 *
	 * @return boolean
	 * @throws Xserver_Migrator_Database_Dump_Exception
	 */
	public function dump()
	{
		$mysqldump = $this->get_mysqldump_path();

		$defaults_extra_file = XSERVER_MIGRATOR_WORKSPACE_DIR . Xserver_Migrator_File::create_random_string() . '.txt';

		if ( ! touch( $defaults_extra_file ) ) {
			throw new Xserver_Migrator_Database_Dump_Exception( 'Can\'t create defaults extra file: ' . $defaults_extra_file );
		}

		$content  = '[client]' . PHP_EOL;
		$content .= 'user = \'' . DB_USER . '\'' . PHP_EOL;
		$content .= 'password = \'' . DB_PASSWORD . '\'' . PHP_EOL;
		$content .= 'host = \'' . DB_HOST . '\'' . PHP_EOL;

		Xserver_Migrator_File::writeLine( $defaults_extra_file, $content );

		$privileges = $this->get_privileges();
		$event_option = $this->has_event_privilege( $privileges ) ? '-E' : '';

		$command = $mysqldump . ' --defaults-extra-file=' . $defaults_extra_file
			. ' -R ' . $event_option . ' --default-character-set=' . $this->wpdb->charset . ' ' . DB_NAME
			. ' 2> ' . $this->error_dump_file_path . ' > ' . $this->dump_file_path;

		exec( $command, $output, $return_var );

		if ( ! Xserver_Migrator_File::remove( $defaults_extra_file ) ) {
			Xserver_Migrator_Log::error( 'Can\'t remove file: ' . $defaults_extra_file );
		}

		if ( $return_var !== 0 ) {
			$error = Xserver_Migrator_File::read( $this->error_dump_file_path );
			throw new Xserver_Migrator_Database_Dump_Exception( $error );
		}

		Xserver_Migrator_File::remove( $this->error_dump_file_path );

		$this->replace_definer();

		return true;
	}

	/**
	 * mysqldumpコマンド取得
	 *
	 * @return string
	 */
	private function get_mysqldump_path()
	{
		exec( 'which mysqldump', $output, $status );
		if ( $status === 0 ) {
			return $output[0];
		}
		$output = array();
		exec( 'type mysqldump', $output, $status );
		return $status === 0 ? str_replace( 'mysqldump is ', '', $output[0] ) : '';
	}

	/**
	 * ダンプファイルから"DEFINER"関連の記述を削除する
	 *
	 * @return void
	 */
	private function replace_definer()
	{
		$is_bsd = strpos( Xserver_Migrator_Server::os(), 'BSD' ) > 0;

		if ( $is_bsd ) {
			exec( "sed -i '' -e '/^\/\*!50013 DEFINER=/d' " . $this->dump_file_path, $output, $status );
		} else {
			exec( "sed -i -e '/^\/\*!50013 DEFINER=/d' " . $this->dump_file_path, $output, $status );
		}

		if ( $status !== 0 ) {
			Xserver_Migrator_Log::error( 'Can\'t replace DEFINER comment: ' . $this->dump_file_path );
		}

		if ( $is_bsd ) {
			exec( "sed -i '' -r 's/\/\*\!50020 DEFINER=`.*`@`localhost`\*\/ //g' " . $this->dump_file_path, $output, $status );
		} else {
			exec( "sed -i -E 's/\/\*\!50020 DEFINER=`.*`@`localhost`\*\/ //g' " . $this->dump_file_path, $output, $status );
		}

		if ( $status !== 0 ) {
			Xserver_Migrator_Log::error( 'Can\'t replace DEFINER localhost comment: ' . $this->dump_file_path );
		}

		if ( $is_bsd ) {
			exec( "sed -i '' -r 's/CREATE DEFINER=.+ (FUNCTION|PROCEDURE)/CREATE \\1/g' " . $this->dump_file_path, $output, $status );
		} else {
			exec( "sed -i -E 's/CREATE DEFINER=.+\s(FUNCTION|PROCEDURE)/CREATE \\1/g' " . $this->dump_file_path, $output, $status );
		}

		if ( $status !== 0 ) {
			Xserver_Migrator_Log::error( 'Can\'t replace CREATE DEFINER: ' . $this->dump_file_path );
		}

		// MariaDB 10.5.25 以降で mysqldump が生成するコメントを削除
		if ( $is_bsd ) {
			exec( "sed -i '' -e '/^\/\*!999999\\\- enable the sandbox mode/d' " . $this->dump_file_path, $output, $status );
		} else {
			exec( "sed -i -e '/^\/\*!999999\\\- enable the sandbox mode/d' " . $this->dump_file_path, $output, $status );
		}

		if ( $status !== 0 ) {
			Xserver_Migrator_Log::error( 'Can\'t replace sandbox mode comment: ' . $this->dump_file_path );
		}
	}
}