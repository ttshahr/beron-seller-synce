<?php
if (!defined('ABSPATH')) exit;

class User_Meta_Manager {
    
    public function __construct() {
        add_action('show_user_profile', [$this, 'render_user_fields']);
        add_action('edit_user_profile', [$this, 'render_user_fields']);
        add_action('personal_options_update', [$this, 'save_user_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_fields']);
    }
    
    public function render_user_fields($user) {
        if (!Vendor_Meta_Handler::is_vendor_user($user->ID)) {
            return;
        }
        
        echo '<h3>اطلاعات فروشنده / همکار</h3>';
        echo '<table class="form-table">';
        
        foreach (Meta_Definitions::USER_META as $meta_key => $definition) {
            $current_value = get_user_meta($user->ID, $meta_key, true);
            
            echo '<tr>';
            echo '<th><label for="' . esc_attr($meta_key) . '">' . esc_html($definition['label']) . '</label></th>';
            echo '<td>';
            
            switch ($definition['type']) {
                case 'select':
                    echo '<select name="' . $meta_key . '" id="' . $meta_key . '">';
                    foreach ($definition['options'] as $value => $label) {
                        echo '<option value="' . esc_attr($value) . '" ' . selected($current_value, $value, false) . '>' . esc_html($label) . '</option>';
                    }
                    echo '</select>';
                    break;
                    
                case 'number':
                    echo '<input type="number" name="' . $meta_key . '" id="' . $meta_key . '" 
                           value="' . esc_attr($current_value) . '" step="' . ($definition['step'] ?? '1') . '" min="0" style="width:100px;">';
                    if (strpos($meta_key, 'percent') !== false) echo ' %';
                    break;
                    
                default:
                    echo '<input type="text" name="' . $meta_key . '" id="' . $meta_key . '" 
                           value="' . esc_attr($current_value) . '" class="regular-text">';
            }
            
            echo '</td></tr>';
        }
        
        echo '</table>';
    }
    
    public function save_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id) || !Vendor_Meta_Handler::is_vendor_user($user_id)) {
            return;
        }
        
        foreach (Meta_Definitions::USER_META as $meta_key => $definition) {
            if (isset($_POST[$meta_key])) {
                update_user_meta($user_id, $meta_key, sanitize_text_field($_POST[$meta_key]));
            }
        }
    }
}