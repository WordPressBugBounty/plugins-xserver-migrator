<?php

class Xserver_Migrator_SSL
{
	/**
	 * acme challenge用ファイル生成
	 *
	 * @param string $file_path
	 * @param string $contents
	 * @return array
	 */
	public function create_file( $file_name, $contents )
	{
		$is_created_dir_1 = false;
		$is_created_dir_2 = false;

		$document_root = Xserver_Migrator_Server::document_root();

		$file_path = $document_root . '.well-known' . DIRECTORY_SEPARATOR . 'acme-challenge' . DIRECTORY_SEPARATOR . $file_name;

		if ( ! is_dir( $document_root . '.well-known' ) ) {
			$is_created_dir_1 = true;
			@mkdir( $document_root . '.well-known' );
		}

		if ( ! is_dir( dirname( $file_path ) ) ) {
			$is_created_dir_2 = true;
			@mkdir( dirname( $file_path ) );
		}

		$result = file_put_contents( $file_path, $contents );

		if ( ! $result ) {
			return array();
		}

		return array(
			'file_path' => $file_path . $file_name,
			'is_created_dir_1' => $is_created_dir_1,
			'is_created_dir_2' => $is_created_dir_2,
		);
	}

	/**
	 * acme challenge用ファイル削除
	 *
	 * @param $file_path
	 * @param $token
	 */
	public function delete_file( $file_path, $is_created_dir_1, $is_created_dir_2 )
	{
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$dir_1_path = dirname( dirname( $file_path ) );
		$dir_2_path = dirname( $file_path );

		if ( $is_created_dir_1 == 1 && basename( $dir_1_path ) === '.well-known' ) {
			Xserver_Migrator_File::remove( $dir_1_path );
		} elseif ( ( $is_created_dir_1 == 0 && $is_created_dir_2 == 1 ) && basename( $dir_2_path ) === 'acme-challenge' ) {
			Xserver_Migrator_File::remove( $dir_2_path );
		} else {
			Xserver_Migrator_File::remove( $file_path );
		}

		return array(
			'dir_1_path' => $dir_1_path,
			'dir_2_path' => $dir_2_path,
			'dir_1_created' => $is_created_dir_1,
			'dir_2_created' => $is_created_dir_2,
		);
	}
}