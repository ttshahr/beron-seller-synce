<?php
/**
 * Plugin Name: همگام سازی برون
 * Plugin URI: https://github.com/ttshahr/beron-seller-synce
 * Description: افزونه حرفه‌ای همگام‌سازی محصولات، قیمت‌ها و موجودی با فروشندگان مختلف. قابلیت مدیریت چند فروشنده، پردازش دسته‌ای، لاگ‌گیری پیشرفته و رابط کاربری فارسی.
 * Version: 3.1.9
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

// تعریف ثابت‌ها
define( 'BERON_SELLER_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'BERON_SELLER_SYNC_URL', plugin_dir_url( __FILE__ ) );

// فراخوانی فایل‌های استاتیک برای بخش مدیریت
function beron_load_assets($hook) {
    // فقط در صفحات افزونه خودمون بارگذاری بشه
    if (strpos($hook, 'beron-seller-sync') === false) {
        return;
    }
    
    // CSS
    wp_enqueue_style(
        'beron-progress-css',
        plugins_url('assets/progress.css', __FILE__)
    );
    // در تابع beron_load_assets اضافه کنید:
    // wp_enqueue_style(
    //     'beron-admin-price-css',
    //     plugins_url('assets/admin-price-pages.css', __FILE__)
    // );
    
    // JS
    wp_enqueue_script(
        'beron-progress-js', 
        plugins_url('assets/progress.js', __FILE__),
        array('jquery') // نیاز به jQuery داره
    );
    
    // انتقال داده به JS برای AJAX
    wp_localize_script('beron-progress-js', 'beron_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('beron_nonce')
    ));
}

// وصل کردن به وردپرس
add_action('admin_enqueue_scripts', 'beron_load_assets');

// فایل‌های با اولویت بالا
$priority_files = [
    'inc/class-vendor-logger.php',
    'inc/class-vendor-api-optimizer.php', 
    'meta/class-meta-handler.php'
];

// بارگذاری فایل‌های با اولویت
foreach ( $priority_files as $file ) {
    $file_path = BERON_SELLER_SYNC_PATH . $file;
    if ( file_exists( $file_path ) ) {
        require_once $file_path;
    }
}

// بارگذاری کلی همه فایل‌های پوشه meta
foreach ( glob( BERON_SELLER_SYNC_PATH . 'meta/*.php' ) as $file ) {
    $filename = basename( $file );
    if ( in_array( 'meta/' . $filename, $priority_files ) ) {
        continue; // اگر قبلاً بارگذاری شده، ردش کن
    }
    require_once $file;
}

// بارگذاری کلی همه فایل‌های پوشه inc
foreach ( glob( BERON_SELLER_SYNC_PATH . 'inc/*.php' ) as $file ) {
    $filename = basename( $file );
    if ( in_array( 'inc/' . $filename, $priority_files ) ) {
        continue; // اگر قبلاً بارگذاری شده، ردش کن
    }
    require_once $file;
}

// بارگذاری فایل‌های مدیریت
foreach ( glob( BERON_SELLER_SYNC_PATH . 'admin/*.php' ) as $file ) {
    require_once $file;
}

// بارگذاری فایل‌های هندلر
foreach ( glob( BERON_SELLER_SYNC_PATH . 'handlers/*.php' ) as $file ) {
    require_once $file;
}