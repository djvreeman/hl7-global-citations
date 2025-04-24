<?php

add_action('admin_menu', function () {
    add_menu_page('Zotero Map Settings', 'Zotero Map', 'manage_options', 'zotero-map', 'zotero_map_settings_page');
});

// Export settings
if (isset($_GET['zotero_map_export'])) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="zotero-map-settings.json"');
    echo json_encode([
        'zotero_map_settings' => get_option('zotero_map_settings'),
        'highlight_color' => get_option('zotero_map_highlight_color'),
        'default_color' => get_option('zotero_map_default_color'),
        'border_color' => get_option('zotero_map_border_color'),
        'water_color' => get_option('zotero_map_water_color'),
        'enable_export' => get_option('zotero_map_enable_export')
    ], JSON_PRETTY_PRINT);
    exit;
}

function zotero_map_settings_page() {
    if (!current_user_can('manage_options')) return;

    $upload_dir = wp_upload_dir();
    $log_path = $upload_dir['basedir'] . '/zotero-map/update.log';
    $countries_file = $upload_dir['basedir'] . '/zotero-map/world_bank_countries.json';
    $countries = file_exists($countries_file) ? json_decode(file_get_contents($countries_file), true) : [];

    // Save API settings
    if (isset($_POST['zotero_map_save']) && check_admin_referer('zotero_map_save_settings')) {
        update_option('zotero_map_settings', [
            'group_id' => sanitize_text_field($_POST['group_id']),
            'library' => sanitize_text_field($_POST['library']),
            'collections' => array_filter(array_map('trim', explode("\n", $_POST['collections'])))
        ]);
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    // Save color settings
    if (isset($_POST['zotero_map_save_colors']) && check_admin_referer('zotero_map_save_colors')) {
        update_option('zotero_map_highlight_color', sanitize_hex_color($_POST['highlight_color']));
        update_option('zotero_map_default_color', sanitize_hex_color($_POST['default_color']));
        update_option('zotero_map_border_color', sanitize_hex_color($_POST['border_color']));
        update_option('zotero_map_water_color', sanitize_hex_color($_POST['water_color']));
        echo '<div class="updated"><p>Color settings saved.</p></div>';
    }

    // Save export toggle
    if (isset($_POST['zotero_map_toggle_export'])) {
        update_option('zotero_map_enable_export', isset($_POST['enable_export']) ? 1 : 0);
        echo '<div class="updated"><p>Export toggle saved.</p></div>';
    }

    // Import JSON
    if (isset($_POST['zotero_map_import']) && check_admin_referer('zotero_map_import_settings')) {
        if (!empty($_FILES['zotero_settings_file']['tmp_name'])) {
            $data = json_decode(file_get_contents($_FILES['zotero_settings_file']['tmp_name']), true);
            if (is_array($data)) {
                if (isset($data['zotero_map_settings'])) update_option('zotero_map_settings', $data['zotero_map_settings']);
                if (isset($data['highlight_color'])) update_option('zotero_map_highlight_color', $data['highlight_color']);
                if (isset($data['default_color'])) update_option('zotero_map_default_color', $data['default_color']);
                if (isset($data['border_color'])) update_option('zotero_map_border_color', $data['border_color']);
                if (isset($data['water_color'])) update_option('zotero_map_water_color', $data['water_color']);
                if (isset($data['enable_export'])) update_option('zotero_map_enable_export', $data['enable_export']);
                echo '<div class="updated"><p>Settings imported successfully.</p></div>';
            }
        }
    }

    // Clear log
    if (isset($_POST['zotero_map_clear_log']) && check_admin_referer('zotero_map_clear_log')) {
        file_put_contents($log_path, '');
        echo '<div class="updated"><p>Log cleared.</p></div>';
    }

    $settings = get_option('zotero_map_settings', []);
?>

<div class="wrap">
    <h1>Zotero Map Settings</h1>

    <h2>Zotero API Settings</h2>
    <form method="post">
        <?php wp_nonce_field('zotero_map_save_settings'); ?>
        <table class="form-table">
            <tr><th><label>Zotero Group ID</label></th>
                <td><input type="text" name="group_id" value="<?php echo esc_attr($settings['group_id'] ?? ''); ?>" class="regular-text"></td></tr>
            <tr><th><label>Library Name</label></th>
                <td><input type="text" name="library" value="<?php echo esc_attr($settings['library'] ?? ''); ?>" class="regular-text"></td></tr>
            <tr><th><label>Collection Keys (one per line)</label></th>
                <td><textarea name="collections" rows="5" cols="50"><?php echo esc_textarea(implode("\n", $settings['collections'] ?? [])); ?></textarea></td></tr>
        </table>
        <?php submit_button('Save Settings', 'primary', 'zotero_map_save'); ?>
    </form>

    <h2>Map Color Customization</h2>
    <form method="post">
        <?php wp_nonce_field('zotero_map_save_colors'); ?>
        <table class="form-table">
            <tr><th><label>Highlighted Country Color</label></th>
                <td><input type="color" name="highlight_color" value="<?php echo esc_attr(get_option('zotero_map_highlight_color', '#0073e6')); ?>"></td></tr>
            <tr><th><label>Default Country Color</label></th>
                <td><input type="color" name="default_color" value="<?php echo esc_attr(get_option('zotero_map_default_color', '#dddddd')); ?>"></td></tr>
            <tr><th><label>Border Color</label></th>
                <td><input type="color" name="border_color" value="<?php echo esc_attr(get_option('zotero_map_border_color', '#444444')); ?>"></td></tr>
            <tr><th><label>Water Color</label></th>
                <td><input type="color" name="water_color" value="<?php echo esc_attr(get_option('zotero_map_water_color', '#b3d1ff')); ?>"></td></tr>
        </table>
        <?php submit_button('Save Colors', 'secondary', 'zotero_map_save_colors'); ?>
    </form>

    <h2>Map Export Settings</h2>
    <form method="post">
        <label><input type="checkbox" name="enable_export" value="1" <?php checked(get_option('zotero_map_enable_export'), 1); ?>> Enable Export to PNG button below the map</label>
        <?php submit_button('Save Export Settings', 'secondary', 'zotero_map_toggle_export'); ?>
    </form>

    <h2>Manual Update URLs</h2>
    <ul>
        <li><code><a href="<?php echo esc_url(admin_url('?trigger_zotero_map_update')); ?>">?trigger_zotero_map_update</a></code></li>
        <li><code><a href="<?php echo esc_url(admin_url('?trigger_world_bank_update')); ?>">?trigger_world_bank_update</a></code></li>
    </ul>

    <h2>Import / Export Settings</h2>
    <p><a href="?page=zotero-map&zotero_map_export=1" class="button">Export Settings as JSON</a></p>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('zotero_map_import_settings'); ?>
        <input type="file" name="zotero_settings_file" accept=".json" required>
        <?php submit_button('Import Settings', 'secondary', 'zotero_map_import'); ?>
    </form>

    <h2>Plugin Log Viewer</h2>
    <form method="post"><?php wp_nonce_field('zotero_map_clear_log'); ?>
        <?php submit_button('Clear Log', 'delete', 'zotero_map_clear_log'); ?>
    </form>
    <pre style="background:#f7f7f7; padding:1em; border:1px solid #ccc; max-height:300px; overflow:auto;"><?php
        if (file_exists($log_path)) {
            $lines = array_reverse(file($log_path));
            echo esc_html(implode('', array_slice($lines, 0, 100)));
        } else {
            echo "Log file not found.";
        }
    ?></pre>

    <h2>World Bank Country List</h2>
    <p>Showing <?php echo count($countries); ?> countries:</p>
    <ul style="columns: 3;"><?php foreach ($countries as $country): ?><li><?php echo esc_html($country); ?></li><?php endforeach; ?></ul>
</div>
<?php } ?>
