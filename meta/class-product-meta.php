<?php
if (!defined('ABSPATH')) exit;

class Product_Meta_Manager {
    
    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_product_meta_fields']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_meta_fields']);
    }
    
    public function add_product_meta_fields() {
        echo '<div class="options_group">';
        
        foreach (Meta_Definitions::PRODUCT_META as $meta_key => $definition) {
            woocommerce_wp_text_input([
                'id' => $meta_key,
                'label' => $definition['label'],
                'desc_tip' => true,
                'description' => $definition['description'],
                'type' => $definition['type'],
                'custom_attributes' => isset($definition['step']) ? ['step' => 'any', 'min' => '0'] : []
            ]);
        }
        
        echo '</div>';
    }
    
    public function save_product_meta_fields($product) {
        foreach (Meta_Definitions::PRODUCT_META as $meta_key => $definition) {
            if (isset($_POST[$meta_key])) {
                $product->update_meta_data($meta_key, sanitize_text_field($_POST[$meta_key]));
            }
        }
    }
}