<?php

class Xserver_Migrator_Download
{
	const ACTION = 'xserver_migrator_download_archive';
	const LOCK_FILE = 'archive.lock';
	const RETENTION = 86400;

	/**
	 * アーカイブ名を最後のパラメータにした、サイトURL基準のパスを返す。
	 *
	 * @param string $archive_file_path アーカイブの絶対パス。
	 * @return string|false
	 */
	public static function create_download_path( $archive_file_path )
	{
		$archive_id = basename( $archive_file_path );
		if ( ! self::valid_id( $archive_id ) || ! self::archive_path( $archive_id ) ||
			realpath( $archive_file_path ) !== self::archive_path( $archive_id ) ) {
			Xserver_Migrator_Log::error( 'Archive download: invalid archive path' );
			return false;
		}

		$expires = time() + 3600;
		$sig = self::signature( $archive_id, $expires );
		$path = parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- 対応対象のWordPress 4.0にはwp_parse_urlがない。
		$site_path = parse_url( home_url(), PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress 4.0との互換性を保つ。
		$site_path = is_string( $site_path ) ? rtrim( $site_path, '/' ) : '';
		if ( ! is_string( $path ) || '/' !== substr( $path, 0, 1 ) ||
			0 !== strpos( $path, $site_path . '/' ) ||
			'/wp-admin/admin-ajax.php' !== substr( $path, -strlen( '/wp-admin/admin-ajax.php' ) ) ) {
			Xserver_Migrator_Log::error( 'Archive download: invalid admin URL' );
			return false;
		}
		$path = substr( $path, strlen( $site_path ) );

		// cronが動かない場合は更新日時を発行時刻として起動時に掃除する。
		if ( ! @touch( $archive_file_path ) ) {
			Xserver_Migrator_Log::error( 'Archive download: cannot update retention time' );
			return false;
		}

		return $path . '?action=' . self::ACTION . '&expires=' . $expires . '&sig=' . $sig . '&archive_id=' . $archive_id;
	}

	/**
	 * ログインCookieを使用せず、アーカイブ全体を配信する。
	 */
	public static function download_archive()
	{
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			self::fail( 400, 'Invalid request method' );
		}

		$query = self::download_query();
		if ( false === $query ) {
			self::fail( 400, 'Invalid download parameters' );
		}

		if ( time() > (int) $query['expires'] ||
			! self::constant_time_equal( self::signature( $query['archive_id'], $query['expires'] ), $query['sig'] ) ) {
			self::fail( 403, 'Invalid or expired download signature' );
		}

		$lock = self::lock_workspace( true );
		if ( false === $lock ) {
			self::fail( 500, 'Archive temporarily unavailable' );
		}

		$path = self::archive_path( $query['archive_id'] );
		if ( false === $path ) {
			self::unlock_workspace( $lock );
			self::fail( 404, 'Archive not found' );
		}

		$file = @fopen( $path, 'rb' );
		if ( false === $file ) {
			self::unlock_workspace( $lock );
			self::fail( 500, 'Archive cannot be read' );
		}

		$stat = @fstat( $file );
		$size = $stat ? $stat['size'] : false;
		$path_stat = @stat( $path );
		if ( ! $stat || ( $stat['mode'] & 0170000 ) !== 0100000 ||
			! is_int( $size ) || $size < 0 ||
			$path !== self::archive_path( $query['archive_id'] ) ||
			! $path_stat || $stat['dev'] !== $path_stat['dev'] || $stat['ino'] !== $path_stat['ino'] ) {
			fclose( $file );
			self::unlock_workspace( $lock );
			self::fail( 500, 'Archive cannot be read' );
		}

		@set_time_limit( 0 );
		@ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- Content-Lengthと送信サイズを一致させるため圧縮を無効にする。
		$compression = ini_get( 'zlib.output_compression' );
		if ( $compression && 'Off' !== $compression ) {
			fclose( $file );
			self::unlock_workspace( $lock );
			self::fail( 500, 'Archive cannot be streamed without compression' );
		}
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv -- Webサーバーによるアーカイブの再圧縮を抑止する。
		}
		while ( ob_get_level() > 0 ) {
			if ( ! @ob_end_clean() ) {
				break;
			}
		}
		if ( ob_get_level() > 0 ) {
			fclose( $file );
			self::unlock_workspace( $lock );
			self::fail( 500, 'Archive output buffer cannot be cleared' );
		}
		ignore_user_abort( true );
		status_header( 200 );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Length: ' . $size );
		header( 'Content-Disposition: attachment; filename="' . $query['archive_id'] . '"' );
		header( 'Cache-Control: no-store' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Accel-Buffering: no' );

		$sent = 0;
		$failed = false;
		while ( $sent < $size ) {
			$chunk = @fread( $file, min( 1048576, $size - $sent ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- 大容量ファイルをメモリに載せず逐次配信する。
			if ( false === $chunk || '' === $chunk ) {
				$failed = true;
				break;
			}
			echo $chunk;
			$sent += strlen( $chunk );
			flush();
			if ( connection_aborted() ) {
				$failed = true;
				break;
			}
		}
		fclose( $file );

		if ( ! $failed && $sent === $size && ! connection_aborted() ) {
			if ( ! @unlink( $path ) ) {
				Xserver_Migrator_Log::error( 'Archive download: cannot delete delivered archive' );
			}
		} else {
			Xserver_Migrator_Log::error( 'Archive download: incomplete transfer' );
		}
		self::unlock_workspace( $lock );
		exit;
	}

	/**
	 * 残存したアーカイブの削除を発行から24時間後に予約する。
	 *
	 * @param string $archive_file_path アーカイブの絶対パス。
	 * @return bool
	 */
	public static function schedule_cleanup( $archive_file_path )
	{
		$archive_id = basename( $archive_file_path );
		$path = self::archive_path( $archive_id );
		if ( false === $path || $path !== realpath( $archive_file_path ) ) {
			Xserver_Migrator_Log::error( 'Archive cleanup: invalid archive path' );
			return false;
		}
		wp_schedule_single_event( time() + self::RETENTION, 'xserver_migrator_cleanup_archive', array( $archive_id ) );
		if ( ! wp_next_scheduled( 'xserver_migrator_cleanup_archive', array( $archive_id ) ) ) {
			Xserver_Migrator_Log::error( 'Archive cleanup: scheduling failed' );
			return false;
		}
		return true;
	}

	/**
	 * 保存期限を過ぎたアーカイブを削除する。
	 *
	 * @param string $archive_id アーカイブのファイル名。
	 * @return bool
	 */
	public static function cleanup_archive( $archive_id )
	{
		if ( ! self::valid_id( $archive_id ) ) {
			Xserver_Migrator_Log::error( 'Archive cleanup: invalid archive name' );
			return false;
		}
		$lock = self::lock_workspace( false );
		if ( false === $lock ) {
			Xserver_Migrator_Log::error( 'Archive cleanup: workspace busy' );
			return false;
		}
		$path = self::archive_path( $archive_id );
		$result = true;
		if ( false === $path && ( file_exists( XSERVER_MIGRATOR_WORKSPACE_DIR . $archive_id ) ||
			is_link( XSERVER_MIGRATOR_WORKSPACE_DIR . $archive_id ) ) ) {
			Xserver_Migrator_Log::error( 'Archive cleanup: unsafe archive path' );
			$result = false;
		} elseif ( false !== $path ) {
			$mtime = @filemtime( $path );
			if ( false === $mtime ) {
				Xserver_Migrator_Log::error( 'Archive cleanup: cannot read archive age' );
				$result = false;
			} elseif ( $mtime <= time() - self::RETENTION && ! @unlink( $path ) ) {
				Xserver_Migrator_Log::error( 'Archive cleanup: cannot delete archive' );
				$result = false;
			}
		}
		self::unlock_workspace( $lock );
		return $result;
	}

	/**
	 * WP-Cronが動かなかった場合に古いアーカイブを掃除する。
	 */
	public static function cleanup_expired_archives()
	{
		$lock = self::lock_workspace( false );
		if ( false === $lock ) {
			return;
		}
		$workspace = realpath( XSERVER_MIGRATOR_WORKSPACE_DIR );
		if ( false === $workspace ) {
			self::unlock_workspace( $lock );
			return;
		}
		$entries = @scandir( $workspace );
		if ( false === $entries ) {
			Xserver_Migrator_Log::error( 'Archive cleanup: cannot list workspace' );
			self::unlock_workspace( $lock );
			return;
		}
		foreach ( $entries as $archive_id ) {
			if ( ! self::valid_id( $archive_id ) ) {
				continue;
			}
			$path = self::archive_path( $archive_id );
			if ( false !== $path ) {
				$mtime = @filemtime( $path );
				if ( false === $mtime ) {
					Xserver_Migrator_Log::error( 'Archive cleanup: cannot read archive age' );
				} elseif ( $mtime <= time() - self::RETENTION && ! @unlink( $path ) ) {
					Xserver_Migrator_Log::error( 'Archive cleanup: cannot delete archive' );
				}
			}
		}
		self::unlock_workspace( $lock );
	}

	private static function valid_id( $archive_id )
	{
		return is_string( $archive_id ) && 1 === preg_match( '/\A[0-9A-Za-z]{32}\.(?:tgz|zip)\z/D', $archive_id );
	}

	private static function archive_path( $archive_id )
	{
		$workspace = realpath( XSERVER_MIGRATOR_WORKSPACE_DIR );
		if ( false === $workspace || ! self::valid_id( $archive_id ) ) {
			return false;
		}
		$path = $workspace . DIRECTORY_SEPARATOR . $archive_id;
		if ( is_link( $path ) || ! is_file( $path ) || realpath( $path ) !== $path ) {
			return false;
		}
		return $path;
	}

	private static function download_query()
	{
		if ( ! isset( $_SERVER['QUERY_STRING'] ) || ! is_string( $_SERVER['QUERY_STRING'] ) ||
			strlen( $_SERVER['QUERY_STRING'] ) > 256 || count( $_GET ) !== 4 ) {
			return false;
		}
		$parts = explode( '&', $_SERVER['QUERY_STRING'] );
		if ( 4 !== count( $parts ) ) {
			return false;
		}
		$query = array();
		foreach ( $parts as $part ) {
			$pair = explode( '=', $part, 2 );
			if ( 2 !== count( $pair ) || ! in_array( $pair[0], array( 'action', 'expires', 'sig', 'archive_id' ), true ) ||
				isset( $query[$pair[0]] ) ) {
				return false;
			}
			$query[$pair[0]] = $pair[1];
		}
		if ( ! isset( $query['action'], $query['expires'], $query['sig'], $query['archive_id'] ) ||
			self::ACTION !== $query['action'] || ! self::valid_id( $query['archive_id'] ) ||
			! preg_match( '/\A(?:0|[1-9][0-9]*)\z/D', $query['expires'] ) ||
			! preg_match( '/\A[0-9a-f]{64}\z/D', $query['sig'] ) ) {
			return false;
		}
		foreach ( $query as $key => $value ) {
			if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) || $_GET[ $key ] !== $value ) {
				return false;
			}
		}
		$expires = filter_var( $query['expires'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
		if ( false === $expires || (string) $expires !== $query['expires'] ) {
			return false;
		}
		return $query;
	}

	private static function signature( $archive_id, $expires )
	{
		return hash_hmac( 'sha256', "xserver_migrator_download_archive_v1\n" . $archive_id . "\n" . $expires, wp_salt( 'auth' ) );
	}

	private static function constant_time_equal( $expected, $actual )
	{
		if ( function_exists( 'hash_equals' ) ) {
			return hash_equals( $expected, $actual );
		}
		$length = strlen( $expected );
		if ( $length !== strlen( $actual ) ) {
			return false;
		}
		$diff = 0;
		for ( $i = 0; $i < $length; $i++ ) {
			$diff |= ord( $expected[$i] ) ^ ord( $actual[$i] );
		}
		return 0 === $diff;
	}

	private static function lock_workspace( $wait )
	{
		if ( ! is_dir( XSERVER_MIGRATOR_WORKSPACE_DIR ) ) {
			return false;
		}
		$lock = @fopen( XSERVER_MIGRATOR_WORKSPACE_DIR . self::LOCK_FILE, 'c+' );
		if ( false === $lock ) {
			return false;
		}
		$deadline = microtime( true ) + 5;
		do {
			if ( @flock( $lock, LOCK_EX | LOCK_NB ) ) {
				return $lock;
			}
			if ( ! $wait ) {
				break;
			}
			usleep( 100000 );
		} while ( microtime( true ) < $deadline );
		fclose( $lock );
		return false;
	}

	private static function unlock_workspace( $lock )
	{
		flock( $lock, LOCK_UN );
		fclose( $lock );
	}

	private static function fail( $status, $message )
	{
		Xserver_Migrator_Log::error( 'Archive download: ' . $message );
		status_header( $status );
		wp_send_json_error( array( 'message' => $message, 'operation' => 'archive' ), $status );
		exit;
	}
}
