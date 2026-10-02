<?php

class Xserver_Migrator_File
{
	/**
	 * 書き込み
	 *
	 * @param $file
	 * @param $text
	 * @return bool
	 */
	public static function write( $file, $text )
	{
		$fp = fopen( $file, 'ab' );
		$result = fwrite( $fp, $text );
		fclose( $fp );
		return $result !== false;
	}

	/**
	 * 書き込み
	 *
	 * @param $file
	 * @param $text
	 * @return bool
	 */
	public static function writeLine( $file, $text )
	{
		return self::write( $file, $text . PHP_EOL );
	}

	/**
	 * 読み込み
	 *
	 * @param $file
	 * @return bool|string
	 */
	public static function read( $file )
	{
		return file_get_contents( $file );
	}

	/**
	 * ファイル、ディレクトリ削除
	 *
	 * @param $file
	 * @return bool
	 */
	public static function remove( $file )
	{
		if ( ! file_exists( $file ) ) {
			return false;
		}

		if ( is_dir( $file ) ) {
			$directory_iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $file, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $directory_iterator as $item ) {
				if ( $item->isDir() ) {
					@rmdir( $item->getPathname() );
				} else {
					@unlink( $item->getPathname() );
				}
			}

			return @rmdir( $file );
		}

		return @unlink( $file );
	}

	/**
	 * index.phpを生成
	 *
	 * @param string $path index.phpまでのパス
	 */
	public static function create_index_file( $path )
	{
		$index_file_path = rtrim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'index.php';

		if ( file_exists( $index_file_path ) ) {
			return;
		}

		Xserver_Migrator_File::writeLine( $index_file_path, '<?php // Silence is golden' );
	}

	/**
	 * .htaccessを生成
	 *
	 * @param string $path .htaccessを置くディレクトリ
	 * @return bool
	 */
	public static function create_htaccess_file( $path )
	{
		$htaccess_file_path = rtrim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '.htaccess';
		$legacy = '<Files ~ ".(log|sql|txt)$">' . "\n"
			. '    <IfModule mod_authz_core.c>' . "\n"
			. '        Require all denied' . "\n"
			. '    </IfModule>' . "\n"
			. '    <IfModule !mod_authz_core.c>' . "\n"
			. '        Order allow,deny' . "\n"
			. '        Deny from all' . "\n"
			. '    </IfModule>' . "\n"
			. '</Files>' . "\n\n";
		$content = '<IfModule mod_authz_core.c>' . "\n"
			. '    Require all denied' . "\n"
			. '</IfModule>' . "\n"
			. '<IfModule !mod_authz_core.c>' . "\n"
			. '    Order allow,deny' . "\n"
			. '    Deny from all' . "\n"
			. '</IfModule>' . "\n";

		if ( is_link( $htaccess_file_path ) ) {
			return false;
		}

		if ( file_exists( $htaccess_file_path ) ) {
			if ( ! is_file( $htaccess_file_path ) || ! is_readable( $htaccess_file_path ) ) {
				return false;
			}
			$existing = @file_get_contents( $htaccess_file_path );
			if ( false === $existing ) {
				return false;
			}
			if ( $existing === $content ) {
				return true;
			}
			if ( str_replace( "\r\n", "\n", $existing ) !== $legacy ) {
				return false;
			}
		}

		$temporary_file = false;
		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$temporary_path = $htaccess_file_path . '.' . self::create_random_string() . '.tmp';
			$temporary_file = @fopen( $temporary_path, 'xb' );
			if ( $temporary_file ) {
				break;
			}
			if ( ! file_exists( $temporary_path ) && ! is_link( $temporary_path ) ) {
				return false;
			}
		}
		if ( ! $temporary_file ) {
			return false;
		}

		$written = 0;
		$length = strlen( $content );
		while ( $written < $length ) {
			$count = @fwrite( $temporary_file, substr( $content, $written ) );
			if ( false === $count || 0 === $count ) {
				break;
			}
			$written += $count;
		}
		$flushed = $written === $length && @fflush( $temporary_file );
		$closed = @fclose( $temporary_file );
		clearstatcache( true, $htaccess_file_path );
		if ( ! $flushed || ! $closed || is_link( $htaccess_file_path ) ) {
			@unlink( $temporary_path );
			return false;
		}

		// 一時ファイルの書き込み中に変更された設定は上書きしない。
		if ( file_exists( $htaccess_file_path ) ) {
			$existing = @file_get_contents( $htaccess_file_path );
			if ( false === $existing || str_replace( "\r\n", "\n", $existing ) !== $legacy ) {
				@unlink( $temporary_path );
				return $existing === $content;
			}
		} else {
			// ハードリンクで完成したファイルを公開し、同時に作られた設定は上書きしない。
			$created = @link( $temporary_path, $htaccess_file_path );
			@unlink( $temporary_path );
			return $created;
		}
		if ( ! @rename( $temporary_path, $htaccess_file_path ) ) {
			@unlink( $temporary_path );
			return false;
		}
		return true;
	}

	/**
	 * ファイル名書き換え
	 *
	 * @param $file_path
	 * @param $file_name
	 */
	public static function rename_file( $file_path, $file_name )
	{
		$new_file_path = dirname( $file_path );
		rename( $file_path, $new_file_path . DIRECTORY_SEPARATOR . $file_name );
	}

	/**
	 * ランダム文字列生成
	 *
	 * @param  int
	 * @return string
	 */
	public static function create_random_string( $length = 32 )
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$result = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$result .= $characters[ wp_rand( 0, 61 ) ];
		}
		return $result;
	}
}