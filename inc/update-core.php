<?php
// اضافه کردن سیستم آپدیت خودکار از گیتهاب
add_filter('pre_set_site_transient_update_plugins', 'beron_check_github_updates');
add_filter('upgrader_post_install', 'beron_fix_update_folder', 10, 3);

function beron_check_github_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_file = 'beron-seller-synce/beron-seller-sync.php';
    
    if (!isset($transient->checked[$plugin_file])) {
        return $transient;
    }
    
    $current_version = $transient->checked[$plugin_file];
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
        
        $transient->response[$plugin_file] = $item;
        
        error_log("🎯 Beron Update Available: {$current_version} -> {$release_info['version']}");
    }
    
    return $transient;
}

function beron_fix_update_folder($true, $hook_extra, $result) {
    global $wp_filesystem;
    
    // فقط برای افزونه ما اعمال بشه
    if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== 'beron-seller-synce/beron-seller-sync.php') {
        return $true;
    }
    
    $plugin_dir = WP_PLUGIN_DIR . '/beron-seller-synce/';
    $temp_dir = $result['destination'];
    
    error_log("🔄 Starting folder fix: {$temp_dir} -> {$plugin_dir}");
    
    // محتوای پوشه temp رو بررسی کن
    $temp_items = $wp_filesystem->dirlist($temp_dir);
    
    if (!$temp_items) {
        error_log("❌ No items found in temp directory");
        return $true;
    }
    
    error_log("🔄 Items in temp directory: " . implode(', ', array_keys($temp_items)));
    
    // پوشه اصلی رو پاک کن
    if ($wp_filesystem->exists($plugin_dir)) {
        error_log("🔄 Deleting old plugin directory: {$plugin_dir}");
        $wp_filesystem->delete($plugin_dir, true);
    }
    
    // ایجاد پوشه اصلی دوباره
    if (!$wp_filesystem->mkdir($plugin_dir)) {
        error_log("❌ Failed to create plugin directory");
        return $true;
    }
    
    // هر فایل/پوشه از پوشه temp رو به پوشه اصلی منتقل کن
    $all_moved = true;
    foreach ($temp_items as $item_name => $item_info) {
        $source_path = $temp_dir . '/' . $item_name;
        $destination_path = $plugin_dir . $item_name;
        
        $move_result = $wp_filesystem->move($source_path, $destination_path);
        
        if (!$move_result) {
            error_log("❌ Failed to move: {$item_name}");
            $all_moved = false;
        } else {
            error_log("✅ Successfully moved: {$item_name}");
        }
    }
    
    if ($all_moved) {
        error_log("✅ All files moved successfully to: {$plugin_dir}");
        // پوشه temp رو پاک کن
        $wp_filesystem->delete($temp_dir, true);
        error_log("✅ Temp directory cleaned up");
        
        // فعال کردن مجدد افزونه
        $plugin_file = 'beron-seller-synce/beron-seller-sync.php';
        activate_plugin($plugin_file);
        error_log("✅ Plugin reactivated: {$plugin_file}");
    } else {
        error_log("❌ Some files failed to move");
    }
    
    return $all_moved;
}

function beron_get_github_release_info($username, $repo) {
    $api_url = "https://api.github.com/repos/{$username}/{$repo}/releases/latest";
    
    $response = wp_remote_get($api_url, array(
        'timeout' => 15,
        'headers' => array(
            'User-Agent' => 'WordPress-Beron-Plugin',
            'Accept' => 'application/vnd.github.v3+json'
        )
    ));
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return false;
    }
    
    $release_data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($release_data['tag_name'])) {
        return false;
    }
    
    $version = ltrim($release_data['tag_name'], 'v');
    $download_url = "https://github.com/{$username}/{$repo}/archive/refs/tags/{$release_data['tag_name']}.zip";
    
    return array(
        'version' => $version,
        'download_url' => $download_url
    );
}

// نمایش وضعیت در پیشخوان
add_action('admin_notices', 'beron_show_update_status');
function beron_show_update_status() {
    if (!current_user_can('manage_options')) return;
    
    $plugin_file = 'beron-seller-synce/beron-seller-sync.php';
    $all_plugins = get_plugins();
    
    if (!isset($all_plugins[$plugin_file])) {
        return;
    }
    
    $current_version = $all_plugins[$plugin_file]['Version'];
    $github_username = 'ttshahr';
    $github_repo = 'beron-seller-synce';
    $release_info = beron_get_github_release_info($github_username, $github_repo);
    
    if ($release_info && version_compare($current_version, $release_info['version'], '<')) {
        echo '<div class="notice notice-success">';
        echo '<h4>بروزرسانی افزونه برون موجود است!</h4>';
        echo '<p>نسخه فعلی: <strong>' . esc_html($current_version) . '</strong></p>';
        echo '<p>نسخه جدید: <strong style="color: #00a32a">' . esc_html($release_info['version']) . '</strong></p>';
        echo '<p><a href="' . admin_url('update-core.php') . '" class="button button-primary">بروزرسانی الان</a></p>';
        echo '</div>';
    }
}

// اضافه کردن فیلتر برای اطلاعات افزونه
add_filter('plugins_api', 'beron_plugin_info', 20, 3);
function beron_plugin_info($false, $action, $response) {
    if ($action !== 'plugin_information' || empty($response->slug) || $response->slug !== 'beron-seller-sync') {
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
        'changelog' => 'برای مشاهده تغییرات به ریپازیتوری گیتهاب مراجعه کنید.'
    );
    
    return $info;
}