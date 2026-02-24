<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'kylas_field_mappings';

// Handle Saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kylas_crm_mapping_nonce']) && wp_verify_nonce($_POST['kylas_crm_mapping_nonce'], 'kylas_crm_save_mapping')) {
    $form_type = sanitize_text_field($_POST['form_type']);
    $form_id = intval($_POST['form_id']);
    $mapping = $_POST['mapping']; // array( cf7_field => kylas_field )

    if ($form_id > 0 && !empty($mapping)) {
        $mapping_json = wp_json_encode($mapping);
        
        // Check if exists
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE form_type = %s AND form_id = %d", $form_type, $form_id));
        
        if ($existing) {
            $wpdb->update(
                $table_name,
                array('mapping_json' => $mapping_json, 'updated_at' => current_time('mysql')),
                array('id' => $existing)
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'form_type' => $form_type,
                    'form_id' => $form_id,
                    'mapping_json' => $mapping_json,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        }
        echo '<div class="notice notice-success is-dismissible"><p>Mapping saved successfully.</p></div>';
    }
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && check_admin_referer('delete_mapping_' . $_GET['id'])) {
    $wpdb->delete($table_name, array('id' => intval($_GET['id'])));
    echo '<div class="notice notice-success is-dismissible"><p>Mapping deleted successfully.</p></div>';
}

// Fetch CF7 Forms
$cf7_forms = array();
if (class_exists('WPCF7_ContactForm')) {
    $cf7_forms = WPCF7_ContactForm::find();
}

// Define Default Kylas Fields
$default_kylas_fields = array(
    'firstName'   => array('label' => 'First Name', 'type' => 'Text'),
    'lastName'    => array('label' => 'Last Name', 'type' => 'Text'),
    'email'       => array('label' => 'Email', 'type' => 'Text'),
    'phone'       => array('label' => 'Phone', 'type' => 'Text'),
    'requirement' => array('label' => 'Requirement (Note)', 'type' => 'Text'),
    'source'      => array('label' => 'Source', 'type' => 'List'),
    'companyName' => array('label' => 'Company Name', 'type' => 'Text'),
    'designation' => array('label' => 'Designation', 'type' => 'Text'),
    'city'        => array('label' => 'City', 'type' => 'Text'),
    'state'       => array('label' => 'State', 'type' => 'Text'),
    'zipCode'     => array('label' => 'Zip Code', 'type' => 'Text'),
    'website'     => array('label' => 'Website', 'type' => 'Text'),
    'description' => array('label' => 'Description (Full Note)', 'type' => 'Text'),
    'industry'    => array('label' => 'Industry', 'type' => 'List'),
);

// Fetch current fields from DB or use defaults
$kylas_fields = get_option('kylas_crm_lead_fields', $default_kylas_fields);

// Handle Sync Fields (Placeholder for future API integration)
if (isset($_POST['kylas_crm_sync_fields']) && check_admin_referer('kylas_crm_sync_fields', 'kylas_sync_nonce')) {
    // This is where we would call Kylas API to fetch available fields
    // For now, we'll just ensure the option is safely stored
    update_option('kylas_crm_lead_fields', $kylas_fields);
    echo '<div class="notice notice-info is-dismissible"><p>Fields synced successfully (using defaults/available mappings).</p></div>';
}

$selected_form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$current_mapping = array();
if ($selected_form_id > 0) {
    $mapping_data = $wpdb->get_row($wpdb->prepare("SELECT mapping_json FROM $table_name WHERE form_id = %d", $selected_form_id));
    if ($mapping_data) {
        $current_mapping = json_decode($mapping_data->mapping_json, true);
    }
}
?>

