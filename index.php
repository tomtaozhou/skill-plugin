<?php
/*
Plugin Name: SKILL Plugin
Plugin URI: http://your_plugin_uri_here
Description: Control and receive notifications for Ubi-House Smart Home Devices based on post values.
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

//添加联动界面
function skill_add_linkage_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.')));
    }

    // 检查是否有提交
    if (isset($_POST['set_linkage'])) {
        update_option('linkage_device', sanitize_text_field($_POST['device_name']));
        update_option('linkage_threshold_greater', intval($_POST['threshold_greater']));
        update_option('linkage_threshold_less', intval($_POST['threshold_less']));
        echo '<div class="updated"><p>Linkage updated successfully!</p></div>';
    }

    // 获取已保存的联动设备和阈值
    $saved_device_name = get_option('linkage_device', '');
    $saved_threshold_greater = get_option('linkage_threshold_greater', '');
    $saved_threshold_less = get_option('linkage_threshold_less', '');

    ?>
    <div class="wrap">
        <h1>Add Linkage</h1>
        
        <form method="post" action="">
            <label for="device_name">Device Name:</label>
            <input type="text" name="device_name" id="device_name" value="<?php echo esc_attr($saved_device_name); ?>" required>
            
            <label for="threshold_greater">Threshold (Greater than or equal to):</label>
            <input type="number" name="threshold_greater" id="threshold_greater" value="<?php echo esc_attr($saved_threshold_greater); ?>" required>
            
            <label for="threshold_less">Threshold (Less than):</label>
            <input type="number" name="threshold_less" id="threshold_less" value="<?php echo esc_attr($saved_threshold_less); ?>" required>
            
            <input type="submit" name="set_linkage" value="Set Linkage">
        </form>
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

// 检查post的数值并进行联动
add_action('save_post', 'check_post_value_for_linkage');

function check_post_value_for_linkage($post_id) {
    // 如果这是一个自动保存的版本，我们不会发送通知
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
        return;

    // 获取post内容
    $post_content = get_post_field('post_content', $post_id);

    // 这里我们假设数值是在post内容中的某个特定格式，例如[value=123]，你可以根据实际情况进行修改
    preg_match('/\[value=(\d+)\]/', $post_content, $matches);
    if (isset($matches[1])) {
        $post_value = intval($matches[1]);

        // 获取联动设定的阈值
        $threshold_greater = get_option('linkage_threshold_greater', 0);
        $threshold_less = get_option('linkage_threshold_less', PHP_INT_MAX);

        // 检查数值是否满足联动条件
        if ($post_value >= $threshold_greater && $post_value < $threshold_less) {
            // 这里执行联动操作，例如发送ActivityPub通知到Mastodon账号
            send_notification_to_mastodon("The value in post ID {$post_id} has reached the linkage threshold.");
        }
    }
}

function send_notification_to_mastodon($message) {
    $mastodon_api_url = get_option('mastodon_api_url', '');
    $mastodon_token = get_option('mastodon_token', '');

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
