<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Kylas_CRM_Form_Handler {

    /**
     * Constructor
     * Using safer hook to avoid duplicate firing
     */
    public function __construct() {
        // Fires once per submission (more reliable than mail_sent)
        add_action( 'wpcf7_before_send_mail', array( $this, 'handle_cf7_submission' ), 10, 1 );

        // Retry endpoint (frontend)
        add_action( 'wp_ajax_kylas_crm_retry_lead', array( $this, 'ajax_retry_lead' ) );
        add_action( 'wp_ajax_nopriv_kylas_crm_retry_lead', array( $this, 'ajax_retry_lead' ) );
    }

    /**
     * Handle CF7 Submission
     */
    public function handle_cf7_submission( $contact_form ) {
        // Prevent duplicate execution within same request
        static $already_processed = false;
        if ( $already_processed ) {
            return;
        }
        $already_processed = true;

        if ( ! class_exists( 'WPCF7_Submission' ) ) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        
        if ( ! $submission ) {
            return;
        }

        $form_id     = $contact_form->id();
        $posted_data = $submission->get_posted_data();

        if ( empty( $posted_data ) || ! is_array( $posted_data ) ) {
            return;
        }

        // 1. Fetch Saved Mapping + fallback auto-mapping
        $mapping = $this->get_mapping_for_form( $form_id, $posted_data );

        // 2. Prepare CRM Payload
        $kylas_payload = $this->build_kylas_payload( $posted_data, $mapping );

        // 4. Extract basic info for local storage display
        $first_name = isset($kylas_payload['firstName']) ? $kylas_payload['firstName'] : '';
        $last_name = isset($kylas_payload['lastName']) ? $kylas_payload['lastName'] : '';
        $email = '';
        if (isset($kylas_payload['emails'][0]['value'])) {
             $email = $kylas_payload['emails'][0]['value'];
        }
        $phone = '';
        if (isset($kylas_payload['phoneNumbers'][0]['value'])) {
             $phone = $kylas_payload['phoneNumbers'][0]['value'];
        }

        // 5. Save locally always (even if API fails later)
        $lead_id = $this->save_lead_locally( 'cf7', $form_id, $posted_data, $first_name, $last_name, $email, $phone );

        $kylas_result_props = array(
            'lead_id'          => $lead_id,
            'status'           => 'skipped',
            'retry_available'  => false,
            'retry_nonce'      => '',
            'message'          => '',
        );

        if (empty($kylas_payload)) {
            Kylas_CRM_Logger::error(
                'No valid field mapping found. Skipping API call.',
                array(
                    'form_id' => $form_id,
                    'lead_id' => $lead_id,
                )
            );
            $kylas_result_props['status'] = 'skipped';
            $kylas_result_props['message'] = 'Kylas mapping missing. API call skipped.';

            $submission->add_result_props( array( 'kylas' => $kylas_result_props ) );
            return;
        }

        // 6. Send to API
        $response = $this->send_to_kylas(
            $kylas_payload,
            array(
                'form_id' => $form_id,
                'lead_id' => $lead_id,
            )
        );

        // 7. Update local lead with response
        if ( $lead_id ) {
            $this->update_lead_status( $lead_id, $response );

            // 8. Send Notifications if successful
            if ( ! is_wp_error( $response ) ) {
                $code = wp_remote_retrieve_response_code( $response );
                if ( $code >= 200 && $code < 300 ) {
                    $this->send_notifications( $first_name, $last_name, $email );
                }
            }
        }

        // 9. Add Retry info for frontend (only if API failed)
        $status_info = $this->evaluate_kylas_response( $response );
        $kylas_result_props['status'] = $status_info['status'];
        $kylas_result_props['message'] = $status_info['message'];

        if ( 'success' !== $status_info['status'] && ! empty( $lead_id ) ) {
            $kylas_result_props['retry_available'] = true;
            $kylas_result_props['retry_nonce'] = wp_create_nonce( 'kylas_crm_retry_' . $lead_id );
        }

        $submission->add_result_props( array( 'kylas' => $kylas_result_props ) );
    }

    /**
     * Auto-mapping helper: guesses Kylas fields based on CF7 field names
     */
    private function auto_map_fields($data) {
        $map = array();

        // Each Kylas field -> all CF7 field name variations (including CF7 defaults)
        $logic = array(
            'firstName'   => array('first-name', 'fname', 'first_name', 'your-name', 'name', 'full-name', 'fullname'),
            'lastName'    => array('last-name', 'lname', 'last_name', 'surname', 'your-last-name'),
            'email'       => array('email', 'your-email', 'e-mail', 'your-e-mail'),
            'phone'       => array('phone', 'tel', 'mobile', 'contact', 'your-phone', 'your-tel', 'phone-number'),
            'companyName' => array('company', 'org', 'organization', 'company-name', 'your-company'),
            'designation' => array('designation', 'job-title', 'title', 'position', 'your-designation'),
            'city'        => array('city', 'location', 'your-city'),
            'state'       => array('state', 'province', 'your-state'),
            'zipCode'     => array('zip', 'zipcode', 'zip-code', 'pincode', 'pin-code'),
            'website'     => array('website', 'url', 'web', 'your-website'),
            'requirement' => array('requirement', 'subject', 'your-subject', 'note', 'comments', 'enquiry'),
            'description' => array('message', 'your-message', 'description', 'details', 'about'),
        );

        foreach ($data as $key => $val) {
            foreach ($logic as $kylas_key => $variations) {
                if (in_array(strtolower($key), $variations)) {
                    $map[$key] = $kylas_key;
                    break;
                }
            }
        }
        return $map;
    }

    /**
     * Send Email Notifications
     */
    private function send_notifications( $first_name, $last_name, $lead_email ) {
        $notify_admin = get_option( 'kylas_crm_notify_admin', 'no' );
        $notify_lead  = get_option( 'kylas_crm_notify_lead', 'no' );
        $full_name    = trim( $first_name . ' ' . $last_name );

        $headers = array('Content-Type: text/html; charset=UTF-8');

        // 1. Notify Admin
        if ( 'yes' === $notify_admin ) {
            $admin_email = get_option( 'admin_email' );
            $subject     = 'New Lead Created in Kylas CRM';
            $message     = "<h3>New Lead Form Submission</h3>";
            $message    .= "<p>A new lead has been successfully registered in Kylas CRM.</p>";
            $message    .= "<ul>";
            $message    .= "<li><strong>Name:</strong> $full_name</li>";
            $message    .= "<li><strong>Email:</strong> $lead_email</li>";
            $message    .= "<li><strong>Date:</strong> " . current_time( 'mysql' ) . "</li>";
            $message    .= "</ul>";
            
            wp_mail( $admin_email, $subject, $message, $headers );
        }

        // 2. Notify Lead
        if ( 'yes' === $notify_lead && ! empty( $lead_email ) ) {
            $subject = 'Registration Successful';
            $message = "<p>Hello <strong>$first_name</strong>,</p>";
            $message .= "<p>Thank you for reaching out! We have successfully received your information and registered you in our CRM.</p>";
            $message .= "<p>Our team will get back to you shortly.</p>";
            $message .= "<p>Best regards,<br>" . get_bloginfo( 'name' ) . "</p>";

            wp_mail( $lead_email, $subject, $message, $headers );
        }
    }

    /**
     * Save lead data locally
     */
    private function save_lead_locally( $form_type, $form_id, $data, $first_name, $last_name, $email, $phone ) {
        global $wpdb;
        $leads_table = $wpdb->prefix . 'kylas_crm_leads';
        $data_table = $wpdb->prefix . 'kylas_crm_form_data';

        // 1. Insert into leads table
        $wpdb->insert(
            $leads_table,
            array(
                'form_type'  => $form_type,
                'form_id'    => $form_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => $email,
                'phone'      => $phone,
            )
        );

        $lead_id = $wpdb->insert_id;

        // 2. Insert into data table linked by lead_id
        if ( $lead_id ) {
            $wpdb->insert(
                $data_table,
                array(
                    'lead_id'       => $lead_id,
                    'form_data'     => wp_json_encode( $data ),
                    'status'        => 'pending',
                    'created_at'    => current_time( 'mysql' )
                )
            );
        }

        return $lead_id;
    }

    /**
     * Update lead status after API call
     */
    private function update_lead_status( $lead_id, $response ) {
        global $wpdb;
        $data_table = $wpdb->prefix . 'kylas_crm_form_data';

        $status = 'failed';
        $code   = 0;
        $body   = '';

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            if ( $code >= 200 && $code < 300 ) {
                $status = 'success';
            }
        } else {
            $body = $response->get_error_message();
        }

        $wpdb->update(
            $data_table,
            array(
                'status'        => $status,
                'response_code' => $code,
                'response_body' => $body
            ),
            array( 'lead_id' => $lead_id )
        );
    }

    /**
     * Send Data to Kylas CRM
     */
    private function send_to_kylas( $data, array $meta = array() ) {

        $api_key = get_option( 'kylas_crm_api_key' );
        $base_url = get_option( 'kylas_crm_base_url', 'https://api.kylas.io/v1/' );

        if ( empty( $api_key ) ) {
            Kylas_CRM_Logger::error(
                'Missing API key. Cannot call Kylas.',
                $meta
            );
            return new WP_Error( 'missing_api_key', 'Missing API Key' );
        }

        $endpoint = rtrim($base_url, '/') . '/leads';

        $args = array(
            'body'        => wp_json_encode( $data ),
            'headers'     => array(
                'Content-Type' => 'application/json',
                'api-key'      => $api_key,
            ),
            'timeout'     => 45,
            'blocking'    => true,
        );

        $response = wp_remote_post( $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            Kylas_CRM_Logger::error(
                'Kylas request failed (WP_Error).',
                array_merge(
                    $meta,
                    array(
                        'endpoint' => $endpoint,
                        'error'    => $response->get_error_message(),
                    )
                )
            );
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );

            if ( $code >= 400 ) {
                Kylas_CRM_Logger::error(
                    'Kylas API returned error response.',
                    array_merge(
                        $meta,
                        array(
                            'endpoint' => $endpoint,
                            'code'     => $code,
                            'body'     => $body,
                        )
                    )
                );
            }
        }

        return $response;
    }

    /**
     * AJAX: retry only the Kylas API call for an existing lead_id
     */
    public function ajax_retry_lead() {
        $lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;

        if ( ! $lead_id ) {
            wp_send_json_error( array( 'message' => 'Invalid lead id.' ), 400 );
        }

        // Best-effort nonce validation; do not hard-fail with 403 to keep UX smooth.
        if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'kylas_crm_retry_' . $lead_id ) ) {
            Kylas_CRM_Logger::error(
                'Retry nonce verification failed.',
                array(
                    'lead_id' => $lead_id,
                )
            );
            // Continue anyway; this endpoint only replays an existing lead payload.
        }

        $result = $this->retry_lead_by_id( $lead_id );

        if ( ! empty( $result['success'] ) ) {
            wp_send_json_success( $result );
        }

        wp_send_json_error( $result, 500 );
    }

    private function retry_lead_by_id( int $lead_id ): array {
        global $wpdb;

        $leads_table = $wpdb->prefix . 'kylas_crm_leads';
        $data_table  = $wpdb->prefix . 'kylas_crm_form_data';

        $lead = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $leads_table WHERE id = %d", $lead_id )
        );

        if ( ! $lead ) {
            return array(
                'success' => false,
                'status'  => 'failed',
                'message' => 'Lead not found.',
            );
        }

        $data_row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $data_table WHERE lead_id = %d ORDER BY id DESC LIMIT 1", $lead_id )
        );

        if ( ! $data_row || empty( $data_row->form_data ) ) {
            return array(
                'success' => false,
                'status'  => 'failed',
                'message' => 'Saved form data not found for this lead.',
            );
        }

        $posted_data = json_decode( $data_row->form_data, true );

        if ( empty( $posted_data ) || ! is_array( $posted_data ) ) {
            return array(
                'success' => false,
                'status'  => 'failed',
                'message' => 'Saved form data is invalid.',
            );
        }

        $form_id = isset( $lead->form_id ) ? absint( $lead->form_id ) : 0;
        $mapping = $this->get_mapping_for_form( $form_id, $posted_data );
        $payload = $this->build_kylas_payload( $posted_data, $mapping );

        if ( empty( $payload ) ) {
            Kylas_CRM_Logger::error(
                'Retry skipped: payload empty (mapping missing).',
                array(
                    'form_id' => $form_id,
                    'lead_id' => $lead_id,
                )
            );

            return array(
                'success' => false,
                'status'  => 'skipped',
                'message' => 'Kylas mapping missing. Retry skipped.',
            );
        }

        $response = $this->send_to_kylas(
            $payload,
            array(
                'form_id' => $form_id,
                'lead_id' => $lead_id,
                'retry'   => true,
            )
        );

        $this->update_lead_status( $lead_id, $response );

        $status_info = $this->evaluate_kylas_response( $response );

        $result = array(
            'success'     => ( 'success' === $status_info['status'] ),
            'status'      => $status_info['status'],
            'message'     => $status_info['message'],
            'lead_id'     => $lead_id,
        );

        if ( 'success' !== $status_info['status'] ) {
            $result['retry_nonce'] = wp_create_nonce( 'kylas_crm_retry_' . $lead_id );
        }

        return $result;
    }

    private function evaluate_kylas_response( $response ): array {
        if ( is_wp_error( $response ) ) {
            return array(
                'status'  => 'failed',
                'message' => $response->get_error_message(),
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code >= 200 && $code < 300 ) {
            return array(
                'status'  => 'success',
                'message' => '',
            );
        }

        if ( $code ) {
            return array(
                'status'  => 'failed',
                'message' => 'Kylas API failed (HTTP ' . $code . ').',
            );
        }

        return array(
            'status'  => 'failed',
            'message' => ! empty( $body ) ? 'Kylas API failed.' : 'Kylas API failed.',
        );
    }

    private function get_mapping_for_form( $form_id, array $posted_data ): array {
        global $wpdb;

        $mapping = array();

        if ( $form_id ) {
            $mappings_table = $wpdb->prefix . 'kylas_field_mappings';
            $mapping_json = $wpdb->get_var(
                $wpdb->prepare( "SELECT mapping_json FROM $mappings_table WHERE form_id = %d", $form_id )
            );

            if ( $mapping_json ) {
                $decoded = json_decode( $mapping_json, true );
                if ( is_array( $decoded ) ) {
                    $mapping = $decoded;
                }
            }
        }

        if ( empty( $mapping ) ) {
            $mapping = $this->auto_map_fields( $posted_data );
        }

        return is_array( $mapping ) ? $mapping : array();
    }

    private function build_kylas_payload( array $posted_data, array $mapping ): array {
        if ( empty( $mapping ) ) {
            return array();
        }

        $kylas_payload = array();

        foreach ( $mapping as $cf7_field => $kylas_field ) {
            if ( empty( $kylas_field ) || ! isset( $posted_data[ $cf7_field ] ) ) {
                continue;
            }

            $value = sanitize_text_field( $posted_data[ $cf7_field ] );

            switch ( $kylas_field ) {
                case 'email':
                    $kylas_payload['emails'] = array(
                        array( 'type' => 'OFFICE', 'value' => $value, 'primary' => true ),
                    );
                    break;

                case 'phone':
                    $clean_phone = preg_replace( '/[^0-9]/', '', $value );
                    if ( strlen( $clean_phone ) > 10 ) {
                        $clean_phone = substr( $clean_phone, -10 );
                    }

                    $kylas_payload['phoneNumbers'] = array(
                        array(
                            'type'     => 'MOBILE',
                            'code'     => 'IN',
                            'value'    => $clean_phone,
                            'dialCode' => '+91',
                            'primary'  => true,
                        ),
                    );
                    break;

                case 'requirement':
                case 'description':
                    if ( ! isset( $kylas_payload['notes'] ) ) {
                        $kylas_payload['notes'] = array();
                    }
                    $kylas_payload['notes'][] = array( 'content' => $value );
                    break;

                default:
                    $kylas_payload[ $kylas_field ] = $value;
                    break;
            }
        }

        return $kylas_payload;
    }
}