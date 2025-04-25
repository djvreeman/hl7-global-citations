<?php
add_action('admin_menu', function () {
    add_options_page('Zotero Map Settings', 'Zotero Map', 'manage_options', 'zotero-map', 'zotero_map_settings_page');
});

function zotero_map_settings_page() {
    $highlight = get_option('zotero_map_highlight_color', '#ec2227');
    $default   = get_option('zotero_map_default_color', '#e4e4e4');
    $border    = get_option('zotero_map_border_color', '#ababab');
    $water     = get_option('zotero_map_water_color', '#ffffff');
    $enable_export = get_option('zotero_map_enable_export', false);
    $upload_dir = wp_upload_dir();
    $log_path = $upload_dir['basedir'] . '/zotero-map/update.log';
    $country_path = $upload_dir['basedir'] . '/zotero-map/world_bank_countries.json';
    $countries = file_exists($country_path) ? json_decode(file_get_contents($country_path), true) : [];
    ?>
    <div class="wrap">
      <h1>Zotero Map Settings</h1>

      <form method="post">
        <?php wp_nonce_field('zotero_map_save_colors'); ?>
        <h2>Color Settings</h2>
        <table class="form-table">
          <tr><th>Highlight Color</th><td><input type="color" name="highlight_color" value="<?php echo esc_attr($highlight); ?>"></td></tr>
          <tr><th>Default Color</th><td><input type="color" name="default_color" value="<?php echo esc_attr($default); ?>"></td></tr>
          <tr><th>Border Color</th><td><input type="color" name="border_color" value="<?php echo esc_attr($border); ?>"></td></tr>
          <tr><th>Water Color</th><td><input type="color" name="water_color" value="<?php echo esc_attr($water); ?>"></td></tr>
        </table>
        <p><input type="submit" name="zotero_map_save_colors" class="button-primary" value="Save Colors"></p>
      </form>

      <form method="post">
        <?php wp_nonce_field('zotero_map_clear_log'); ?>
        <p><input type="submit" name="zotero_map_clear_log" class="button" value="Clear Update Log"></p>
      </form>

      <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('zotero_map_import_settings'); ?>
        <p>Import Settings JSON: <input type="file" name="zotero_settings_file">
        <input type="submit" name="zotero_map_import" class="button" value="Import"></p>
      </form>

      <p><a href="?page=zotero-map&zotero_map_export=true" class="button">Export Settings</a></p>

      <form method="post">
        <?php wp_nonce_field('zotero_map_toggle_export'); ?>
        <h2>Export Option</h2>
        <label><input type="checkbox" name="enable_export" value="1" <?php checked($enable_export, true); ?>> Enable "Export Map as PNG" Button</label>
        <p><input type="submit" name="zotero_map_toggle_export" class="button" value="Save Export Setting"></p>
      </form>

      <hr>
      <h2>Manual Data Updates</h2>
      <p>
        <a href="?trigger_zotero_map_update=true" class="button button-secondary">🔄 Update Zotero Map Data</a>
        <a href="?trigger_world_bank_update=true" class="button button-secondary">🌍 Update World Bank Country List</a>
      </p>
    </div>
    <?php
}
