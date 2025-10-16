<?php
/**
 * Plugin Name: همگام سازی برون
 * Description: همگام سازی محصولات، فروشندگان، و سفارشات برون
 * Version: 3.0.6
 * Author: ویرانت
 * Text Domain: beron-seller-sync
 * Update URI: https://github.com/ttshahr/beron-seller-synce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'Beron_Seller_Sync_PATH', plugin_dir_path( __FILE__ ) );
define( 'Beron_Seller_Sync_URL', plugin_dir_url( __FILE__ ) );

foreach ( glob( Beron_Seller_Sync_PATH . 'inc/*.php' ) as $file ) {
    if ( basename($file) !== 'class-plugin-update-checker.php' ) {
        include_once $file;
    }
}
