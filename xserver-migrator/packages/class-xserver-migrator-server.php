<?php

class Xserver_Migrator_Server
{
	/**
	 * OS取得
	 *
	 * @return string
	 */
	public static function os()
	{
		return PHP_OS;
	}

	/**
	 * PHPのバージョン取得
	 *
	 * @return string
	 */
	public static function php_version()
	{
		return phpversion();
	}

	/**
	 * WordPressのバージョン取得
	 *
	 * @return string
	 */
	public static function wordpress_version()
	{
		global $wp_version;
		return $wp_version;
	}

	/**
	 * WordPressで利用しているDBのバージョン取得
	 *
	 * @return string
	 */
	public static function wordpress_db_version()
	{
		global $wp_db_version;
		return $wp_db_version;
	}

	public static function wordpress_table_prefix()
	{
		global $table_prefix;
		return $table_prefix;
	}

	/**
	 * ドキュメントルート取得
	 *
	 * @return string
	 */
	public static function document_root()
	{
		return $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR;
	}

	/**
	 * メモリ上限取得
	 *
	 * @return string
	 */
	public static function memory_limit()
	{
		return ini_get( 'memory_limit' );
	}

	/**
	 * メモリ使用量取得
	 *
	 * @return float|int
	 */
	public static function memory_usage()
	{
		return memory_get_usage( true ) / 1024 / 1024;
	}

	/**
	 * 最大メモリ使用量取得
	 *
	 * @return float|int
	 */
	public static function max_memory_usage()
	{
		return memory_get_peak_usage( true ) / 1024 / 1024;
	}

	/**
	 * exec()が使えるか
	 *
	 * @return bool
	 */
	public static function is_available_exec()
	{
		exec( 'pwd', $output, $return_var );
		return $return_var === 0;
	}

	/**
	 * zipコマンドが使えるか
	 *
	 * @return string|bool
	 */
	public static function is_available_zip_command()
	{
		exec( 'which zip', $output, $return_var );
		if ( $return_var === 0 ) {
			return $output[0];
		}
		$output = array();
		exec( 'type zip', $output, $return_var );
		return $return_var === 0 ? str_replace( 'zip is ', '', $output[0] ) : false;
	}

	/**
	 * zipinfoコマンドが使えるか
	 *
	 * @return bool
	 */
	public static function is_available_zipinfo_command()
	{
		exec( 'which zipinfo', $output, $return_var );
		return $return_var === 0 ? $output[0] : false;
	}

	/**
	 * tarコマンドが使えるか
	 *
	 * @return string|bool
	 */
	public static function is_available_tar_command()
	{
		exec( 'which tar', $output, $return_var );
		if ( $return_var === 0 ) {
			return $output[0];
		}
		$output = array();
		exec( 'type tar', $output, $return_var );
		return $return_var === 0 ? str_replace( 'tar is ', '', $output[0] ) : false;
	}

	/**
	 * mysqldumpコマンドが使えるか
	 *
	 * @return string|bool
	 */
	public static function is_available_mysqldump()
	{
		exec( 'which mysqldump', $output, $return_var );
		if ( $return_var === 0 ) {
			return $output[0];
		}
		$output = array();
		exec( 'type mysqldump', $output, $return_var );
		return $return_var === 0 ? str_replace( 'mysqldump is ', '', $output[0] ) : false;
	}

	/**
	 * zipモジュールが読み込まれているか
	 *
	 * @return bool
	 */
	public static function is_loaded_zip_extension()
	{
		return extension_loaded( 'zip' );
	}

	/**
	 * ファイル数をカウントできるコマンドが使えるか
	 *
	 * @return array|bool
	 */
	public static function is_available_file_count_command()
	{
		exec( 'which find && which wc', $output, $return_var );
		return $return_var === 0 ? array( 'find' => $output[0], 'wc' => $output[1] ) : false;
	}

	/**
	 * WPで使用しているDBのサイズ
	 * (サイズ取得のSQL実行失敗によるエラーが多いため廃止)
	 *
	 * @return int
	 */
	public static function wpdb_size()
	{
		// DBサイズ超過の検出よりサイズ取得SQLの実行失敗によるエラーのほうが多いと思われるため廃止、固定値を返す
		return 0;
	}
}