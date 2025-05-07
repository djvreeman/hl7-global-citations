<?php
/*
Plugin Name: Zotero Citation Map
Description: Displays an interactive map linking countries to Zotero citation tags and a bar chart of citation counts by year.
Version: 1.3
Author: Daniel J. Vreeman, PT, DPT, MS, FACMI, FIAHSI
*/

define('ZOTERO_MAP_OPTION_KEY', 'zotero_map_settings');

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

function zotero_map_enqueue_scripts() {
    // Ensure jQuery is loaded first
    wp_enqueue_script('jquery');
    
    // First enqueue styles
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
    wp_enqueue_style('zotero-map-css', plugin_dir_url(__FILE__) . 'css/map.css', ['leaflet-css'], '1.0.2');

    // Then enqueue scripts - load Chart.js first
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', ['jquery'], '4.4.1', true);
    
    // Then load Leaflet and make sure to remove defer
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', ['jquery'], '1.9.4', true);
    add_filter('script_loader_tag', 'zotero_map_remove_defer_from_leaflet', 10, 3);
    
    // Only after that, load your scripts
    wp_enqueue_script('zotero-map-js', plugin_dir_url(__FILE__) . 'js/map.js', ['jquery', 'leaflet-js', 'chartjs'], '1.0.5', true);
    wp_enqueue_script('zotero-bar-js', plugin_dir_url(__FILE__) . 'js/zotero-bar-chart.js', ['jquery', 'chartjs'], '1.0.0', true);

    $upload_dir = wp_upload_dir();
    $json_path = $upload_dir['basedir'] . '/zotero-map/country_tag_map.json';
    $map_data = file_exists($json_path) ? json_decode(file_get_contents($json_path), true) : [];

    if ($map_data === null) {
        $map_data = [];
    }

    $colors = [
        'highlight' => get_option('zotero_map_highlight_color', '#ec2227'),
        'default'   => get_option('zotero_map_default_color', '#e4e4e4'),
        'border'    => get_option('zotero_map_border_color', '#ababab'),
    ];

    wp_localize_script('zotero-map-js', 'ZoteroMapData', [
        'countryUrls' => $map_data,
        'mapColors'   => $colors,
        'waterColor'  => get_option('zotero_map_water_color', '#ffffff'),
        'enableExport'=> (bool) get_option('zotero_map_enable_export', false),
        'geojsonUrl'  => plugins_url('data/world.geo.json', __FILE__),
        'nameAliases' => zotero_map_get_name_aliases(),
        'pluginUrl'   => plugin_dir_url(__FILE__),
    ]);
}

// Fix the remove defer function to be more robust
function zotero_map_remove_defer_from_leaflet($tag, $handle, $src) {
    if ('leaflet-js' === $handle) {
        // More comprehensive approach to remove defer attributes
        $tag = preg_replace('/ defer(=[\'"]defer[\'"])?/', '', $tag);
    }
    return $tag;
}
add_action('wp_enqueue_scripts', 'zotero_map_enqueue_scripts');

