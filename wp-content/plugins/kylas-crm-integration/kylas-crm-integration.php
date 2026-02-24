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
 * Frontend assets (Retry button + behavior)
 */
function kylas_crm_enqueue_assets() {
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

/**
 * Global Contact Form 7 Modern Styling
 */
function kylas_crm_global_styles() {
    ?>
    <style>
    /* Clean & Professional Contact Form 7 Styling */
    .wpcf7-form {
        max-width: 500px;
        margin: 30px 0;
        padding: 40px;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        color: #333;
    }

    .wpcf7-form label {
        display: block;
        margin-bottom: 20px;
        color: #444;
        font-size: 15px;
        font-weight: 500;
    }

    .wpcf7-form-control-wrap {
        display: block;
        margin-top: 8px;
    }

    .wpcf7-form input[type="text"],
    .wpcf7-form input[type="email"],
    .wpcf7-form input[type="tel"],
    .wpcf7-form textarea {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        background: #fcfcfc;
        color: #333 !important;
        font-size: 15px;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .wpcf7-form input:focus {
        outline: none;
        border-color: #6c5ce7;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
    }

    .wpcf7-submit {
        width: 100%;
        padding: 14px !important;
        background: #6c5ce7 !important;
        color: #fff !important;
        border: none !important;
        border-radius: 8px !important;
        font-size: 16px !important;
        font-weight: 600 !important;
        cursor: pointer;
        transition: background 0.3s ease !important;
        margin-top: 15px;
    }

    .wpcf7-submit:hover {
        background: #5b4cc4 !important;
    }

    .wpcf7-submit:active {
        transform: translateY(1px);
    }

    .kylas-crm-retry {
        width: 100%;
        padding: 14px !important;
        background: #f5f6fa !important;
        color: #2d3436 !important;
        border: 1px solid #dfe6e9 !important;
        border-radius: 8px !important;
        font-size: 16px !important;
        font-weight: 600 !important;
        cursor: pointer;
        transition: background 0.2s ease, border-color 0.2s ease !important;
        margin-top: 10px;
    }

    .kylas-crm-retry:hover {
        background: #eef0f6 !important;
        border-color: #cfd6d9 !important;
    }

    .kylas-crm-retry[disabled] {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .kylas-crm-retry-status {
        margin-top: 10px;
        font-size: 14px;
        color: #636e72;
    }
    </style>
    <?php
}
add_action( 'wp_head', 'kylas_crm_global_styles' );
