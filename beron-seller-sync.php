<?php
/**
 * Plugin Name: همگام سازی برون
 * Plugin URI: https://github.com/ttshahr/beron-seller-synce
 * Description: افزونه حرفه‌ای همگام‌سازی محصولات، قیمت‌ها و موجودی با فروشندگان مختلف. قابلیت مدیریت چند فروشنده، پردازش دسته‌ای، لاگ‌گیری پیشرفته و رابط کاربری فارسی.
 * Version: 3.1.2.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.6
 * Author: ویرانت
 * Author URI: https://github.com/ttshahr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: beron-seller-sync
 * Domain Path: /languages
 * Update URI: https://github.com/ttshahr/beron-seller-synce
 * 
 * @package BeronSellerSync
 * @category WooCommerce
 * @author ویرانت
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'Beron_Seller_Sync_PATH', plugin_dir_path( __FILE__ ) );
define( 'Beron_Seller_Sync_URL', plugin_dir_url( __FILE__ ) );

foreach ( glob( Beron_Seller_Sync_PATH . 'inc/*.php' ) as $file ) {

        include_once $file;
}
