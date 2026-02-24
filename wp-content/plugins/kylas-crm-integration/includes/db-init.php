<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kylas_crm_create_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // 1. Table for local lead storage
    $leads_table = $wpdb->prefix . 'kylas_crm_leads';
    $sql1 = "CREATE TABLE $leads_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        form_type varchar(50) NOT NULL,
        form_id bigint(20) NOT NULL,
        first_name varchar(100) DEFAULT '',
        last_name varchar(100) DEFAULT '',
        email varchar(150) DEFAULT '',
        phone varchar(20) DEFAULT '',
        PRIMARY KEY (id)
    ) $charset_collate;";

    // 2. Table for form data and status (Linked to leads)
    $data_table = $wpdb->prefix . 'kylas_crm_form_data';
    $sql2 = "CREATE TABLE $data_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        lead_id bigint(20) NOT NULL,
        form_data longtext NOT NULL,
        status varchar(50) DEFAULT 'unprocessed',
        response_code int(10) DEFAULT NULL,
        response_body longtext DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        KEY lead_id (lead_id)
    ) $charset_collate;";

    // 3. Table for field mappings
    $mappings_table = $wpdb->prefix . 'kylas_field_mappings';
    $sql3 = "CREATE TABLE $mappings_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        form_type varchar(50) NOT NULL,
        form_id bigint(20) NOT NULL,
        mapping_json longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql1 );
    dbDelta( $sql2 );
    dbDelta( $sql3 );
}
