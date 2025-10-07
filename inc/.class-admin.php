<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class My_Plugin_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
    }

    public function add_admin_page() {
        add_menu_page(
            __( 'My Plugin', 'my-plugin' ),
            __( 'My Plugin', 'my-plugin' ),
            'manage_options',
            'my-plugin',
            [ $this, 'render_admin_page' ],
            'dashicons-admin-generic'
        );
    }

    public function render_admin_page() {
        echo '<h1>' . __( 'Welcome to My Plugin', 'my-plugin' ) . '</h1>';
    }
}
