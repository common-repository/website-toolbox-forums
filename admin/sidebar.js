(function (wp) {
    var registerPlugin  = wp.plugins.registerPlugin;
    var PluginSidebar   = wp.editPost.PluginSidebar;
    var el              = wp.element.createElement;
    var Selectinput     = wp.components.SelectControl;
    var Textinput       = wp.components.TextControl;
    var withSelect      = wp.data.withSelect;
    var withDispatch    = wp.data.withDispatch;
    var compose         = wp.compose.compose;
    const iconEl = el('img', {
        width: 20,
        height: 20,
        class: 'wt_chat_icon',
        src: '../wp-content/plugins/website-toolbox-forums/admin/images/chat_icon.svg'
    });
    const isSidebarOpened = wp.data.select('core/edit-post').isEditorSidebarOpened();
    const isFullscreenMode = wp.data.select('core/edit-post').isFeatureActive('fullscreenMode');
    if (isFullscreenMode) {
        wp.data.dispatch('core/edit-post').toggleFeature('website-toolbox-forum');
    }
    if (!isSidebarOpened) {
        wp.data.dispatch('core/edit-post').openGeneralSidebar('website-toolbox-forum');
    }
    var MetaHyperLink = (
        withSelect(function (select, props) {
            return {
                metaFieldValue: select('core/editor')
                    .getEditedPostAttribute('meta')['website_toolbox_forum_postUrl'],
            }
        })
    )(function (props) { 
        if ((props.metaFieldValue == 'deleted')) { 
            html = '<p class="notice notice-error"><b>This topic is deleted from the Website Toolbox Community.</b></p>';
        } else if (props.metaFieldValue != '') {
            html = 'Published on<br /><a href="' + props.metaFieldValue + '" style="text-decoration:none" target="_blank"> ' + decodeURI(props.metaFieldValue) + '</a>';
        } else {
            html = '';
        }
        return el('div', {
            dangerouslySetInnerHTML: {
                __html: html
            }
        });
    });

    var MetaBlockField = compose(
        withDispatch(function (dispatch, props) {
            return {
                setMetaFieldValue: function (value) { 
                    dispatch('core/editor').editPost({
                        meta: {
                            website_toolbox_publish_on_forum: value
                        }
                    });
                }
            }
        }),
        withSelect(function (select, props) { 
            var defaultValue = select('core/editor').getEditedPostAttribute('meta')['website_toolbox_publish_on_forum'];
            if(defaultValue == ''){
                var currentPostType = wp.data.select("core/editor").getCurrentPostType();
                if (currentPostType == 'page') {
                    defaultValue = defaultParameter['pageContent'];
                } else {
                    defaultValue = defaultParameter['postContent'];
                }
            }
            return {
                metaFieldValue: defaultValue,
            }
        })
    )(function (props) {      
        if (props.metaFieldValue) {
            var valueDefault = props.metaFieldValue; 
        } else {
            var currentPostType = wp.data.select("core/editor").getCurrentPostType();
            if (currentPostType == 'page') {
                var valueDefault = defaultParameter['pageContent'];
            } else {
                var valueDefault = defaultParameter['postContent'];
            }
        }
        props.setMetaFieldValue(valueDefault);
        return el(Selectinput, {
            label: 'Publish On Community',
            options: [{
                    label: 'No',
                    value: 0
                },
                {
                    label: 'First Paragraph',
                    value: 1
                },
                {
                    label: 'Full Contents',
                    value: 2
                },
            ],
            onChange: function (content) {
                props.setMetaFieldValue(content);
                if (content != 0) {
                    document.getElementById('forumCategory').style.display = 'block';
                } else {
                    document.getElementById('forumCategory').style.display = 'none';
                }
            },
            value: valueDefault,
        })
    });
    var MetaBlockToggle = compose(
        withDispatch(function (dispatch, props) {
            return {
                setMetaFieldValue: function (value) { 
                    dispatch('core/editor').editPost({
                        meta: {
                            [props.fieldName]: value
                        }
                    });
                }
            }
        }),
        withSelect(function (select, props) {
            var defaultValue = select('core/editor').getEditedPostAttribute('meta')[props.fieldName];
            if(defaultValue == ''){
                var currentPostType = wp.data.select("core/editor").getCurrentPostType();
                if (currentPostType == 'page') {
                    defaultValue = defaultParameter['pageCategory'];
                } else {
                    defaultValue = defaultParameter['postCategory'];
                }
            }
            return {
                metaFieldValue: defaultValue,
            }
        })
    )(function (props) {
        var metaValues = props.metaFieldValue;  
        var posttypeOptions = [{
            value: 0,
            label: 'Select a category...'
        }];
        for (categoryId in sidebarCategories) {
            posttypeOptions.push({
                value: categoryId,
                label: sidebarCategories[categoryId]
            });
        }
        if (props.metaFieldValue) {
            var categoryDisplay = "block";
            var valueDefault = props.metaFieldValue;
        } else {
            var currentPostType = wp.data.select("core/editor").getCurrentPostType();
            if (currentPostType == 'page') {
                var valueDefault = defaultParameter['pageCategory'];
            } else {
                var valueDefault = defaultParameter['postCategory'];
            }
        }
        return el(Selectinput, {
            label: 'Category',
            options: posttypeOptions,
            onChange: function (content) {
                props.setMetaFieldValue(content);
            },
            value: valueDefault,
        })
    });
    registerPlugin('website-toolbox-forum', {
        render: function () { 
            var forumCategory   = wp.data.select('core/editor').getEditedPostAttribute('meta')['website_toolbox_forum_category'];
            var publishOnforum  = wp.data.select('core/editor').getEditedPostAttribute('meta')['website_toolbox_publish_on_forum'];
            var currentPostType = wp.data.select("core/editor").getCurrentPostType();
            if((forumCategory == '')){
                if (currentPostType == 'page') {
                    var valueDefault        = defaultParameter['pageCategory'];
                    var valuePostContent    = defaultParameter['pageContent'];
                } else {
                    var valueDefault        = defaultParameter['postCategory'];
                    var valuePostContent    = defaultParameter['postContent'];
                }
            }           
            if ((forumCategory || valueDefault) && (defaultParameter['latestCategory'] != 'website_toolbox_forum_publishing_error')) { 
                if(valuePostContent != 0){  
                    if((publishOnforum == 0) && publishOnforum != ''){
                        var className = 'dev-sidebar-category-none';
                    } else{   
                        var className = 'dev-sidebar-category-block';
                    }
                }else{  
                    var className = 'dev-sidebar-category-none';
                }
            } else { 
                var className = 'dev-sidebar-category-none';
            }
            return el(PluginSidebar, {
                    name: 'website-toolbox-forum',
                    icon: iconEl,
                    title: 'Website Toolbox Community',
                },
                el('div', {
                        className: 'dev-sidebar-hyperLink'
                    },
                    el(MetaHyperLink, {
                        fieldName: 'forumHyperLink'
                    }),
                ),
                el('div', {
                        className: 'dev-sidebar-publishForum'
                    },
                    el(MetaBlockField, {
                        fieldName: 'website_toolbox_publish_on_forum'
                    }),
                ),
                el('div', {
                        className: className,
                        id: 'forumCategory'
                    },
                    el(MetaBlockToggle, {
                        fieldName: 'website_toolbox_forum_category'
                    }),
                ),

            );
        }
    });
})(window.wp);