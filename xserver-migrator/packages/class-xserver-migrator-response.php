<?php

class Xserver_Migrator_Response
{
	/**
	 * success
	 *
	 * @param     $data
	 * @param int $status_code
	 */
	public static function success( $data, $status_code = 200 )
	{
		wp_send_json_success( $data, $status_code );
	}

    /**
     * error
     *
     * @param string $message
     * @param string $operation
     * @param int $status_code
     * @param bool $is_output_log
     */
	public static function error($message, $operation, $status_code = 500, $is_output_log = true )
	{
		if ($is_output_log === true) {
			Xserver_Migrator_Log::error( $message );
		}

		wp_send_json_error( array( 'message' => $message, 'operation' => $operation ) , $status_code );
	}
}