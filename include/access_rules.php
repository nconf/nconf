<?php

# Define page permissions
# access based on types of groups (admins, ordinary users)
# admins have no limitations
# the following configuration will allow other groups like users (currently this is the only )
# access pages
#   which are configured over script name
#   optional: if file should exactly match ( FALSE ) or the scriptname should begin with it ( TRUE)
#   optional: if some request elements are needed (limit some actions to some classes)


# Config starts here:

###
# access rights for all, also non-authenticated users (loginpage)
###
    $NConf_PERMISSIONS->setURL('index.php');


###
# Info: admins are not limited, this is handled by the class itself
###


###
# access rights for 'users'
# they have no rights expect the once loaded with the navigation
# for more rights, the rules are defined here :
###


    $NConf_PERMISSIONS->setURL('detail.php', FALSE, array('user'));

    // set in module ?
    //main.php ?  call_file ?


    # pages in the navigation are automatically added by the menu create function
    # they will be open like this
    //$NConf_PERMISSIONS->setURL('dependency.php');
    //$NConf_PERMISSIONS->setURL('history.php');
    //$NConf_PERMISSIONS->setURL('deploy_config.php');
    //$NConf_PERMISSIONS->setURL('handle_item.php');

    # general pages

	/*
	 * TODO: Check these settings, seems that all coming already from the navigation...
	 * 
    # overview
    $NConf_PERMISSIONS->setURL('overview.php',           FALSE, array('user'),   array('item'=>'host') );
    $NConf_PERMISSIONS->setURL('overview.php',           FALSE, array('user'),   array('item'=>'hostgroup') );
    $NConf_PERMISSIONS->setURL('overview.php',           FALSE, array('user'),   array('item'=>'service') );
    $NConf_PERMISSIONS->setURL('overview.php',           FALSE, array('user'),   array('item'=>'advanced-service') );
    $NConf_PERMISSIONS->setURL('overview.php',           FALSE, array('user'),   array('item'=>'servicegroup') );
    $NConf_PERMISSIONS->setURL('overview.php',           FALSE, array('user'),   array('item'=>'contact', 'xmode'=>'pikett') );
    $NConf_PERMISSIONS->setURL('overview.php',           FALSE, array('user'),   array('item'=>'contact', 'xmode'=>'oncall') );
    $NConf_PERMISSIONS->setURL('overview.php',           FALSE, array('user'),   array('item'=>'contact', 'xmode'=>'on-call') );
    $NConf_PERMISSIONS->setURL('overview.php',           FALSE, array('user'),   array('item'=>'contact', 'xmode'=>'on_call') );
    

    # add / modify / multimodify items
    $NConf_PERMISSIONS->setURL('handle_item.php',           FALSE, array('user'),   array('item'=>'host') );
    $NConf_PERMISSIONS->setURL('handle_item.php',           FALSE, array('user'),   array('item'=>'hostgroup') );
    $NConf_PERMISSIONS->setURL('handle_item.php',           FALSE, array('user'),   array('item'=>'service') );
    $NConf_PERMISSIONS->setURL('handle_item.php',           FALSE, array('user'),   array('item'=>'advanced-service') );
    $NConf_PERMISSIONS->setURL('handle_item.php',           FALSE, array('user'),   array('item'=>'servicegroup') );
    $NConf_PERMISSIONS->setURL('handle_item.php',           FALSE, array('user'),   array('item'=>'contact') );
    $NConf_PERMISSIONS->setURL('handle_item.php',           FALSE, array('user'),   array('xmode'=>'pikett') );
    $NConf_PERMISSIONS->setURL('handle_item.php',           FALSE, array('user'),   array('xmode'=>'oncall') );
    $NConf_PERMISSIONS->setURL('handle_item.php',           FALSE, array('user'),   array('xmode'=>'on-call') );
    $NConf_PERMISSIONS->setURL('handle_item.php',           FALSE, array('user'),   array('xmode'=>'on_call') );
	*/
	
    # write add items
    $NConf_PERMISSIONS->setURL('add_item_step2.php', FALSE, array('user') );

    # write modify items
    $NConf_PERMISSIONS->setURL('modify_item_write2db.php', FALSE, array('user') );
    
    # write multimodifications
    $NConf_PERMISSIONS->setURL('multimodify_attr_write2db.php', FALSE, array('user') );
    
    
    # automated delete items permission
    $query = 'SELECT config_class FROM ConfigClasses WHERE nav_privs = "user"';
    $user_class_permissions = db_handler($query, "array_direct", "Select all classes where user has permission");
    if ($user_class_permissions){
        foreach ($user_class_permissions AS $permit_class){
            $NConf_PERMISSIONS->setURL('delete_item.php',       FALSE, array('user'),   array('item' => $permit_class) );
        }
    }

    # Hosts Service view
    $NConf_PERMISSIONS->setURL('modify_item_service.php', FALSE, array('user') );

    # Clone host/service
    $NConf_PERMISSIONS->setURL('clone_host.php',              FALSE, array('user') );
    $NConf_PERMISSIONS->setURL('clone_host_write2db.php',     FALSE, array('user') );
    $NConf_PERMISSIONS->setURL('clone_service.php',           FALSE, array('user') );
    $NConf_PERMISSIONS->setURL('clone_service_write2db.php',  FALSE, array('user') );

    # Config deployment
    $NConf_PERMISSIONS->setURL('call_file.php', FALSE, array('user') );
    

    # id_wrapper
    // here was also "&id_str="... really need that?
    $NConf_PERMISSIONS->setURL('id_wrapper.php',           FALSE, array('user'),   array('item'=>'host') );
    $NConf_PERMISSIONS->setURL('id_wrapper.php',           FALSE, array('user'),   array('item'=>'hostgroup') );
    $NConf_PERMISSIONS->setURL('id_wrapper.php',           FALSE, array('user'),   array('item'=>'service') );
    $NConf_PERMISSIONS->setURL('id_wrapper.php',           FALSE, array('user'),   array('item'=>'advanced-service') );
    $NConf_PERMISSIONS->setURL('id_wrapper.php',           FALSE, array('user'),   array('item'=>'servicegroup') );
    

    # Update process for admin
    $NConf_PERMISSIONS->setURL('UPDATE.php', TRUE, array('admin') );
    
    # Install for all
    $NConf_PERMISSIONS->setURL('INSTALL.php');
?>
