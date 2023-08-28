<?php
/*
Plugin Name: SKILL Plugin
Plugin URI: http://your_plugin_uri_here
Description: Sends notifications to Mastodon based on Ubi-House Smart Home Device data.
Author: Tao Zhou
Author URI: http://your_author_uri_here
Version: 1.0
*/

// Register REST API endpoint to receive carbon dioxide data
add_action('rest_api_init', function () {
    register_rest_route('skill/v1', '/carbon_data/', array(
        'methods' => 'POST',
        'callback' => 'store_carbon_data',
        'permission_callback' => '__return_true'
    ));
});

function store_carbon_data(WP_REST_Request $request) {
    $carbon_data = $request->get_param('data');
    if (is_null($carbon_data)) {
        return new WP_Error('no_data', 'No data received', array('status' => 400));
    }
    update_option('carbon_data_value', $carbon_data);
    trigger_mastodon_message($carbon_data);
    return new WP_REST_Response('Data stored successfully', 200);
}

function trigger_mastodon_message($carbon_data) {
    $co2_turn_on_threshold = (int) get_option('co2_threshold', 1000);
    $co2_turn_off_threshold = (int) get_option('co2_turn_off_threshold', 500);

    if ($carbon_data >= $co2_turn_on_threshold) {
        send_to_mastodon('turn on');
    } elseif ($carbon_data <= $co2_turn_off_threshold) {
        send_to_mastodon('turn off');
    }
}

function send_to_mastodon($message) {
    $mastodon_api_url = get_option('mastodon_api_url');
    $mastodon_token = get_option('mastodon_token');
    $response = wp_remote_post($mastodon_api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $mastodon_token,
        ),
        'body' => array(
            'status' => $message,
        ),
    ));
    if (is_wp_error($response)) {
        error_log($response->get_error_message());
    }
}

function skill_plugin_menu() {
    add_options_page(
        'SKILL Plugin Settings',
        'SKILL Plugin',
        'manage_options',
        'ubi-house-device-settings',
        'skill_plugin_settings_page'
    );
}
add_action('admin_menu', 'skill_plugin_menu');

function skill_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>SKILL Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ubi_house_device_settings_group');
            do_settings_sections('ubi-house-device-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function skill_plugin_settings_init() {
    register_setting('ubi_house_device_settings_group', 'mastodon_api_url');
    register_setting('ubi_house_device_settings_group', 'mastodon_token');
    register_setting('ubi_house_device_settings_group', 'co2_threshold');
    register_setting('ubi_house_device_settings_group', 'co2_turn_off_threshold');

    add_settings_section(
        'ubi_house_device_settings_mastodon_section',
        'Mastodon Settings',
        null,
        'ubi-house-device-settings'
    );

    add_settings_field(
        'mastodon_api_url',
        'Mastodon API URL',
        'skill_plugin_mastodon_api_url_callback',
        'ubi-house-device-settings',
        'ubi_house_device_settings_mastodon_section'
    );

    add_settings_field(
        'mastodon_token',
        'Mastodon Token',
        'skill_plugin_mastodon_token_callback',
        'ubi-house-device-settings',
        'ubi_house_device_settings_mastodon_section'
    );

    add_settings_field(
        'co2_threshold',
        'CO2 "Turn On" Threshold (in ppm)',
        'skill_plugin_co2_threshold_callback',
        'ubi-house-device-settings',
        'ubi_house_device_settings_mastodon_section'
    );

    add_settings_field(
        'co2_turn_off_threshold',
        'CO2 "Turn Off" Threshold (in ppm)',
        'skill_plugin_co2_turn_off_threshold_callback',
        'ubi-house-device-settings',
        'ubi_house_device_settings_mastodon_section'
    );
}
add_action('admin_init', 'skill_plugin_settings_init');

function skill_plugin_mastodon_api_url_callback() {
    $mastodon_api_url = esc_attr(get_option('mastodon_api_url', ''));
    echo "<input type='text' name='mastodon_api_url' value='{$mastodon_api_url}' />";
}

function skill_plugin_mastodon_token_callback() {
    $mastodon_token = esc_attr(get_option('mastodon_token', ''));
    echo "<input type='password' name='mastodon_token' value='{$mastodon_token}' />";
}

function skill_plugin_co2_threshold_callback() {
    $co2_threshold = esc_attr(get_option('co2_threshold', '1000'));
    echo "<input type='text' name='co2_threshold' value='{$co2_threshold}' />";
}

function skill_plugin_co2_turn_off_threshold_callback() {
    $co2_turn_off_threshold = esc_attr(get_option('co2_turn_off_threshold', '500'));
    echo "<input type='text' name='co2_turn_off_threshold' value='{$co2_turn_off_threshold}' />";
}
?>
