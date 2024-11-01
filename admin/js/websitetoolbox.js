document.addEventListener("DOMContentLoaded", function() {
    wtbxPlugin.admin.bindCategoryDropDownOnPublishOption();
    wtbxPlugin.admin.disableSSOForAllUsersRole();
    wtbxPlugin.admin.showOptionsForUserRoles();
    var userRolesCheckbox = document.querySelector('input[name="user_roles[]"]');
    if (userRolesCheckbox) {
        userRolesCheckbox.addEventListener('click', function() {
            wtbxPlugin.admin.enableSsoForAllUserRoles(this);
        });
    }
    var radioAllUsers = document.getElementById('all_users');
    if (radioAllUsers) {
        radioAllUsers.addEventListener('click', function(e) {
            wtbxPlugin.admin.enableSsoForAllUserRoles(this);
        });
    }
});

var wtbxPlugin = {};
wtbxPlugin.admin = {

    // Function to show user roles in SSO settings
    showOptionsForUserRoles: function(){
        var radioSelectedRoles = document.getElementById('selected_roles');
        if (radioSelectedRoles) {
            radioSelectedRoles.addEventListener('click', function(e) {
                var userRoles = document.getElementById('hiddenUserRoles').value;
                var arrayUserRoles = userRoles.split(",");
                var checkboxes = document.getElementsByName('user_roles[]');
                checkboxes.forEach(function(checkbox) {
                    if (arrayUserRoles.includes(checkbox.value)) {
                        checkbox.checked = true;
                        checkbox.checked = "checked";
                    } else {
                        checkbox.checked = false;
                        checkbox.removeAttribute('checked');
                    }
                });
                document.getElementById('allOptions').style.display = "block";
            });
        }
    },
    // Function to enable all user roles in SSO settings
    enableSsoForAllUserRoles: function(e){
        document.getElementById('allOptions').style.display = "none";
        document.getElementById('all_users').checked = true;
        var checkboxes = document.getElementsByName('user_roles[]');
        if (e.checked) {
            checkboxes.forEach(function(checkbox){
                checkbox.checked = true;
                checkbox.checked = "checked";
            });
        }else{
            document.getElementById('no_users').checked = true;                
            checkboxes.forEach(function(checkbox){
                checkbox.checked = false;
                checkbox.removeAttribute('checked');
            });
        }
    },
    // Function to disable all user roles in SSO settings
    disableSSOForAllUsersRole: function(){
        var radioNoUsers = document.getElementById('no_users');
        if (radioNoUsers) {
            radioNoUsers.addEventListener('click', function(e) {
                document.getElementById('allOptions').style.display = "none";
                document.getElementById('all_users').checked = false;
                document.getElementById('all_users').removeAttribute('checked');
                var checkboxes = document.getElementsByName('user_roles[]');
                if (e.target.id === 'no_users' && e.target.checked) {
                    checkboxes.forEach(function(checkbox){
                        checkbox.checked = false;
                        checkbox.removeAttribute('checked');
                    });
                }
            });
        }
    },

    // Function to bind forum categories dropdown with publish option in post/pages sidebar
    bindCategoryDropDownOnPublishOption: function() {
        var dropdown = document.getElementById("publishOnForum");
        if(dropdown !== null){
            dropdown.addEventListener("change", function() {
                var selectedValue = dropdown.value;
                var element = document.getElementById('divCategory');
                if(selectedValue != 0){
                    element.style.display = 'block';
                }else{
                    element.style.display = 'none';
                }

            });
        }
    }
};
