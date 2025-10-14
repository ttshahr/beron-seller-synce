<?php
if (!defined('ABSPATH')) exit;

class Beron_Plugin_Update_Checker {
    
    private $github_url;
    private $plugin_file;
    private $plugin_slug;
    
    public function __construct($github_url, $plugin_file, $plugin_slug) {
        $this->github_url = $github_url;
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = $plugin_slug;
        
        // اضافه کردن فیلترها
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        
        // برای دیباگ
        add_action('admin_notices', array($this, 'debug_notice'));
    }
    
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // دریافت ورژن از گیت‌هاب
        $remote_version = $this->get_remote_version();
        $current_version = $transient->checked[$this->plugin_file];
        
        if ($remote_version && version_compare($current_version, $remote_version, '<')) {
            $plugin_data = get_plugin_data($this->plugin_file);
            
            $item = new stdClass();
            $item->id = $this->plugin_slug;
            $item->slug = $this->plugin_slug;
            $item->plugin = $this->plugin_file;
            $item->new_version = $remote_version;
            $item->url = $this->github_url;
            $item->package = $this->github_url . '/archive/main.zip';
            $item->tested = $plugin_data['TestedUpTo'] ?? '6.0';
            $item->requires_php = $plugin_data['RequiresPHP'] ?? '7.4';
            
            $transient->response[$this->plugin_file] = $item;
        }
        
        return $transient;
    }
    
    public function plugin_info($false, $action, $arg) {
        if ($action !== 'plugin_information') {
            return $false;
        }
        
        if (!isset($arg->slug) || $arg->slug !== $this->plugin_slug) {
            return $false;
        }
        
        $remote_version = $this->get_remote_version();
        
        $info = new stdClass();
        $info->name = 'همگام سازی برون';
        $info->slug = $this->plugin_slug;
        $info->version = $remote_version;
        $info->author = 'ویرانت';
        $info->author_profile = 'https://github.com/ttshahr';
        $info->requires = '6.0';
        $info->tested = '6.0';
        $info->requires_php = '7.4';
        $info->last_updated = date('Y-m-d');
        $info->homepage = $this->github_url;
        $info->download_link = $this->github_url . '/archive/main.zip';
        
        $info->sections = array(
            'description' => 'افزونه همگام‌سازی محصولات، قیمت‌ها و موجودی با فروشندگان مختلف',
            'changelog' => 'بروزرسانی از طریق گیت‌هاب'
        );
        
        return $info;
    }
    
    private function get_remote_version() {
        $response = wp_remote_get(
            'https://raw.githubusercontent.com/ttshahr/beron-seller-synce/main/beron-seller-sync.php',
            array('timeout' => 10)
        );
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $file_content = wp_remote_retrieve_body($response);
        
        if (preg_match('/Version:\s*([0-9.]+)/i', $file_content, $matches)) {
            return $matches[1];
        }
        
        return false;
    }
    
    public function debug_notice() {
        if (current_user_can('manage_options') && isset($_GET['page']) && $_GET['page'] === 'plugins.php') {
            $remote_version = $this->get_remote_version();
            $current_version = get_plugin_data($this->plugin_file)['Version'];
            
            echo '<div class="notice notice-info">';
            echo '<p>دیباگ آپدیت چکر: Local=' . $current_version . ', Remote=' . ($remote_version ?: 'Error') . '</p>';
            echo '</div>';
        }
    }
}