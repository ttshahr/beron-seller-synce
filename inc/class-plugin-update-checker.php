<?php
if (!defined('ABSPATH')) exit;

class Beron_Plugin_Update_Checker {
    
    private $github_url;
    private $plugin_file;
    private $plugin_slug;
    private $current_version;
    
    public function __construct($github_url, $plugin_file, $plugin_slug) {
        $this->github_url = $github_url;
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = $plugin_slug;
        $this->current_version = $this->get_plugin_version();
        
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
    }
    
    private function get_plugin_version() {
        $plugin_data = get_file_data($this->plugin_file, ['Version' => 'Version']);
        return $plugin_data['Version'];
    }
    
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_version = $this->get_remote_version();
        
        if ($remote_version && version_compare($this->current_version, $remote_version, '<')) {
            $obj = new stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->new_version = $remote_version;
            $obj->url = $this->github_url;
            $obj->package = $this->github_url . '/archive/main.zip';
            $transient->response[$this->plugin_file] = $obj;
        }
        
        return $transient;
    }
    
    public function plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information') {
            return $false;
        }
        
        if ($response->slug !== $this->plugin_slug) {
            return $false;
        }
        
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
    
    private function get_remote_version() {
        $response = wp_remote_get($this->github_url . '/raw/main/beron-seller-sync.php');
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $file_content = wp_remote_retrieve_body($response);
        preg_match('/Version:\s*([0-9.]+)/', $file_content, $matches);
        
        return isset($matches[1]) ? $matches[1] : false;
    }
}
