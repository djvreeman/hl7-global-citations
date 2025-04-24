<?php
/*
Plugin Name: Zotero Citation Map
Description: Displays an interactive map linking countries to Zotero citation tags.
Version: 1.1
Author: Daniel J. Vreeman, PT, DPT, MS, FACMI, FIAHSI
*/

define('ZOTERO_MAP_OPTION_KEY', 'zotero_map_settings');

function zotero_map_enqueue_scripts() {
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    wp_enqueue_style('zotero-map-css', plugin_dir_url(__FILE__) . 'css/map.css');

    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);
    wp_enqueue_script('zotero-map-js', plugin_dir_url(__FILE__) . 'js/map.js', ['leaflet-js'], null, true);

    $settings = get_option(ZOTERO_MAP_OPTION_KEY, []);
    $upload_dir = wp_upload_dir();
    $json_path = $upload_dir['basedir'] . '/zotero-map/country_tag_map.json';
    $map_data = file_exists($json_path) ? json_decode(file_get_contents($json_path), true) : [];

    
    $colors = [
        'highlight' => get_option('zotero_map_highlight_color', '#0073e6'),
        'default' => get_option('zotero_map_default_color', '#dddddd'),
        'border' => get_option('zotero_map_border_color', '#444444'),
    ];
    wp_localize_script('zotero-map-js', 'ZoteroMapData', [
        'countryUrls' => $map_data,
        'mapColors' => $colors
    ,
        'nameAliases' => zotero_map_get_name_aliases(),
        'waterColor' => get_option('zotero_map_water_color', '#b3d1ff'),
        'mapColors' => [
            'highlight' => get_option('zotero_map_highlight_color', '#ec2227'),
            'default' => get_option('zotero_map_default_color', '#e4e4e4'),
            'border' => get_option('zotero_map_border_color', '#ababab')
        ],
        'waterColor' => get_option('zotero_map_water_color', '#ffffff'),
        'enableExport' => (bool) get_option('zotero_map_enable_export', false)]);
    
}
add_action('wp_enqueue_scripts', 'zotero_map_enqueue_scripts');

function zotero_map_shortcode() {
    return '<div id="zotero-map" style="height:600px;"></div><noscript><p>This map requires JavaScript to be enabled.</p></noscript>';
}
add_shortcode('zotero_citation_map', 'zotero_map_shortcode');

function zotero_map_schedule_events() {
    if (!wp_next_scheduled('zotero_map_update_json')) {
        wp_schedule_event(time(), 'daily', 'zotero_map_update_json');
    }
    if (!wp_next_scheduled('zotero_map_update_countries')) {
        wp_schedule_event(time(), 'weekly', 'zotero_map_update_countries');
    }
}
register_activation_hook(__FILE__, 'zotero_map_schedule_events');

function zotero_map_update_countries() {
    $url = "http://api.worldbank.org/v2/country?format=json&per_page=500";
    $response = wp_remote_get($url);

    if (is_wp_error($response)) return;
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if (!is_array($json) || !isset($json[1])) return;

    $countries = array_map(function($c) {
        return $c['name'];
    }, $json[1]);

    $upload_dir = wp_upload_dir();
    $data_dir = $upload_dir['basedir'] . '/zotero-map';
    if (!file_exists($data_dir)) wp_mkdir_p($data_dir);

    file_put_contents("$data_dir/world_bank_countries.json", json_encode($countries, JSON_PRETTY_PRINT));
}



