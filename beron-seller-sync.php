<?php
/**
 * Plugin Name: همگام سازی برون
 * Description: همگام سازی محصولات، فروشندگان، و سفارشات برون
 * Version: 3.0.2
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


// اضافه کردن سیستم آپدیت خودکار از گیتهاب
add_filter('pre_set_site_transient_update_plugins', 'beron_check_github_updates');

function beron_check_github_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    // مسیر صحیح افزونه - با توجه به لاگ
    $plugin_file = 'beron-seller-synce/beron-seller-sync.php';
    
    // اگر افزونه ما در لیست نیست، return کن
    if (!isset($transient->checked[$plugin_file])) {
        error_log("❌ Plugin not found in transient: {$plugin_file}");
        return $transient;
    }
    
    $current_version = $transient->checked[$plugin_file];
    
    // دریافت اطلاعات از گیتهاب
    $github_username = 'ttshahr';
    $github_repo = 'beron-seller-synce';
    
    $release_info = beron_get_github_release_info($github_username, $github_repo);
    
    if ($release_info && version_compare($current_version, $release_info['version'], '<')) {
        $item = new stdClass();
        $item->id = 'beron-seller-sync';
        $item->slug = 'beron-seller-sync';
        $item->plugin = $plugin_file;
        $item->new_version = $release_info['version'];
        $item->url = "https://github.com/{$github_username}/{$github_repo}";
        $item->package = $release_info['download_url'];
        $item->tested = '6.3';
        $item->requires_php = '7.4';
        $item->requires = '6.0';
        $item->icons = array(
            '1x' => 'https://raw.githubusercontent.com/ttshahr/beron-seller-synce/main/assets/icon-128x128.png',
            '2x' => 'https://raw.githubusercontent.com/ttshahr/beron-seller-synce/main/assets/icon-256x256.png'
        );
        
        $transient->response[$plugin_file] = $item;
        
        error_log("🎯 Beron Update Available: {$current_version} -> {$release_info['version']}");
        error_log("🎯 Update package: {$release_info['download_url']}");
    } else {
        error_log("🔍 Beron Check: Current={$current_version}, Remote=" . ($release_info ? $release_info['version'] : 'NOT_FOUND') . ", Update=" . ($release_info && version_compare($current_version, $release_info['version'], '<') ? 'YES' : 'NO'));
    }
    
    return $transient;
}

function beron_get_github_release_info($username, $repo) {
    // روش ۱: از طریق API Releases
    $api_url = "https://api.github.com/repos/{$username}/{$repo}/releases/latest";
    
    $response = wp_remote_get($api_url, array(
        'timeout' => 15,
        'headers' => array(
            'User-Agent' => 'WordPress-Beron-Plugin',
            'Accept' => 'application/vnd.github.v3+json'
        )
    ));
    
    if (is_wp_error($response)) {
        error_log('❌ GitHub API Error: ' . $response->get_error_message());
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        error_log("❌ GitHub API HTTP Error: {$response_code}");
        return false;
    }
    
    $release_data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($release_data['tag_name'])) {
        error_log('❌ No release tag found in GitHub response');
        return false;
    }
    
    $version = ltrim($release_data['tag_name'], 'v');
    $download_url = "https://github.com/{$username}/{$repo}/archive/refs/tags/{$release_data['tag_name']}.zip";
    
    error_log("✅ GitHub Release found: {$version}");
    error_log("✅ Download URL: {$download_url}");
    
    return array(
        'version' => $version,
        'download_url' => $download_url
    );
}

// نمایش وضعیت در پیشخوان - نسخه بهبود یافته
add_action('admin_notices', 'beron_show_update_status');
function beron_show_update_status() {
    if (!current_user_can('manage_options')) return;
    
    $plugin_file = 'beron-seller-synce/beron-seller-sync.php';
    $all_plugins = get_plugins();
    
    if (!isset($all_plugins[$plugin_file])) {
        echo '<div class="notice notice-error">';
        echo '<p>افزونه برون در مسیر ' . esc_html($plugin_file) . ' پیدا نشد!</p>';
        echo '</div>';
        return;
    }
    
    $current_version = $all_plugins[$plugin_file]['Version'];
    $github_username = 'ttshahr';
    $github_repo = 'beron-seller-synce';
    $release_info = beron_get_github_release_info($github_username, $github_repo);
    
    echo '<div class="notice notice-info">';
    echo '<h4>وضعیت آپدیت افزونه برون</h4>';
    echo '<p><strong>مسیر فایل:</strong> ' . esc_html($plugin_file) . '</p>';
    echo '<p><strong>نسخه فعلی:</strong> ' . esc_html($current_version) . '</p>';
    
    if ($release_info) {
        $update_available = version_compare($current_version, $release_info['version'], '<');
        $color = $update_available ? '#d63638' : '#00a32a';
        
        echo '<p><strong>آخرین نسخه در گیتهاب:</strong> <span style="color: ' . $color . '">' . esc_html($release_info['version']) . '</span></p>';
        echo '<p><strong>وضعیت:</strong> ' . ($update_available ? '🟢 بروزرسانی موجود است' : '🟡 آخرین نسخه نصب شده') . '</p>';
        echo '<p><strong>لینک دانلود:</strong> <a href="' . esc_url($release_info['download_url']) . '" target="_blank">مشاهده</a></p>';
        
        if ($update_available) {
            echo '<p><a href="' . admin_url('update-core.php') . '" class="button button-primary">بررسی بروزرسانی‌ها</a></p>';
            
            // دکمه آپدیت فوری
            echo '<p><strong>آپدیت فوری:</strong> ';
            echo '<a href="' . wp_nonce_url(admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($plugin_file)), 'upgrade-plugin_' . $plugin_file) . '" class="button button-secondary">آپدیت همین الان</a>';
            echo '</p>';
        }
    } else {
        echo '<p><strong>آخرین نسخه در گیتهاب:</strong> <span style="color: #d63638;">خطا در دریافت اطلاعات</span></p>';
    }
    
    echo '</div>';
}

// اضافه کردن فیلتر برای اطلاعات افزونه (ضروری برای نمایش در صفحه آپدیت)
add_filter('plugins_api', 'beron_plugin_info', 20, 3);
function beron_plugin_info($false, $action, $response) {
    if ($action !== 'plugin_information') {
        return $false;
    }
    
    if (empty($response->slug) || $response->slug !== 'beron-seller-sync') {
        return $false;
    }
    
    $github_username = 'ttshahr';
    $github_repo = 'beron-seller-synce';
    $release_info = beron_get_github_release_info($github_username, $github_repo);
    
    if (!$release_info) {
        return $false;
    }
    
    $info = new stdClass();
    $info->name = 'همگام سازی برون';
    $info->slug = 'beron-seller-sync';
    $info->version = $release_info['version'];
    $info->author = 'ویرانت';
    $info->author_profile = 'https://github.com/ttshahr';
    $info->requires = '6.0';
    $info->tested = '6.3';
    $info->requires_php = '7.4';
    $info->last_updated = date('Y-m-d');
    $info->homepage = "https://github.com/{$github_username}/{$github_repo}";
    $info->download_link = $release_info['download_url'];
    $info->sections = array(
        'description' => 'افزونه همگام‌سازی محصولات، قیمت‌ها و موجودی با فروشندگان مختلف',
        'changelog' => 'برای مشاهده تغییرات به ریپازیتوری گیتهاب مراجعه کنید: ' . $info->homepage
    );
    
    return $info;
}

// اضافه کردن فیلتر برای درست کردن slug (اختیاری اما مفید)
add_filter('site_transient_update_plugins', 'beron_fix_plugin_slug');
function beron_fix_plugin_slug($transient) {
    if (!empty($transient->response)) {
        $plugin_file = 'beron-seller-synce/beron-seller-sync.php';
        
        if (isset($transient->response[$plugin_file])) {
            $transient->response[$plugin_file]->slug = 'beron-seller-sync';
            $transient->response[$plugin_file]->plugin = $plugin_file;
        }
    }
    
    return $transient;
}