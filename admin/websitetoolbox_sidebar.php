<?php 
namespace WebsiteToolboxAdminSidebar;
use WebsiteToolboxForum;

function getAllCategoriesForSideBar(){
    global $pagenow;
    global $getAllCategories;
    if($pagenow == 'post-new.php' || $pagenow == 'post.php' ){
        $getAllCategories = WebsiteToolboxForum\getCategoryList();
    }
}

function sidebar_init(){
    add_meta_box("websitetoolbox-meta-box", "Website Toolbox Community", "WebsiteToolboxAdminSidebar\\custom_meta_box_markup", array('page','post'), "side", "high", array(
        '__block_editor_compatible_meta_box' => false,
        '__back_compat_meta_box'             => true,
    ));    
}
/* show metabox in classic editor */
function custom_meta_box_markup($post){
    wp_nonce_field(basename(__FILE__), "meta-box-nonce");
    getAllCategoriesForSideBar();
    global $pagenow;
    global $getAllCategories;
    $contentType = "Excerpt"; ?>
    <div class="pluginSidebar"><?php 
        $publishOnForum     = 0;
        $getPostUrl         = get_post_meta($post->ID,'website_toolbox_forum_postUrl',true);
        $publishingError    = get_post_meta($post->ID,'website_toolbox_forum_publishing_error',true);
        $current_post_type  = get_post_type($post->ID);
        if($pagenow == 'post-new.php'){
            if($current_post_type == 'page'){
                $publishOnForum             = get_option("websitetoolbox_page_content");
                $existingforumCategory      = get_option("websitetoolbox_page_category");
                $contentType                = "First Paragraph";
            }else{
                $publishOnForum             = get_option("websitetoolbox_post_content");
                $existingforumCategory      = get_option("websitetoolbox_post_category");                
            }
        }else{
            if($current_post_type == 'page'){
                $contentType                = "First Paragraph";
            }
            $publishOnForum             = get_post_meta($post->ID,'website_toolbox_publish_on_forum',true);
            $existingforumCategory      = get_post_meta($post->ID,'website_toolbox_forum_category',true);
        }
        $existingTopicId    = get_post_meta($post->ID,'forum_topicId',true);
        if($existingTopicId && $existingTopicId != ''){
            $getTopicId         = WebsiteToolboxForum\apiRequest('GET', "/topics/".$existingTopicId);
        }
        if(isset($getTopicId->status)){
            if(isset($getTopicId->error->param) && ($getTopicId->error->param == 'topicId')){ 
                update_post_meta( $post->ID, 'website_toolbox_forum_publishing_error', "This topic is deleted from the Website Toolbox Community." );
                delete_post_meta( $post->ID, 'website_toolbox_forum_postUrl');
                delete_post_meta( $post->ID, 'website_toolbox_forum_category' );  
                $publishOnForum = 0;
            }elseif(isset($getTopicId->error->code) && ($getTopicId->error->code == 'InternalServerError')){
                update_post_meta( $post->ID, 'website_toolbox_forum_publishing_error', "Some internal server error!" );
            }
        }elseif(isset($getTopicId)){
            $existingTopicDetails = $getTopicId;
        }
        if($existingforumCategory && $publishOnForum != 0){
            $display = "block";
        }else{
            $display = "none";
        }
        if($publishingError){ ?>
            <p class="notice notice-error"><b><?php echo $publishingError;?></b></p><?php
        }else{
            if($getPostUrl){?>
                <p><b>Published on </b><br /><a href="<?php echo $getPostUrl;?>" target="_blank"><?php echo $getPostUrl;?></a></p><?php
            } 
        }   
        delete_post_meta( $post->ID, 'website_toolbox_forum_publishing_error' ); ?>
    </div>
    <div>
        <label for="meta-box-text" class="sidebarFields"><b>Publish On Community</b></label>
    </div>
    <div>
        <select name="publishOnForum" id="publishOnForum" class="sidebarFields">
            <option value="0" <?php echo ($publishOnForum == 0) ? 'selected' : ''; ?>>No</option>
            <option value="1" <?php echo ($publishOnForum == 1) ? 'selected' : ''; ?>><?php echo $contentType;?></option>
            <option value="2" <?php echo ($publishOnForum == 2) ? 'selected' : ''; ?>>Full Content</option>
        </select>
    </div>
    <?php
    if(!empty($getAllCategories)){?>
    <div id="divCategory" style="display:<?php echo $display;?>">
        <div>
            <label for="forumCategory"  class="sidebarFields"><b>Category</b></label>
        </div>
    <div>  
            <select name="forumCategory" id="forumCategory"  class="sidebarFields">
                <option value="0">Select a category</option>
            <?php
                if($existingTopicId && isset($existingTopicDetails)){
                    $latestForumCategory = $existingTopicDetails->{'categoryId'};
                }else{
                    $latestForumCategory = $existingforumCategory;
                }
                for($i=0;$i<count($getAllCategories);$i++){
                    $catagoryId     = $getAllCategories[$i]->{'categoryId'};
                    $categoryTitle  = $getAllCategories[$i]->{'title'};?>
                    <option value="<?php echo $catagoryId;?>" <?php echo ($latestForumCategory == $catagoryId) ? 'selected' : ''; ?>><?php echo $categoryTitle;?></option>
                <?php
                }
            ?>
            </select>
        </div>
    </div>
<?php }else{?>
            <div id="divCategory" style="display:none">
                <input type='hidden' name='forumCategory' id='forumCategory' value='-1'>
            </div>
        <?php }
}
/* show sidebar in block editor */
function sidebar_plugin_register() {
    getAllCategoriesForSideBar();
    global $getAllCategories;
    if(get_option("websitetoolbox_api")){
        $postTypeDefault    = array();
        $sidebarCategories   = array();
        wp_register_script(
            'plugin-sidebar-js',
            plugins_url( 'sidebar.js', __FILE__ ),
            array(
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-components', 
                'wp-compose',
            )
        );
        if(is_array($getAllCategories)){ 
            for($i=0;$i<count($getAllCategories);$i++){
                $catagoryId                     = $getAllCategories[$i]->{'categoryId'};
                $sidebarCategories[$catagoryId] = $getAllCategories[$i]->{'title'};        
            }
        }
 
        $latestForumCategory    = '';
        $existingTopicId        = '';
        if(isset($_GET['post'])){ 
            $existingforumCategory      = get_post_meta($_GET['post'],'website_toolbox_forum_category',true);
            $existingTopicId            = get_post_meta($_GET['post'],'forum_topicId',true);
            if($existingTopicId){
                $latestForumCategory    = WebsiteToolboxForum\checkTopicCategory($existingTopicId,$existingforumCategory);
            }

        }
        register_post_meta( '', 'website_toolbox_forum_postUrl', array(
                'single'        => true,
                'show_in_rest'  => true,
                'type'          => 'string',
            )
        );
        register_post_meta( '', 'website_toolbox_forum_category', array(
                'single'        => true,
                'show_in_rest'  => true,
                'type'          => 'string',
                'auth_callback' => function() {
                    return current_user_can( 'edit_posts' );
                }
            )
        ); 
        register_post_meta( '', 'website_toolbox_publish_on_forum', array(
                'single'        => true,
                'show_in_rest'  => true,
                'type'          => 'string',
                'auth_callback' => function() {
                    return current_user_can( 'edit_posts' );
                }
            ) 
        );
        $postTypeDefault= array(
            'pageContent'   => get_option("websitetoolbox_page_content"),
            'pageCategory'  => get_option("websitetoolbox_page_category"),
            'postCategory'  => get_option("websitetoolbox_post_category"),
            'postContent'   => get_option("websitetoolbox_post_content"),
            'latestCategory'=> $latestForumCategory,
            'topicId'       => $existingTopicId
        );
        wp_localize_script( 'plugin-sidebar-js', 'sidebarCategories', $sidebarCategories );
        wp_localize_script( 'plugin-sidebar-js', 'defaultParameter', $postTypeDefault );
    }
}

function sidebar_plugin_script_enqueue() {
    wp_enqueue_script( 'plugin-sidebar-js' );  
}