function log_zotero_event($message) {
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/zotero-map/update.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Manual trigger with admin notice
add_action('admin_init', function () {
    if (isset($_GET['trigger_zotero_map_update'])) {
        try {
            zotero_map_update_json();
            log_zotero_event("Manual Zotero tag update succeeded.");
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>Zotero tag map updated successfully.</p></div>';
            });
        } catch (Exception $e) {
            log_zotero_event("Manual Zotero tag update failed: " . $e->getMessage());
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error"><p>Error updating Zotero tag map: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
});



// Manual trigger for World Bank country list update
add_action('admin_init', function () {
    if (isset($_GET['trigger_world_bank_update'])) {
        try {
            zotero_map_update_countries();
            log_zotero_event("Manual World Bank country list update succeeded.");
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>World Bank country list updated successfully.</p></div>';
            });
        } catch (Exception $e) {
            log_zotero_event("Manual World Bank country list update failed: " . $e->getMessage());
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error"><p>Error updating World Bank country list: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
});

require_once plugin_dir_path(__FILE__) . 'admin-page.php';


// Save map color options
add_action('admin_init', function () {
    if (isset($_POST['zotero_map_save_colors']) && check_admin_referer('zotero_map_save_colors')) {
        update_option('zotero_map_highlight_color', sanitize_hex_color($_POST['highlight_color']));
        update_option('zotero_map_default_color', sanitize_hex_color($_POST['default_color']));
        update_option('zotero_map_border_color', sanitize_hex_color($_POST['border_color']));
        update_option('zotero_map_water_color', sanitize_hex_color($_POST['water_color']));
    }
});



function zotero_map_update_json() {
    $settings = get_option('zotero_map_settings', []);
    $collections = $settings['collections'] ?? [];
    $group = $settings['group_id'] ?? '';
    $library = $settings['library'] ?? '';

    if (empty($collections) || empty($group) || empty($library)) {
        log_zotero_event("❌ Missing Zotero configuration settings.");
        return;
    }

    $upload_dir = wp_upload_dir();
    $country_file = $upload_dir['basedir'] . '/zotero-map/world_bank_countries.json';
    $countries = file_exists($country_file) ? json_decode(file_get_contents($country_file), true) : [];

    if (!is_array($countries)) {
        log_zotero_event("❌ World Bank countries list is not a valid array.");
        return;
    }

    $country_map = [];

    foreach ($collections as $collection) {
        $url = "https://api.zotero.org/groups/$group/collections/$collection/items?include=data&limit=100";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            log_zotero_event("❌ Error fetching items for $collection: " . $response->get_error_message());
            continue;
        }

        $items = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($items)) {
            log_zotero_event("❌ Invalid item response from Zotero for $collection.");
            continue;
        }

        log_zotero_event("✅ Fetched " . count($items) . " items from collection $collection.");

        foreach ($items as $item) {
            if (!isset($item['data']['tags'])) continue;
            foreach ($item['data']['tags'] as $tag_data) {
                $tag = $tag_data['tag'] ?? '';
                if ($tag && in_array($tag, $countries)) {
                    if (!isset($country_map[$tag])) {
                        $encoded = rawurlencode($tag);
                        $country_map[$tag] = [
                            'url' => "https://www.zotero.org/groups/$group/$library/collections/$collection/tags/$encoded/collection",
                            'count' => 1
                        ];
                    } else {
                        $country_map[$tag]['count'] += 1;
                    }
                }
            }
        }
    }

    $data_dir = $upload_dir['basedir'] . '/zotero-map';
    if (!file_exists($data_dir)) wp_mkdir_p($data_dir);
    file_put_contents("$data_dir/country_tag_map.json", json_encode($country_map, JSON_PRETTY_PRINT));
    log_zotero_event("✅ Finished writing country_tag_map.json with " . count($country_map) . " entries.");
}




function zotero_map_get_name_aliases() {
    return [
        "United States of America" => "United States",
        "Russian Federation" => "Russia",
        "Viet Nam" => "Vietnam",
        "Iran (Islamic Republic of)" => "Iran",
        "Venezuela (Bolivarian Republic of)" => "Venezuela",
        "Syrian Arab Republic" => "Syria",
        "Dem. Rep. Korea" => "North Korea",
        "Republic of Korea" => "South Korea",
        "Democratic Republic of the Congo" => "Congo, Dem. Rep.",
        "Republic of the Congo" => "Congo, Rep.",
        "Bolivia (Plurinational State of)" => "Bolivia",
        "United Republic of Tanzania" => "Tanzania",
        "Côte d'Ivoire" => "Cote d'Ivoire",
        "Lao People's Democratic Republic" => "Laos",
        "Egypt" => "Egypt, Arab Rep.",
        "Gambia" => "Gambia, The",
        "Bahamas" => "Bahamas, The",
        "Yemen" => "Yemen, Rep.",
        "Micronesia (Federated States of)" => "Micronesia, Fed. Sts.",
        "Slovakia" => "Slovak Republic",
        "Czechia" => "Czech Republic",
        "Kyrgyzstan" => "Kyrgyz Republic",
        "Brunei" => "Brunei Darussalam",
        "Cabo Verde" => "Cape Verde",
        "North Macedonia" => "Macedonia, FYR"
    ];
}