<div class="wrap">
    <h1>Kylas CRM Field Mapping</h1>
    
    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h2>Step 1 & 2: Select Form</h2>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <form method="get" action="" style="flex-grow: 1;">
                <input type="hidden" name="page" value="kylas-crm-mapping" />
                <table class="form-table">
                    <tr>
                        <th scope="row">Select CF7 Form</th>
                        <td>
                            <select name="form_id" onchange="this.form.submit()">
                                <option value="">-- Select a Form --</option>
                                <?php foreach ($cf7_forms as $form) : ?>
                                    <option value="<?php echo $form->id(); ?>" <?php selected($selected_form_id, $form->id()); ?>>
                                        <?php echo esc_html($form->title()); ?> (ID: <?php echo $form->id(); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </form>
            
            <form method="post" action="">
                <?php wp_nonce_field('kylas_crm_sync_fields', 'kylas_sync_nonce'); ?>
                <button type="submit" name="kylas_crm_sync_fields" class="button button-secondary">
                    Sync Kylas Fields
                </button>
            </form>
        </div>
    </div>

    <?php if ($selected_form_id > 0) : 
        $contact_form = WPCF7_ContactForm::get_instance($selected_form_id);
        if ($contact_form) :
            $tags = $contact_form->scan_form_tags();
            // Filter out non-input tags
            $input_tags = array_filter($tags, function($tag) {
                return !empty($tag->name) && !in_array($tag->type, array('submit', 'captcha', 'quiz'));
            });
    ?>
        <div class="card" style="max-width: 100%; margin-top: 20px;">
            <h2>Step 3: Mapping Panel</h2>
            <form method="post" action="">
                <?php wp_nonce_field('kylas_crm_save_mapping', 'kylas_crm_mapping_nonce'); ?>
                <input type="hidden" name="form_type" value="cf7" />
                <input type="hidden" name="form_id" value="<?php echo $selected_form_id; ?>" />
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>CF7 Field (Read Only)</th>
                            <th>Kylas CRM Field</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($input_tags as $tag) : ?>
                            <tr>
                                <td>
                                    <strong>[<?php echo esc_html($tag->name); ?>]</strong>
                                    <span class="description">(Type: <?php echo esc_html($tag->type); ?>)</span>
                                </td>
                                <td>
                                    <select name="mapping[<?php echo esc_attr($tag->name); ?>]">
                                        <option value="">-- Do Not Map --</option>
                                        <?php foreach ($kylas_fields as $key => $data) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($current_mapping[$tag->name]) && $current_mapping[$tag->name] === $key); ?>>
                                                <?php echo esc_html($data['label']); ?> 
                                                (<?php echo esc_html($data['type']); ?><?php if ($key === 'lastName') echo ', Required'; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top: 20px;">
                    <?php submit_button('Save Mapping'); ?>
                </div>
            </form>
        </div>
    <?php endif; endif; ?>

    <div class="card" style="max-width: 100%; margin-top: 40px;">
        <h2>Step 4: View Saved Mappings</h2>
        <?php
        $saved_mappings = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_at DESC");
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Form Type</th>
                    <th>Form Name (ID)</th>
                    <th>Mapping Count</th>
                    <th>Updated Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($saved_mappings) : foreach ($saved_mappings as $row) : 
                    $form_name = 'Unknown';
                    if ($row->form_type === 'cf7') {
                        $f = WPCF7_ContactForm::get_instance($row->form_id);
                        if ($f) $form_name = $f->title();
                    }
                    $map_arr = json_decode($row->mapping_json, true);
                    $count = is_array($map_arr) ? count($map_arr) : 0;
                ?>
                    <tr>
                        <td><?php echo strtoupper(esc_html($row->form_type)); ?></td>
                        <td><?php echo esc_html($form_name); ?> (<?php echo intval($row->form_id); ?>)</td>
                        <td><?php echo $count; ?> fields</td>
                        <td><?php echo esc_html($row->updated_at); ?></td>
                        <td>
                            <a href="?page=kylas-crm-mapping&form_id=<?php echo $row->form_id; ?>" class="button button-small">Edit</a>
                            <a href="<?php echo wp_nonce_url('?page=kylas-crm-mapping&action=delete&id=' . $row->id, 'delete_mapping_' . $row->id); ?>" 
                               class="button button-small button-link-delete" 
                               onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="5">No mappings found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selects = document.querySelectorAll('select[name^="mapping"]');
    const form = selects[0].closest('form');

    function updateDropdowns() {
        const selectedValues = Array.from(selects)
            .map(s => s.value)
            .filter(v => v !== '');

        selects.forEach(select => {
            const currentValue = select.value;
            Array.from(select.options).forEach(option => {
                if (option.value !== '' && option.value !== currentValue) {
                    option.disabled = selectedValues.includes(option.value);
                } else {
                    option.disabled = false;
                }
            });
        });
    }

    selects.forEach(select => {
        select.addEventListener('change', updateDropdowns);
    });

    form.addEventListener('submit', function(e) {
        const selectedValues = Array.from(selects).map(s => s.value);
        if (!selectedValues.includes('lastName')) {
            e.preventDefault();
            alert('Error: The "Last Name" field is required by Kylas CRM and must be mapped.');
        }
    });

    updateDropdowns(); // Initial run
});
</script>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    margin-bottom: 20px;
}
.required-star {
    color: #d63638;
    font-weight: bold;
    margin-left: 5px;
}
</style>
