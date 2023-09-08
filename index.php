
<?php
/*
Plugin Name: SKILL Plugin
Plugin URI: http://your_plugin_uri_here
Description: Control and send notifications to ActivityPub devices based on post values from selected user.
Author: Tao Zhou
Author URI: http://your_author_uri_here
Version: 1.0
*/

// 创建插件菜单项
function skill_plugin_menu() {
    add_menu_page('SKILL Control Panel', 'SKILL Control', 'manage_options', 'skill-main', 'skill_plugin_main_page', 'dashicons-smartphone', 90);
}
add_action('admin_menu', 'skill_plugin_menu');

// 插件主页
function skill_plugin_main_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Handle form submission
    if (isset($_POST['submit_settings'])) {
        update_option('selected_user', sanitize_text_field($_POST['selected_user']));
        update_option('condition', sanitize_text_field($_POST['condition']));
        update_option('threshold_value', intval($_POST['threshold_value']));
        update_option('mastodon_api_url', sanitize_text_field($_POST['mastodon_api_url']));
        update_option('mastodon_token', sanitize_text_field($_POST['mastodon_token']));
        update_option('notification_message', sanitize_text_field($_POST['notification_message']));
        echo '<div class="updated"><p>Settings updated successfully!</p></div>';
    }

    // Fetch saved settings
    $selected_user = get_option('selected_user', '');
    $condition = get_option('condition', '=');
    $threshold_value = get_option('threshold_value', 0);
    $mastodon_api_url = get_option('mastodon_api_url', '');
    $mastodon_token = get_option('mastodon_token', '');
    $notification_message = get_option('notification_message', '');

    // Fetch all users
    $users = get_users();

    echo '<div class="wrap">';
    echo '<h1>SKILL Control Panel</h1>';
    echo '<form method="post" action="">';

    // User selection
    echo '<h2>Select User</h2>';
    echo '<select name="selected_user">';
    foreach ($users as $user) {
        echo '<option value="' . esc_attr($user->ID) . '" ' . selected($selected_user, $user->ID, false) . '>' . esc_html($user->display_name) . '</option>';
    }
    echo '</select>';

    // Condition settings
    echo '<h2>Condition Settings</h2>';
    echo '<select name="condition">';
    echo '<option value="=" ' . selected($condition, '=', false) . '>Equal to</option>';
    echo '<option value=">" ' . selected($condition, '>', false) . '>Greater than</option>';
    echo '<option value="<" ' . selected($condition, '<', false) . '>Less than</option>';
    echo '</select>';
    echo '<input type="number" name="threshold_value" value="' . esc_attr($threshold_value) . '">';

    // Mastodon settings
    echo '<h2>Mastodon Settings</h2>';
    echo 'API URL: <input type="text" name="mastodon_api_url" value="' . esc_attr($mastodon_api_url) . '"><br>';
    echo 'Token: <input type="text" name="mastodon_token" value="' . esc_attr($mastodon_token) . '"><br>';

    // Notification message
    echo '<h2>Notification Message</h2>';
    echo '<textarea name="notification_message" rows="4" cols="50">' . esc_textarea($notification_message) . '</textarea><br>';

    // Submit button
    echo '<input type="submit" name="submit_settings" value="Save Settings">';
    echo '</form>';
    echo '</div>';
}

// Check post values and send notification if necessary
add_action('save_post', 'check_post_value_and_notify');

function check_post_value_and_notify($post_id) {
    // If this is an autosave, we won't send a notification
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
        return;

    $selected_user = get_option('selected_user', '');
    $post_author = get_post_field('post_author', $post_id);

    // Check if the post is from the selected user
    if ($selected_user == $post_author) {
        $post_content = get_post_field('post_content', $post_id);
        preg_match('/\[value=(\d+)\]/', $post_content, $matches);
        if (isset($matches[1])) {
            $post_value = intval($matches[1]);
            $condition = get_option('condition', '=');
            $threshold_value = get_option('threshold_value', 0);

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
                send_notification_to_mastodon(get_option('notification_message', ''));
            }
        }
    }
}

function send_notification_to_mastodon($message) {
    $mastodon_api_url = get_option('mastodon_api_url', '');
    $mastodon_token = get_option('mastodon_token', '');

    // Prepare the request
    $api_endpoint = rtrim($mastodon_api_url, '/') . '/api/v1/statuses'; // Ensure the URL is correctly formatted
    $headers = array(
        'Authorization' => 'Bearer ' . $mastodon_token,
        'Content-Type' => 'application/x-www-form-urlencoded' // Set the content type
    );
    $body = array(
        'status' => $message
    );

    // Send the request
    $response = wp_remote_post($api_endpoint, array(
        'headers' => $headers,
        'body' => http_build_query($body), // Convert the body array to a query string
        'timeout' => 15 // Set a timeout
    ));

    // Handle the response
    if (is_wp_error($response)) {
        error_log($response->get_error_message());
    } else {
        $response_body = wp_remote_retrieve_body($response);
        $status = json_decode($response_body);
        if (isset($status->url)) {
            // Successfully posted to Mastodon
            error_log("Successfully posted to Mastodon: " . $status->url);
        } elseif (isset($status->error)) {
            // There was an error posting to Mastodon
            error_log("Error posting to Mastodon: " . $status->error);
        } else {
            // Unexpected response from Mastodon
            error_log("Unexpected response from Mastodon: " . $response_body);
        }
    }
}
?>
