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
        error_log('Kylas handle_cf7_submission triggered for form ' . $contact_form->id());
        // Prevent duplicate execution within same request
        static $already_processed = false;
        if ( $already_processed ) {
            error_log('Kylas already processed for this request.');
            return;
        }
        $already_processed = true;

        if ( ! class_exists( 'WPCF7_Submission' ) ) {
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        
        if ( ! $submission ) {
            error_log('Kylas error: WPCF7_Submission::get_instance() returned null.');
            return;
        }

        $form_id     = $contact_form->id();
        $posted_data = $submission->get_posted_data();

        if ( empty( $posted_data ) || ! is_array( $posted_data ) ) {
            error_log('Kylas error: posted_data is empty or not an array.');
            return;
        }

        // 1. Fetch Saved Mapping + fallback auto-mapping
        $mapping = $this->get_mapping_for_form( $form_id, $posted_data );

        // 2. Prepare CRM Payload
        $kylas_payload = $this->build_kylas_payload( $posted_data, $mapping );

        // 4. Extract basic info for local storage (robust extraction)
        $first_name = '';
        foreach(['firstName', 'first-name', 'fname', 'first_name', 'your-name', 'name'] as $k) {
            if (!empty($posted_data[$k])) { $first_name = is_array($posted_data[$k]) ? $posted_data[$k][0] : $posted_data[$k]; break; }
        }
        $last_name = '';
        foreach(['lastName', 'last-name', 'lname', 'last_name', 'surname'] as $k) {
            if (!empty($posted_data[$k])) { $last_name = is_array($posted_data[$k]) ? $posted_data[$k][0] : $posted_data[$k]; break; }
        }
        $email = '';
        foreach(['emails', 'email', 'your-email', 'e-mail'] as $k) {
            if (!empty($posted_data[$k])) { $email = is_array($posted_data[$k]) ? $posted_data[$k][0] : $posted_data[$k]; break; }
        }
        $phone = '';
        foreach(['phoneNumbers', 'phone', 'tel', 'mobile'] as $k) {
            if (!empty($posted_data[$k])) { $phone = is_array($posted_data[$k]) ? $posted_data[$k][0] : $posted_data[$k]; break; }
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

            if ( $lead_id ) {
                $wpdb->update(
                    $wpdb->prefix . 'kylas_crm_form_data',
                    array( 'status' => 'skipped', 'response_body' => 'Mapping missing' ),
                    array( 'lead_id' => $lead_id )
                );
            }

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
        $logic = kylas_crm_get_auto_mapping_logic();

        foreach ($data as $key => $val) {
            $lower_key = strtolower($key);
            foreach ($logic as $kylas_key => $variations) {
                if (in_array($lower_key, $variations)) {
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
            $error_message = $response->get_error_message();
            Kylas_CRM_Logger::error(
                'Kylas request failed (WP_Error).',
                array_merge(
                    $meta,
                    array(
                        'endpoint' => $endpoint,
                        'error'    => $error_message,
                    )
                )
            );

            // QM Debug
            if ( class_exists( 'QM_Collectors' ) ) {
                do_action( 'qm/error', "[Kylas CRM] API Request Failed: $error_message", $data );
            }
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );

            // QM Debug for successful or failed responses
            if ( class_exists( 'QM_Collectors' ) ) {
                $qm_level = ( $code >= 200 && $code < 300 ) ? 'info' : 'error';
                do_action( "qm/$qm_level", "[Kylas CRM] Response $code", array( 'payload' => $data, 'response' => $body ) );
            }

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

            $raw_value = $posted_data[ $cf7_field ];
            if ( is_array( $raw_value ) ) {
                $value = isset($raw_value[0]) ? sanitize_text_field( $raw_value[0] ) : '';
            } else {
                $value = sanitize_text_field( $raw_value );
            }

            if ( $value === '' && $kylas_field !== 'dnd' ) {
                continue;
            }

            switch ( $kylas_field ) {
                case 'email':
                case 'emails':
                    $kylas_payload['emails'] = array(
                        array( 'type' => 'OFFICE', 'value' => $value, 'primary' => true ),
                    );
                    break;

                case 'phone':
                case 'phoneNumbers':
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
                case 'aboutYou':
                    if ( ! isset( $kylas_payload['notes'] ) ) {
                        $kylas_payload['notes'] = array();
                    }
                    $kylas_payload['notes'][] = array( 'content' => $value );
                    break;

                case 'companyPhones':
                    $clean_phone = preg_replace( '/[^0-9]/', '', $value );
                    if ( strlen( $clean_phone ) > 10 ) {
                        $clean_phone = substr( $clean_phone, -10 );
                    }

                    $kylas_payload['companyPhones'] = array(
                        array(
                            'type'     => 'MOBILE',
                            'code'     => 'IN',
                            'value'    => $clean_phone,
                            'dialCode' => '+91',
                            'primary'  => true,
                        ),
                    );
                    break;

                case 'dnd':
                case 'isNew':
                    $val_lower = strtolower( trim( $value ) );
                    $kylas_payload[ $kylas_field ] = in_array( $val_lower, array( 'yes', 'true', '1', 'on' ), true );
                    break;
                
                case 'companyEmployees':
                case 'requirementBudget':
                case 'score':
                case 'aging':
                    $kylas_payload[ $kylas_field ] = intval( $value );
                    break;

                case 'companyAnnualRevenue':
                    $kylas_payload[ $kylas_field ] = floatval( $value );
                    break;

                // Date fields
                case 'expectedClosureOn':
                case 'actualClosureDate':
                case 'convertedAt':
                case 'taskDueOn':
                case 'meetingScheduledOn':
                case 'latestActivityCreatedAt':
                    if ( ! empty( $value ) ) {
                        // Convert to ISO 8601 format if needed
                        try {
                            $date = new DateTime( $value );
                            $kylas_payload[ $kylas_field ] = $date->format( 'c' );
                        } catch (Exception $e) {
                            Kylas_CRM_Logger::error('Invalid date format for field ' . $kylas_field . ': ' . $value);
                        }
                    }
                    break;

                // URL fields
                case 'facebook':
                case 'twitter':
                case 'linkedIn':
                case 'companyWebsite':
                    if ( ! empty( $value ) ) {
                        // Ensure URL has protocol
                        if ( ! preg_match( '/^https?:\/\//', $value ) ) {
                            $value = 'https://' . $value;
                        }
                        $kylas_payload[ $kylas_field ] = esc_url_raw( $value );
                    }
                    break;

                // Handle legacy field mappings
                case 'zipCode':
                    $kylas_payload['zipcode'] = $value;
                    break;

                default:
                    $kylas_payload[ $kylas_field ] = $value;
                    break;
            }
        }

        return $kylas_payload;
    }
}