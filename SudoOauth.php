<?php
$dir_file = dirname(__FILE__);
$service_path = substr($dir_file,0,strlen($dir_file) - 30);
require( $service_path . '/wp-load.php' );
$plugin_url = plugins_url();
$host_name = substr($plugin_url,0,strlen($plugin_url) - 19);
$client_id = get_option('sudooauth_option_name');
$client_key = get_option('sudooauth_option_pwd');
$host_id = get_option('sudooauth_option_host');
if(!$client_id || !$client_key || $client_id == '' || $client_key == '')
   die('Bạn chưa nhập thông tin Client mà ID đã cấp !');
?>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<?php
$access_code = $_REQUEST['access_code'];
if(isset($access_code) && $access_code != '') {
   $token_url = $host_id.'/oauth/accessCode/'.$access_code.'';
   $context = stream_context_create(array(
       'http' => array(
           'header'  => "Authorization: Basic " . base64_encode("$client_id:$client_key")
       )
   ));
   $data = file_get_contents($token_url, false, $context);
   $info = json_decode(base64_decode($data),true);
   if($info['status'] == 1) {
      global $wpdb;
      $user = array();
      $user['email'] = $info['user']['email'];
      $user['name'] = substr($user['email'],0,strpos($user['email'],'@'));
      $user['email'] = $user['name'].'@sudo.vn';
      
      $check_user = $wpdb->get_results('SELECT ID FROM '.$wpdb->prefix.'users WHERE user_email = "'.$user['email'].'"',ARRAY_A);
      if($check_user) {
         $check_sudo_user = $wpdb->query('SELECT use_id FROM '.$wpdb->prefix.'sudo_users WHERE use_email = "'.$user['email'].'"');
         if($check_sudo_user) {
            //Update _sudo_access
            $user_sudo_access = get_user_meta($check_user[0]['ID'],'_sudo_access');
            if(is_array($user_sudo_access)) $user_sudo_access = $user_sudo_access[0];
            if($user_sudo_access != get_option('sudooauth_option_cat')) {
               if( update_user_meta( $check_user[0]['ID'], '_sudo_access', get_option('sudooauth_option_cat') ) != false) {
                  $sudo_user = $wpdb->get_row('SELECT use_id,use_pass FROM '.$wpdb->prefix.'sudo_users WHERE use_email = "'.$user['email'].'" ORDER BY use_id DESC LIMIT 1',ARRAY_A);
                  $user['password'] = md5($sudo_user['use_pass'].$info['user']['id']);
                  $str = "<form action='".$host_name."/wp-login.php' method='post' name='frm'>";
                  $str .= "<input type='hidden' name='log' value='".$user['name']."'>";
                  $str .= "<input type='hidden' name='pwd' value='".$user['password']."'>";
                  $str .= "<input type='hidden' name='wp-submit' value='Log In'>";
                  $str .= "<input type='hidden' name='redirect_to' value='".admin_url()."post-new.php'>";
                  $str .= "</form>";
                  $str .= '<script language="JavaScript">document.frm.submit();</script>';
                  echo $str;
               }else {
                  die('Không thể hạn chế được danh mục đăng bài cho thành viên này');
               }
            }else {
               $sudo_user = $wpdb->get_row('SELECT use_id,use_pass FROM '.$wpdb->prefix.'sudo_users WHERE use_email = "'.$user['email'].'" ORDER BY use_id DESC LIMIT 1',ARRAY_A);
               $user['password'] = md5($sudo_user['use_pass'].$info['user']['id']);
               $str = "<form action='".$host_name."/wp-login.php' method='post' name='frm'>";
               $str .= "<input type='hidden' name='log' value='".$user['name']."'>";
               $str .= "<input type='hidden' name='pwd' value='".$user['password']."'>";
               $str .= "<input type='hidden' name='wp-submit' value='Log In'>";
               $str .= "<input type='hidden' name='redirect_to' value='".admin_url()."post-new.php'>";
               $str .= "</form>";
               $str .= '<script language="JavaScript">document.frm.submit();</script>';
               echo $str;
            } 
         }else {
            die('Tài khoản này đã có trước khi kết nối với Sudo ID !');
         }
      }else {
         $sudo_pass = rand(111111,999999);
         $user['password'] = md5($sudo_pass.$info['user']['id']);
         $u_id = wp_create_user($user['name'],$user['password'],$user['email']);
         if(is_object($u_id)) {
            $err = $u_id->errors;
            $existing_user_email = $err['existing_user_email'][0];
            $existing_user_login = $err['existing_user_login'][0];
            echo $existing_user_email.'-'.$existing_user_login;die;
         }else {
            //Update _sudo_access
            if( update_user_meta( $u_id, '_sudo_access', get_option('sudooauth_option_cat') ) != false) {
               $wpdb->update( 
               	''.$table_prefix.'usermeta', 
               	array( 
               		'meta_value' => 'a:1:{s:6:"author";b:1;}',	// string
               	), 
               	array( 'user_id' => $u_id, 'meta_key' => ''.$table_prefix.'capabilities' ), 
               	array( 
               		'%s'
               	), 
               	array( '%d', '%s' ) 
               );
               $wpdb->update( 
               	''.$table_prefix.'usermeta', 
               	array( 
               		'meta_value' => '2'	// integer (number) 
               	), 
               	array( 'user_id' => $u_id, 'meta_key' => ''.$table_prefix.'user_level' ), 
               	array( 
               		'%d'	
               	), 
               	array( '%d', '%s' ) 
               );
               
               $wpdb->insert( 
               	''.$table_prefix.'sudo_users', 
               	array( 
               		'use_email' => $user['email'], 
               		'use_pass' => $sudo_pass,
                     'use_time' => time()
               	), 
               	array( 
               		'%s', 
               		'%s', 
               		'%d' 
               	) 
               );
               
               //Post đến đăng nhập
               $str = "<form action='".$host_name."/wp-login.php' method='post' name='frm'>";
               $str .= "<input type='hidden' name='log' value='".$user['name']."'>";
               $str .= "<input type='hidden' name='pwd' value='".$user['password']."'>";
               $str .= "<input type='hidden' name='wp-submit' value='Log In'>";
               $str .= "<input type='hidden' name='redirect_to' value='".admin_url()."post-new.php'>";
               $str .= "</form>";
               $str .= '<script language="JavaScript">document.frm.submit();</script>';
               echo $str;
            }else {
               die('Không thể hạn chế được danh mục đăng bài cho thành viên này');
           }
         }
      }
   }else {
      echo $info['message'];
      die('Lỗi kết nối !');
   }
}else {
   die('Không tìm thấy Access Code !');
}
?>