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
        
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update'], 10, 1);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        
        error_log('Beron Update Checker Initialized: ' . $github_url); // برای دیباگ
    }
    
    public function check_update($transient) {
        // لاگ برای دیباگ
        error_log('Beron Update Check: Checking for updates...');
        
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $current_version = $transient->checked[$this->plugin_file];
        $remote_version = $this->get_remote_version();
        
        error_log("Beron Update Check: Current=$current_version, Remote=$remote_version");
        
        if ($remote_version && version_compare($current_version, $remote_version, '<')) {
            error_log("Beron Update Check: Update available! $current_version -> $remote_version");
            
            $obj = new stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->plugin = $this->plugin_file;
            $obj->new_version = $remote_version;
            $obj->url = $this->github_url;
            $obj->package = $this->github_url . '/archive/main.zip';
            $obj->tested = get_bloginfo('version');
            $transient->response[$this->plugin_file] = $obj;
        } else {
            error_log("Beron Update Check: No update needed");
        }
        
        return $transient;
    }
    
    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information') {
            return $false;
        }
        
        if (isset($response->slug) && $response->slug === $this->plugin_slug) {
            $info = new stdClass();
            $info->name = 'همگام سازی برون';
            $info->slug = $this->plugin_slug;
            $info->version = $this->get_remote_version();
            $info->author = 'ویرانت';
            $info->homepage = $this->github_url;
            $info->download_link = $this->github_url . '/archive/main.zip';
            $info->sections = [
                'description' => 'افزونه همگام‌سازی محصولات، قیمت‌ها و موجودی با فروشندگان مختلف'
            ];
            
            return $info;
        }
        
        return $false;
    }
    
    private function get_remote_version() {
        $response = wp_remote_get('https://raw.githubusercontent.com/ttshahr/beron-seller-synce/main/beron-seller-sync.php', [
            'timeout' => 10,
        ]);
        
        if (is_wp_error($response)) {
            error_log('Beron Update Check: HTTP Error - ' . $response->get_error_message());
            return false;
        }
        
        if (wp_remote_retrieve_response_code($response) !== 200) {
            error_log('Beron Update Check: HTTP Code - ' . wp_remote_retrieve_response_code($response));
            return false;
        }
        
        $file_content = wp_remote_retrieve_body($response);
        preg_match('/Version:\s*([0-9.]+)/', $file_content, $matches);
        
        if (isset($matches[1])) {
            error_log('Beron Update Check: Remote version found - ' . $matches[1]);
            return $matches[1];
        }
        
        error_log('Beron Update Check: No version found in remote file');
        return false;
    }
}