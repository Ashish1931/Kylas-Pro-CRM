<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Kylas_CRM_Admin_Menu {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts') );
    }

    public function enqueue_scripts( $hook ) {
        // Only load scripts/styles on our plugin pages
        if ( strpos( $hook, 'kylas-crm-mapping' ) !== false ) {
            wp_enqueue_style(
                'kylas-crm-admin-mapping',
                KYLAS_CRM_PLUGIN_URL . 'assets/css/admin-mapping.css',
                array(),
                KYLAS_CRM_VERSION
            );

            wp_enqueue_script(
                'kylas-crm-admin-mapping',
                KYLAS_CRM_PLUGIN_URL . 'assets/js/admin-mapping.js',
                array('jquery'),
                KYLAS_CRM_VERSION,
                true
            );
        }

        if ( strpos( $hook, 'kylas-crm' ) === false ) {
            return;
        }

        wp_enqueue_script( 'jquery' ); 
    }

    public function add_menu_page() {
        add_menu_page(
            'Kylas CRM',
            'Kylas CRM',
            'manage_options',
            'kylas-crm',
            array($this, 'render_settings_page'),
            'dashicons-chart-bar',
            6
        );

        add_submenu_page(
            'kylas-crm',
            'Settings',
            'Settings',
            'manage_options',
            'kylas-crm',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'kylas-crm',
            'Field Mapping',
            'Field Mapping',
            'manage_options',
            'kylas-crm-mapping',
            array($this, 'render_mapping_page')
        );
    }

    public function render_settings_page() {
        require_once KYLAS_CRM_PLUGIN_DIR . 'admin/settings-page.php';
    }

    public function render_mapping_page() {
        require_once KYLAS_CRM_PLUGIN_DIR . 'admin/mapping-page.php';
    }
}
