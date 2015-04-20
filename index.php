<?php
/*
Plugin Name: Sudo Oauth
Plugin URI: http://id.sudo.vn
Description: Free Plugin supported connect to system id.sudo.vn - a system manager account. If you want build a system manager account for SEO, Manager staff please contact me.
Author: caotu
Version: 1.0.6
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
        register_setting( 'sudooauth-settings-group', 'sudooauth_option_multicat' );
        register_setting( 'sudooauth-settings-group', 'sudooauth_option_limitpost' );
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
add_filter( 'list_terms_exclusions', 'sudo_exclusions_terms' );
function sudo_exclusions_terms() {
   $excluded = '';
   $current_user = wp_get_current_user();
   if(strpos($current_user->user_email,'@sudo.vn')) {
      $multicat_settings = get_option('sudooauth_option_multicat');
      if ( $multicat_settings != false ) {
         $str_cat_list = '';
         foreach($multicat_settings as $value) {
            $str_cat_list .= $value.',';
         }
         $str_cat_list = rtrim($str_cat_list,',');
         $excluded = " AND ( t.term_id IN ( $str_cat_list ) OR tt.taxonomy NOT IN ( 'category' ) )";
      }
   }
   return $excluded;
}
/* End Restrict cat */

/* One post per day */
add_action( 'admin_init', 'sudo_post_per_day_limit' );
function sudo_post_per_day_limit() {
   $current_user = wp_get_current_user();
   if(strpos($current_user->user_email,'@sudo.vn')) {
      global $wpdb;
      $tz = new DateTimeZone('Asia/Bangkok');
      $time_current_sv = new SudoDateTime();
      $time_current_sv_str = $time_current_sv->format('Y-m-d H:i:s');
      $time_current_sv_int = $time_current_sv->getTimestamp();
      
      $time_current_sv->setTimeZone($tz);
      $time_current_tz_str = $time_current_sv->format('Y-m-d H:i:s');
      $time_current_tz = new SudoDateTime($time_current_tz_str);
      $time_current_tz_int = $time_current_tz->getTimestamp();
      
      $time_start_tz_str = $time_current_sv->format('Y-m-d 00:00:01');
      $time_start_tz = new SudoDateTime($time_start_tz_str);
      $time_start_tz_int = $time_start_tz->getTimestamp();
      
      $time_start_sv_int = $time_current_sv_int - $time_current_tz_int + $time_start_tz_int;
      $time_start_sv_str = date('Y-m-d H:i:s',$time_start_sv_int);
      $time_start_sv = new SudoDateTime($time_start_sv_str);
      
      $count_post_today = $wpdb->get_var("SELECT COUNT(ID)
                                          FROM $wpdb->posts 
                                          WHERE post_status = 'publish'
                                          AND post_author = $current_user->ID 
                                          AND post_type NOT IN('attachment','revision')
                                          AND post_date_gmt >= '$time_start_sv_str'");
                                          
      if($count_post_today >= get_option('sudooauth_option_limitpost',1)) {
         global $pagenow;
         /* Check current admin page. */
         if($pagenow == 'post-new.php'){
            echo '<meta http-equiv="Content-Type" content="text/html"; charset="utf-8">';
            echo "<center>";
            echo '<br /><br />Giới hạn '.get_option('sudooauth_option_limitpost',1).' bài 1 ngày.<br /><br /> Hôm nay bạn đã đăng đủ bài trên trang này rồi.<br /><br /> Vui lòng quay lại vào ngày mai, xin cám ơn!';
            echo "</center>";
            exit();
         }
      }
   }
}
/* End One post per day */

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
         <th scope="row">Tài khoản kết nối được đăng bao nhiêu bài một ngày</th>
         <td><input type="text" name="sudooauth_option_limitpost" value="<?php echo get_option('sudooauth_option_limitpost') != '' ? get_option('sudooauth_option_limitpost') : '1'; ?>" /></td>
        </tr>
        <tr valign="top">
         <th scope="row">Chọn danh mục tài khoản kết nối được phép post bài</th>
         <td>
         <?php
         $walker = new Sudo_Walker_Category_Checklist();
         $settings = get_option('sudooauth_option_multicat');
         if ( isset( $settings) && is_array( $settings) )
				$selected = $settings;
			else
				$selected = array();
         ?>
            <div id="side-sortables" class="metabox-holder" style="float:left; padding:5px;">
   				<div class="postbox">
   					<h3 class="hndle"><span>Giới hạn đa danh mục</span></h3>
   
   	            <div class="inside" style="padding:0 10px;">
   						<div class="taxonomydiv">
   							<div id="id-all" class="tabs-panel tabs-panel-active">
   								<ul class="categorychecklist form-no-clear">
   								<?php
   									wp_list_categories(
   										array(
   										'selected_cats'  => $selected,
   										'options_name'   => 'sudooauth_option_multicat',
   										'hide_empty'     => 0,
   										'title_li'       => '',
   										'walker'         => $walker
   										)
   									);
   								?>
   	                     </ul>
   							</div>
   						</div>
   					</div>
   				</div>
   			</div>
         </td>
        </tr>
    </table>
    <?php submit_button(); ?>
</form>
</div>
<?php 
} 


class Sudo_Walker_Category_Checklist extends Walker {
	var $tree_type = 'category';
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this

	function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent<ul class='children'>\n";
	}

	function end_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	function start_el( &$output, $category, $depth = 0, $args = array(), $current_object_id = 0 ) {
		extract($args);

		if ( empty( $taxonomy ) )
			$taxonomy = 'category';

		$output .= sprintf(
			'<li id="category-%1$d"><label class="selectit"><input value="%1$s" type="checkbox" name="sudooauth_option_multicat[]" %2$s /> %3$s</label>',
			$category->term_id,
			checked( in_array( $category->term_id, $selected_cats ), true, false ),
			esc_html( apply_filters( 'the_category', $category->name ) )
		);
	}

	function end_el( &$output, $category, $depth = 0, $args= array() ) {
		$output .= "</li>\n";
	}
}

//Sudo replace datetime for php version lower 5.3
class SudoDateTime extends DateTime
{
    public function setTimestamp( $timestamp )
    {
        $date = getdate( ( int ) $timestamp );
        $this->setDate( $date['year'] , $date['mon'] , $date['mday'] );
        $this->setTime( $date['hours'] , $date['minutes'] , $date['seconds'] );
    }

    public function getTimestamp()
    {
        return $this->format( 'U' );
    }
}
?>