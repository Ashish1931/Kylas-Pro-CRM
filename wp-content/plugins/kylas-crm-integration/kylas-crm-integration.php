<?php
/**
 * Plugin Name: Kylas CRM Integration
 * Plugin URI: https://example.com/kylas-crm
 * Description: Integrates Contact Form 7 with Kylas CRM, allowing field mapping.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KYLAS_CRM_VERSION', '1.0.0' );
define( 'KYLAS_CRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KYLAS_CRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once KYLAS_CRM_PLUGIN_DIR . 'includes/db-init.php';
require_once KYLAS_CRM_PLUGIN_DIR . 'includes/logger.php';
require_once KYLAS_CRM_PLUGIN_DIR . 'includes/field-config.php';
require_once KYLAS_CRM_PLUGIN_DIR . 'admin/admin-menu.php';
require_once KYLAS_CRM_PLUGIN_DIR . 'includes/form-handler.php';

// Activation Hook
register_activation_hook( __FILE__, 'kylas_crm_create_tables' );

// Initialize Admin Menu (only in admin)
if ( is_admin() ) {
    new Kylas_CRM_Admin_Menu();
}

/**
 * Initialize Form Handler safely
 * Prevents duplicate CRM entries caused by multiple hook registrations
 */
function kylas_crm_init_form_handler() {
	static $initialized = false;

	if ( $initialized ) {
		return;
	}

	$initialized = true;

	new Kylas_CRM_Form_Handler();
}
add_action( 'plugins_loaded', 'kylas_crm_init_form_handler' );

/**
 * Frontend assets (Retry button + behavior + Styles)
 */
function kylas_crm_enqueue_assets() {
    // Enqueue Styles
    wp_enqueue_style(
        'kylas-crm-frontend',
        KYLAS_CRM_PLUGIN_URL . 'assets/css/frontend-style.css',
        array(),
        KYLAS_CRM_VERSION
    );

    // Enqueue Scripts
	wp_enqueue_script(
		'kylas-crm-retry',
		KYLAS_CRM_PLUGIN_URL . 'assets/js/retry.js',
		array( 'contact-form-7' ),
		KYLAS_CRM_VERSION,
		true
	);

	wp_localize_script(
		'kylas-crm-retry',
		'KylasCrmRetry',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => 'kylas_crm_retry_lead',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'kylas_crm_enqueue_assets' );

