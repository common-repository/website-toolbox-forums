<?php

/**
 * @package Website Toolbox Community
 * @author Website Toolbox
 */

/**
 * Plugin Name: Website Toolbox Community
 * Plugin URI:  https://www.websitetoolbox.com/wordpress
 * Description: Website Toolbox is the easiest way to add a discussion community to WordPress. The community plugin features single sign on and seamlessly embeds into your website.
 * Author:      Website Toolbox
 * Author URI:  https://www.websitetoolbox.com/wordpress
 * Version:     1.9.9
 */
namespace WebsiteToolboxForum;

/* Class to define plugin specific global variable  */
/* Change these if you need duplicate plugin  */
class globalVariables {

	public static $WTBPREFIX = '';
	public static $PAGENAME = 'Community';
	public static $loginParam = 'forum';
	// Website Toolbox URL For validate login as well as update SSO URLS.
	public static $WTBSETTINGSPAGEURL = 'https://www.websitetoolbox.com/tool/members/mb/settings';
	public static $WTBAPIPAGEURL = 'https://api.websitetoolbox.com/v1/api';

}

include("wt_forum_include.php");
include("forumHook.php");
include("core/events.php");
include("admin/admin.php");

function redirectionToAvoidAMP() {
	if(isset($_GET['amp'])) {
		global $wp_query;
		$currentPostID = intval($wp_query->queried_object->ID);
		$wtbPageId = get_option(globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
		if(isset($currentPostID) && isset($wtbPageId) && $currentPostID == $wtbPageId){
			$currentPageUrl = \WebsiteToolboxInclude\wtbGetCurrentPageUrl();
			$updatedPageUrl =  remove_query_arg('amp', $currentPageUrl);
			$updatedPageUrl =  add_query_arg( 'noamp', 'mobile', $updatedPageUrl);
			wp_redirect( $updatedPageUrl );
			exit;
		}
	}
}

/* Purpose: Set page content on the front end according to the basic theme.
Parameter: None
Return: None */
function embedContent($content) {
	$websitetoolboxpage_id = get_option(globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
	$page_content = get_page($websitetoolboxpage_id);
	$page_content = $page_content->post_content;
	$theme_data = wp_get_theme();
	$wrap_pre = "<style>.nocomments { display: block; }</style>";
	$wrap_post = "";
	if($theme_data['Name']=="WordPress Default" && strpos($theme_data['Description'], '>Kubrick<')==90) {
		$wrap_pre .= "<div style='background-color: white;'>";
		$wrap_post .= "</div>";
	}
	if($theme_data['Template'] == "twentyeleven") {
		$wrap_pre .= <<<STYLE
		<style type="text/css">
		.singular .entry-header, .singular .entry-content, .singular footer.entry-meta, .singular #comments-title {
		width: 100%;
		}
		.singular #content, .left-sidebar.singular #content {
		margin: 0 1.5%;
		}
		.page-id-$websitetoolboxpage_id  .entry-title {display: none;}

		#main { padding: 0; }
		.singular.page .hentry { padding: 0; }
		</style>
STYLE;
	}
	return <<<EMBED
	$wrap_pre
	$page_content
	$wrap_post
EMBED;
}
function pluginAssets() {
    wp_register_style( 'websitetoolbox-css', plugins_url( 'css/websitetoolbox-frontend.css' , __FILE__ ) );
    wp_enqueue_style( 'websitetoolbox-css' );
}
/* Purpose: create a new page for front end.
Parameter: None
Return: None */
function publishForumPage() {
    $websitetoolboxpage_id = get_option(globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
	if(is_page($websitetoolboxpage_id)) {
        // Get post data on the basis of postid
        $postData = get_post($websitetoolboxpage_id);
        if($postData && $postData->post_status!='publish') {
            $postData->post_status = 'publish';
            wp_update_post($postData);
        }
        add_filter("the_content", "WebsiteToolboxForum\\embedContent");
    }
}

/* Purpose: This function is used to set authentication token into session variable if user logged-in.
Param: authentication token
Return: Nothing */
function saveAuthToken($authtoken,$wtbUserid) {
	// Start Session if not started previously.
	\WebsiteToolboxInclude\startForumSession();
	$_SESSION[globalVariables::$WTBPREFIX.'wtb_login_auth_token'] = $authtoken;
	\WebsiteToolboxInclude\setForumCookies(globalVariables::$WTBPREFIX.'wtb_login_userid', $wtbUserid, 0);
}

/* Purpose: If a user Logged-in/logged-out on WordPress site from front end/admin section write an image tag after page load to loggout from forum.
Parameter: None
Return: None */
function ssoLoginLogout() {
	// Start Session if not started previously.
	\WebsiteToolboxInclude\startForumSession();
	// If user logged-out from the Forum then call wp_logout function to logged-out from WordPress site as well as forum.
	if(isset($_GET['action']) && $_GET['action']=='ssoLogout' && is_user_logged_in()) {
		unset($_SESSION[globalVariables::$WTBPREFIX.'wtb_account_settings']);
		unset($_SESSION[globalVariables::$WTBPREFIX.'wtbForumCheck']);
		wp_logout();
		exit;
	}
	// If user logged-out from WordPress Site
	if (isset($_SESSION[globalVariables::$WTBPREFIX.'wtb_login_auth_token'])) {
		$login_auth_url = get_option(globalVariables::$WTBPREFIX.'websitetoolbox_url');
		// Remove http: | https: from the Forum URL so that it will not print into IMG tag src attribbute.
		$login_auth_url = preg_replace('#^https?:#i', '', $login_auth_url);
		$login_auth_url = $login_auth_url."/register/dologin?authtoken=".$_SESSION[globalVariables::$WTBPREFIX.'wtb_login_auth_token'];
		if(isset($_COOKIE[globalVariables::$WTBPREFIX.'wt_login_remember'])) {
			$login_auth_url = $login_auth_url."&remember=".$_COOKIE[globalVariables::$WTBPREFIX.'wt_login_remember'];
		}

		/* Print image tag on the login landing success page to sent login request on the related forum */
		echo '<img src="'.$login_auth_url.'" border="0" width="0" height="0" alt="" style="display: block;">';
		unsetLoginSession();
		return false;
	}
	if(isset($_COOKIE[globalVariables::$WTBPREFIX.'wt_logout_token'])) {
		$isSSOEnableForRole = '';
		if(is_user_logged_in()) {
			$wpLoggedinUserid = get_current_user_id();
			$userObj = new \WP_User($wpLoggedinUserid);
			$isSSOEnableForRole = isSsoEnableForUserRole($userObj);
		}
		if(!is_user_logged_in() || $isSSOEnableForRole == 0) {
			$logout_auth_url = get_option(globalVariables::$WTBPREFIX.'websitetoolbox_url')."/register/logout?authtoken=".$_COOKIE[globalVariables::$WTBPREFIX.'wt_logout_token'];
			$logout_auth_url = preg_replace('#^https?:#i', '', $logout_auth_url);
			/* Print image tag on the header section sent logout request on the related forum */
			echo '<img src="'.$logout_auth_url.'" border="0" width="0" height="0" alt="" style="display: block;">';
			resetCookieOnLogout();
			return false;
		}
	}

	// User logged-out from wordpress site and session varible exist then unset the session varible.
	if(!is_user_logged_in() && isset($_SESSION[globalVariables::$WTBPREFIX.'wtb_account_settings'])) {
		unset($_SESSION[globalVariables::$WTBPREFIX.'wtb_account_settings']);
		unset($_SESSION[globalVariables::$WTBPREFIX.'wtbForumCheck']);
	}

	loginAndSetCookies();
}

function loginAndSetCookies () {
	$forumAddressChanged = (isset($_COOKIE[globalVariables::$WTBPREFIX.'wt_logout_token']) && isset($_COOKIE[globalVariables::$WTBPREFIX.'wt_forum_url']) &&  $_COOKIE[globalVariables::$WTBPREFIX.'wt_forum_url'] != get_option(globalVariables::$WTBPREFIX."websitetoolbox_url"));

	// If user is login after registration then execute SSO login functinality as well.
	if(is_user_logged_in() && (empty($_COOKIE[globalVariables::$WTBPREFIX.'wt_logout_token']) || $forumAddressChanged)) {
		$wpLoggedinUserid = get_current_user_id();
		$userObj = new \WP_User($wpLoggedinUserid);
		$isSSOEnableForRole = isSsoEnableForUserRole($userObj);
		if ($isSSOEnableForRole && $isSSOEnableForRole == 1) {
			if ($forumAddressChanged) {
				resetCookieOnLogout();
			}
			$responseArray = ssoHttpRequest($userObj);
			setCookieOnLogin($responseArray,'');
			#Save authentication token into session variable.
			if(isset($responseArray['authtoken'])) {
				saveAuthToken($responseArray['authtoken'],$responseArray['wtbUserid']);
				return $responseArray['authtoken'];
			}
		}
	}
}

function setCookieOnLogin($responseArray, $rememberme) {
	$persistent = 0;
	if($rememberme != "") {
		\WebsiteToolboxInclude\setForumCookies(globalVariables::$WTBPREFIX.'wt_login_remember', "checked", $persistent);
		$persistent = 1;
	}
	// Save authentication token into cookie for one day to use into SSO logout.
	if(isset($responseArray['authtoken'])){
		\WebsiteToolboxInclude\setForumCookies(globalVariables::$WTBPREFIX.'wt_logout_token', $responseArray['authtoken'], $persistent);
		\WebsiteToolboxInclude\setForumCookies(globalVariables::$WTBPREFIX.'wt_login_token', $responseArray['authtoken'], $persistent);
		\WebsiteToolboxInclude\setForumCookies(globalVariables::$WTBPREFIX.'wt_forum_url',get_option(globalVariables::$WTBPREFIX."websitetoolbox_url"),$persistent);
	}
	return true;
}

function ssoHttpRequest($userObj) {
	$forum_api		= get_option(globalVariables::$WTBPREFIX."websitetoolbox_api");
	$forum_url		= get_option(globalVariables::$WTBPREFIX."websitetoolbox_url");
	if($forum_api) {
		$fullName = trim($userObj->first_name." ".$userObj->last_name);
		// create URL to get authentication token.
		$URL = $forum_url."/register/setauthtoken";
		// Append fileds email and password to create an account on the related forum if account not exist.
		$fields = array(
		        'type' 			 =>'json',
		        'apikey' 		 => $forum_api,
		        'user' 			 => $userObj->user_login,
		        'email' 		 => $userObj->user_email,
		        'name' 			 => $fullName,
				"avatar" 	 	 => getProfileImage($userObj->ID),
		        'externalUserid' => $userObj->ID
		    );
		// Get plain password from WordPress login form value and append it with HTTP request.
		if(isset($_POST['pwd']) && $_POST['pwd'] != "") {
			$fields['pw'] = $_POST['pwd'];
		}
		// Send http or https request to get authentication token.
		$response_array = wp_remote_post($URL, array('method' => 'POST', 'body' => $fields));
		//Check if http/https request could not return any error then filter JSON from response
		if(!is_wp_error( $response_array )) {
			$response = trim(wp_remote_retrieve_body($response_array));
			$response = json_decode($response);
			if($response && $response->{'authtoken'} != "") {
				$responseArray = array(
					'authtoken' => $response->{'authtoken'},
					'wtbUserid' => $response->{'userid'}
				);
				return $responseArray;
			}
		} else if (isset($_GET['from']) && $_GET['from'] == globalVariables::$WTBPREFIX.globalVariables::$loginParam) {
			wp_die("Error message during discussion community SSO: " . $response_array->get_error_message());
		}
	}
}

/* Purpose: This function is used to unset session variable if user logged-in so that this session variable did't effect any other place.
Param1: None
Return: None */
function unsetLoginSession() {
	unset($_SESSION[globalVariables::$WTBPREFIX.'wtb_login_auth_token']);
}

/* Purpose: This function is used to reset cookie variable if user logged-out from the WordPress website so that authtoken did't effected.
Param1: type (login/logout)
Return: None */
function resetCookieOnLogout() {
	setcookie(globalVariables::$WTBPREFIX.'wt_logout_token', '', 0, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN);
	setcookie(globalVariables::$WTBPREFIX.'wtb_login_userid', '', 0, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN);
	setcookie(globalVariables::$WTBPREFIX.'wt_login_remember', '', 0, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN);
	setcookie(globalVariables::$WTBPREFIX.'wt_login_token', '', 0, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN);
	setcookie(globalVariables::$WTBPREFIX.'wt_forum_url', '', 0, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN);
}

# for URl making
if (!function_exists('esc_attr')) {
	function esc_attr($attr){return attribute_escape( $attr );}
	function esc_url($url){return clean_url( $url );}
}

/* Purpose: set forum page link in WordPress menu in case of forum is not embedded on WordPress website.
Parameter: None
Return: None */
if(get_option(globalVariables::$WTBPREFIX."websitetoolbox_redirect") == '') {
	function createForumLinkInMenu ($link, $post) {
		if(isset($post->ID)) {
			$id = $post->ID;
		} else {
			$id = $post;
		}
		#get array
		$newCheck = getForumPostData();
		if(!is_array($newCheck)) { $newCheck = array(); }
		#check array according to the key if forum used external url
		if(array_key_exists($id, $newCheck)) {
			$matchedID = $newCheck[$id];
			$newURL = $matchedID[globalVariables::$WTBPREFIX.'_links_to'];
			if(strpos($newURL,get_option('home'))>=0 || strpos($newURL,'www.')>=0 || strpos($newURL,'http://')>=0 || strpos($newURL,'https://')>=0) {
				if($matchedID[globalVariables::$WTBPREFIX.'_links_to_target'] == globalVariables::$WTBPREFIX.'websitetoolbox') {
					$newURL = trim($matchedID[globalVariables::$WTBPREFIX.'_links_to']);
					// Added / at the end of forum url if open into parent window.
					if(!preg_match("/\/$/", $newURL)) {
						$link = $newURL."/";
					}
				} else {
					$link = esc_url( $newURL );
				}
			} else {
				if($matchedID[globalVariables::$WTBPREFIX.'_links_to_target'] == globalVariables::$WTBPREFIX.'websitetoolbox') {
					// Added / at the end of forum url if open into parent window.
					if(!preg_match("/\/$/", $newURL)) {
						$link = $newURL."/";
					}
				} else {
					$link = esc_url( get_option( 'home').'/'. $newURL );
				}
			}
		}
		return $link;
	}
	add_filter('page_link', 'WebsiteToolboxForum\\createForumLinkInMenu', 20, 2);
	add_filter('post_link', 'WebsiteToolboxForum\\createForumLinkInMenu', 20, 2);
}

# Get main array from the post meta and post table according to redirect url
function getForumPostData(){
	global $wpdb;
	$theArray = array();

	$theqsl = "SELECT * FROM $wpdb->postmeta a, $wpdb->posts b  WHERE a.`post_id`=b.`ID` AND b.`post_status`!='trash' AND (a.`meta_key` = '".globalVariables::$WTBPREFIX."_wtbredirect_active' || a.`meta_key` = '".globalVariables::$WTBPREFIX."_links_to' || a.`meta_key` = '".globalVariables::$WTBPREFIX."_links_to_target' || a.`meta_key` = '".globalVariables::$WTBPREFIX."_links_to_type') ORDER BY a.`post_id` ASC;";
	$thetemp = $wpdb->get_results($theqsl);
	if(count($thetemp)>0){
		foreach($thetemp as $key){
			$theArray[$key->post_id][$key->meta_key] = $key->meta_value;
		}
		foreach($thetemp as $key){
			// defaults
			if(!isset($theArray[$key->post_id][globalVariables::$WTBPREFIX.'_links_to'])){$theArray[$key->post_id][globalVariables::$WTBPREFIX.'_links_to']	= 0;}
			if(!isset($theArray[$key->post_id][globalVariables::$WTBPREFIX.'_links_to_type'] )){$theArray[$key->post_id][globalVariables::$WTBPREFIX.'_links_to_type']				= 302;}
			if(!isset($theArray[$key->post_id][globalVariables::$WTBPREFIX.'_links_to_target'])){$theArray[$key->post_id][globalVariables::$WTBPREFIX.'_links_to_target']	= 0;}
		}

	}
	return $theArray;
}
/* Purpose: Function is used to append authtoken at the end of URL if user logged-in on wordpress site and then clicks on the forum link.
Parameter: Manual link and post id.
Return: Manual links */
function changeForumLink($items, $post){
    // Append authtoken in the forum link if user logged-in on WordPress site and forum open in window independently.
    if(is_user_logged_in() && $post == get_option(globalVariables::$WTBPREFIX.'websitetoolbox_pageid') && get_option(globalVariables::$WTBPREFIX."websitetoolbox_redirect") == '') {
        $add_token = appendTokenInURL();
        $items = add_query_arg( $add_token, $items );
    }
    return $items;
}
add_filter('page_link', 'WebsiteToolboxForum\\changeForumLink', 20, 2);
add_filter('post_link', 'WebsiteToolboxForum\\changeForumLink', 20, 2);

/* Purpose: Function is used to append authentication token into the forum URL.
Parameter: page content.
Return: replace page content */
function updatePageContent($content) {
	// Append authtoken in the embed code if user logged-in on WordPress site and forum open in iframe.
 	if($GLOBALS['post']->ID == get_option(globalVariables::$WTBPREFIX.'websitetoolbox_pageid') && get_option(globalVariables::$WTBPREFIX."websitetoolbox_redirect") != '' && is_user_logged_in()) {
		$wpLoggedinUserid 	= get_current_user_id();
		$userObj 			= new \WP_User($wpLoggedinUserid);
		$userRoles       	= (array) json_decode(get_option(globalVariables::$WTBPREFIX.'websitetoolbox_user_roles'));
		if($userRoles){
			$userType       = $userRoles['users'];
		}
		if($userType && $userType == 'no_users') {
			return $content;
		} else {
	 		$auth_token = '';
			if(isset($_COOKIE[globalVariables::$WTBPREFIX.'wt_login_token'])) {
				$auth_token = "?authtoken=".$_COOKIE[globalVariables::$WTBPREFIX.'wt_login_token'];
				setcookie(globalVariables::$WTBPREFIX.'wt_login_token', '', 0, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN);
			}

			if(isset($_COOKIE[globalVariables::$WTBPREFIX.'wt_logout_token']) && isset($_COOKIE[globalVariables::$WTBPREFIX.'wt_login_remember'])) {
				$auth_token .= "&remember=".$_COOKIE[globalVariables::$WTBPREFIX.'wt_login_remember'];
			}

			$embedSrcCount = preg_match('/id="embedded_forum"\ssrc=(["\'])(.*?)\1/', $content, $match);
			if($embedSrcCount == 1) {
				$embedURL = $match[2];
				$newURL =  $match[2].$auth_token;
			}
			$content = str_replace($embedURL,$newURL,$content);
		}
 	}
	return $content;
}
add_filter( 'the_content', 'WebsiteToolboxForum\\updatePageContent', 999);

/* Purpose: Function is used to append authtoken at the end of URL if user logged-in on wordpress site and custom menu is activated and used custom link for Forum in menu instead of default page.
Parameter: Manual link and post id.
Return: Manual links */
function addAuthtokenCustomLink($items, $args){
// Append authtoken in the forum link if user logged-in on WordPress site and forum open in window independently.
	if(is_user_logged_in()) {
		$urls = array();
		$custom_anchor_links = array();
		// Get forum URL and append / at the end of the URL if not exist.
		$wtbForumURL = get_option(globalVariables::$WTBPREFIX.'websitetoolbox_url');
		if(substr($wtbForumURL, -1) != '/') {
			$wtbForumURL .= "/";
		}
		// Get Website URL and parse it.
		$websiteUrl = parse_url(get_site_url());
		$wtbForumURL = parse_url($wtbForumURL);

		foreach ($items as $key => $item) {
			$urls[$item->ID] = $item->url;

			// Append authtoken with forum URL if user added link in custom menu and it is on HTTP and the website is on HTTPS.
			if($item->url == "/") {
				$item->url=get_site_url();
			}

			$wtbMenuLink = parse_url($item->url);
			if(isset($wtbMenuLink['host'])){
				// Check Forum URL with the custom link. If forum URl exists in the custom link then append authtoken into it.
				if ($item->object == 'custom' && $wtbMenuLink['host'] == $wtbForumURL['host']) {
					if(substr($item->url, -1) != '/') {
						$item->url .= "/";
					}
					if($wtbMenuLink['scheme'] == 'http' && $websiteUrl['scheme'] == 'https') {
						$item->url = str_replace( 'http://', 'https://', $item->url );
					}
					$add_token = appendTokenInURL();
					$item->url = add_query_arg( $add_token, $item->url );
				}
			}
		}
	}
	return $items;
}
add_filter('wp_nav_menu_objects', 'WebsiteToolboxForum\\addAuthtokenCustomLink', 10, 2);

/* Purpose: create an array and set authtoken and remember-me as value.
Parameter: none
Return: array */
function appendTokenInURL () {
	$tokenArray = array();
	if(isset($_COOKIE[globalVariables::$WTBPREFIX.'wt_logout_token'])){
		$authtoken = $_COOKIE[globalVariables::$WTBPREFIX.'wt_logout_token'];
		$tokenArray['authtoken'] = $authtoken;
	}
	if(isset($_COOKIE[globalVariables::$WTBPREFIX.'wt_login_remember'])) {
		$remember = $_COOKIE[globalVariables::$WTBPREFIX.'wt_login_remember'];
	}
	return $tokenArray;
}

/* Purpose: Append auttoken in the URL and redirected to the related URL after login on the WordPress website in case of forum is private and SSO login URL is exist.
Parameter: none
Return: none */
function afterLoginRedirection($authtoken) {
	\WebsiteToolboxInclude\startForumSession();
	$forumEmbedUrl 	= '';
	$referralUrl	= '';
	if(isset($_SESSION[globalVariables::$WTBPREFIX.'wtb_login_referer']) && $_SESSION[globalVariables::$WTBPREFIX.'wtb_login_referer']!="" ) {
		$redirectedUrl = urldecode($_SESSION[globalVariables::$WTBPREFIX.'wtb_login_referer']);
		if(strpos($redirectedUrl, '?') !== false) {
			$redirectedUrl .= "&authtoken=".$authtoken;
		} else {
			$redirectedUrl .= "?authtoken=".$authtoken;
		}
		unset($_SESSION[globalVariables::$WTBPREFIX.'wtb_login_referer']);
		wp_redirect($redirectedUrl);
		exit;
	}else{
		$referralPageUrl = wp_get_referer();
		// Get paramter value from the referer URL.
		$referralUrl = parse_url($referralPageUrl);
		if(array_key_exists('query',$referralUrl)){
			parse_str($referralUrl['query'], $queryString);
			if(array_key_exists('wtbForumUrl',$queryString)){
				$forumEmbedUrl = $queryString['wtbForumUrl'];
			}
		}
		if($forumEmbedUrl) {
			if(strpos($referralPageUrl, '?p=') || strpos($forumEmbedUrl, '?p=')) {
				$tokenParam = "&authtoken=$authtoken";
				$forumEmbedUrl .= $tokenParam;
			} else {
				$tokenParam = "?p=&authtoken=$authtoken";
				$forumEmbedUrl .= $tokenParam;
			}
 			header("Location:$forumEmbedUrl");
			exit();
		}
	}
}

/* Purpose: remove paramete and it's value from the URL.
Parameter: URL, parameter name
Return: Updated URL */
function removeParam($url, $param) {
    $url = preg_replace('/(&|\?)'.preg_quote($param).'=[^&]*$/', '', $url);
    $url = preg_replace('/(&|\?)'.preg_quote($param).'=[^&]*&/', '$1', $url);
    return $url;
}

/* Purpose: execute http request through rest API.
Parameter: method (GET, POST), request URL, array
Return: JSON data */
function apiRequest($method, $path, $data = '',$header = '') {
	$url = globalVariables::$WTBAPIPAGEURL.$path;
	if($data != ''){
		if(strtoupper($method) == "GET") {
			$url = sprintf("%s?%s", $url, http_build_query($data));
		}
	}
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_HTTPHEADER, array(
		"x-api-key: ".get_option(globalVariables::$WTBPREFIX."websitetoolbox_api"),
		'Content-Type: application/json',
	));
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_USERAGENT, 'Website Toolbox WordPress Plugin');
	if(strtoupper($method) == "POST") {
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
	} else if (strtoupper($method) == "GET") {
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	}
	$response = curl_exec($curl);
	curl_close($curl);
	return json_decode($response);
}
/* Purpose: Get userid of the user from the forum.
Parameter: user's email
Return: userid */
function getUserid($userEmail) {
	$data =  array(
		"email"	=> $userEmail
	);
	if(sizeof($data) == 1) {
		$response = apiRequest('GET', "/users/", $data);
		if($response->{"data"}["0"]->{"userId"}) {
			return $response->{"data"}["0"]->{"userId"};
		}
	}
}

