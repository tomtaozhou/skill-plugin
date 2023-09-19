<?php
/*
Plugin Name: SKILL Plugin
Plugin URI: http://your_plugin_uri_here
Description: Control and send notifications to ActivityPub devices based on post values from selected user.
Author: Tao Zhou
Author URI: http://your_author_uri_here
Version: 1.0
*/

 if ( ! defined( 'ABSPATH' ) ){
     die;
 };
 
 function my_admin_notices() {
     global $pagenow;
     // 仅在插件设置页面生成通知
     if ($pagenow == 'options-general.php' && $_GET['page'] == 'linkplugin') {
         $plugins_required = array(
             array(
                 'path' => 'activitypub/activitypub.php', 
                 'url' => 'https://wordpress.org/plugins/activitypub/',
                 'name' => 'ActivityPub'
             ),
             array(
                 'path' => 'friends/friends.php', 
                 'url' => 'https://wordpress.org/plugins/friends/',
                 'name' => 'Friends'
             ),
         );
 
         include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
 
         foreach ($plugins_required as $plugin) {
             if (!file_exists(WP_PLUGIN_DIR.'/'.$plugin['path'])) {
                 echo '<div class="notice notice-warning is-dismissible">
                     <p>'.$plugin['name'].' The plugin is not installed! Please <a href="'. admin_url('plugin-install.php?tab=search&type=term&s=' . urlencode($plugin['name'])) .'" >click here</a> to download, install, and enable it.</p>
                 </div>';
             } elseif (!is_plugin_active($plugin['path'])) {
                 echo '<div class="notice notice-warning is-dismissible">
                     <p>'.$plugin['name'].' Plugin is installed but not enabled! Please go to <a href="'.admin_url('plugins.php').'">Plugin page</a> to enable it.</p>
                 </div>';
             }
         }
     }
 }
 add_action( 'admin_notices', 'my_admin_notices' );
 
 function handle_linkplugin_delete_user() {
     if (!isset($_GET['user_id'])) {
         wp_die('Missing user_id GET variable.');
     }
 
     $user_id = intval($_GET['user_id']);
 
     if (wp_delete_user($user_id)) {
         // 删除用户成功，存储成功消息到transients
         set_transient('linkplugin_delete_user_message', 'User deleted successfully.', 60);
     } else {
         // 删除用户失败，存储错误消息到transients
         set_transient('linkplugin_delete_user_error', 'Error deleting user.', 60);
     }
 
     // 重定向回原页面
     wp_redirect($_SERVER['HTTP_REFERER']);
     exit;
 }
 add_action('admin_post_linkplugin_delete_user', 'handle_linkplugin_delete_user');
 
 // 创建一个新的插件设置页面
 function linkplugin_settings_page() {
     add_options_page('UPOD Plugin Settings', 'UPOD Plugin', 'manage_options', 'linkplugin', 'linkplugin_settings_page_content');
 }
 add_action('admin_menu', 'linkplugin_settings_page');
 
 // 输出设置页面的内容
 function linkplugin_settings_page_content() {
     // 检查用户是否已经登录
     if (!is_user_logged_in()) {
         return;
     }
 
     if (isset($_GET['mastodon-settings-saved'])) {
        echo '<div class="updated notice"><p>Mastodon settings saved successfully.</p></div>';
    }
    
       // 显示transients中的消息
       if ($message = get_transient('linkplugin_create_user_message')) {
         echo '<div class="updated notice"><p>' . $message . '</p></div>';
         delete_transient('linkplugin_create_user_message');
     }
 
     if ($error = get_transient('linkplugin_create_user_error')) {
         echo '<div class="error notice"><p>' . $error . '</p></div>';
         delete_transient('linkplugin_create_user_error');
     }
     // 显示用户列表
      $users = get_users();
 
  // 分类用户
  $users_without_email = array_filter($users, function($user) {
    return empty($user->user_email);
});
$users_with_app = array_filter($users, function($user) {
    return !empty($user->user_email) && !empty(get_user_meta($user->ID, '_application_passwords', true));
});
$users_without_app = array_filter($users, function($user) {
    return !empty($user->user_email) && empty(get_user_meta($user->ID, '_application_passwords', true));
});

     // 显示设置表单
     ?>
     <div class="wrap">
         <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
         <form action="options.php" method="post">
             <?php
             // 输出安全字段，用于安全检查
             settings_fields('linkplugin');
             // 输出设置字段
             do_settings_sections('linkplugin');
             // 输出保存设置的按钮
             submit_button('Save Changes');
             ?>
             <div class="wrap">
         <h1>Friends Plugin Settings</h1>
 
         <button id="myButton">Click to Add New Friends Page</button>
         <button id="myButton2">Click to Friends&Request Page</button>
     </div>
 
     <script>
     jQuery(document).ready(function($) {
         var myButton = $('#myButton');
         var myButton2 = $('#myButton2');
 
         myButton.on('click', function(event) {
             event.preventDefault();
 
             // 跳转到"add-friend"页面
             window.location.href = '<?php echo admin_url("admin.php?page=add-friend"); ?>';
         });
 
         myButton2.on('click', function(event) {
             event.preventDefault();
 
             // 跳转到"friends-list"页面
             window.location.href = '<?php echo admin_url("admin.php?page=friends-list"); ?>';
         });
     });
     </script>
         </form>
     </div>
     <div style="display: flex; justify-content: space-between;">
    <div>
    <h2>New virtual role</h2>
    <form method="POST">
        <input type="hidden" name="linkplugin_create_user" value="1">
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="username">Username</label></th>
                <td><input name="username" type="text" id="username" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="useremail">Email</label></th>
                <td><input name="useremail" type="email" id="useremail" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="userpass">Password</label></th>
                <td><input name="userpass" type="password" id="userpass" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="new_userrole">Role</label></th>
                <td>
                    <select name="new_userrole" id="new_userrole" required>
                        <?php 
                            $roles = get_editable_roles(); // 获取所有可编辑的用户角色
                            foreach ($roles as $role_name => $role_info): 
                        ?>
                            <option value="<?php echo esc_attr($role_name); ?>">
                                <?php echo esc_html($role_info['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button('Add virtual role'); ?>
    </form>
    </div>
    <div>
    <h2>New UPOD connection account</h2>
    <form method="POST">
        <input type="hidden" name="linkplugin_create_user_with_password" value="1">
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="username_pw">Username</label></th>
                <td><input name="username_pw" type="text" id="username_pw" class="regular-text" required /></td>
            </tr>
			 <tr>
                <th scope="row"><label for="email_pw">Email</label></th>
                <td><input name="email_pw" type="email" id="email_pw" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="password_pw">Password</label></th>
                <td><input name="password_pw" type="password" id="password_pw" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="app_name">UPOD API Name</label></th>
                <td><input name="app_name" type="text" id="app_name" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="new_userrole">Role</label></th>
                <td>
                    <select name="new_userrole" id="new_userrole" required>
                        <?php 
                            $roles = get_editable_roles(); // 获取所有可编辑的用户角色
                            foreach ($roles as $role_name => $role_info): 
                        ?>
                            <option value="<?php echo esc_attr($role_name); ?>">
                                <?php echo esc_html($role_info['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button('Create UPOD connection account'); ?>
    </form>
    </div>
   </div>
   <div class="linkplugin-user-lists">
         <div class="linkplugin-user-list">
         <div class="linkplugin-user-list">
         <div class="linkplugin-user-list">
         <div>
         <h2>Mastodon Alert Settings</h2>
<form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <input type="hidden" name="action" value="save_mastodon_settings">
    <!-- Trigger Value Setting -->
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="mastodon_trigger_value">Trigger Value</label></th>
            <td><input name="mastodon_trigger_value" type="text" id="mastodon_trigger_value" class="regular-text" required /></td>
        </tr>

        <!-- Mastodon Account Setting -->
        <tr>
            <th scope="row"><label for="mastodon_account">Mastodon Account</label></th>
            <td><input name="mastodon_account" type="text" id="mastodon_account" class="regular-text" required /></td>
        </tr>

        <!-- Toot Content Setting -->
        <tr>
            <th scope="row"><label for="mastodon_toot_content">Toot Content</label></th>
            <td><textarea name="mastodon_toot_content" id="mastodon_toot_content" class="regular-text" required></textarea></td>
        </tr>
    </table>
    <?php submit_button('Save Mastodon Settings'); ?>
</form>
</div>
         <h2>IoT Users</h2>
             <?php linkplugin_show_user_table($users_with_app); ?>
         </div>  
             <h2>Robot Users</h2>
             <?php linkplugin_show_user_table($users_without_email); ?>
         </div>
             <h2>Virtual Roles</h2>
             <?php linkplugin_show_user_table($users_without_app); ?>
         </div>
     </div>
     <?php
 }
     // 显示用户表格
 function linkplugin_show_user_table($users){
     ?>
     <table class="wp-list-table widefat fixed striped users">
         <thead>
             <tr>
                 <th scope="col" class="manage-column column-name">Username</th>
                 <th scope="col" class="manage-column column-email">Email</th>
                 <th scope="col" class="manage-column column-actions">Actions</th>
             </tr>
         </thead>
         <tbody>
             <?php foreach ($users as $user) : ?>
                 <tr>
                     <td><?php echo $user->user_login; ?></td>
                     <td><?php echo $user->user_email; ?></td>
                     <td><a href="<?php echo esc_url(admin_url('admin-post.php?action=linkplugin_delete_user&user_id=' . $user->ID)); ?>" onclick="return confirm('Are you sure?')">Delete</a></td>
                 </tr>
             <?php endforeach; ?>
         </tbody>
     </table>
     <?php
 }
 
 // 注册插件设置
 function linkplugin_settings_init() {
     // Register a new setting
     register_setting('linkplugin', 'linkplugin_author_replacements_old', array('default' => array()));
     register_setting('linkplugin', 'linkplugin_author_replacements_new', array('default' => array()));
 
     // 在linkplugin设置页面添加一个新的设置区段
     add_settings_section(
         'linkplugin_section',
         'Link',
		 '',
         'linkplugin'
     );
 
     // 在linkplugin设置区段添加一个新的设置字段
     add_settings_field(
         'linkplugin_field',
         '',
         'linkplugin_field_callback',
         'linkplugin',
         'linkplugin_section'
     );
 }
 
 function get_user_id_dropdown($name, $selected_value, $users, $group = 0) {
    $html = '<select name="' . $name . '">'; 
    $html .= '<option value=""></option>'; 
    foreach ($users as $user) {
        $email = $user->user_email;
        $isHasEmail = empty($email);

        $appPassword = get_user_meta($user->ID, '_application_passwords', true);
        $isHasApp = !empty($appPassword);

        $appPassword = get_user_meta($user->ID, '_application_passwords', true);
        $isHasnoApp = empty($appPassword);

        if ($group === 0) {  // 第一组：有电子邮件并且有应用程序的用户，或者没有电子邮件的用户
            if (!$isHasEmail && !$isHasApp && $isHasnoApp) {  // 如果用户有电子邮件但是没有应用程序，跳过这个用户
                continue;
            }
        } else{  // 第二组：有电子邮件但是没有应用程序的用户
            if ($isHasEmail || $isHasApp && !$isHasnoApp) {  // 如果用户没有电子邮件，或者有应用程序，跳过这个用户
                continue;
            }
        }

        $selected = $user->ID == $selected_value ? ' selected' : '';
        $html .= '<option value="' . $user->ID . '" data-email="' . $user->user_email . '"' . $selected . '>' . $user->user_login . '</option>';
    }
    $html .= '</select>';
    return $html;
}

add_action('admin_init', 'linkplugin_settings_init');

 // 输出设置字段的内容
 function linkplugin_field_callback() {
       // 添加包裹设置字段内容的 div 元素，并应用左边距样式
       echo '<div style="margin-left: -225px;">';
       echo '<div style="margin-top: -25px;">';
     // 获取当前的设置值
     $old_option = (array) get_option('linkplugin_author_replacements_old', array());
     $new_option = (array) get_option('linkplugin_author_replacements_new', array());
     // 获取所有用户
     $users = get_users();
     $count = max(count($old_option), count($new_option));
	 $old_count = (is_array($old_option) || $old_option instanceof Countable) ? count($old_option) : 0;
$new_count = (is_array($new_option) || $new_option instanceof Countable) ? count($new_option) : 0;
$count = max($old_count, $new_count);
     // 如果没有设置任何替换规则，就不显示任何下拉菜单
     if ($count == 0) {
         echo '<p id="linkplugin_no_replacements">No replacements set. Click "Add New Link" to add one.</p>';
     } else {
         for ($i = 0; $i < $count; $i++) {
             $old_id = $old_option[$i] ?? '';
             $new_id = $new_option[$i] ?? '';
             echo '<p>';
             echo get_user_id_dropdown('linkplugin_author_replacements_old[]', $old_id, $users);
             echo ' => ';
             echo get_user_id_dropdown('linkplugin_author_replacements_new[]', $new_id, $users, true);            
             echo ' <button type="button" onclick="linkplugin_remove_replacement(this)">Delete</button></p>';
         }
         
     }

     echo '<p><button type="button" onclick="linkplugin_add_replacement()">Add New User Connection</button></p>';
 
     echo '<script>
     function linkplugin_remove_replacement(button) {
         var p = button.parentNode;
         p.parentNode.removeChild(p);
         if (document.querySelectorAll(\'[name="linkplugin_author_replacements_old[]"]\').length == 0) {
             var no_replacements = document.getElementById("linkplugin_no_replacements");
             if (!no_replacements) {
                 var new_p = document.createElement("p");
                 new_p.id = "linkplugin_no_replacements";
                 new_p.innerText = "No replacements set. Click \\"Add replacement\\" to add one.";
                 button.parentNode.parentNode.insertBefore(new_p, button.parentNode);
             }
         }
     }
     function linkplugin_add_replacement() {
        var p = document.createElement("p");
        var old_dropdown = \'' . str_replace("'", "\'", get_user_id_dropdown('linkplugin_author_replacements_old[]', '', $users)) . '\';
        var new_dropdown = \'' . str_replace("'", "\'", get_user_id_dropdown('linkplugin_author_replacements_new[]', '', $users, true)) . '\';
        p.innerHTML = old_dropdown + " => " + new_dropdown + \' <button type="button" onclick="linkplugin_remove_replacement(this)">Remove</button>\';
        var add_button = document.querySelector("button[onclick=\'linkplugin_add_replacement()\']");
        add_button.parentNode.insertBefore(p, add_button);
        var no_replacements = document.getElementById("linkplugin_no_replacements");
        if (no_replacements) {
            no_replacements.parentNode.removeChild(no_replacements);
        }
        // Group and sort options in the dropdown menus
        var selectElements = p.querySelectorAll(\'select\');
        selectElements.forEach(function(select) {
            var optionsArray = Array.prototype.slice.call(select.options);
            optionsArray.sort(function(a, b) {
                return a.dataset.url.localeCompare(b.dataset.url) || a.text.localeCompare(b.text);
            });
            optionsArray.forEach(function(option) {
                select.appendChild(option);
            });
        });
    }
    
    </script>';
}  
 
 // 在复制文章时使用设置的替换规则
 function my_copy_posts_plugin_copy_cached_post( $post_id ) {
    $post = get_post( $post_id );

    // 获取设置的作者ID替换规则
    $old_option = get_option('linkplugin_author_replacements_old', array());
    $new_option = get_option('linkplugin_author_replacements_new', array());
    
  // 检查文章类型
$accepted_post_types = array('friend_post_cache', 'post');
if ( !in_array($post->post_type, $accepted_post_types) ) {
    return;
}

    // 获取原始作者ID
    $original_author_id = $post->post_author;

    // 获取原先文章的作者
    $original_author = get_user_by('id', $original_author_id);
    
    // 检查原始作者ID是否在$old_option中，如果在，则替换为相应的新作者ID
    if ( in_array( $original_author_id, $old_option ) ) {
        $indices = array_keys($old_option, $original_author_id); // 获取所有匹配项的索引
        
        foreach($indices as $index){
            $new_author_id = $new_option[$index]; // 根据每个索引，获取新作者ID
            
            // 获取用户对象
            $new_author = get_user_by( 'id', $new_author_id );
            
            // 如果用户不存在，则继续下一个循环
            if ( ! $new_author ) {
                continue;
            }
    
            // 复制文章
            $new_post = array(
                'post_title'   => $original_author->user_login, // 将新文章的标题设为原文章的作者的用户名
                'post_content' => "<!-- wp:paragraph {\"className\":\"only-friends\"} -->\n<p class=\"only-friends\">" . $post->post_content . "</p>\n<!-- /wp:paragraph -->", // 包装文章内容到一个带有"only-friends"类的段落块中
                'post_status'  => 'publish',
                'post_type'    => 'post',
                'post_author'  => $new_author->ID, // 设置指定的用户为新作者
            );
            $new_post_id = wp_insert_post( $new_post );
        }
    }
}
add_action( 'save_post', 'my_copy_posts_plugin_copy_cached_post', 10, 1 );

// 处理表单提交
add_action( 'admin_init', function() {
   // 检查是否提交了普通用户创建表单
if ( isset( $_POST['linkplugin_create_user'] ) ) {
    $username = sanitize_text_field( $_POST['username'] );
    $password = $_POST['password']; 
    $email = sanitize_email( $_POST['email'] );
    $role = sanitize_text_field( $_POST['new_userrole'] );

    // 创建用户
    $user_data = array(
        'user_login' => $username,
        'user_pass'  => $password,
        'user_email' => $email,
        'role'       => $role, // 添加用户角色
    );

    $user_id = wp_insert_user( $user_data );
    if ( is_wp_error( $user_id ) ) {
        wp_die( $user_id->get_error_message() );
    }
}

   // 检查是否提交了创建用户并生成应用程序密码的表单
if ( isset( $_POST['linkplugin_create_user_with_password'] ) ) {
    $username_pw = sanitize_text_field( $_POST['username_pw'] );
    $password_pw = $_POST['password_pw']; 
    $email_pw = sanitize_email( $_POST['email_pw'] );
    $app_name = sanitize_text_field( $_POST['app_name'] );
    $role = sanitize_text_field( $_POST['new_userrole'] ); // 获取提交的角色信息

    // 创建用户
    $userdata = array(
        'user_login' => $username_pw,
        'user_pass'  => $password_pw,
        'user_email' => $email_pw,
        'role'       => $role // 设定用户角色
    );
    $user_id_pw = wp_insert_user( $userdata ); // 使用wp_insert_user替代wp_create_user

    if ( is_wp_error( $user_id_pw ) ) {
        wp_die( $user_id_pw->get_error_message() );
    }
}

// 创建应用程序密码
$app_password = WP_Application_Passwords::create_new_application_password( $user_id_pw, array( 'name' => $app_name ) );

// 显示应用程序密码
if ( !is_wp_error( $app_password ) ) { // Check if NOT an error
    add_action( 'admin_notices', function() use ( $app_password, $username_pw ) {  // 将 $username_pw 传入到回调函数中
        ?>
        <div class="notice notice-success is-dismissible">
            <p>Your new password for <?php echo esc_html( $username_pw ); ?> is:  <?php echo esc_html( implode( ' ', str_split( $app_password[0], 4 ) ) ); ?></p>
            <p>Be sure to save this in a safe location. You will not be able to retrieve it.</p>
        </div>
        <?php
    } );
}
});
function handle_mastodon_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    update_option('mastodon_trigger_value', $_POST['mastodon_trigger_value']);
    update_option('mastodon_account', $_POST['mastodon_account']);
    update_option('mastodon_toot_content', $_POST['mastodon_toot_content']);
    
    $redirect_url = add_query_arg('mastodon-settings-saved', 'true', wp_get_referer());
    wp_safe_redirect($redirect_url);
    exit;
}

add_action('admin_post_save_mastodon_settings', 'handle_mastodon_settings');
