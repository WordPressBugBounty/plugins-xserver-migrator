<?php

class Xserver_Migrator_Archiver
{
	/** @var string エラーログファイルパス */
	private $error_archive_log_file_path;

	/** @var array 除外対象ファイルパス */
	private $exclude_file_path;

	/** @var string 圧縮したファイルパス */
	private $archive_file_path;

	/** @var string ファイル拡張子 */
	private $file_extension;

	/**
	 * Xserver_Migrator_Archiver constructor.
	 */
	public function __construct()
	{
		$this->error_archive_log_file_path = XSERVER_MIGRATOR_WORKSPACE_DIR . 'error_archive.log';

		if ( Xserver_Migrator_Server::is_available_tar_command() ) {
			$this->file_extension = '.tgz';
		} elseif ( Xserver_Migrator_Server::is_available_zip_command() || Xserver_Migrator_Server::is_loaded_zip_extension() ) {
			$this->file_extension = '.zip';
		}

		$random_string = Xserver_Migrator_File::create_random_string();

		$this->archive_file_path = XSERVER_MIGRATOR_WORKSPACE_DIR . $random_string . $this->file_extension;

		$this->exclude_file_path = array(
			'wp-content/cache/*',
			'wp-content' . DIRECTORY_SEPARATOR . XSERVER_MIGRATOR_PLUGIN_NAME . DIRECTORY_SEPARATOR . basename( $this->error_archive_log_file_path ),
			'wp-content' . DIRECTORY_SEPARATOR . XSERVER_MIGRATOR_PLUGIN_NAME . DIRECTORY_SEPARATOR . $random_string . $this->file_extension,

			'wp-content/ai1wm-backups/*',
			'wp-content/updraft/*',
			'wp-content/uploads/backwpup-*',
			'wp-content/uploads/backup-guard/*',
		);
	}

	/**
	 * アーカイブ
	 *
	 * @return array
	 * @throws Xserver_Migrator_Archive_Exception
	 */
	public function archive()
	{
		Xserver_Migrator_Log::info( 'start wp-content archive...' );

		if ( $command = Xserver_Migrator_Server::is_available_tar_command() ) {
			Xserver_Migrator_Log::info( 'create archive is tar' );
			$result = $this->tar_gz( $command );
		} elseif ( $command = Xserver_Migrator_Server::is_available_zip_command() ) {
			Xserver_Migrator_Log::info( 'create archive is zip' );
			$result = $this->zip( $command );
		} elseif ( Xserver_Migrator_Server::is_loaded_zip_extension() ) {
			Xserver_Migrator_Log::info( 'create archive is php-zip' );
			$result = $this->zip_php();
		} else {
			throw new Xserver_Migrator_Archive_Exception( 'Not found archiver' );
		}

		if ( ! $result ) {
			$error = Xserver_Migrator_File::read( $this->error_archive_log_file_path );
			Xserver_Migrator_File::remove( $this->error_archive_log_file_path );
			throw new Xserver_Migrator_Archive_Exception( $error );
		}

		$archive_file_size = $this->get_archived_file_size( $this->archive_file_path );

		Xserver_Migrator_Log::info( 'archived file path=' . $this->archive_file_path . ', archived_file_size=' . $archive_file_size );

		if ( @filesize( $this->error_archive_log_file_path ) > 0 ) {
			$error = Xserver_Migrator_File::read( $this->error_archive_log_file_path );
			Xserver_Migrator_Log::error( $error );
		}

		Xserver_Migrator_File::remove( $this->error_archive_log_file_path );

		Xserver_Migrator_Log::info( 'end wp-content archive' );

		return array(
			'archived_file_name' => $this->archive_file_path,
			'archived_file_size' => $archive_file_size,
		);
	}

	/**
	 * アーカイブファイルパス取得
	 *
	 * @return string
	 */
	public function get_archive_file_path()
	{
		return $this->archive_file_path;
	}

	/**
	 * アーカイブファイルが存在するか
	 *
	 * @return bool
	 */
	public function is_archive_file_exist()
	{
		return file_exists( $this->archive_file_path );
	}

	/**
	 * tarを生成して圧縮 (command)
	 *
	 * @return bool
	 */
	private function tar_gz( $tar_command )
	{
		$exclude_file_arg = '--exclude=' . implode( ' --exclude=', $this->exclude_file_path );

		$command = $tar_command . ' ' . $exclude_file_arg . ' -z -cvf ' . $this->archive_file_path
			. ' -C ' . dirname( WP_CONTENT_DIR ) . ' wp-content 2> ' . $this->error_archive_log_file_path
			. ' > /dev/null ';

		exec( $command, $output, $return_var );

		return $return_var === 0 || $return_var === 1;
	}

	/**
	 * zipで圧縮 (command)
	 *
	 * @return bool
	 */
	private function zip( $zip_command )
	{
		$exclude_file_arg = '--exclude=' . implode( ' --exclude=', $this->exclude_file_path );

		$command = 'cd ' . dirname( WP_CONTENT_DIR ) . ' && ' . $zip_command . ' -r ' . $exclude_file_arg
			. ' ' . $this->archive_file_path . ' wp-content 2> ' . $this->error_archive_log_file_path
			. ' > /dev/null';

		exec( $command, $output, $return_var );

		return $return_var === 0;
	}

	/**
	 * zipで圧縮 (PHP)
	 *
	 * @return bool
	 */
	private function zip_php()
	{
		@set_error_handler( array( $this, 'error_handler' ) );

		$zip = new ZipArchive();
		if ( ! $zip->open( $this->archive_file_path, ZipArchive::CREATE ) ) {
			return false;
		}

		$zip->addEmptyDir( 'wp-content' );

		$wp_content_iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				WP_CONTENT_DIR,
				FilesystemIterator::SKIP_DOTS
				| FilesystemIterator::KEY_AS_FILENAME
				| FilesystemIterator::CURRENT_AS_FILEINFO
			),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $wp_content_iterator as $path => $file_info ) {
			$pathname = $file_info->getPathname();
			$current_iterate_path = 'wp-content' . mb_substr( $pathname, mb_strlen( WP_CONTENT_DIR ) );

			$should_exclude = array_reduce( $this->exclude_file_path, function ( $carry, $exclude ) use ( $current_iterate_path ) {
				return $carry
					|| ( strpos( $current_iterate_path, rtrim( $exclude, '/*' ) ) !== false );
			}, false );
			if ( $should_exclude ) continue;

			if ( $file_info->isDir() ) {
				$zip->addEmptyDir( $current_iterate_path );
				continue;
			}

			$zip->addFile( $pathname, $current_iterate_path );
		}

		$result = $zip->close();
		@restore_error_handler();
		return $result;
	}

	private function error_handler( $errno, $errstr, $errfile, $errline )
	{
		if ( false !== strpos( $errfile, '/xserver-migrator/' ) ) {
			Xserver_Migrator_File::writeLine( $this->error_archive_log_file_path, "$errstr [$errfile:$errline]" );
		}
	}

	/**
	 * ファイルサイズ取得
	 *
	 * @param $file_path
	 * @return bool|int
	 */
	public function get_archived_file_size( $file_path )
	{
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		return @filesize( $file_path );
	}
}