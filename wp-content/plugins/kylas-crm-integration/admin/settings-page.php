<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kylas_crm_settings_nonce']) && wp_verify_nonce($_POST['kylas_crm_settings_nonce'], 'kylas_crm_save_settings')) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized user' );
    }

    $api_key = sanitize_text_field($_POST['kylas_crm_api_key']);
    $base_url = esc_url_raw($_POST['kylas_crm_base_url']);
    $notify_admin = isset($_POST['kylas_crm_notify_admin']) ? 'yes' : 'no';
    $notify_lead = isset($_POST['kylas_crm_notify_lead']) ? 'yes' : 'no';

    update_option('kylas_crm_api_key', $api_key);
    update_option('kylas_crm_base_url', $base_url);
    update_option('kylas_crm_notify_admin', $notify_admin);
    update_option('kylas_crm_notify_lead', $notify_lead);

    echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
}

$api_key = get_option('kylas_crm_api_key', '');
$base_url = get_option('kylas_crm_base_url', 'https://api.kylas.io/v1/');
$notify_admin = get_option('kylas_crm_notify_admin', 'no');
$notify_lead = get_option('kylas_crm_notify_lead', 'no');
?>

<div class="wrap">
    <h1>Kylas CRM Settings</h1>
    <form method="post" action="">
        <?php wp_nonce_field('kylas_crm_save_settings', 'kylas_crm_settings_nonce'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">API Key</th>
                <td>
                    <input type="password" name="kylas_crm_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                    <p class="description">Enter your Kylas CRM API Key.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Base URL</th>
                <td>
                    <input type="text" name="kylas_crm_base_url" value="<?php echo esc_attr($base_url); ?>" class="regular-text" />
                    <p class="description">Default: <code>https://api.kylas.io/v1/</code></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Email Notifications</th>
                <td>
                    <label>
                        <input type="checkbox" name="kylas_crm_notify_admin" value="yes" <?php checked($notify_admin, 'yes'); ?> />
                        Notify Admin on successful integration
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="kylas_crm_notify_lead" value="yes" <?php checked($notify_lead, 'yes'); ?> />
                        Notify Lead on successful registration
                    </label>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