function zotero_map_shortcode() {
    $highlight = get_option('zotero_map_highlight_color', '#ec2227');
    $default   = get_option('zotero_map_default_color', '#e4e4e4');
    $border    = get_option('zotero_map_border_color', '#ababab');
    $water     = get_option('zotero_map_water_color', '#ffffff');
    
    $upload_dir = wp_upload_dir();
    $json_path = $upload_dir['basedir'] . '/zotero-map/country_tag_map.json';
    $map_data = file_exists($json_path) ? json_decode(file_get_contents($json_path), true) : [];

    if ($map_data === null) {
        $map_data = [];
    }

    $map_data_json = json_encode($map_data);
    $name_aliases_json = json_encode(zotero_map_get_name_aliases());
    
    $output = '<div id="zotero-map-loading" style="text-align: center; padding: 20px;">Loading map...</div>';
    $output .= '<div id="zotero-map" style="height: 60vh; min-height: 300px; width: 100%; background-color: ' . esc_attr($water) . ';"></div>';
    $output .= '<noscript><div style="text-align: center; padding: 20px; color: #ff0000;">JavaScript is required to view this map. Please enable JavaScript in your browser settings.</div></noscript>';
    $output .= '<script>
        window.ZoteroMapData = {
            countryUrls: ' . $map_data_json . ',
            mapColors: {
                highlight: "' . esc_js($highlight) . '",
                default: "' . esc_js($default) . '",
                border: "' . esc_js($border) . '"
            },
            waterColor: "' . esc_js($water) . '",
            enableExport: ' . (get_option('zotero_map_enable_export', false) ? 'true' : 'false') . ',
            geojsonUrl: "' . esc_js(plugins_url('data/world.geo.json', __FILE__)) . '",
            nameAliases: ' . $name_aliases_json . ',
            pluginUrl: "' . esc_js(plugin_dir_url(__FILE__)) . '"
        };
    </script>';
    return $output;
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

// Save map color options
add_action('admin_init', function () {
    if (isset($_POST['zotero_map_save_colors']) && check_admin_referer('zotero_map_save_colors')) {
        update_option('zotero_map_highlight_color', sanitize_hex_color($_POST['highlight_color']));
        update_option('zotero_map_default_color', sanitize_hex_color($_POST['default_color']));
        update_option('zotero_map_border_color', sanitize_hex_color($_POST['border_color']));
        update_option('zotero_map_water_color', sanitize_hex_color($_POST['water_color']));
    }
});

add_action('admin_menu', function () {
    add_options_page('Zotero Map Settings', 'Zotero Map', 'manage_options', 'zotero-map-settings', function () {
        require plugin_dir_path(__FILE__) . 'admin-page.php';
    });
});

// Cache Functions

add_action('admin_menu', function () {
    add_options_page('Zotero Map Settings', 'Zotero Map', 'manage_options', 'zotero-map-settings', function () {
        require plugin_dir_path(__FILE__) . 'admin-page.php';
    });
});

add_action('admin_init', function () {
    if (isset($_GET['zotero_clear_cache']) && current_user_can('manage_options')) {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/zotero-map/cache';

        if (file_exists($cache_dir)) {
            foreach (glob("$cache_dir/*.json") as $file) {
                unlink($file);
            }
        }

        log_zotero_event("✅ Admin manually cleared bar chart cache.");

        add_action('admin_notices', function () {
            echo '<div class="notice notice-success is-dismissible"><p>Zotero bar chart cache cleared.</p></div>';
        });
    }
});

// Register additional shortcode for bar chart
add_shortcode('zotero_bar_chart', function ($atts) {
    static $chart_counter = 0;
    $chart_counter++;
    $chart_id = 'zotero-bar-chart-' . $chart_counter;

    $atts = shortcode_atts([
        'group_id' => '',
        'collection' => '',
        'color' => '#ec2227',
        'extrapolate' => false,
        'width' => '100%',
        'height' => '400px',
    ], $atts);

    $data = [
        'group_id' => $atts['group_id'],
        'collection' => $atts['collection'],
        'color' => $atts['color'],
        'extrapolate' => filter_var($atts['extrapolate'], FILTER_VALIDATE_BOOLEAN),
        'chartId' => $chart_id,
    ];

    $js_var = 'zoteroBarChartSettings_' . $chart_counter;
    
    // Add the settings BEFORE the canvas element
    ob_start();
    echo '<script type="text/javascript">window.' . esc_js($js_var) . ' = ' . json_encode($data) . ';</script>';
    echo '<canvas id="' . esc_attr($chart_id) . '" style="width:' . esc_attr($atts['width']) . '; height:' . esc_attr($atts['height']) . '"></canvas>';
    return ob_get_clean();
});

// Handle bar chart cache uploads via AJAX
add_action('wp_ajax_zotero_generate_barchart_cache', 'zotero_generate_barchart_cache');

function zotero_generate_barchart_cache() {
    if (!isset($_POST['group_id']) || !isset($_POST['collection'])) {
        wp_send_json_error("Missing required parameters", 400);
    }

    $group_id = sanitize_text_field($_POST['group_id']);
    $collection = sanitize_text_field($_POST['collection']);

    $url = "https://api.zotero.org/groups/$group_id/collections/$collection/items?include=data&limit=100&start=";
    $all_items = [];
    $start = 0;

    while (true) {
        $response = wp_remote_get($url . $start);
        if (is_wp_error($response)) {
            wp_send_json_error("Zotero API error: " . $response->get_error_message(), 500);
        }

        $items = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($items) || empty($items)) break;

        $all_items = array_merge($all_items, $items);
        $start += count($items);
    }

    $upload_dir = wp_upload_dir();
    $cache_dir = $upload_dir['basedir'] . '/zotero-map';
    if (!file_exists($cache_dir)) wp_mkdir_p($cache_dir);

    $cache_filename = "$cache_dir/bar_chart_{$group_id}_{$collection}.json";
    file_put_contents($cache_filename, json_encode($all_items, JSON_PRETTY_PRINT));

    wp_send_json_success("Cache saved", 200);
}