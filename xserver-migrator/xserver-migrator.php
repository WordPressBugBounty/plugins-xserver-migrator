<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * Dashboard. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * this starts the plugin.
 *
 * @link              http://www.xserver.ne.jp
 * @package           Xserver_Migrator
 *
 * @wordpress-plugin
 * Plugin Name:       Xserver Migrator
 * Plugin URI:        https://ja.wordpress.org/plugins/xserver-migrator
 * Description:       エックスサーバー株式会社が提供するレンタルサーバーサービス「エックスサーバー」「wpX Speed」の「WordPress簡単移行機能」専用のプラグインです。
 * Version:           1.6.4
 * Author:            XSERVER Inc.
 * Author URI:        https://www.xserver.ne.jp
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       xserver-migrator
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Access denied.' );
}

// プラグイン名
define( 'XSERVER_MIGRATOR_PLUGIN_NAME', basename( plugin_dir_path( __FILE__ ) ) );

// プラグインディレクトリ
define( 'XSERVER_MIGRATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// プラグイン作業ディレクトリ
define( 'XSERVER_MIGRATOR_WORKSPACE_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'xserver-migrator' . DIRECTORY_SEPARATOR );

// ログファイルパス
define( 'XSERVER_MIGRATOR_LOG_FILE_PATH', XSERVER_MIGRATOR_WORKSPACE_DIR . 'migrator.log' );

// Xserver_Migrator
require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'class-xserver-migrator.php';

// Activator
require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-activator.php';

// Deactivator
require_once XSERVER_MIGRATOR_PLUGIN_DIR . 'packages' . DIRECTORY_SEPARATOR . 'class-xserver-migrator-deactivator.php';

// Activation hook
register_activation_hook( __FILE__, array( 'Xserver_Migrator_Activator', 'activate' ) );

// Deactivation hook
register_deactivation_hook( __FILE__, array( 'Xserver_Migrator_Deactivator', 'deactivate' ) );

// アクション追加
add_action( 'plugins_loaded', 'run_xserver_migrator' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function run_xserver_migrator() {

	Xserver_Migrator::get_instance();

}