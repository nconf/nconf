<?php
echo '
    <h2 id="nav-basic-items" class="ui-nconf-header ui-widget-header ui-corner-top pointer"><span>'.TXT_MENU_BASIC.'</span></h2>
    <div class="ui-widget-content box_content">';

        ###
        # user navigation start
        $user_menu_begin = array();
        array_push($user_menu_begin, array("nav_links" => "History::history.php", "friendly_name" => "History", "grouping" => "", "icon" => "history" ));
        array_push($user_menu_begin, array("nav_links" => "Host parent / child view::dependency.php", "friendly_name" => "Host parent / child view", "grouping" => "", "icon" => "dependency" ));
        # create output
        create_menu($user_menu_begin);

        $user_menu_begin2 = array();
        # Create oncall link, if $ONCALL_GROUPS is defined
        if (!empty($ONCALL_GROUPS)){
            array_push($user_menu_begin2, array("nav_links" => "Change on-call settings::overview.php?class=contact&amp;xmode=pikett", "friendly_name" => "Change on-call settings", "grouping" => "", "icon" => "oncall"));
        }
        # Generate Nagios config link
        array_push($user_menu_begin2, array("nav_links" => "Generate Nagios config::generate_config.php", "friendly_name" => "Generate Nagios config", "grouping" => "", "icon" => "generate-config"));

        # create output
        create_menu($user_menu_begin2);



        ###
        # user navigation links of classes
        # Select ConfigClasses
        $query = 'SELECT grouping, nav_links, friendly_name, config_class  FROM ConfigClasses WHERE nav_privs = "user" AND nav_visible = "yes" ORDER BY UPPER(grouping), ordering ASC, config_class';
        $user_menu_end = db_handler($query, "array", "Select user Navigation classes");


        /* -> this seems to be not needed anymore, it makes not correct ordering in the menu
        # sorts a multidimensional array (grouping)
        # because of manual added entries like "generate nagios config"
        $tmp = Array();
        foreach($user_menu_end as &$ma){
            $tmp[] = &$ma["grouping"];
        }
        NConf_DEBUG::set($tmp, 'DEBUG', 'temporary');
        NConf_DEBUG::set($user_menu_end, 'DEBUG', 'user_menu_end');
        array_multisort($tmp, $user_menu_end); 
        */
        NConf_DEBUG::set($user_menu_end, 'DEBUG', 'Create menu user');

        # Display  menu
        create_menu($user_menu_end);


echo '</div>';

include('include/menu/static_content/menu_user_end.html');

?>
