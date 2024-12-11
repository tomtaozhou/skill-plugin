<?php
/*
Plugin Name: SKILL
Description: Control and send notifications to ActivityPub devices based on post values from selected user.
Author: Tao Zhou
Version: 1.0
*/

// 创建插件设置页面
function skill_plugin_menu() {
    add_options_page('SKILL Control Panel', 'SKILL Control', 'manage_options', 'skill-plugin-menu', 'skill_plugin_main_page');
}
add_action('admin_menu', 'skill_plugin_menu');

// 插件主页
function skill_plugin_main_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Handle form submission
    if (isset($_POST['submit_settings'])) {
        foreach ($_POST['devices'] as $device_id => $settings) {
            update_user_meta($device_id, 'device_condition', sanitize_text_field($settings['condition']));
            update_user_meta($device_id, 'device_threshold', intval($settings['threshold']));
            update_user_meta($device_id, 'device_message', sanitize_text_field($settings['message']));
        }

        // Update Mastodon connection settings
        update_option('mastodon_api_url', sanitize_text_field($_POST['mastodon_api_url']));
        update_option('mastodon_token', sanitize_text_field($_POST['mastodon_token']));
        echo '<div class="updated"><p>Settings updated successfully!</p></div>';
    }

    // Fetch saved Mastodon settings
    $mastodon_api_url = get_option('mastodon_api_url', '');
    $mastodon_token = get_option('mastodon_token', '');

    // Fetch all users (assuming they represent devices)
    $users = get_users();

    echo '<div class="wrap">';
    echo '<h1>SKILL Control Panel</h1>';
    echo '<form method="post" action="">';

    // Display settings for each device user
    foreach ($users as $user) {
        echo '<h2>Device: ' . esc_html($user->display_name) . '</h2>';

        $selected_condition = get_user_meta($user->ID, 'device_condition', true);
        $selected_threshold = get_user_meta($user->ID, 'device_threshold', true);
        $selected_message = get_user_meta($user->ID, 'device_message', true);

        // Condition settings for the device
        echo '<select name="devices[' . $user->ID . '][condition]">';
        echo '<option value="=" ' . selected($selected_condition, '=', false) . '>Equal to</option>';
        echo '<option value=">" ' . selected($selected_condition, '>', false) . '>Greater than</option>';
        echo '<option value="<" ' . selected($selected_condition, '<', false) . '>Less than</option>';
        echo '</select>';
        echo '<input type="number" name="devices[' . $user->ID . '][threshold]" value="' . esc_attr($selected_threshold) . '">';
        echo '<input type="text" name="devices[' . $user->ID . '][message]" value="' . esc_attr($selected_message) . '"><br>';
    }

    // Mastodon settings
    echo '<h2>Mastodon Settings</h2>';
    echo 'API URL: <input type="text" name="mastodon_api_url" value="' . esc_attr($mastodon_api_url) . '"><br>';
    echo 'Token: <input type="text" name="mastodon_token" value="' . esc_attr($mastodon_token) . '"><br>';

    // Submit button
    echo '<input type="submit" name="submit_settings" value="Save Settings">';
    echo '</form>';
    echo '</div>';
}

// Check post values and send notification if necessary
add_action('save_post', 'check_post_value_and_notify');

function check_post_value_and_notify($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
        return;

    $post_author = get_post_field('post_author', $post_id);
    $post_content = get_post_field('post_content', $post_id);
    preg_match('/\[value=(\d+)\]/', $post_content, $matches);

    if (isset($matches[1])) {
        $post_value = intval($matches[1]);

        $condition = get_user_meta($post_author, 'device_condition', true);
        $threshold_value = get_user_meta($post_author, 'device_threshold', true);

        $should_notify = false;
        switch ($condition) {
            case '=':
                $should_notify = ($post_value == $threshold_value);
                break;
            case '>':
                $should_notify = ($post_value > $threshold_value);
                break;
            case '<':
                $should_notify = ($post_value < $threshold_value);
                break;
        }

        if ($should_notify) {
            $message = get_user_meta($post_author, 'device_message', true);
            send_notification_to_mastodon($message);
        }
    }
}

// Send a notification to Mastodon
function send_notification_to_mastodon($message) {
    $api_url = get_option('mastodon_api_url', '');
    $token = get_option('mastodon_token', '');

    if (empty($api_url) || empty($token)) {
        return;
    }

    $endpoint = rtrim($api_url, '/') . '/api/v1/statuses';

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'status' => $message,
            'visibility' => 'public'
        )),
        'method' => 'POST'
    );

    $response = wp_remote_request($endpoint, $args);

    if (is_wp_error($response)) {
        error_log('Failed to send notification to Mastodon: ' . $response->get_error_message());
    }
}
?>
