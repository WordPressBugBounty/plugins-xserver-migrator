<?php

class Xserver_Migrator_Archiver
{
	/** @var string エラーログファイルパス */
	private $error_archive_log_file_path;

	/** @var array 除外対象ファイルパス */
	private $exclude_file_path;

	/** @var string 圧縮したファイルパス */
	private $archive_file_path;

	private $archive_reserved = false;

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

		$workspace_name = basename( rtrim( XSERVER_MIGRATOR_WORKSPACE_DIR, DIRECTORY_SEPARATOR ) );
		$this->exclude_file_path = array(
			'wp-content/cache/*',
			'wp-content' . DIRECTORY_SEPARATOR . $workspace_name . DIRECTORY_SEPARATOR . basename( $this->error_archive_log_file_path ),
			'wp-content' . DIRECTORY_SEPARATOR . $workspace_name . DIRECTORY_SEPARATOR . '*.tgz',
			'wp-content' . DIRECTORY_SEPARATOR . $workspace_name . DIRECTORY_SEPARATOR . '*.zip',
			'wp-content' . DIRECTORY_SEPARATOR . $workspace_name . DIRECTORY_SEPARATOR . 'archive.lock',

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

		if ( ! $this->file_extension ) {
			throw new Xserver_Migrator_Archive_Exception( 'Not found archiver' );
		}

		// ファイル名を排他的に確保する。zipコマンドで更新できるよう、空のZIPを作成する。
		$reserved = false;
		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			if ( $attempt > 0 ) {
				$this->archive_file_path = XSERVER_MIGRATOR_WORKSPACE_DIR
					. Xserver_Migrator_File::create_random_string() . $this->file_extension;
			}
			$file = @fopen( $this->archive_file_path, 'xb' );
			if ( $file ) {
				$header_written = true;
				if ( '.zip' === $this->file_extension ) {
					$header_written = 22 === @fwrite( $file, "PK\x05\x06" . str_repeat( "\x00", 18 ) );
				}
				$closed = @fclose( $file );
				if ( ! $header_written || ! $closed ) {
					@unlink( $this->archive_file_path );
					throw new Xserver_Migrator_Archive_Exception( 'Cannot reserve archive file' );
				}
				$reserved = true;
				$this->archive_reserved = true;
				break;
			}
			if ( ! file_exists( $this->archive_file_path ) && ! is_link( $this->archive_file_path ) ) {
				throw new Xserver_Migrator_Archive_Exception( 'Cannot reserve archive file' );
			}
		}
		if ( ! $reserved ) {
			throw new Xserver_Migrator_Archive_Exception( 'Cannot reserve archive file' );
		}

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
			@unlink( $this->archive_file_path );
			throw new Xserver_Migrator_Archive_Exception( 'Not found archiver' );
		}

		if ( ! $result ) {
			@unlink( $this->archive_file_path );
			$error = @Xserver_Migrator_File::read( $this->error_archive_log_file_path );
			Xserver_Migrator_File::remove( $this->error_archive_log_file_path );
			throw new Xserver_Migrator_Archive_Exception( $error ? $error : 'Cannot create archive' );
		}

		$archive_file_size = $this->get_archived_file_size( $this->archive_file_path );
		if ( ! is_int( $archive_file_size ) || $archive_file_size <= 0 ) {
			throw new Xserver_Migrator_Archive_Exception( 'Cannot read archive size' );
		}

		Xserver_Migrator_Log::info( 'archived_file_size=' . $archive_file_size );

		if ( @filesize( $this->error_archive_log_file_path ) > 0 ) {
			Xserver_Migrator_Log::error( 'Archive command reported warnings' );
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
		return $this->archive_reserved ? $this->archive_file_path : false;
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
		$exclude_file_arg = '';
		foreach ( $this->exclude_file_path as $exclude ) {
			$exclude_file_arg .= ' --exclude=' . escapeshellarg( str_replace( DIRECTORY_SEPARATOR, '/', $exclude ) );
		}

		$command = $tar_command . $exclude_file_arg . ' -z -cvf ' . $this->archive_file_path
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
		$exclude_file_arg = '';
		foreach ( $this->exclude_file_path as $exclude ) {
			$exclude_file_arg .= ' ' . escapeshellarg( str_replace( DIRECTORY_SEPARATOR, '/', $exclude ) );
		}

		$command = 'cd ' . dirname( WP_CONTENT_DIR ) . ' && ' . $zip_command . ' -r '
			. $this->archive_file_path . ' wp-content -x' . $exclude_file_arg . ' 2> ' . $this->error_archive_log_file_path
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
			@restore_error_handler();
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
			$relative_path = str_replace( DIRECTORY_SEPARATOR, '/', $current_iterate_path );
			$workspace_prefix = 'wp-content/' . basename( rtrim( XSERVER_MIGRATOR_WORKSPACE_DIR, DIRECTORY_SEPARATOR ) ) . '/';
			if ( 0 === strpos( $relative_path, $workspace_prefix ) ) {
				$workspace_file = substr( $relative_path, strlen( $workspace_prefix ) );
				if ( false === strpos( $workspace_file, '/' )
					&& ( 'archive.lock' === $workspace_file || preg_match( '/\.(tgz|zip)$/', $workspace_file ) ) ) {
					continue;
				}
			}

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

	private function error_handler( $errno, $errstr, $errfile, $errline ) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- PHPのエラーハンドラーに必要な引数。
	{
		if ( false !== strpos( $errfile, '/xserver-migrator/' ) ) {
			Xserver_Migrator_File::writeLine( $this->error_archive_log_file_path, 'Archive backend warning' );
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
