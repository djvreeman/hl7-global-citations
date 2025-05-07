<?php
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$zotero_settings = get_option('zotero_map_settings', [
    'group_id' => '',
    'library' => '',
    'collections' => []
]);

if (isset($_POST['zotero_map_save_settings']) && check_admin_referer('zotero_map_save_settings')) {
    $zotero_settings['group_id'] = sanitize_text_field($_POST['group_id']);
    $zotero_settings['library'] = sanitize_text_field($_POST['library']);
    $collections_raw = explode(',', $_POST['collections']);
    $zotero_settings['collections'] = array_filter(array_map('sanitize_text_field', $collections_raw));

    update_option('zotero_map_settings', $zotero_settings);

    echo '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
}
?>

<div class="wrap">
    <h1>Zotero Citation Map Settings</h1>

    <!-- Map Color Settings -->
    <form method="post">
        <?php wp_nonce_field('zotero_map_save_colors'); ?>
        <h2>Map Color Configuration</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="highlight_color">Highlight Color</label></th>
                <td><input name="highlight_color" type="text" id="highlight_color" value="<?php echo esc_attr(get_option('zotero_map_highlight_color', '#ec2227')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="default_color">Default Color</label></th>
                <td><input name="default_color" type="text" id="default_color" value="<?php echo esc_attr(get_option('zotero_map_default_color', '#e4e4e4')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="border_color">Border Color</label></th>
                <td><input name="border_color" type="text" id="border_color" value="<?php echo esc_attr(get_option('zotero_map_border_color', '#ababab')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="water_color">Water Color</label></th>
                <td><input name="water_color" type="text" id="water_color" value="<?php echo esc_attr(get_option('zotero_map_water_color', '#ffffff')); ?>" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button('Save Map Colors', 'primary', 'zotero_map_save_colors'); ?>
    </form>

    <hr>

    <!-- Zotero API Settings -->
    <form method="post">
        <?php wp_nonce_field('zotero_map_save_settings'); ?>
        <h2>Zotero API Configuration</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="group_id">Group ID</label></th>
                <td><input name="group_id" type="text" id="group_id" value="<?php echo esc_attr($zotero_settings['group_id']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="library">Library Name</label></th>
                <td><input name="library" type="text" id="library" value="<?php echo esc_attr($zotero_settings['library']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="collections">Collection Keys (comma-separated)</label></th>
                <td><input name="collections" type="text" id="collections" value="<?php echo esc_attr(implode(',', $zotero_settings['collections'])); ?>" class="large-text"></td>
            </tr>
        </table>
        <?php submit_button('Save Zotero Settings', 'primary', 'zotero_map_save_settings'); ?>
    </form>
</div>

<!-- Cachce Settings -->
<h2>Chart Cache Management</h2>
<p>This will attempt to clear locally cached chart data in the browser (if supported).</p>
<button id="clear-zotero-cache" class="button button-secondary">Clear Cached Chart Data</button>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('clear-zotero-cache');
    btn.addEventListener('click', function () {
        const keysToClear = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith('zotero_')) {
                keysToClear.push(key);
            }
        }

        keysToClear.forEach(key => localStorage.removeItem(key));

        alert('Zotero chart cache cleared from localStorage.');
    });
});
</script>