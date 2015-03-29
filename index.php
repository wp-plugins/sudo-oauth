<?php
/*
Plugin Name: Sudo Oauth
Plugin URI: http://id.sudo.vn
Description: Free Plugin supported connect to system id.sudo.vn - a system manager account. If you want build a system manager account for SEO, Manager staff please contact me.
Author: caotu
Version: 1.0.4
Author URI: http://sudo.vn
*/
$dir_file = dirname(__FILE__);
$service_path = substr($dir_file,0,strlen($dir_file) - 30);
require( $service_path . '/wp-load.php' );

function sudo_create_table () {
   global $wpdb;
   $table_name = $wpdb->prefix.'sudo_users';
   if($wpdb->get_var("SHOW TABLEs LIKE $table_name") != $table_name) {
      $sql = "CREATE TABLE ".$table_name."(
               use_id INTEGER(11) UNSIGNED AUTO_INCREMENT,
               use_email VARCHAR(255) NOT NULL,
               use_pass VARCHAR(255) NOT NULL,
               use_time INTEGER(11) NOT NULL,
               PRIMARY KEY (use_id)
            )";
      require_once(ABSPATH.'wp-admin/includes/upgrade.php');
      dbDelta($sql);
   }
}

register_activation_hook(__FILE__,'sudo_create_table');
?>
<?php
function register_mysettings() {
        register_setting( 'sudooauth-settings-group', 'sudooauth_option_name' );
        register_setting( 'sudooauth-settings-group', 'sudooauth_option_pwd' );
        register_setting( 'sudooauth-settings-group', 'sudooauth_option_host' );
        register_setting( 'sudooauth-settings-group', 'sudooauth_option_cat' );
}
 
function sudooauth_create_menu() {
        add_menu_page('Sudo Oauth Plugin Settings', 'Sudo Oauth Settings', 'administrator', __FILE__, 'sudooauth_settings_page',plugins_url('icon.png', __FILE__), 100);
        add_action( 'admin_init', 'register_mysettings' );
}
add_action('admin_menu', 'sudooauth_create_menu'); 
/* Tu Cao Update: Disable change Passwords & Email for Website */
if ( is_admin() )
  add_action( 'init', 'disable_password_fields', 10 );
  
function disable_password_fields() {
  if ( ! current_user_can( 'administrator' ) )
    $show_password_fields = add_filter( 'show_password_fields', '__return_false' );	
} 
add_action( 'user_profile_update_errors', 'prevent_email_change', 10, 3 );
function prevent_email_change( $errors, $update, $user ) {
    $old = get_user_by('id', $user->ID);
    if( $user->user_email != $old->user_email )
        $user->user_email = $old->user_email;
}
/* Tu Cao: End  */  

/* Restrict cat */
function sudo_restrict_save_data( $user_id ) {
	if ( ! current_user_can( 'add_users' ) )
		return false;
	update_user_meta( $user_id, '_sudo_access', get_option('sudooauth_option_cat') );
}

// check author có bị hạn chế ko ?
function sudo_is_restrict() {
	if ( get_user_meta(get_current_user_id(), '_sudo_access', true) > 0 )
			return true;
	else
			return false;
}
/* tự động lưu danh mục hạn chế cho post của author */
add_action( 'save_post', 'sudo_save_restrict_post' );
function sudo_save_restrict_post( $post_id ) {
	if ( ! wp_is_post_revision( $post_id ) && sudo_is_restrict() ){
	remove_action('save_post', 'sudo_save_restrict_post');
		wp_set_post_categories( $post_id, get_user_meta( get_current_user_id() , '_sudo_access', true) );
	add_action('save_post', 'sudo_save_restrict_post');
	}
}
/* cảnh báo */
add_action( 'edit_form_after_title', 'sudo_restrict_warning' );
function sudo_restrict_warning( $post_data = false ) {
	if (sudo_is_restrict()) {
		$c = get_user_meta( get_current_user_id() , '_sudo_access', true);
		$data = get_category($c);
		echo 'Bạn chỉ được phép đăng bài trong danh mục: <strong>'. $data->name .'</strong><br /><br />';
	}
}
/* Xóa box chọn cate */
function sudo_restrict_remove_meta_boxes() {
	if (sudo_is_restrict() )
		remove_meta_box('categorydiv', 'post', 'normal');
}
add_action( 'admin_menu', 'sudo_restrict_remove_meta_boxes' );
/* End Restrict cat */

function sudooauth_settings_page() {
?>
<div class="wrap">
<h2>Thông tin client kết nối với ID</h2>
<p>Nhập thông tin được thống nhất và cấp bởi ID</p>
<?php if( isset($_GET['settings-updated']) ) { ?>
    <div id="message" class="updated">
        <p><strong><?php _e('Settings saved.') ?></strong></p>
    </div>
<?php } ?>
<form method="post" action="options.php">
    <?php settings_fields( 'sudooauth-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
         <th scope="row">Client name</th>
         <td><input type="text" name="sudooauth_option_name" value="<?php echo get_option('sudooauth_option_name'); ?>" /></td>
        </tr>
        <tr valign="top">
         <th scope="row">Client key</th>
         <td><input type="text" name="sudooauth_option_pwd" value="<?php echo get_option('sudooauth_option_pwd'); ?>" /></td>
        </tr>
        <tr valign="top">
         <th scope="row">Host</th>
         <td><input type="text" name="sudooauth_option_host" value="<?php echo get_option('sudooauth_option_host') != '' ? get_option('sudooauth_option_host') : 'http://id.sudo.vn'; ?>" /></td>
        </tr>
        <tr valign="top">
         <th scope="row">Hạn chế danh mục post</th>
         <td>
            <?php wp_dropdown_categories(array(
                                 				'show_option_all'    => '',
                                 				'show_option_none'   => '== Không hạn chế ==',
                                 				'orderby'            => 'ID', 
                                 				'order'              => 'ASC',
                                 				'show_count'         => 0,
                                 				'hide_empty'         => 0,
                                 				'child_of'           => 0,
                                 				'exclude'            => '',
                                 				'echo'               => 1,
                                 				'selected'           => get_option('sudooauth_option_cat'),
                                 				'hierarchical'       => 0, 
                                 				'name'               => 'sudooauth_option_cat',
                                 				'id'                 => '',
                                 				'class'              => 'postform',
                                 				'depth'              => 0,
                                 				'tab_index'          => 0,
                                 				'taxonomy'           => 'category',
                                 				'hide_if_empty'      => false,
                                 				'walker'             => ''
                                 			)); ?>
         </td>
        </tr>
    </table>
    <?php submit_button(); ?>
</form>
</div>
<?php } ?>