<?php
/**
 * WP-WebsiteToolbox admin settings
 */
namespace WebsiteToolboxAdmin;
use WebsiteToolboxForum;
use WebsiteToolboxInclude;
include("websitetoolbox_sidebar.php");
function userRolesScript() {
    wp_enqueue_script( 'websitetoolbox-js', plugins_url( '/js/websitetoolbox.js', __FILE__ ));
    wp_enqueue_style( 'websitetoolbox-css', plugins_url( '/css/websitetoolbox.css', __FILE__ ));
}
/* Purpose: this function returns string which is domain of provided URL
Parameter: URL
Return: string */
function getDomainName($url){
  //if $url is empty return $url
  if(empty($url)){
    return $url;
  }
  $parsedURL = parse_url($url);
  $domain = isset($parsedURL['host']) ? $parsedURL['host'] : $parsedURL['path'];
  if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
    return $regs['domain'];
  }
}

/* Purpose: this function return boolean if referral url and forum address matched
Parameter: None
Return: true or false */
function isForumRefererUrl() {
    if(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url") !== false){
        return parse_url(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url"), PHP_URL_HOST) === parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    }else{
        return false;
    }
}

/* Purpose: this function sets session variable if referral url matches with forum address
Parameter: None
Return: true or false */
function checkReferelUrlOnLogin() {
    //to check if wp-login page contains action query parameter
    $action='';
    $currentURL= $_SERVER['REQUEST_URI'];
    //variable defined to search for action in current URL
    $searchAction='action';
    if(strpos($currentURL, $searchAction) !== false){
        $action=$_GET['action'];
    }
    //If user logged out from forum and redirected to wp logout page
    if($action=="logout"){
        if ( isset($_SERVER['HTTP_REFERER']) && isForumRefererUrl()) {
            WebsiteToolboxInclude\startForumSession();
            $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_logout_referer']= $_SERVER['HTTP_REFERER'];
        }
    }
    if($action!="logout" && $action!="lostpassword"){
        if ( isset($_SERVER['HTTP_REFERER']) && isForumRefererUrl()) {
            WebsiteToolboxInclude\startForumSession();
            $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_login_referer']= $_SERVER['HTTP_REFERER'];
        }
    }
}
/* Purpose: this function getForumSettings used to activate the plugin.
Parameter: None
Return: None */
function onForumPluginActivation($condition = '') {
    // Start Session if not started previously.
    WebsiteToolboxInclude\startForumSession();
    // If page is already exist (in draft) then publish same page.
    $websitetoolboxpage_id = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
    if($websitetoolboxpage_id) {
        $wtbPost = get_post($websitetoolboxpage_id);
        if($wtbPost) {
            $wtbPostData = array(
                'ID'           => $websitetoolboxpage_id,
                'post_status'   => 'publish'
            );
            wp_update_post($wtbPostData);
        }
    }
    $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbPluginActivated'] = 1;
    if($condition == ''){
        add_option( WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_activation_redirect', true );
    }

    //To update domain on plugin activation
    if(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username") && get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api")) {

            $reinstallResponseArray = httpRequestForUninstallReinstallPlugin('reinstallPlugin');

            // Update secret key on plugin reactivation
            if(!is_wp_error( $reinstallResponseArray )) {
                $reinstallResponse = trim(wp_remote_retrieve_body($reinstallResponseArray));
                $reinstallResponse = json_decode($reinstallResponse);
                if(isset($reinstallResponse->{'secretKey'})){
                    $newSecretKey = $reinstallResponse->{'secretKey'};
                    update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wt_secret_key', $newSecretKey);  
                }
            }

            $response_array = validateAPIKeyCall();
 
            $newDomain = '';
            if(!is_wp_error( $response_array )) {
                $response = trim(wp_remote_retrieve_body($response_array));
                $response = json_decode($response);
                $newDomain = $response->{'forum_address'};
                $altEmbedParam=$response->{'altEmbedParam'};
                // Update domain if changed
                updateDomainViaSQL($newDomain,$altEmbedParam);
            }
        }
}
/**
 * Redirects the user after plugin activation
 */
function redirectAfterActivation() {
    if(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username")){
        $page = WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxUpdateOptions';
    }else{
        $page = WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxoptions';
    }
    // Make sure it's the correct user
    if ( get_option( WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_activation_redirect', false ) ) {
        // Make sure we don't redirect again after this one
        delete_option( WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_activation_redirect' );
        wp_safe_redirect( admin_url( 'options-general.php?page='.$page ) );
        exit;
    }
}
/* Purpose: this function is used to de-activate the plugin.
Parameter: None
Return: None */
function onForumPluginDeactivation() {
    // hide the Website Toolbox forum page if it exists
    $websitetoolboxpage_id = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
    if($websitetoolboxpage_id) {
        $wtbPost = get_post($websitetoolboxpage_id);
        if($wtbPost) {
            $wtbPostData = array(
                'ID'           => $websitetoolboxpage_id,
                'post_status'   => 'draft'
            );
            wp_update_post($wtbPostData);
        }
    }

    if(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username")){
        httpRequestForUninstallReinstallPlugin('uninstallPlugin');
    }    
}

/* Purpose: function is used to create Website Toolbox page on the WordPress website with default title "Community"
Parameter: None
Return: None */
function createForumPage() {
    $my_post = array();
    //get post data on the basis of postid.
    $forumPostId = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
    $forumPostData = get_post($forumPostId);
    // Use default post titile "Community" if not already added by the client.
    // If client alrady added embed code or custom link on his website then didn't created new post.
    if($forumPostData) {
        $my_post['post_title']  = $forumPostData->post_title;
    } else {
        $my_post['post_title']  = WebsiteToolboxForum\globalVariables::$PAGENAME;
    }
    $my_post['post_content']    = "Please go to the admin section and change your Website Toolbox Community settings.";
    $my_post['post_status']     = 'publish';
    $my_post['post_author']     = 1;
    $my_post['post_category']   = array(1);
    $my_post['post_type']       = 'page';
    $my_post['comment_status']  = 'closed';
    $my_post['ping_status']     = 'closed';
    $postId = wp_insert_post( $my_post );
    return $postId;
}

/* Purpose: Create an 'Forums by Website Toolbox' settings menu under settings tab into WordPress admin menu.
Parameter: None
Return: None */
function showForumDashboardMenu() {
    $wtbMenuName = 'Website Toolbox Community';
    if(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username')) {
        add_options_page( 'Website Toolbox', $wtbMenuName, 'manage_options', WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxUpdateOptions', 'WebsiteToolboxAdmin\\onForumUpdateSettings');
        $wtbMenuName = '';
    }
    add_options_page( 'Website Toolbox', $wtbMenuName, 'manage_options', WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxoptions', 'WebsiteToolboxAdmin\\onForumDashboardLogin');
}

/* Start plugin login option page */

/* Purpose: Create an Website Toolbox Login options page to set forum settings into WordPress admin section.
Parameter: None
Return: None */

function showForumDashboardLoginPage() {
    // To show plugin login description on Websitetoolbox login page on WordPress admin panel.
    add_settings_section(
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_settings_section',
        '',
        '',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxoptions'
    );

    // To show forum username option on forum login settings page on WordPress admin panel.
    add_settings_field(
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username',
        'Email Address or Website Toolbox Username',
        'WebsiteToolboxAdmin\\dashboardLoginUsername',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxoptions',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_settings_section'
    );
    // To show forum password option on forum login settings page on WordPress admin panel.
    add_settings_field(
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_password',
        'Website Toolbox Password',
        'WebsiteToolboxAdmin\\dashboardLoginPassword',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxoptions',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_settings_section'
    );
    // To show 'Log In' button on forum login settings page on WordPress admin panel.
    add_settings_field(
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_Login',
        '',
        'WebsiteToolboxAdmin\\dashboardLoginCreateAccountOption',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxoptions',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_settings_section'
    );
}

/* Purpose: Add description on forum login settings page into WordPress admin section.
Parameter: None
Return: None */
function dashboardLoginDescription() {
    $loginDescription  = '<h2>Website Toolbox Community</h2>';
    $loginDescription .= '<b>Please log in to your Website Toolbox account to enable the plugin.</b>';
    $loginDescription .= '<p>Not a Website Toolbox Community owner? <a href="https://www.websitetoolbox.com/wordpress" target="_blank">Create A Community Now!</a></p>';
    $loginDescription .= '<p>Please <a href="https://www.websitetoolbox.com/contact?subject=WordPress+Plugin+Setup+Help" target="_blank">contact customer support</a> if you need help getting set up.</p>';
    echo $loginDescription;
}

/* Purpose: Add username option on Forum login settings page into WordPress Forum settings page.
Parameter: None
Return: None */
function dashboardLoginUsername($args) {
    $websitetoolbox_username = isset($_POST['websitetoolbox_username']) ? $_POST['websitetoolbox_username'] : get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username');
    $html = '<input type="text" name="websitetoolbox_username" id="websitetoolbox_username" value="'.$websitetoolbox_username.'" size="30"/>';
    $html .= '<span class="custom-error">Please enter your Website Toolbox username.</span>';
    echo $html;
}

/* Purpose: Add password option on Forum login settings page into WordPress Forum settings page.
Parameter: None
Return: None */
function dashboardLoginPassword($args) {
    $html = '<input type="password" name="websitetoolbox_password" id="websitetoolbox_password" value="" size="30"/>';
    $html .= '<span class="custom-error">Please enter your Website Toolbox password.</span><span class="forgot-password-link"><a href="https://www.websitetoolbox.com/tool/members/reset-password" style="text-decoration:none;" target="_blank">Forgot password?</a></span>';
    echo $html;
}
function dashboardUserRolesMultiSelect($args){
    global $wp_roles;
    $userType = '';
    $userRoles = array();
    if (!isset($wp_roles)){
        $wp_roles = new WP_Roles();
    }
    $roles          = $wp_roles->get_names();
    $getUserRoles   = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_user_roles');
    $userRoles      = (array) json_decode($getUserRoles);

    if($userRoles){
        $userType       = $userRoles['users'];
    }
    $checked        = 0;
    if($userType != 'no_users'){
        $showMessage = "block";
    }else{
        $showMessage = "none";
    }
    $userRoleshtml         = '<div class="wt-custom-btn-group" data-custom-tooltip="Specify if all users, no users, or users in the selected roles are automatically signed up and signed in to your forum when they sign in to your WordPress website.">';
    if ($userType == 'all_users') {
        $checked = 1;
        $userRoleshtml .= '<input type="radio" id="all_users" name="sso_setting" value="all_users" checked>';
    } else {
        $userRoleshtml .= '<input type="radio" id="all_users" name="sso_setting" value="all_users">';
    }
    $userRoleshtml .= '<label for="all_users" class="button">All Users</label>';
    if ($userType == 'no_users') {
        $userRoleshtml .= '<input type="radio" id="no_users" name="sso_setting" value="no_users"  checked>';
    } else {
        $userRoleshtml .= '<input type="radio" id="no_users" name="sso_setting" value="no_users">';
    }
    $userRoleshtml .= '<label for="no_users" class="button">No Users</label>';
    if (($userType != 'all_users') && ($userType != 'no_users')) {
        $userRoleshtml .= '<input type="radio" id="selected_roles" name="sso_setting" value="selected_roles" checked>';
        $displayCheckbox= "block";
    } else {
        $userRoleshtml .= '<input type="radio" id="selected_roles" name="sso_setting" value="selected_roles">';
        $displayCheckbox= "none";
    }
    $userRoleshtml .= '<label for="selected_roles" class="button">Selected Roles</label></div><br />';
    $strUserRoles = '';
    foreach ($userRoles as $userRolesValue) {
        $strUserRoles .= $userRolesValue . ",";
    }
    $strUserRoles = substr($strUserRoles, 0, strlen($strUserRoles) - 1);
    $userRoleshtml .= '<input type="hidden" name="hiddenUserRoles" id="hiddenUserRoles" value ="' . $strUserRoles . '">';

    $userRoleshtml .= '<div style="float:left;display:' . $displayCheckbox . ';" id="allOptions"><br /><input type="checkbox" value="all_user_Select" id="user_roles[]" name="user_roles[]"' . checked(1, $checked, false) . '>Select/Deselect All Roles';
    $userRoleshtml .= '<fieldset class="group"><ul class="checkbox">';
    $i = 0;
    foreach ($roles as $role_value => $role_name) {
        if (is_array($userRoles)) {
            $key = array_search(strtolower($role_value), $userRoles);
        }
        if (is_numeric($key)) {
            $userRoleshtml .='<li><input type="checkbox" id="user_roles[]" value="' . $role_value . '" name="user_roles[]" checked>' . $role_name . '</li>';
        } else {
            $userRoleshtml .= '<li><input type="checkbox" id="user_roles[]" value="' . $role_value . '" name="user_roles[]">' . $role_name . '</li>';
        }
        $i++;
    }
    $userRoleshtml .= '</ul>';
    $userRoleshtml .= '</fieldset></div>';
    if(!get_option('users_can_register')) {
            $userRoleshtml .= '<div style="float:left;display:'.$showMessage.'" id="showmessage" ><p style="margin-top:15px;">If your website has a log in or sign up page, please <a href="https://www.websitetoolbox.com/tool/members/mb/settings?tab=Single+Sign+On">specify them in your community settings</a> to finish setting up Single Sign On.</p></div>';
    }

    echo $userRoleshtml;
}
/* Purpose: Add "Log In" option on Forum login settings page into WordPress Forum settings page.
Parameter: None
Return: None */
function dashboardLoginCreateAccountOption($args) {
    $html = submit_button('Log In', 'primary', 'submit', false);
    $html .= '<span class="create-an-account-link">or<a href="https://www.websitetoolbox.com/tool/members/signup?tool=mb&name=" target="_blank">Create an account</a></span>';
    echo $html;
}

/* Purpose: This function is used to show forum login settings page. Also show error message if any.
Param: none
Return: Nothing */
function forumSettingsPage($message = "") {
    ?>
    <!-- create a form in the wordpress admin panel -->
    <div class="wrap">
        <form name="form_lol" action="options-general.php?page=<?php echo(WebsiteToolboxForum\globalVariables::$WTBPREFIX); ?>websitetoolboxoptions" method="POST" onsubmit="return ValidateForm();" class="custom-login-page"><?php
            dashboardLoginDescription();
            if($message == 'username' || $message == 'password') {
                echo "<div id='wtb-warning' class='error'  style='margin-top:20px !important;'><p>Please enter your Website Toolbox $message. </p></div>";
            } else if($message && $message != 'success') {
                $message = rtrim($message,'.');
                if(strpos($message,'username')){
                    echo "<div id='wtb-warning' class='error' style='margin-top:20px !important;'><p>$message or <a href = 'https://www.websitetoolbox.com/tool/members/signup?tool=mb&name=' target='_blank'>create an account.</a></p></div>";
                }else{
                    echo "<div id='wtb-warning' class='error'  style='margin-top:20px !important;'><p>$message or <a href = 'https://www.websitetoolbox.com/tool/members/reset-password?username=' target='_blank'>reset password.</a></p></div>";
                }
            }
            do_settings_sections( WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxoptions' ); ?>
        </form>
    </div>
    <?php
}
/* End plugin login page. */

/* Purpose: Validate login and add/update data in the WordPress database once login is validated.
Param: none
Return: Nothing */
function onForumDashboardLogin() {
    // Start Session if not started previously.
    WebsiteToolboxInclude\startForumSession();
    if($_POST) {
        global $wpdb;
        // Check if username is blank then show error message.
        if($_POST['websitetoolbox_username'] == "") {
            forumSettingsPage('username');
            exit;
        }
        // Check if password is blank then show error message.
        if($_POST['websitetoolbox_password'] == "") {
            forumSettingsPage('password');
            exit;
        }
        // Validate the forum if submit the form after fill website toolbox username and password.
        if(isset($_POST['websitetoolbox_username']) && isset($_POST['websitetoolbox_password'])) {
            // Remove all white space from the username.
            $_POST['websitetoolbox_username'] = preg_replace('/\s+/', '', $_POST['websitetoolbox_username']);
            // Validate login information (Website Toolbox) provided by the user.
            $response_array = checkPluginLogin($_POST['websitetoolbox_username'], $_POST['websitetoolbox_password']);
            //Show error message if any error message return in the response other wise stire forum address and API key in a varible het in the JSON response.
            if(!is_wp_error( $response_array )) {
                $response = trim(wp_remote_retrieve_body($response_array));
                $response = json_decode($response);
                if(wp_remote_retrieve_response_code($response_array) != 200 || $response->{'errorMessage'}) {
                    if(wp_remote_retrieve_response_code($response_array) == 403) {
                      $ip = file_get_contents('https://api.ipify.org');
                      $errorMessage = "Your IP address ($ip) is blocked. Please <a href='https://www.websitetoolbox.com/contact?subject=WordPress+IP($ip)+Blocked' target='_blank'>Contact Website Toolbox</a>.";
                    } elseif (wp_remote_retrieve_response_code($response_array) != 200) {
                      $errorMessage = wp_remote_retrieve_response_message($response_array);
                    } else {
                      #show error message to the user.
                      $errorMessage = $response->{'errorMessage'};
                      $errorMessage = preg_replace('#^Error:#', '', $errorMessage);
                    }
                    forumSettingsPage($errorMessage);
                    exit;
                } else {
                    $forum_address = $response->{'forumAddress'};
                    $forumApiKey  = $response->{'forumApiKey'};
                    unsetWarnings($response);
                }
            }
            $firstSetup = 0;
            # remove the backslash at the end for consistency
            $forum_address = preg_replace('#/$#', '', $forum_address);
            $wtSecretKey = $response->{'secretKey'};
            // Update forum address in WordPress in case of forum is already integrated and forum domain changed.
            if(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username") && get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api") && get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url")) {
                if($forum_address != ''){
                    if($forum_address != get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url")) {
                        update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url", $forum_address);
                        $wtbForumpageid = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
                        update_post_meta( $wtbForumpageid, WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to', $forum_address );
                    }else{
                        $wtbForumpageid = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
                    }
                }

            }
            if(!get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_user_roles')){
                global $wp_roles;
                $all_roles = $wp_roles->roles;
                $allUser   = array(
                    "users" => "all_users"
                );
                foreach ($all_roles as $key => $item) {
                    $roles[] = $key;
                }
                $userArray = array_merge($allUser, $roles);
                $userRoles = json_encode($userArray);
                update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_user_roles', $userRoles);
            }
            // If forum already integrated then used same page instead of creating a new page.
            $wtbExist = 1;

            // If forum didn't already integrated on the WordPress Website page/post or custom link then create anew page for the forum.
            if(!$wtbForumpageid) {
                $wtbExist = 0;
                $wtbForumpageid = createForumPage();
            } else {
                onForumPluginActivation('settings');
            }
            // get page/post data.
            $pageData = get_post($wtbForumpageid);
            // Preapre array to save data (forum pageid, username, api key and forum address) in the WordPress database for future use.
            $wtbOptions = array(
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid' => $wtbForumpageid,
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username' => $_POST['websitetoolbox_username'],
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_api' => $forumApiKey,
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_url' => $forum_address,
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wt_secret_key' => $wtSecretKey
            );
            // if page is embedded then add/update it as embedded otherwise as a normal link.
            $embedPageUrl;
            if($pageData->post_type == 'nav_menu_item') {
                $wtbOptions = array_merge($wtbOptions, array(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_redirect"=>''));
            } else {
                $embedPageUrl = $pageData->guid;
                $wtbOptions = array_merge($wtbOptions, array(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_redirect"=>1));
            }
            // Add/Update forum information (username, api key, forum address and embed or not) in the WordPress database.
            if(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username")) {
                foreach ($wtbOptions as $key => $item) {
                    update_option($key, $item);
                }
            } else {
                foreach ($wtbOptions as $key => $item) {
                    add_option($key, $item);
                }
                $firstSetup = 1;
            }

            $websitetoolbox_url = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url");
            #preapre array to add/update data in "PREFIX_postmeta" table in WordPress.
            $wtbMetaValue = array(
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to' => $websitetoolbox_url,
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to_target' => WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox',
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to_type' => 'custom_post_type',
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_wtbredirect_active'=> '1',
                '_wp_page_template' => 'full-width-page.php'
            );
            // Added data in "PREFIX_postmeta" table if not exist, if exist then update it.
            if(sizeof(get_post_meta( $wtbForumpageid))) {
                foreach ($wtbMetaValue as $key => $item) {
                    update_post_meta( $wtbForumpageid, $key, $item );
                }
            } else {
                foreach ($wtbMetaValue as $key => $item) {
                    add_post_meta( $wtbForumpageid, $key, $item );
                }
            }
            // Appended http with the forum address if not alray added.
            if(preg_match('#^https?://#', get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_url'))) {
                $wtb_url = $websitetoolbox_url;
            } else {
                $wtb_url = "http://".$websitetoolbox_url;
            }
            // Added/update forum page information in case of embedded and non-embedded.
            if($embedPageUrl) {
                $response_array = updateForumData($_POST['websitetoolbox_username'], $forumApiKey, $embedPageUrl);
                $wtb_embed_url = preg_replace('#^https?:#i', '', $wtb_url);
                $altEmbedParam = json_decode($response_array['body']);
                $altEmbedParam = $altEmbedParam->{'altEmbedParam'};
                #Set default title if not exist..
                if($pageData->post_title) {
                    $postTitle = $pageData->post_title;
                } else {
                    $postTitle = WebsiteToolboxForum\globalVariables::$PAGENAME;
                }
                if($altEmbedParam){
                    // Get page content and apended embed code into it if some other content already does exist on the page instead of embed code.
                    $postContent = '';
                    if($pageData->post_content && strpos($pageData->post_content, $wtb_embed_url) !== false) {
                        $postContent = str_replace('Embed Code',$pageData->post_content,'<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js" data-version="1.1"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>');
                    }
                    if($postContent) {
                        $postContent = str_replace('Embed Code', '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js" data-version="1.1"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>',$postContent);
                    } else {
                        $postContent = '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js" data-version="1.1"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>';
                    }
                }else{
                    // Get page content and appended embed code into it if some other content already exist on the page instead of embed code.
                    if($pageData->post_content && strpos($pageData->post_content, $wtb_embed_url) !== false) {
                        $postContent = str_replace('Embed Code',$pageData->post_content,'<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>');
                    }
                    if($postContent) {
                        $postContent = str_replace('Embed Code', '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>',$postContent);
                    } else {
                        $postContent = '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>';
                    }
                }

                $postData = array(
                    'ID'           => $wtbForumpageid,
                    'post_title'   => $postTitle,
                    'post_content' => $postContent
                );
                wp_update_post($postData);
                update_post_meta( $wtbForumpageid, WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_wtbredirect_active', '' );
            } else {
                #open forum in new tab independently.
                update_post_meta( $wtbForumpageid, WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_wtbredirect_active', '1' );
            }
        }
        if($wtbExist) {
            $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings'] = 3;
        } else {
            $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings'] = 4;
        }
        unset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbPluginActivated']);
        wp_redirect("options-general.php?page=".WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolboxUpdateOptions");
        exit;
    }
    forumSettingsPage();
    ?>
    <script language="javascript">
        /* validate admin form information */
       function ValidateForm(){
            var websitetoolbox_username = document.getElementById('websitetoolbox_username').value;
            var websitetoolbox_password = document.getElementById('websitetoolbox_password').value;
            if(websitetoolbox_username=="") {
                document.getElementById('websitetoolbox_username').focus();
                document.getElementById('websitetoolbox_username').classList.add('has-error');
                return false;
            }
            if(websitetoolbox_password=="") {
                document.getElementById('websitetoolbox_password').focus();
                document.getElementById('websitetoolbox_password').classList.add('has-error');
                return false;
            }
     }
    </script>
    <?php
}

/* Purpose: to show settigns field into forum plugin settinga page.
Parameter: None
Return: None */
function showForumDashboardUpdatePage() {
    // To show settings description on Websitetoolbox settings page on WordPress admin panel.
    add_settings_section(
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_settings_update_section',
        '<h2>Website Toolbox Community</h2>',
        'WebsiteToolboxAdmin\\forumDashboardUpdateDescription',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxUpdateOptions'
    );
    // To show forum username as a text on forum settings page on WordPress admin panel.
    add_settings_field(
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxUsername',
        'Account',
        'WebsiteToolboxAdmin\\forumDashboardUpdateUsername',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxUpdateOptions',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_settings_update_section'
    );
    // To show forum embed option on forum settings page on WordPress admin panel.
    add_settings_field(
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect',
        'Embedded ',
        'WebsiteToolboxAdmin\\forumDashboardUpdateEmbedOption',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxUpdateOptions',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_settings_update_section'
    );
    add_settings_field(
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_user_roles',
        'Single Sign On',
        'WebsiteToolboxAdmin\\dashboardUserRolesMultiSelect',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxUpdateOptions',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_settings_update_section'
    );
    // To show embedded comments feature on forum settings page of WordPress admin panel.
    add_settings_field(
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_embeddComments',
        'Embedded Comments ',
        'WebsiteToolboxAdmin\\forumDashboardUpdateEmbeddedCommentsOption',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxUpdateOptions',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_settings_update_section'
    );
    // To show "Update" button on forum settings page on WordPress admin panel.
    add_settings_field(
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_update',
        '',
        'WebsiteToolboxAdmin\\forumDashboardUpdateOption',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxUpdateOptions',
        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_settings_update_section'
    );
}

/* Purpose: to show description on plugin settings page.
Parameter: None
Return: None */
function forumDashboardUpdateDescription() {
    echo '<p class="description">Please <a href="https://www.websitetoolbox.com/contact?subject=WordPress+Plugin+Setup+Help" target="_blank">Contact Customer Support</a> if you need help getting set up.</p>';
}

/* Purpose: to show forum username as text and "Change" link on the forum plugin settings page.
Parameter: None
Return: None */
function forumDashboardUpdateUsername($args) {
    $websitetoolbox_username = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username');
    $html = $websitetoolbox_username.'&nbsp;&nbsp;<a href="options-general.php?page='.WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxoptions">Change</a>';
    echo $html;
}

/* Purpose: to show embed option as a check-box on the forum plugin settings page.
Parameter: None
Return: None */
function forumDashboardUpdateEmbedOption($args) {
    // enable Embed option when user install the plugin.
    if(!get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username')) {
        $checked = 1;
    } else {
        $checked = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect');
    }
    $html = '<input type="checkbox" name="'.WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect" id="'.WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect" value="1" ' . checked(1, $checked, false) . '/>';
    $html .= '<label for="'.WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect">Yes</label>';
    $html .= '<p class="description" style="padding-top: 7px;">Enable this option to have your community embedded in a page of your website.</p>
    <p class="description">Disable this option to have your community load in a full-sized window. You can use the Layout section in your Website Toolbox account to <a href="https://www.websitetoolbox.com/support/making-your-forum-layout-match-your-website-148" target="_blank">customize your community layout to match your website</a> or <a href="https://www.websitetoolbox.com/contact?subject=Customize+Forum+Layout" target="_blank">contact Website Toolbox support to customize it for you</a>.</p>';
    echo $html;
}

/* Purpose: to show embedded comments feature on the forum plugin settings page.
Parameter: None
Return: None */
function forumDashboardUpdateEmbeddedCommentsOption() {

    $html = '<p class="embedCommentsdescription" >While publishing a new page or blog post, you can automatically  create a discussion topic in your community and embed the comments using this icon:</p>
    <img width="250" height="200" src="../wp-content/plugins/website-toolbox-forums/admin/images/embedComments.png" style="padding-top:10px">';
    echo $html;

}

/* Purpose: To show update button on the forum plugin settings page.
Parameter: None
Return: None */
function forumDashboardUpdateOption($args) {
    $html = submit_button('Update');;
    echo $html;
}

/* Purpose: To show plugin settigns page as well as error and success message.
Parameter: None
Return: None */
function showForumUpdateSettingsPage($message = "") {
    // Start Session if not started previously.
    WebsiteToolboxInclude\startForumSession();
    $pid = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
    $showMessageInFooter = 1;
    if(isset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings']) && $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings'] == 3) {
        unset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings']);
        $showMessageInFooter = 0;
        echo '<div id="setting-error-settings_updated" class="updated notice"><p>Congrats! Single Sign On between your website and community has been enabled.</p></div>';
    } else if(isset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings']) && $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings'] == 4) {
        unset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings']);
        $showMessageInFooter = 0;
        $wtbForumpageid = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
        $wtbPageData = get_post($wtbForumpageid);
        $embedPageUrl = $wtbPageData->guid;
        echo '<div id="setting-error-settings_updated" class="updated notice"><p>Congrats! Your community has been embedded into your website, a new <a href='.$embedPageUrl.' target="_blank">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a> link has been added to your website navigation, and Single Sign On between your website and community has been enabled.</p></div>';
    } else if($message == '0') {
        echo '<div id="setting-error-settings_updated" class="updated notice"><p>Your community needs to <a href= "https://www.websitetoolbox.com/tool/members/domain?tool=mb" target="_blank">use a subdomain</a> for it to appear as embedded instead of full-screen in the Safari browser.</p></div>';
    } else if($message == 'success') {
        echo '<div id="setting-error-settings_updated" class="updated notice"><p>Your settings have been updated.</p></div>';
    }
    ?>
    <!-- create a form in the wordpress admin panel -->
    <div class="wrap">
        <form name="form_lol" action="options-general.php?page=<?php echo(WebsiteToolboxForum\globalVariables::$WTBPREFIX); ?>websitetoolboxUpdateOptions" method="POST" >
            <?php
              do_settings_sections( WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolboxUpdateOptions' );
            ?>
        </form>
    </div>
    <?php
}

/* Purpose: Add/update data in WordPress as well as update data SSO URLS on the forum via HTTP request.
Parameter: None
Return: None */
function onForumUpdateSettings() {
    if(!get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_user_roles')){
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        $allUser   = array(
            "users" => "all_users"
        );
        foreach ($all_roles as $key => $item) {
            $roles[] = $key;
        }
        $userArray = array_merge($allUser, $roles);
        $userRoles = json_encode($userArray);
        update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_user_roles', $userRoles);
    }
    if($_POST) {
        global $wpdb;
        $embedPageUrl;
        $userRoles = '';
        // Get forum address once update plugin settings and if the forum address is different then update it in the wordpress database.
        if(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username") && get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api")) {
            // Create an arry of parameters
            $response_array = validateAPIKeyCall();
            // Print warning if get any API key not match.
            $websitetoolbox_url = '';
            if(!is_wp_error( $response_array )) {
                $response = trim(wp_remote_retrieve_body($response_array));
                $response = json_decode($response);
                $websitetoolbox_url = $response->{'forum_address'};
                $altEmbedParam=$response->{'altEmbedParam'};
                unsetWarnings($response);
            }
        }
        if($websitetoolbox_url != get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url") && $websitetoolbox_url != '' ) {
            update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url", $websitetoolbox_url);
        }

        // Preapre array to set forum username, API and embed option.
        $wtbOptions = array(
            WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username' => get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username'),
            WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_api' => get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_api')
        );
        $websitetoolbox_url      = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url");
        $wtbForumpageid = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
        if(preg_match('#^https?://#', get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_url'))) {
            $wtb_url = $websitetoolbox_url;
        } else {
            $wtb_url = "http://".$websitetoolbox_url;
        }
        $pageData = get_post($wtbForumpageid);
        if ($_POST['sso_setting'] == 'no_users') {
            $noUser    = array(
                "users" => "no_users"
            );
            $userRoles = json_encode($noUser);
            update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_user_roles', $userRoles);
        } else{
            if (isset($_POST['user_roles'])) {
               if ($_POST['sso_setting'] == 'all_users') {
                    $allUser   = array(
                        "users" => "all_users"
                    );
                    $userArray = array_merge($allUser, $_POST['user_roles']);
                    $userRoles = json_encode($userArray);
                } else {
                    $selectedUser = array(
                        "users" => "selected_users"
                    );
                    $userArray    = array_merge($selectedUser, $_POST['user_roles']);
                    $userRoles    = json_encode($userArray);
                }
            }
            update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_user_roles', $userRoles);
        }

        //To update slug if doesn't match with page-slug
        $currentGUID=get_page_by_path(basename($pageData->guid));
        if(!$currentGUID){
            if(basename($pageData->guid) != $pageData->post_name){
                wp_update_post(
                    array (
                        'ID'        => $pageData->ID,
                        'post_name' => basename($pageData->guid)
                    )
                );  
            }
        }

        // Add/update data if embeded option is enable.
        if(isset($_POST[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect'])) {
            if($pageData === NULL)
            {
                $wtbForumpageid = createForumPage();
                update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid', $wtbForumpageid);
                $pageData = get_post($wtbForumpageid);
            }
            $postTitle = $pageData->post_title;
            if($wtbForumpageid) {
                onForumPluginActivation('settings');
            }
            // Create a new page for forum if forum is alrady integrated as a custom link on WordPress website.
            // User save settings after enable "Embed" option. So that didn't effect on custom link.
            if($pageData->post_type == 'nav_menu_item') {
                $wtbForumpageid = createForumPage();
                // Update page id in options table
                update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid', $wtbForumpageid);
                #check on post meta
                $wtbMetaValue = array(
                    WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to' => $websitetoolbox_url,
                    WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to_target' => WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox',
                    WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to_type' => 'custom_post_type',
                    WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_wtbredirect_active'=> '1',
                    '_wp_page_template' => 'full-width-page.php'
                );
                if(sizeof(get_post_meta( $wtbForumpageid))) {
                    foreach ($wtbMetaValue as $key => $item) {
                        update_post_meta( $wtbForumpageid, $key, $item );
                    }
                } else {
                    foreach ($wtbMetaValue as $key => $item) {
                        add_post_meta( $wtbForumpageid, $key, $item );
                    }
                }
                $pageData = get_post($wtbForumpageid);
                $postTitle = WebsiteToolboxForum\globalVariables::$PAGENAME;
            }
            // Add/update embed code for the forum page on WordPress.
            $embedPageUrl = $pageData->guid;
            $wtb_embed_url = preg_replace('#^https?:#i', '', $wtb_url);

            if($altEmbedParam){
                // Get page content and apended embed code into it if some other content already does exist on the page instead of embed code.
                $postContent = '';
                if($pageData->post_content && strpos($pageData->post_content, $wtb_embed_url) !== false) {
                    $postContent = str_replace('Embed Code',$pageData->post_content,'<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js" data-version="1.1"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>');
                }
                if($postContent) {
                    $postContent = str_replace('Embed Code', '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js" data-version="1.1"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>',$postContent);
                } else {
                    $postContent = '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js" data-version="1.1"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>';
                }
            }else{
                // Get page content and apended embed code into it if some other content already does exist on the page instead of embed code.
                $postContent = '';
                if($pageData->post_content && strpos($pageData->post_content, $wtb_embed_url) !== false) {
                    $postContent = str_replace('Embed Code',$pageData->post_content,'<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js" data-version="1.1"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>');
                }
                if($postContent) {
                    $postContent = str_replace('Embed Code', '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>',$postContent);
                } else {
                    $postContent = '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>';
                }
            }

            $postData = array(
                'ID'           => $wtbForumpageid,
                'post_title'   => $postTitle,
                'post_content' => $postContent
            );
            $isforumEmbedOnHomePage = isForumEmbeddedOnHomePage();
            $response_array = updateForumData(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username'), get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_api'), $embedPageUrl, $isforumEmbedOnHomePage);
            update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect', 1);
            wp_update_post($postData);
            update_post_meta( $wtbForumpageid, WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_wtbredirect_active', '' );
        } else {
            $isforumEmbedOnHomePage = isForumEmbeddedOnHomePage();
            #Remove embed code and set it as a link if "embed" option is disabled.
            $response_array = updateForumData(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username'), get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_api'), '', $isforumEmbedOnHomePage);
            update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect', '');
            $wtbMetaValue = array(
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to' => $websitetoolbox_url,
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_wtbredirect_active'=> '1'
            );
            foreach ($wtbMetaValue as $key => $item) {
                update_post_meta( $wtbForumpageid, $key, $item );
            }
        }
        showForumUpdateSettingsPage('success');
        exit;
    }
    $forumWebsiteOnSameDomain = isForumAndWebsiteOnsameDomain();
    showForumUpdateSettingsPage($forumWebsiteOnSameDomain);
}
/* End Upate option page */

/* Purpose: Unset warning messages
Parameter: Response from API
Return: None */
function unsetWarnings($response){
    if(!isset($response->error)){
        if(isset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings']) && $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings'] == 1) {
                unset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings']);
        }elseif(isset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings']) && $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings'] == 2){
            unset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings']);
        }
    }
}

/* Purpose: Execute a HTTP request to validate forum login provided by the user.
Parameter: forum username, forum password.
Return: JSON responce */
function checkPluginLogin($forumUsername, $forumPassword) {
    $websitetoolbox_login_url = '';
    $websitetoolbox_logout_url = '';
    $websitetoolbox_register_url = '';
    if(get_option('users_can_register')) {
        $userType = '';
        $getUserRoles   = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_user_roles');
        if($getUserRoles) {
            $userRoles      = (array) json_decode($getUserRoles);
            if($userRoles){
                $userType       = $userRoles['users'];
            }
        }

        $websitetoolbox_login_url = WebsiteToolboxInclude\getLoginUrl();
        if(!validateForumUrl($websitetoolbox_login_url) || $userType == 'no_users') {
            $websitetoolbox_login_url = '';
        }
        // Get Logout URL and remove "_wpnonce" and it's value from the URL if exist.
        // "_wpnonce" paramete's value is a dynamic key diffrent for every logged-in user so we need to remove it befor save into SSO logout URL into our forum.
        $websitetoolbox_logout_url = html_entity_decode(esc_url(wp_logout_url()));
        $websitetoolbox_logout_url = remove_query_arg( '_wpnonce', $websitetoolbox_logout_url );
        if(!validateForumUrl($websitetoolbox_logout_url) || $userType == 'no_users') {
            $websitetoolbox_logout_url = '';
        }
        $websitetoolbox_register_url = wp_registration_url();
        if(!validateForumUrl($websitetoolbox_register_url) || $userType == 'no_users') {
            $websitetoolbox_register_url = '';
        }
    }
    $fields = array(
            'action' => 'checkPluginLogin',
            'plugin' => 'wordpress',
            'websiteBuilder' => 'wordpress',
            'type' =>'json',
            'username' => $forumUsername,
            'password' => $forumPassword,
            'login_page_url' => $websitetoolbox_login_url,
            'logout_page_url' => $websitetoolbox_logout_url,
            'registration_url' => $websitetoolbox_register_url,
            'pluginWebhookUrl' => get_site_url().'/index.php/wtWebhookEndpoint?actionWebhook'
        );
    $response_array = wp_remote_post(WebsiteToolboxForum\globalVariables::$WTBSETTINGSPAGEURL, array('method' => 'POST', 'body' => $fields));
    return $response_array;
}

/* Purpose: Execute a HTTP request to update SSO URLs (Registration, login logout and embed URL) on the forum
Parameter: forum username, forum API Key, embedded URL.
Return: JSON responce */
function updateForumData($forumUsername, $forumAPI, $embedUrl = "", $isForumEmbeddedHomePage = "") {
    // If registration option is enable from WordPress site then sent login, logout, registration URL into HTTP request.
    $fields = array(
        'action' => 'modifySSOURLs',
        'forumUsername' => $forumUsername,
        'forumApikey' => $forumAPI,
        'embed_page_url' => $embedUrl,
        'altEmbedParam' => $isForumEmbeddedHomePage,
        'plugin'    => 'wordpress'
    );
    if(get_option('users_can_register')) {
        $websitetoolbox_login_url = WebsiteToolboxInclude\getLoginUrl();
        if(!validateForumUrl($websitetoolbox_login_url) || (isset($_POST['sso_setting']) && $_POST['sso_setting'] == 'no_users')) {
            $websitetoolbox_login_url = '';
        }
        $fields['login_page_url'] = $websitetoolbox_login_url;
        // Get Logout URL and remove "_wpnonce" and it's value from the URL if exist.
        // "_wpnonce" paramete's value is a dynamic key diffrent for every logged-in user so we need to remove it befor save into SSO logout URL into our forum.
        $websitetoolbox_logout_url = html_entity_decode(esc_url(wp_logout_url()));
        $websitetoolbox_logout_url = remove_query_arg( '_wpnonce', $websitetoolbox_logout_url );
        if(!validateForumUrl($websitetoolbox_logout_url) || (isset($_POST['sso_setting']) && $_POST['sso_setting'] == 'no_users')) {
            $websitetoolbox_logout_url = '';
        }
        $fields['logout_page_url'] = $websitetoolbox_logout_url;
        $websitetoolbox_register_url = wp_registration_url();
        if(!validateForumUrl($websitetoolbox_register_url) || (isset($_POST['sso_setting']) && $_POST['sso_setting'] == 'no_users')) {
            $websitetoolbox_register_url = '';
        }
        $fields['registration_url'] = $websitetoolbox_register_url;
    }
    $response_array = wp_remote_post(WebsiteToolboxForum\globalVariables::$WTBSETTINGSPAGEURL, array('method' => 'POST', 'body' => $fields));
    return $response_array;
}

#delete user if username exist on the forum
function deleteExistingForumUser( $id, $reassign = 'novalue' ) {
    global $wpdb;
    $id = (int) $id;
    // allow for transaction statement
    do_action('delete_user', $id);
    if ( 'novalue' === $reassign || null === $reassign ) {
        $post_ids = $wpdb->get_col( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_author = %d", $id) );
        if ( $post_ids ) {
            foreach ( $post_ids as $post_id )
            wp_delete_post($post_id);
        }
        // Clean links
        $link_ids = $wpdb->get_col( $wpdb->prepare("SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $id) );
        if ( $link_ids ) {
            foreach ( $link_ids as $link_id )
            wp_delete_link($link_id);
        }
    } else {
        $reassign = (int) $reassign;
        $wpdb->update( $wpdb->posts, array('post_author' => $reassign), array('post_author' => $id) );
        $wpdb->update( $wpdb->links, array('link_owner' => $reassign), array('link_owner' => $id) );
    }
    clean_user_cache($id);
    // FINALLY, delete user
    if ( !is_multisite() ) {
        $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id = %d", $id) );
        $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->users WHERE ID = %d", $id) );
    } else {
        $level_key = $wpdb->get_blog_prefix() . 'capabilities'; // wpmu site admins don't have user_levels
        $wpdb->query("DELETE FROM $wpdb->usermeta WHERE user_id = $id AND meta_key = '{$level_key}'");
    }
    // allow for commit transaction
    do_action('deleted_user', $id);
    return true;
}

/* Purpose: Show warning message on the plugin settings page.
Parameter: None.
Return: None */
function showWarnings() {
    // Start Session if not started previously.
    WebsiteToolboxInclude\startForumSession();
    $user_ID = get_current_user_id();
    $is_admin = is_super_admin($user_ID);
    if($is_admin == 1 && isset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbPluginActivated']) && (!get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username") || !get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api") || !get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url"))) {
        if($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbPluginActivated'] == 1) {
            $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbPluginActivated'] = '';
            unset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbPluginActivated']);
        } else if($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbPluginActivated'] == 2) {
            $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbPluginActivated'] = '';
            unset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbPluginActivated']);
        }

    } else if($is_admin == 1 &&  isset($_SESSION['wtb_account_settings'])) {
        if($_SESSION['wtb_account_settings'] == 1) {
            echo '<div id="wtb-warning" class="error"><p>Your Website Toolbox Community account is currently disabled. Please <a href="https://www.websitetoolbox.com/tool/members/login/'.get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username").'" target="_blank">login to your Website Toolbox account</a> for more information.</a></p></div>';
        } else if($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings'] == 2) {
            echo '<div id="wtb-warning" class="error"><p>The API Key integrated with the Website Toolbox Community plugin is invalid. Please <a href="options-general.php?page=websitetoolboxoptions">log in</a> again to update it.</p></div>';
        }
    }
}

/* Purpose: Sent HTTP request on the related forum validate API and show error message if not validate.
Parameter: None
Return: None */
function checkForumEnableOrInvalidAPI() {
    // Start Session if not started previously.
    WebsiteToolboxInclude\startForumSession();
    // Check logged-in user is admin
    $user_ID = get_current_user_id();
    $is_admin = is_super_admin($user_ID);

    $ispluginActivate = is_plugin_active( 'wordpress/websitetoolbox.php' );

    if($is_admin == 1 && get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username") && get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api") && get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url") && $is_admin == 1 && !isset($_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbForumCheck'])) {
        // Create an arry of parameters
        $response_array = validateAPIKeyCall();
        // Print warning if get any API ket not match.
        if(!is_wp_error( $response_array )) {
            $response = trim(wp_remote_retrieve_body($response_array));
            $response = json_decode($response);

            // Get authentication token from JSON response.
            if(isset($response->error)) {
                if(str_contains($response->error, 'is disabled.')) {
                    $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings'] = 1;
                } else {
                    $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings'] = 2;
                }
            } else {
                if($response->forum_address != get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url") ){
                    updateForumDataByDomainURL($response->forum_address,get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect'));
                    WebsiteToolboxForum\resetCookieOnLogout();
                }
                $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtb_account_settings'] = 0;
            }
        }
        $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbForumCheck'] = 1;
    } else if ($is_admin == 1 && is_plugin_active('website-toolbox-forums/websitetoolbox.php') && (!get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username") || !get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api") || !get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url"))) {
      $_SESSION[WebsiteToolboxForum\globalVariables::$WTBPREFIX.'wtbPluginActivated'] = 2;
    }
}

/* Purpose: this function is used to validate the URL
Parameter: URL
Return: true/false */
function validateForumUrl($url) {
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return true;
    } else {
        return false;
    }
}

/* Purpose: this function is used to update plugin and page setting if domain is updated
Parameter: forum_address,websitetoolbox_redirect option
Return: none */
function updateForumDataByDomainURL($websitetoolbox_url,$redirect_option){
        global $wpdb;
        $embedPageUrl;

        if($websitetoolbox_url != ''){
            update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url", $websitetoolbox_url);


            // Preapre array to set forum username, API and embed option.
            $wtbOptions = array(
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username' => get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username'),
                WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_api' => get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_api')
            );
            $websitetoolbox_url      = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url");
            $wtbForumpageid = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid');
            if(preg_match('#^https?://#', get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_url'))) {
                $wtb_url = $websitetoolbox_url;
            } else {
                $wtb_url = "http://".$websitetoolbox_url;
            }
            $pageData = get_post($wtbForumpageid);

            // Add/update data if embeded option is enable.
            if($redirect_option==1) {
                $postTitle = $pageData->post_title;
                if($wtbForumpageid) {
                    onForumPluginActivation('settings');
                }
                // Create a new page for forum if forum is alrady integrated as a custom link on WordPress website.
                // User save settings after enable "Embed" option. So that didn't effect on custom link.
                if($pageData->post_type == 'nav_menu_item') {
                    $wtbForumpageid = createForumPage();
                    // Update page id in options table
                    update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid', $wtbForumpageid);
                    #check on post meta
                    $wtbMetaValue = array(
                        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to' => $websitetoolbox_url,
                        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to_target' => WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox',
                        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to_type' => 'custom_post_type',
                        WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_wtbredirect_active'=> '1',
                        '_wp_page_template' => 'full-width-page.php'
                    );
                    if(sizeof(get_post_meta( $wtbForumpageid))) {
                        foreach ($wtbMetaValue as $key => $item) {
                            update_post_meta( $wtbForumpageid, $key, $item );
                        }
                    } else {
                        foreach ($wtbMetaValue as $key => $item) {
                            add_post_meta( $wtbForumpageid, $key, $item );
                        }
                    }
                    $pageData = get_post($wtbForumpageid);
                    $postTitle = WebsiteToolboxForum\globalVariables::$PAGENAME;
                }
                // Add/update embed code for the forum page on WordPress.
                $embedPageUrl = $pageData->guid;
                $wtb_embed_url = preg_replace('#^https?:#i', '', $wtb_url);

                $response_array = updateForumData(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username'), get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_api'), $embedPageUrl);

                // Get page content and apended embed code into it if some other content already does exist on the page instead of embed code.
                $postContent = '';
                if($pageData->post_content && strpos($pageData->post_content, $wtb_embed_url) !== false) {
                    $postContent = str_replace('<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>','Embed Code',$pageData->post_content);
                }
                if($postContent) {
                    $postContent = str_replace('Embed Code', '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>',$postContent);
                } else {
                    $postContent = '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>';
                }
                $postData = array(
                    'ID'           => $wtbForumpageid,
                    'post_title'   => $postTitle,
                    'post_content' => $postContent
                );

                update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect', 1);
                wp_update_post($postData);
                update_post_meta( $wtbForumpageid, WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_wtbredirect_active', '' );
            } else {
                #Remove embed code and set it as a link if "embed" option is disabled.
                $response_array = updateForumData(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_username'), get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_api'));
                update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect', '');
                $wtbMetaValue = array(
                    WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to' => $websitetoolbox_url,
                    WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_wtbredirect_active'=> '1'
                );
                foreach ($wtbMetaValue as $key => $item) {
                    update_post_meta( $wtbForumpageid, $key, $item );
                }
            }
        }

}


function isForumEmbeddedOnHomePage() {
    $forumAddress = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url");
    $pageID = get_option('page_on_front');
    $pageContent = '';
    if($pageID != 0){
        $post = get_post($pageID); 
        $pageContent = $post->post_content;
    }

    $isEmbedded = strpos($pageContent, '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$forumAddress.'/js/mb/embed.js"></script><noscript><a href="'.$forumAddress.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>');
    $wtbforumUrl = preg_replace('#^https?:#i', '', $forumAddress);
    $isEmbeddedHome = strpos($pageContent, '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtbforumUrl.'/js/mb/embed.js"></script><noscript><a href="'.$forumAddress.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>');
    if ($isEmbedded == true || $isEmbeddedHome) {
        return 1;
    } else {
        return 0;
    }
}

function isForumAndWebsiteOnsameDomain() {
    if(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect')) {
        $forumUrl = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url");
        $websiteDomain = getDomainName(get_site_url());
        $forumDomain = getDomainName(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url"));
        if(getDomainName(get_site_url()) == getDomainName(get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url"))) {
            return 1;
        } else {
            return 0;
        }
    }
}

function wtWebhookEndpoint() {
  add_rewrite_rule('^wtWebhookEndpoint/?', 'index.php/?actionWebhook=1', 'top');
}

/*Purpose: Webhook Endpoint handler
Parameter: none
Return: none
*/
function handle_wtWebhookEndpoint() {
  if (isset($_GET['actionWebhook'])) {
    // Call your custom plugin function here
    updateByWebhook();
    exit(); // Stop WordPress from processing further
  }
}

/*Purpose: Update domain and page data by webhook
Parameter: none
Return: none
*/ 
function updateByWebhook(){
    $response = array();
    $postDatas = file_get_contents('php://input');
    $postData = json_decode($postDatas);
    $communityUid = $postData->communityUid;
    $new_domain = $postData->communityAddress;
    $altEmbedParam = $postData->altEmbedParam; 
    $secretKey = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."wt_secret_key");
    $signatureHeader = $_SERVER['HTTP_X_WTSIGNATURE'];
    $signature = hash_hmac('sha256', $postDatas, $secretKey, true);
    $signature = base64_encode($signature);
    while(strlen($signature) % 4){
        $signature .= '=';
    }
    if($_SERVER['REQUEST_METHOD'] == 'POST'){
        if ($signatureHeader == $signature){
            if(isset($new_domain) && $new_domain != ''){
                updateDomainViaSQL($new_domain,$altEmbedParam);
            }
        }
    }
}

function updateDomainViaSQL($new_domain,$altEmbedParam){

    if($new_domain != get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url") && $new_domain !='') {
        update_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_url", $new_domain);                   
        $isEmbedded = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_redirect');

        $wtbForumpageid = get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX.'websitetoolbox_pageid');

        update_post_meta( $wtbForumpageid, WebsiteToolboxForum\globalVariables::$WTBPREFIX.'_links_to', $new_domain );

        if(preg_match('#^https?://#', $new_domain)) {
            $wtb_url = $new_domain;
        } else {
            $wtb_url = "http://".$new_domain;
        }
        $pageData = get_post($wtbForumpageid);

        if($pageData->post_type != 'nav_menu_item') {
            $embedPageUrl = $pageData->guid;   
        }

        if(isset($embedPageUrl)) {
            $wtb_embed_url = preg_replace('#^https?:#i', '', $wtb_url);
            #Set default title if not exist..
            if($pageData->post_title) {
                $postTitle = $pageData->post_title;
            } else {
                $postTitle = WebsiteToolboxForum\globalVariables::$PAGENAME;
            }

                if($altEmbedParam){
                    $postContent = '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js" data-version="1.1"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>';
                }else{
                    $postContent = '<div id="wtEmbedCode"><script type="text/javascript" id="embedded_forum" src="'.$wtb_embed_url.'/js/mb/embed.js"></script><noscript><a href="'.$wtb_url.'">'.WebsiteToolboxForum\globalVariables::$PAGENAME.'</a></noscript></div>';
                }

                global $wpdb;                 
                $table_name = $wpdb->prefix . 'posts';

                $query = $wpdb->prepare(
                    "UPDATE $table_name SET post_content = %s WHERE ID = %d",
                    $postContent,
                    $wtbForumpageid
                );
                $wpdb->query( $query );
        }
    }
}

function validateAPIKeyCall(){

            $fields = array(
                'action' => 'validateAPIKey',
                'type' => 'json',
                'forumUsername' => get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username"),
                'forumApikey' => get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api")
            );
            // Sent HTTP request to forum.
            $response_array = wp_remote_post(WebsiteToolboxForum\globalVariables::$WTBSETTINGSPAGEURL, array('method' => 'POST', 'body' => $fields));

            return $response_array;
}

function httpRequestForUninstallReinstallPlugin($action){
    
    $fields = array(
                 'action' => $action,
                 'plugin' => 'wordpress',
                 'forumUsername' => get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username"),
                 'forumApikey' => get_option(WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api")
    );

    if($action=='reinstallPlugin'){
        $fields['pluginWebhookUrl'] = get_site_url().'/index.php/wtWebhookEndpoint?actionWebhook';
    }

    $response_array = wp_remote_post(WebsiteToolboxForum\globalVariables::$WTBSETTINGSPAGEURL, array('method' => 'POST', 'body' => $fields));

    return $response_array;
}

?>
