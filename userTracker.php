<?php
/*
Plugin Name: UserTracker
Plugin URI: 
Description: Track logged in Users
Author: Chris Black
Version: 1.2
Author URI: http://cjbonline.org
*/


/* CREATE TABLE `cjbonlin_cjbonlinewordpressv23b2`.`cjbonlin_usertracker` (
`id` BIGINT( 20 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`date` DATETIME NOT NULL ,
`user_id` BIGINT( 20 ) NOT NULL ,
`url` VARCHAR( 255 ) NOT NULL ,
`title` VARCHAR( 255 ) NOT NULL
) ENGINE = InnoDB */

$dbVersion = '2';
$myTableName = $wpdb->prefix . 'usertracker';

add_filter('wp_head', 'UT_trackUser');

add_action('admin_menu', 'UT_add_pages');
add_action('wp_login', 'UT_userLogin');

function UT_add_pages() {
	add_submenu_page('index.php', __('UT Stats'), __('UT Stats'), 'manage_options', 'userTracker', 'UT_statsPage');
}

function UT_userLogin($userLogin) {
	$userdata = get_userdatabylogin($userLogin);
	update_usermeta($userdata->ID,'lastLogin',date('m-d-Y G:i:s'));
	return $userLogin;
}

function UT_trackUser() {
	global $userdata, $wpdb, $myTableName,$dbVersion;
	
	$myDatabaseVersion = get_option('userTracker_dbVersion');
	
	if ($myDatabaseVersion == '') {
		createTable();
		add_option('userTracker_dbVersion',$dbVersion);
	} else if ($myDatabaseVersion == '1') {
		updateTable1to2();
	}
	
	if (is_admin()) {
		return true;
	}
	
	$myDB = $wpdb;
	get_currentuserinfo();

	if ($_SERVER['SCRIPT_URI'] != '') {
		$myPage = $_SERVER['SCRIPT_URI'];
	} else if ($_SERVER['REQUEST_URI'] != '') {
		$myPage = $_SERVER['REQUEST_URI'];
	} else {
		$myPage = $_SERVER['SCRIPT_NAME'];
	}
	
	if ($userdata->ID != '') {
		$sql = "INSERT INTO " . $myTableName . " (date,user_id,url,title,referer,user_agent,ip) VALUES (NOW()," . $userdata->ID . ",'" . $myPage . "','','" . $_SERVER['HTTP_REFERER'] . "','" . $_SERVER['HTTP_USER_AGENT'] . "','" . $_SERVER['REMOTE_ADDR'] . "')";
		$myDB->query($sql);
	}
}

