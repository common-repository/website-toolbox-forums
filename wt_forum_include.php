<?php

namespace WebsiteToolboxInclude;
use WebsiteToolboxForum;

try {
    // Set script execution time if execution time is less than default script execution time.
    // Default script execution time is 30 seconds.
    $currExecutionTimeLimit = ini_get('max_execution_time');
    if($currExecutionTimeLimit && $currExecutionTimeLimit < 30) {
        set_time_limit(0);
    }
    ob_start();
} catch (Exception $e) {
}

// start new session if not already started.
function startForumSession() {
	if(!session_id()) {
		session_start();
	}
}

function setForumCookies($cname, $cvalue, $persistent) {
  if($persistent == 1) {
    $expiration = (int) time() + (86400 * 365);
  } else {
    $expiration = 0;
  }
  // Pass param in setcookie function as a array if php version is 7.3 or above, to overcome the waring return on wordpress website.
  if(PHP_VERSION_ID < 70300) {
    if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
      setcookie($cname, $cvalue, $expiration, COOKIEPATH." ; SameSite=None; Secure;", COOKIE_DOMAIN);
    } else {
      setcookie($cname, $cvalue, $expiration, COOKIEPATH, COOKIE_DOMAIN);
    }
  } else {
    $wtbBaseCookieOptions = [
      'expires' => $expiration,
      'path' => COOKIEPATH,
      'domain' => COOKIE_DOMAIN,
      'httponly' => true,
    ];
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
      $wtbBaseCookieOptions['secure'] = true;
      $wtbBaseCookieOptions['samesite'] = 'None';
    }
    setcookie($cname, $cvalue, $wtbBaseCookieOptions);
  }
}
function getLoginUrl(){
    $websitetoolbox_login_url = wp_login_url();
    $websitetoolbox_login_url = add_query_arg('from', WebsiteToolboxForum\globalVariables::$WTBPREFIX.WebsiteToolboxForum\globalVariables::$loginParam, $websitetoolbox_login_url);
    return $websitetoolbox_login_url;
}

function wtbGetCurrentPageUrl() {
  $pageUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  return $pageUrl;
}

?>