function getProfileImage($userid) {
	global $wpdb;
	$profileImgUrl = '';
	# Get profile image uploaded from the plugin.
	if(empty($profileImgUrl)) {
		$profileImgUrl = get_the_author_meta('user_meta_image', $userid);
	}
	if(empty($profileImgUrl)){
		$profileImgUrl = get_the_author_meta('profile_pic', $userid);
	}
	#get profile picture from gravatar if custom profile picture not exists.
	if(empty($profileImgUrl)){
		$profileImgUrl = get_avatar_url($userid);
	}
	return $profileImgUrl;
}

function isSsoEnableForUserRole ($userObj) {
	$isSSOEnable = 0;
	$userRoles = (array) json_decode(get_option(globalVariables::$WTBPREFIX.'websitetoolbox_user_roles'));
	if($userRoles && $userRoles['users'] == "all_users") {
		$isSSOEnable = 1;
	} else if($userRoles && $userRoles['users'] == "no_users") {
		$isSSOEnable = 0;
	} else {
		$currentUserRole = $userObj->roles;
		$roleArrayLength =  count($currentUserRole);
		if($roleArrayLength && $roleArrayLength == 1) {
			$keyValue = key($currentUserRole);
			if(in_array($currentUserRole[$keyValue], $userRoles)) {
				$isSSOEnable = 1;
			}
		} else if($roleArrayLength && $roleArrayLength > 1) {
			for($i = 0; $i < $roleArrayLength; $i++) {
				if(in_array($currentUserRole[$i], $userRoles)) {
					$isSSOEnable = 1;
					break;
				}
			}
		}
	}
	return $isSSOEnable;
}
/* Purpose: set plugin settings link in plugin page.
Parameter: None
Return: None */
function addPluginSettingsLink( $links ) {
	if(get_option(globalVariables::$WTBPREFIX."websitetoolbox_username")){
		$page = globalVariables::$WTBPREFIX.'websitetoolboxUpdateOptions';
	}else{
		$page = globalVariables::$WTBPREFIX.'websitetoolboxoptions';
	}
    $settings_link = '<a href="options-general.php?page='.$page.'">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

//code for create post automatically on forum start
function getCategoryList(){
    $limit 	= array("limit"=>'100');
    $result 	= apiRequest('GET', "/categories", $limit);
    if(isset($result->{'data'})){
    	return $result->{'data'};
    }else{
	return "There was an error returning the category list from Website Toolbox.";
    }
}
function checkTopicCategory($topicId,$categoryId){
	global $post;
	$forumCategoryId = '';
	if(!$post){
		$post_id = $_GET['post'];
	}else{
		$post_id = $post->ID;
	}
	delete_post_meta( $post_id, 'website_toolbox_forum_publishing_error');
	$result = apiRequest('GET', "/topics/".$topicId);
	update_post_meta( $post_id, 'result', $result  );
	if(isset($result->status) && ($result->error->param == 'topicId')){
		update_post_meta( $post_id, 'website_toolbox_forum_publishing_error', "This topic is deleted from the Website Toolbox Community." );
		update_post_meta( $post_id, 'website_toolbox_publish_on_forum', 0 );
		update_post_meta( $post_id, 'website_toolbox_forum_postUrl','deleted');
		delete_post_meta( $post_id, 'website_toolbox_forum_category' );
		return "website_toolbox_forum_publishing_error";
	}else{
       		$forumCategoryId = $result->{'categoryId'};
		if($forumCategoryId != $categoryId){
			update_post_meta( $post_id, 'website_toolbox_forum_category', $forumCategoryId );
		}
		return $forumCategoryId;
    	}

}
function addUpdateTopic($defaultCategoryId,$defaultContentType,$post){
    $post_id 	= $post->ID;
    delete_post_meta( $post_id, 'website_toolbox_forum_publishing_error' );
    $current_user       = wp_get_current_user();
    $userName           = $current_user->user_login;
    $title              = wp_strip_all_tags( $post->post_title );
    $title              = apply_filters( 'wpdc_publish_format_title', $title, $post_id );
    $current_post_type  = get_post_type( $post_id );
    $publishFullPost    = $defaultContentType;
    $forumCategory      = $defaultCategoryId;
    if($forumCategory === 0){
		$publishFullPost = 0;
	}
     if($publishFullPost != 0){
        if($publishFullPost === 1){
            if($post->post_excerpt){
                $content            = $post->post_excerpt;
            }else{
                $content            = $post->post_content;
            }
        }else{
            $content                = $post->post_content;
        }
        if(!$content){
            $content = '&nbsp;&nbsp';
        }
       $existingTopicId    = get_post_meta($post_id,'forum_topicId',true);
       $existingPostId     = get_post_meta($post_id,'forum_postId',true);
       if($existingTopicId){
            $existingForumCategory = get_post_meta( $post_id, 'website_toolbox_existing_forum_category', true );
            $latestCategory = checkTopicCategory($existingTopicId,$forumCategory);
            if($existingForumCategory != $forumCategory){
                $topicDetails 	= array(
                    "username"      => $userName,
                    "content"       => preg_replace('/<!--.*?-->|\r?\n/', '', $content),
                    "title"         => $title,
                    "categoryId"    => $forumCategory
                );
                $resultNewTopic        = apiRequest('POST', "/topics/", $existingTopicId);

            }else{
                $topicDetails       = array(
                    "username"      => $userName,
                    "title"         => $title,
                );
                $resultEditTopic = apiRequest('POST', "/topics/".$existingTopicId,$topicDetails);
                if($resultEditTopic->error->param == 'topicId'){
					update_post_meta( $post_id, 'website_toolbox_forum_publishing_error', "This topic is deleted from the Website Toolbox Community." );
					update_post_meta( $post_id, 'website_toolbox_publish_on_forum', 0 );
					delete_post_meta( $post_id, 'website_toolbox_forum_category');
					update_post_meta( $post_id, 'website_toolbox_forum_postUrl','deleted');
                    return false;
                }
                $postDetails    = array(
                    "username"      => $userName,
                    "content"       => preg_replace('/<!--.*?-->|\r?\n/', '', $content),
                );
                $resultPost = apiRequest('POST', "/posts/".$existingPostId, $postDetails);
                delete_post_meta( $post_id, 'website_toolbox_forum_publishing_error' );
            }

        }else{
            $topicDetails = array(
                "username"      => $userName,
                "content"       => preg_replace('/<!--.*?-->|\r?\n/', '', $content),
                "title"         => $title,
                "categoryId"    => $forumCategory
            );
            if($forumCategory == -1){
            	$resultNewTopic = apiRequest('POST', "/topics", $topicDetails);
            }else{
            	 $headers 		= "x-api-username:".$current_user->user_login;
            	 $resultNewTopic = apiRequest('POST', "/topics", $topicDetails,$headers);
            }
            if(isset($resultNewTopic->status) && $resultNewTopic->{'error'}){
                update_post_meta( $post_id, 'website_toolbox_forum_publishing_error', $resultNewTopic->{'error'}->{'message'}  );
                return false;
            }
            update_option('websitetoolbox_'.$current_post_type."_category", $forumCategory);
            update_option('websitetoolbox_'.$current_post_type."_content", $publishFullPost);
        }

        if((!isset($resultNewTopic->{'status'})) && $resultNewTopic){
	        $forum_url                      = get_option("websitetoolbox_url");
	        $getForumTopicUrl               = $resultNewTopic->{'URL'};
	        $splitForumPostUrl              = explode("=", $getForumTopicUrl);
	        if($splitForumPostUrl[2]){
	            $forumPostUrl               = $forum_url.urldecode($splitForumPostUrl[2]);
	        }else{
	            $forumPostUrl               = $getForumTopicUrl;
	        }
	        $forumTopicId                   = $resultNewTopic->{'topicId'};
	        $forumPostId                    = $resultNewTopic->{'lastPost'}->{'postId'};
	        update_post_meta( $post_id, 'forum_postId', $forumPostId );
	        update_post_meta( $post_id, 'forum_topicId',$forumTopicId );
	        update_post_meta( $post_id, 'website_toolbox_forum_postUrl',$forumPostUrl );
	    }

        if(!empty($_POST)){
	        update_post_meta( $post_id, 'website_toolbox_publish_on_forum', $publishFullPost );
	        update_post_meta( $post_id, 'website_toolbox_forum_category', $forumCategory );
	    }
        update_post_meta( $post_id, 'website_toolbox_existing_forum_category', $forumCategory );
        update_post_meta( $post_id, 'forum_postContent',$content );

    }elseif($publishFullPost !== ''){
    	$existingTopicId    = get_post_meta($post_id,'forum_topicId',true);
    	if($existingTopicId){
    		delete_post_meta( $post_id, 'website_toolbox_forum_category' );
    		delete_post_meta( $post_id, 'website_toolbox_forum_postUrl' );
    		delete_post_meta( $post_id, 'forum_topicId' );
    		delete_post_meta( $post_id, 'forum_postId' );
    		update_post_meta( $post_id, 'website_toolbox_publish_on_forum',0 );
    	}else{
            update_option('websitetoolbox_'.$current_post_type."_category", 0);
	    	update_option('websitetoolbox_'.$current_post_type."_content", $publishFullPost);
        }
    }
}
/* check if post is new or for edit */
function checkPostStatus($post_id,$post){
    $publish_status     = $post->post_status;
    $publish_private    = apply_filters( 'wpdc_publish_private_post', false, $post_id );
    if ( wp_is_post_revision( $post_id )
         || ( $publish_status != 'publish'  )
         || empty( $post->post_title )
    ) {
        return null;
    }
}
function wpse_plugin_comment_template( $comment_template ) {
	global $post;
	global $current_user;

	$showCommentsLayout = '';
	$replyResponse = "";
    $replyCount 	= 0;
    $post_id 		= $post->ID;

	$getTransientResponse 	= get_transient('websitetoolbox_transient_reply');
	$getTransientReplyCount = get_transient('websitetoolbox_transient_replyCount');

	$postTopicId 		= get_post_meta( $post_id, 'forum_topicId', true );
	$postUrl 		= get_post_meta( $post_id, 'website_toolbox_forum_postUrl', true );
	if(($postTopicId !='') &&($postUrl != "deleted")){
		if($getTransientResponse){
			$replyResponse 	= $getTransientResponse;
			$replyCount 	= $getTransientReplyCount;
		}else{
			if(is_user_logged_in()){
				$wpLoggedinUserid = $current_user->user_login;
			}else{
				$wpLoggedinUserid = 'Anonymous';
			}

			$headers 			=  "x-api-username:".$wpLoggedinUserid;

			$replyResponseTopic = apiRequest('GET', "/topics/".$postTopicId,'',$headers);

			if(!isset($replyResponseTopic->{'error'}->{'message'})){
				if((isset($replyResponseTopic->{'replyCount'})) && ($replyResponseTopic->{'replyCount'} != 0)){
					$replyCount 		= $replyResponseTopic->{'replyCount'};
				}
			}elseif(isset($replyResponseTopic->{'error'}->{'message'}) && $replyResponseTopic->{'error'}->{'message'} ="The topicId you specified does not exist." ){
					update_post_meta( $post_id, 'website_toolbox_publish_on_forum', 0 );
					update_post_meta( $post_id, 'website_toolbox_forum_postUrl', "deleted" );
					update_post_meta( $post_id, 'website_toolbox_forum_publishing_error', "This topic is deleted from the Website Toolbox Community." );
			}
			
			set_transient( 'websitetoolbox_transient_replyCount', $replyCount, 60 );

			if($replyCount > 0){
				$data 		= array('topicId'=>$postTopicId);
				$resultPost = apiRequest('GET', "/posts/?topicId=".$postTopicId,"",$headers);
				set_transient( 'websitetoolbox_transient_reply', $resultPost, 60 );
				$replyResponse = $resultPost;
			}
		}
	}

	if((!empty( $replyResponse->{'data'})) && ($replyCount > 0)){
		if($replyCount <= 5){
			$showComments = $replyCount;
		}else{
			$showComments = 5;
		}
		$attachmentsToShow	= '';
		$showCommentsLayout .= '<div id="wtcomments" class="comments-area">';
		$showCommentsLayout .= '<h2 class="comments-title">Community comments</h2>';
		$showCommentsLayout .= '<ol class="commentlist">';

		for($i=0;$i<$showComments;$i++)
		{

			$attachments = $replyResponse->{'data'}[$i]->{'attachments'};
			$timeStamp = $replyResponse->{'data'}[$i]->{'postTimestamp'};
			$showTime  = date("M j, Y", $timeStamp);
			$showCommentsLayout .= '<li class="comment">';
			$showCommentsLayout .= '<article class="comment-body">';
			$showCommentsLayout .= '<footer class="comment-meta">';
			$showCommentsLayout .= '<div class="comment-author vcard">';
			if(isset($replyResponse->{'data'}[$i]->{'author'}->{'avatarUrl'})){
				$showCommentsLayout .= '<img src='.$replyResponse->{'data'}[$i]->{'author'}->{'avatarUrl'}.' width="50" height="50" class="avatar avatar-60 photo" loading="lazy">';
			}else{
				$showCommentsLayout .= '<img src="https://secure.gravatar.com/avatar/95abc400236da10876d80d4e5e0a8b60?s=50&d=mm&r=g" width="50" height="50" class="avatar avatar-60 photo" loading="lazy">';
			}
			if(isset($replyResponse->{'data'}[$i]->{'author'}->{'URL'})){
				$showCommentsLayout .= '<a href="'.$replyResponse->{'data'}[$i]->{'author'}->{'URL'}.'" rel="external nofollow ugc" class="url"><b class="fn">';
				$showCommentsLayout .= $replyResponse->{'data'}[$i]->{'author'}->{'username'}.'</b></a>';
			}else{
				$showCommentsLayout .= $replyResponse->{'data'}[$i]->{'author'}->{'name'}.'</b></a>';
			}
			$showCommentsLayout .= '</div>';
			$showCommentsLayout .= '<div class="comment-metadata">';
			$showCommentsLayout .= '<time datetime="'.$showTime.'" title="'.$showTime.'">'.$showTime.'</time>';
			$showCommentsLayout .= '</div>';
			$showCommentsLayout .= '</footer>';
			$showCommentsLayout .= '<div class="comment-content wtCommentContent">';
			$showCommentsLayout .= $replyResponse->{'data'}[$i]->{'message'};
			preg_match_all( '@src="([^"]+)"@' , str_replace('thumb/','',urldecode($replyResponse->{'data'}[$i]->{'message'})), $match );
			$massageImage = array_pop($match);

			// Added attachment at the end of the message.
			$attachmentCount = count($attachments);
			if($attachmentCount > 0){
				for($j=0;$j<$attachmentCount;$j++){
					$imageUrl = urldecode($replyResponse->{'data'}[$i]->{'attachments'}[$j]->{'URL'});
					$fileExtension = pathinfo(urldecode($replyResponse->{'data'}[$i]->{'attachments'}[$j]->{'fileName'}), PATHINFO_EXTENSION);
					$imagetype =  array('bmp','gif','jpg','jpe','jpeg','png','heif','heic');
					if(!in_array($imageUrl, $massageImage)){
						if(in_array(strtolower($fileExtension),$imagetype)) {
							$showCommentsLayout .= '<p><a href="'.$imageUrl.'" target="_blank"><img src="'.$imageUrl.'" width=80 height=80 /></a></p>';
						} else {
							$showCommentsLayout .= '<p><a href="'.$imageUrl.'" target="_blank">'.urldecode($replyResponse->{'data'}[$i]->{'attachments'}[$j]->{'fileName'}).'</a></p>';
						}
					}
				}
			}

			$showCommentsLayout .= '</div>';
			$showCommentsLayout .= '</article>';
			$showCommentsLayout .= '</li>';
		}
		$showCommentsLayout .= '</ol>';
		if($replyCount > 5){
			$showReply = $replyCount - 5;
			if($showReply == 1){
				$moreReplies = "reply";
			}else{
				$moreReplies = "replies";
			}
			if(isset($_COOKIE[globalVariables::$WTBPREFIX.'wt_logout_token'])) {
				$showCommentsLayout .= '<div class="comment-reply-title"><h5><a href="'.$postUrl.'?authtoken='.$_COOKIE[globalVariables::$WTBPREFIX.'wt_logout_token'].'">View '.$showReply.' additional '.$moreReplies.' on the community</a></h5></div>';
			}else{
				$showCommentsLayout .= '<div class="comment-reply-title"><h5><a href="'.$postUrl.'">View '.$showReply.' additional '.$moreReplies.' on the community</a></h5></div>';
			}
		}
		$showCommentsLayout .= '</div>';
	}
	echo $showCommentsLayout;
}
// throw this into your plugin or your functions.php file to define the custom comments template.
add_filter( "comments_template", "WebsiteToolboxForum\\wpse_plugin_comment_template" );
add_filter( "plugin_action_links_".plugin_basename(__FILE__), 'WebsiteToolboxForum\\addPluginSettingsLink' );
register_activation_hook( __FILE__, 'WebsiteToolboxAdmin\\onForumPluginActivation' );
register_deactivation_hook( __FILE__, 'WebsiteToolboxAdmin\\onForumPluginDeactivation' );
?>