function UT_statsPage() {
	global $wpdb, $myTableName;

	if ( !isset( $_GET['paged'] ) ) {
		$_GET['paged'] = 1;
	}
	
	$currentPage = $_GET['paged'];

	if (isset($_POST['m']) && $_POST['m'] != '') {
		$selectedUser = $_POST['m'];
//		print("Post:" . $selectedUser);
	} else if (isset($_GET['m']) && $_GET['m'] != '') {
		$selectedUser = $_GET['m'];
//		print("Get:" . $selectedUser);
	} else {
		$selectedUser = '';
//		print("Else:" . $selectedUser);
	}

	
	$sql = "SELECT COUNT(*) as myCount FROM " . $myTableName . " WHERE url NOT LIKE '%adsense-track.js%'";
	if ($selectedUser != '') {
		$sql .= " AND user_id = " . $selectedUser;
	}
	
	$result = $wpdb->get_results($sql);
	foreach ($result as $count) {
		$myCount = $count->myCount;
	}
	
	print ("<div id=\"wpbody\">
	<div class=\"wrap\"><h2>User Tracker Stats</h2>
	<br/><div class=\"tablenav\">");

	$page_links = paginate_links( array(
		'base' => add_query_arg( 'paged', '%#%' ) . "&m=$selectedUser",
		'format' => '',
		'total' => ceil($myCount/30),
		'current' => $currentPage
	));
	
	if ( $page_links )
		echo "<div class='tablenav-pages'>$page_links</div>";
	
	print("<div class=\"alignleft\"><form id=\"userTracker-filter\" action=\"" . get_bloginfo('wpurl') . "/wp-admin/admin.php?page=userTracker\" method=\"post\">
		<select name='m'>
		<option ");
	if ($selectedUser == '') {
		print(" SELECTED ");
	}
	print("value='-1'>View all users</option>");

	$wp_user_search = new WP_User_Search();
	foreach ( $wp_user_search->get_results() as $userid ) {
		$user_object = new WP_User($userid);
		print("<option value='" . $user_object->ID. "'");
		if ($selectedUser == $user_object->ID) {
			print(" SELECTED ");
		}
		print(">" . $user_object->display_name . "");

		$sql = "SELECT COUNT(*) as myCount FROM " . $myTableName . " WHERE url NOT LIKE '%adsense-track.js%' AND user_id = " . $user_object->ID;
		$result = $wpdb->get_results($sql);
		foreach ($result as $count) {
			$myCount = $count->myCount;
		}
		print(" - $myCount </option>");

	}
		
		
	print("</select>
		
		<input type=\"submit\" id=\"post-query-submit\" value=\"Filter\" class=\"button-secondary\" />
		</form>
		</div>
		
		<br class=\"clear\" />
		</div>
		
		<br class=\"clear\" />");
	
	
	$bottom = ($currentPage - 1) * 30;
	$top = $currentPage * 30;
	$sql = "select date as mydate, url , display_name , ip , referer, user_agent, u.ID as user_id from " . $myTableName . " ut inner join " . $wpdb->prefix . 'users' . " u on u.ID =ut.user_id WHERE url NOT LIKE '%adsense-track.js%' ";
	if ($selectedUser != '' && $selectedUser != '-1') {
		$sql .= " AND u.ID = " . $selectedUser;
	}
	$sql .= " ORDER BY ut.date DESC LIMIT " . $bottom . "," . $top;
	
//	print("SQL:" . $sql);
	$result = $wpdb->get_results($sql);
	print("<table class=\"widefat\">
	<thead><tr>
		<th scope=\"col\" >Date</th>
		<th scope=\"col\" >Name</th>
		<th scope=\"col\" >URL</th>
		<th scope=\"col\" >IP</th>
	</tr></thead>");
	$alter = true;
	foreach($result as $stat) {
		print("<tr");
		
		if ($alter) {
		 	$alter = false;
		 	print(" class='alternate' ");
		} else {
			$alter = true;
		}
		print(">
			<td> " . $stat->mydate . "</td>
			<td> <a href=\"" . get_bloginfo('wpurl') . "/wp-admin/user-edit.php?user_id=" . $stat->user_id . "\">" . $stat->display_name . "</a></td>
			<td> <a href=\"" . $stat->url . "\" target=\"_blank\">" . $stat->url . "</a></td>
			<td> <a href=\"http://name.space.xs2.net/cgi-bin/nslookup.pl?pageid=DNS411&nsinput=" . $stat->ip . "&submit=search\">" . $stat->ip . "</a></td>
			</tr>");
	}
	print("</table></div></div>");
}

function createTable() {
	global $wpdb, $myTableName;
	if($wpdb->get_var("show tables like '$myTableName'") != $myTableName ) {
		$sql = "CREATE TABLE IF NOT EXISTS " . $myTableName . " (`id` BIGINT( 20 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,`date` DATETIME NOT NULL ,`user_id` BIGINT( 20 ) NOT NULL ,`url` VARCHAR( 255 ) NOT NULL ,`title` VARCHAR( 255 ) NOT NULL)";
		$wpdb->query($sql);	
	}
	updateTable1to2();

}

function updateTable1to2() {
	global $wpdb, $myTableName;
	$sql = "ALTER TABLE " . $myTableName . " ADD (referer varchar(255), user_agent varchar(1000), ip varchar(255))";
	$wpdb->query($sql);
	update_option('userTracker_dbVersion', $dbVersion);
}
?>
