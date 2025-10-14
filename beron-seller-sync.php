<?php
/**
 * Plugin Name: همگام سازی برون
 * Description: همگام سازی محصولات، فروشندگان، و سفارشات برون
 * Version: 3.0.1
 * Author: ویرانت
 * Text Domain: beron-seller-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'Beron_Seller_Sync_PATH', plugin_dir_path( __FILE__ ) );
define( 'Beron_Seller_Sync_URL', plugin_dir_url( __FILE__ ) );

// اضافه کردن منوی چک آپدیت دستی
add_action('admin_menu', function() {
    add_submenu_page(
        'vendor-sync',
        'بروزرسانی افزونه',
        'بروزرسانی',
        'manage_woocommerce',
        'vendor-sync-update',
        'beron_render_update_page'
    );
});

function beron_render_update_page() {
    ?>
    <div class="wrap">
        <h1>بروزرسانی افزونه از گیت‌هاب</h1>
        
        <?php
        if (isset($_POST['check_update'])) {
            beron_check_github_update();
        }
        
        if (isset($_POST['do_update'])) {
            beron_do_manual_update();
        }
        ?>
        
        <div class="card">
            <h2>وضعیت فعلی</h2>
            <p>ورژن نصب شده: <strong>3.0.0</strong></p>
            <p>ورژن گیت‌هاب: <strong id="github-version"><?php echo beron_get_remote_version(); ?></strong></p>
        </div>
        
        <form method="post">
            <button type="submit" name="check_update" class="button button-primary">بررسی بروزرسانی</button>
            <button type="submit" name="do_update" class="button button-secondary">نصب بروزرسانی</button>
        </form>
    </div>
    <?php
}

function beron_get_remote_version() {
    $response = wp_remote_get('https://raw.githubusercontent.com/ttshahr/beron-seller-synce/main/beron-seller-sync.php');
    
    if (is_wp_error($response)) {
        return 'خطا در دریافت';
    }
    
    $file_content = wp_remote_retrieve_body($response);
    preg_match('/Version:\s*([0-9.]+)/', $file_content, $matches);
    
    return isset($matches[1]) ? $matches[1] : 'نامشخص';
}

// بقیه include ها...
foreach ( glob( Beron_Seller_Sync_PATH . 'inc/*.php' ) as $file ) {
    include_once $file;
}