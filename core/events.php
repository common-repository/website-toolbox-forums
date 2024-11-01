<?php
/**
 * WP-WebsiteToolbox Events
 */
namespace WebsiteToolboxEvents;
use WebsiteToolboxForum;
use WebsiteToolboxAdmin;
use WebsiteToolboxInclude;
/* Purpose: If a user deleted from WordPress site then delete from Forum.
Parameter: None
Return: None */
function deleteForumUser($args) {
    global $wpdb;
    #get All the user id's deleted from wordpress site.
	#Condition added for pending users delete from buddypress
    if(isset($_POST['users'])){
        $userids = implode(",", $_POST['users']);
    }elseif (isset($_REQUEST['signup_ids'])){
        $userids = $_REQUEST['signup_ids'];
    }
 
    #Get username from users table on the basis of userids.
    $user_names = $wpdb->get_results( "
        SELECT user_login
        FROM $wpdb->users
        WHERE ID IN ($userids)" );
    $unames = array();
    foreach ( $user_names as $usernames ) {
        $unames[] = $usernames->user_login;
    }
    #create a comma(,) separated string to sent user delete request on the Forum.
    if ( $unames ) {
        $usernames = implode(",", $unames);
    }
    $forum_api      = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api");
    $forum_url      = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url");
    // create URL to to delete users from related Forum.
    $URL = $forum_url."/register";
    $fields = array(
            'apikey' => $forum_api,
            'massaction' => 'decline_mem',
            'usernames' => $usernames
        );
    // Send http or https request to get authentication token.
    $response_array = wp_remote_post($URL, array('method' => 'POST', 'body' => $fields));
    if(!is_wp_error( $response_array )) {
        $response = trim(wp_remote_retrieve_body($response_array));
        // Decode json string
        $response = json_decode($response);
        if($response->{'success'}) {
            return true;
        }
    }
}


/* Purpose: sent HTTP request for get authtoken if user try to login on the WordPress website.
Parameter: None
Return: None */
function getAuthtokenForLogin($user_login) {
    $userObj = new \WP_User(0,$user_login);
    $isSSOEnableForRole = WebsiteToolboxForum\isSsoEnableForUserRole($userObj);
    if ($isSSOEnableForRole && $isSSOEnableForRole == 1) {
        $responseArray = WebsiteToolboxForum\ssoHttpRequest($userObj);
        if(isset($responseArray['authtoken'])){
            $rememberMe = (isset($_POST['rememberme'])) ? $_POST['rememberme'] : '';
            WebsiteToolboxForum\setCookieOnLogin($responseArray, $rememberMe);
            #Save authentication token into session variable.
            WebsiteToolboxForum\saveAuthToken($responseArray['authtoken'],$responseArray['wtbUserid']);
            WebsiteToolboxForum\afterLoginRedirection($responseArray['authtoken']);
        }
        return true;
    }
}


/* Purpose: execute HTTP request to create an account on the related forum once user create account on the WordPress website.
Parameter: None
Return: None */
function createUserOnForum($userid) {
    $userObj = new \WP_User($userid);
    $isSSOEnableForRole = WebsiteToolboxForum\isSsoEnableForUserRole($userObj);
    if ($isSSOEnableForRole && $isSSOEnableForRole == 1) {
        $forum_api      = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api");
        $forum_url      = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url");
        if($forum_api) {
            $login_id = $userObj->ID;
            $login    = $userObj->user_login; # ie: JohnD223

            // pass1 used for password filed (WordPress Registration form) in older version (below 4.3).
            // pass1-text used for password filed in WordPress versions (4.3 and above).
            // Pass this password into SSO regisration request if not peresent then sent blank password.
            if (isset($_POST['pass1']) && $_POST['pass1'] != "") {
                $password = $_POST['pass1'];
            } elseif (isset($_POST['pass1-text']) && $_POST['pass1-text'] != "") {
                $password = $_POST['pass1-text'];
            } else {
                $password = '';
            }

            $email    = $userObj->user_email;
            $display_name = $userObj->display_name; # ie: John Doe
            $first_name = $userObj->first_name; # ie: John
            $last_name = $userObj->last_name; # ie: Doe
            $fullname = trim($first_name." ".$last_name);
            $externalUserid=$userObj->ID;

            // URL to create a new account on forum.
            $URL = $forum_url."/register/create_account";
            // Fields array.
            $fields = array(
                'apikey'         => $forum_api,
                'member'         => $login,
                'pw'             => $password,
                'email'          => $email,
                'name'           => $fullname,
                'avatar'         => WebsiteToolboxForum\getProfileImage($externalUserid),
                'externalUserid' => $externalUserid
            );
            // Sent https/https request on related forum to create an account on the related forum.
            $response_array = wp_remote_post($URL, array('method' => 'POST', 'body' => $fields));
            return true;
        }
    }
}


/* Purpose: sent logout request to forum once user logged-out from the WordPress website.
Parameter: none
Return: none */
function logoutUserOnForum() {
    if(!is_user_logged_in() && isset($_COOKIE[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wt_logout_token'])) {
        global $wp;
        // Preapred logut URL for forum and append redirect URL into it so that user redirected to related page after logout from the forum.
        $forum_url  = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url");
        //redirect to forum first to logout if forum is not on sub-domain
        if(WebsiteToolboxAdmin\getDomainName(get_site_url()) != WebsiteToolboxAdmin\getDomainName($forum_url)){
            $forum_url .= '/register/logout?redirect=';
            if(isset($_REQUEST['redirect_to'])) {
                $redirec_url = $_REQUEST['redirect_to'];
            } else if(isset($_REQUEST['wtbForumUrl'])) {
                $redirec_url = $_REQUEST['wtbForumUrl'];
            } else if(get_permalink() ) {
                $redirec_url = get_permalink();
            } else {
                $redirec_url = wp_get_referer();
                if(strpos($redirec_url, "action=logout")) {
                    $redirec_url = str_replace('action=logout', '', $redirec_url) ;
                }
            }
            //Remove (authtoken, wtlogin) parameters and values from the URL if exist in the URL after logout.
            $redirec_url = WebsiteToolboxForum\removeParam($redirec_url,"authtoken");
            $redirec_url = WebsiteToolboxForum\removeParam($redirec_url,"wtlogin");
            $redirec_url = urldecode($redirec_url);

            //Setting redirection URL based on frontend or backend logout
            //$word defined to look for string wp-admin in redirect_url
            $word="wp-admin";
            if(strpos($redirec_url, $word) !== false){
                $redirec_url = wp_login_url( $redirec_url );
            }else{
                $redirec_url = home_url();
            }

            $wtbLogoutUrl = $forum_url.urlencode($redirec_url);
            //If user is already logged out on forum
            WebsiteToolboxInclude\startForumSession();
            if(isset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_logout_referer']) && $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_logout_referer']!="" ) {
                $wtbLogoutUrl= home_url();
                unset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_logout_referer']);
            }
            WebsiteToolboxForum\resetCookieOnLogout();
            wp_redirect($wtbLogoutUrl);
            exit;
        }
    }
}


/* Purpose: Update user information the forum.
Parameter: logged-in userid, user data
Return: none */
function updateUserOnForum($wpProfileUserid, $oldUserData) {
    $userObj = new \WP_User($wpProfileUserid);
    $isSSOEnableForRole = WebsiteToolboxForum\isSsoEnableForUserRole($userObj);
    if ($isSSOEnableForRole && $isSSOEnableForRole == 1) {
        // Get logged-in userid of the WordPress website.
        $wpLoggedinUserid = get_current_user_id();
        // change updated userid if admin update other user profile else get update userid from the cookie.
        // WTB logged-in userid store in the cookie when user logged-in on the WordPress website.
        if($wpLoggedinUserid != $wpProfileUserid) {
            $wtbUserid =  WebsiteToolboxForum\getUserid($oldUserData->user_email);
        } else {
            $wtbUserid = $_COOKIE[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_login_userid'];
        }
        // Update user data on the forum.
        if($wpProfileUserid) {
            $userObj = new \WP_User($wpProfileUserid);
            $data =  array(
                "username"  => $userObj->user_login,
                "email"     => $userObj->user_email,
                "name"      => trim($userObj->first_name." ".$userObj->last_name),
                "avatarUrl" => WebsiteToolboxForum\getProfileImage($wpProfileUserid),
                'externalUserid' => $wpProfileUserid
            );
            WebsiteToolboxForum\apiRequest('POST', "/users/$wtbUserid", $data);
        }
    }
}
function redirectPrivateForum(){
    if(is_user_logged_in()){
        if (isset($_GET['from']) && $_GET['from'] == WebsiteToolboxForum\globalVariables::$WTBPREFIX.WebsiteToolboxForum\globalVariables::$loginParam) {
            $wpLoggedinUserid = get_current_user_id();
            $userObj = new \WP_User($wpLoggedinUserid);
            $isSSOEnableForRole = WebsiteToolboxForum\isSsoEnableForUserRole($userObj);
            if (!$isSSOEnableForRole) {
                get_header();
                ?>
                <div class="intro-text" style="text-align: center;vertical-align: middle;line-height: 90px;height:100%;">
                    <b>Sorry, you don’t have permission to log in to the community.</b>
                </div>
                <?php
                get_footer();
                exit();
            } else {
                // Redirected to forum with authtoken in case of authtoken exists but user dind't login on the forum via image tag. So that user logged-in on the forum as well.
                if(!isset($_GET['forumLoginFailed'])) {
                    if (isset($_COOKIE[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wt_logout_token'])) {
                        $forumSSOAuthToken = $_COOKIE[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wt_logout_token'];
                    } else {
                        $forumSSOAuthToken = WebsiteToolboxForum\loginAndSetCookies();
                    }
                    if ($forumSSOAuthToken) {
                        $loginUrlOnForumViaToken = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_url');
                        if ($loginUrlOnForumViaToken) {
                            if(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_redirect") != '') {
                                $loginUrlOnForumViaToken = get_permalink(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid'));
                                if(strpos($loginUrlOnForumViaToken, '?p=')) {
                                    $loginUrlOnForumViaToken .= "&p=";
                                } else {
                                    $loginUrlOnForumViaToken .= "?p=";
                                }
                                if(isset($_GET['requestURI'])) {
                                    $loginUrlOnForumViaToken = $loginUrlOnForumViaToken.$_GET['requestURI'];
                                }
                                $loginUrlOnForumViaToken = add_query_arg('authtoken', $forumSSOAuthToken, $loginUrlOnForumViaToken);
                            } else {
                                $loginUrlOnForumViaToken = add_query_arg('authtoken', $forumSSOAuthToken, $loginUrlOnForumViaToken);
                            }
                            header("Location: $loginUrlOnForumViaToken");
                            exit();
                        } else {
                            wp_die("No community address found.");
                        }
                    } else {
                        wp_die("Unable to generate the community's SSO auth token.");
                    }
                }
            }

        }
    }
}

//code for create post automatically on forum start
//code for classic editor
function publish_post_after_save($post_id,$post){ 
    global $current_screen;
    $defaultCategoryId  = '';
    $defaultContentType = '';
    $currentScreen      = $current_screen->is_block_editor; 
    $current_post_type  = get_post_type( $post_id );  
    if(!empty($_POST)){
        WebsiteToolboxForum\checkPostStatus($post_id, $post);
        if(isset($_POST['forumCategory'])){
            $defaultCategoryId = $_POST['forumCategory'];
        }
        if(isset($_POST['publishOnForum'])){
            $defaultContentType = $_POST['publishOnForum'];
        }
        WebsiteToolboxForum\addUpdateTopic($defaultCategoryId,$defaultContentType,$post);
    }
}

//code for block editor
function createHyperlink($post, $request, $creating = false){
    $post_id            = $post->ID;
    $publish_status     = $post->post_status;
    if($publish_status == 'publish'){
        $defaultCategoryId  = get_post_meta($post_id,'website_toolbox_forum_category',true); 
        $defaultContentType = get_post_meta($post_id,'website_toolbox_publish_on_forum',true); 
        $current_post_type  = get_post_type( $post_id );    
        if($defaultCategoryId == ''){
            if($current_post_type == 'page'){
                $defaultCategoryId = get_option("websitetoolbox_page_category");
            }else{
                $defaultCategoryId = get_option("websitetoolbox_post_category");
            }
        } 
        WebsiteToolboxForum\addUpdateTopic($defaultCategoryId,$defaultContentType,$post);
    }
}

?>
