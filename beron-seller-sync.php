<?php
/**
 * Plugin Name: همگام سازی برون
 * Description: همگام سازی محصولات، فروشندگان، و سفارشات برون
 * Version: 1.0.0
 * Author: ویرانت
 * Text Domain: beron-seller-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // امنیت: جلوگیری از دسترسی مستقیم
}

// تعریف ثابت‌ها برای مسیرها
define( 'MY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// اتولودر ساده یا اینکلود همه فایل‌های inc
foreach ( glob( MY_PLUGIN_PATH . 'inc/*.php' ) as $file ) {
    include_once $file;
}

// نمونه: اجرای فانکشنی بعد از لود پلاگین
add_action( 'plugins_loaded', 'my_plugin_init' );
function my_plugin_init() {
    // اینجا می‌تونی کلاس‌ها رو اینیت کنی یا فانکشن‌های اولیه رو اجرا کنی
    // if ( class_exists( 'My_Plugin_Admin' ) ) {
    //     new My_Plugin_Admin();
    // }

    // if ( class_exists( 'My_Plugin_Frontend' ) ) {
    //     new My_Plugin_Frontend();
    // }
}