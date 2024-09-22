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
	 * @param string $path .htaccessまでのパス
	 */
	public static function create_htaccess_file( $path )
	{
		$htaccess_file_path = rtrim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . '.htaccess';

		if ( file_exists( $htaccess_file_path ) ) {
			return;
		}

		$content  = '<Files ~ ".(log|sql|txt)$">'. PHP_EOL;
		$content .= '    <IfModule mod_authz_core.c>' . PHP_EOL;
		$content .= '        Require all denied' . PHP_EOL;
		$content .= '    </IfModule>' . PHP_EOL;
		$content .= '    <IfModule !mod_authz_core.c>' . PHP_EOL;
		$content .= '        Order allow,deny' . PHP_EOL;
		$content .= '        Deny from all' . PHP_EOL;
		$content .= '    </IfModule>' . PHP_EOL;
		$content .= '</Files>'. PHP_EOL;

		Xserver_Migrator_File::writeLine( $htaccess_file_path, $content );
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
		return substr( str_shuffle( implode( range( 0, 9 ) ) . implode( range( 'a', 'z' ) ) . implode( range( 'A', 'Z' ) ) ), 0, $length );
	}
}