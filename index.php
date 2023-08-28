<?php
/*
Plugin Name: SKILL Plugin
Plugin URI: http://your_plugin_uri_here
Description: Control and receive notifications for Ubi-House Smart Home Devices.
Author: Tao Zhou
Author URI: http://your_author_uri_here
Version: 1.0
*/

// 创建插件菜单项
function skill_plugin_menu() {
    add_menu_page('SKILL Control Panel', 'SKILL Control', 'manage_options', 'skill-main', 'skill_plugin_main_page', 'dashicons-smartphone', 90);
    add_submenu_page('skill-main', 'Ubi-House Device Settings', 'Device Settings', 'manage_options', 'ubi-house-device-settings', 'ubi_house_device_settings_page');
    add_submenu_page('skill-main', 'Add Linkage', 'Add Linkage', 'manage_options', 'skill-add-linkage', 'skill_add_linkage_page');
}
add_action('admin_menu', 'skill_plugin_menu');

// 插件主页
function skill_plugin_main_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    echo '<h1>Welcome to SKILL Control Panel</h1>';
}

// Ubi-House设备设置页面
function ubi_house_device_settings_page() {
    if (isset($_POST['add_device'])) {
        $userdata = array(
            'user_login' => $_POST['device_name'],
            'user_pass'  => wp_generate_password(),
            'role'       => 'ubi_house_device'
        );
        $user_id = wp_insert_user($userdata);

        if (!is_wp_error($user_id)) {
            update_user_meta($user_id, 'device_api_key', $_POST['api_key']);
        }
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1>Ubi-House Smart Home Device Settings</h1>
        
        <h2>Add Device</h2>
        <form method="post" action="">
            <input type="text" name="device_name" placeholder="Device Name" required>
            <input type="text" name="api_key" placeholder="API Key" required>
            <input type="submit" name="add_device" value="Add Device">
        </form>

        <h2>Existing Devices</h2>
        <?php
        $users = get_users(array('role' => 'ubi_house_device'));
        foreach ($users as $user) {
            echo '<p>' . esc_html($user->user_login) . ' | API Key: ' . esc_html(get_user_meta($user->ID, 'device_api_key', true)) . '</p>';
        }
        ?>
        
        <h2>Mastodon Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('ubi_house_device_settings_group');
            do_settings_sections('ubi-house-device-settings');
            submit_button();
            ?>
        </form>
        <p>Current Mastodon API URL: <?php echo esc_attr(get_option('mastodon_api_url', '')); ?></p>
        <p>Current Mastodon Token: <?php echo str_repeat('*', strlen(esc_attr(get_option('mastodon_token', '')))); ?></p>
    </div>
    <?php
}


// 添加联动页面
function skill_add_linkage_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1>Add Linkage</h1>
        <!-- 表单、联动规则等内容可以在这里定义 -->
    </div>
    <?php
}

// Mastodon API相关设置
function skill_plugin_settings_init() {
    register_setting('ubi_house_device_settings_group', 'mastodon_api_url');
    register_setting('ubi_house_device_settings_group', 'mastodon_token');

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

// REST API端点来处理设备数据
add_action('rest_api_init', function () {
    register_rest_route('skill/v1', '/handle_data/', array(
        'methods' => 'POST',
        'callback' => 'skill_handle_device_data',
        'permission_callback' => '__return_true' 
    ));
});

function skill_handle_device_data(WP_REST_Request $request) {
    $received_data = $request->get_param('data');
    $device_name = $request->get_param('device_name'); 

    if (is_null($received_data) || is_null($device_name)) {
        return new WP_Error('no_data', 'No data or device name received', array('status' => 400));
    }

    $user = get_user_by('login', $device_name);
    if (!$user) {
        return new WP_Error('no_device', 'Device not found', array('status' => 400));
    }

    $post_data = array(
        'post_title'    => 'Data from ' . $device_name,
        'post_content'  => json_encode($received_data),
        'post_status'   => 'publish',
        'post_author'   => $user->ID,
        'post_category' => array(8,39) // 需要的话，可以更改或删除这一行
    );

    // 使用wp_insert_post()插入新的post
    $post_id = wp_insert_post($post_data);

    if ($post_id == 0) {
        return new WP_Error('insert_error', 'Failed to insert post', array('status' => 500));
    }

    return new WP_REST_Response('Data saved successfully', 200);
}

function skill_send_command_to_device($device_id, $command) {
    $api_url = get_post_meta($device_id, 'skill_device_api_url', true);
    $token = get_post_meta($device_id, 'skill_device_token', true);

    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'body' => array(
            'command' => $command,
        ),
    ));

    if (is_wp_error($response)) {
        error_log($response->get_error_message());
    }
}

// 当插件被激活时创建一个新的用户角色
function skill_plugin_activation() {
    add_role('ubi_house_device', 'Ubi House Device', array(
        'read' => true,
        'edit_posts' => true,
        'publish_posts' => true,
        'delete_posts' => false,
    ));
}
register_activation_hook(__FILE__, 'skill_plugin_activation');

// 当插件被停用时删除该用户角色
function skill_plugin_deactivation() {
    remove_role('ubi_house_device');
}
register_deactivation_hook(__FILE__, 'skill_plugin_deactivation');
?>
