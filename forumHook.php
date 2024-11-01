<?php
add_action('admin_menu', 'WebsiteToolboxAdmin\\showForumDashboardMenu');
add_action('admin_init', 'WebsiteToolboxAdmin\\showForumDashboardLoginPage');
add_action('admin_init', 'WebsiteToolboxAdmin\\showForumDashboardUpdatePage');
add_action('admin_init', 'WebsiteToolboxAdmin\\redirectAfterActivation' );
add_action('init', 'WebsiteToolboxEvents\\redirectPrivateForum');
add_action('wp_head', 'WebsiteToolboxForum\\publishForumPage');
/* admin_notices to print notice(message) on admin section */
add_action('admin_notices', 'WebsiteToolboxAdmin\\showWarnings');
add_action('admin_footer','WebsiteToolboxForum\\ssoLoginLogout');
/* Call this hook to check API key and forum enable/disable on admin page. */
add_action('admin_head','WebsiteToolboxAdmin\\checkForumEnableOrInvalidAPI');
/* Call this hook if single or multiple user delete from the WordPress site. */
add_action('delete_user', 'WebsiteToolboxEvents\\deleteForumUser');
/* wp_login hook called when user logged-in into wordpress site (front end/back end) */
add_action('wp_login','WebsiteToolboxEvents\\getAuthtokenForLogin', 1);
/* user_register hook called when a new account creates from from wordpress site (front end/back end) */
add_action('user_register', 'WebsiteToolboxEvents\\createUserOnForum');
/* print IMG tags to the footer if needed */
add_action('wp_footer','WebsiteToolboxForum\\ssoLoginLogout', 999);
/* print IMG tags to the admin login page if user redirected to login page after logged-out. */
add_action('login_footer', 'WebsiteToolboxForum\\ssoLoginLogout');
/* Define Hook to sent logout request on the relted forum from WordPress website once user logout from wordpress website. */
add_action('wp_logout', 'WebsiteToolboxEvents\\logoutUserOnForum', 10);
/* Define hook to sent request on the forum to update user information. */
add_action( 'profile_update', 'WebsiteToolboxEvents\\updateUserOnForum', 10, 2 );
/* Define hook to check and set referrer if sent to login page from forum */
add_action( 'login_init', 'WebsiteToolboxAdmin\\checkReferelUrlOnLogin' );
/* hooks for create topic on forum automatically */
add_action( 'after_setup_theme', 'wtCheckForSidebar' );
function wtCheckForSidebar() {
    global $pagenow;

    if ($pagenow == 'post-new.php' || $pagenow == 'post.php' ) {
		add_action( 'enqueue_block_editor_assets', 'WebsiteToolboxAdminSidebar\\sidebar_plugin_script_enqueue' );
    }else{
    	return;
    }
}
add_action( 'init', 'WebsiteToolboxAdminSidebar\\sidebar_plugin_register',10 );
add_action("admin_init", "WebsiteToolboxAdminSidebar\\sidebar_init",10);
add_action('admin_enqueue_scripts','WebsiteToolboxAdmin\\userRolesScript');
add_action( 'publish_post',  'WebsiteToolboxEvents\\publish_post_after_save' , 10, 2 );
add_action( 'publish_page',  'WebsiteToolboxEvents\\publish_post_after_save' , 10, 2 );
add_action('rest_after_insert_post', 'WebsiteToolboxEvents\\createHyperlink', 10, 3);
add_action('rest_after_insert_page', 'WebsiteToolboxEvents\\createHyperlink', 10, 3);
add_action('init', 'WebsiteToolboxAdmin\\wtWebhookEndpoint');
add_action('template_redirect', 'WebsiteToolboxAdmin\\handle_wtWebhookEndpoint');

add_filter('rocket_exclude_defer_js', function ($excluded_files = []) {
	$excluded_files[] = '/js/mb/embed.js';
	return $excluded_files;
});

add_filter('rocket_exclude_js', function ($excluded_files = []) {
	$excluded_files[] = '/js/mb/embed.js';
	return $excluded_files;
});

add_filter('autoptimize_filter_js_exclude', function ($exclude) {
	$exclude .= ',/js/mb/embed.js';
	return $exclude;
});

add_filter('wpfc_exclude_js_from_minify', function ($exclude) {
	$exclude[] = '/js/mb/embed.js';
	return $exclude;
});

add_action('template_redirect', 'WebsiteToolboxForum\\redirectionToAvoidAMP');


?>
