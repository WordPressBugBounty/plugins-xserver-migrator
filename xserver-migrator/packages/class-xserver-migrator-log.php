<?php

class Xserver_Migrator_Log
{
	/** @const string ログファイルパス */
	const LOG_FILE = XSERVER_MIGRATOR_LOG_FILE_PATH;

	/**
	 * ログ書き込み
	 *
	 * @param $text
	 * @param $type
	 */
	private static function output_log( $text, $type )
	{
		$datetime = date( 'Y-m-d H:i:s' );

		if ( ! file_exists( self::LOG_FILE ) ) {
			@touch( self::LOG_FILE );
		}

		if ( is_array( $text ) ) {
			if ( array_values( $text ) === $text ) {
				foreach ( $text as $line ) {
					error_log( sprintf( '%s [%s] %s', $datetime, $type, $line . PHP_EOL ), 3, self::LOG_FILE );
				}
			} else {
				$log = '';
				foreach ( $text as $key => $value ) {
					$log .= $key . '=' . $value;
					if ( end( $text ) !== $text[$key] ) {
						$log .= ', ';
					}
				}
				error_log( sprintf( '%s [%s] %s', $datetime, $type, $log . PHP_EOL ), 3, self::LOG_FILE );
			}
		} else {
			error_log( sprintf( '%s [%s] %s', $datetime, $type, $text . PHP_EOL ), 3, self::LOG_FILE );
		}
	}

	public static function fatal( $fatal )
	{
		self::output_log( $fatal, 'FATAL' );
	}

	public static function error( $error )
	{
		self::output_log( $error, 'ERROR' );
	}

	public static function warn( $warn )
	{
		self::output_log( $warn, 'WARN' );
	}

	public static function info( $info )
	{
		self::output_log( $info, 'INFO' );
	}

	public static function debug( $debug )
	{
		self::output_log( $debug, 'DEBUG' );
	}

	public static function trace( $trace )
	{
		self::output_log( $trace, 'TRACE' );
	}

}