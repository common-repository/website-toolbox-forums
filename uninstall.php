<?php

include_once plugin_dir_path( __FILE__ ) . 'websitetoolbox.php';
    $fields = array(
            'action' => 'uninstallPlugin',
            'plugin' => 'wordpress',
            'forumUsername' => get_option(\WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_username"),
            'forumApikey' => get_option(\WebsiteToolboxForum\globalVariables::$WTBPREFIX."websitetoolbox_api")
        );

    $response_array = wp_remote_post(WebsiteToolboxForum\globalVariables::$WTBSETTINGSPAGEURL, array('method' => 'POST', 'body' => $fields));

?>