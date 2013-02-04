<?php
echo '
    <h2 id="nav-basic-items" class="ui-nconf-header ui-widget-header ui-corner-top pointer"><span>'.TXT_MENU_ADMINISTRATION.'</span></h2>
    <div class="ui-widget-content box_content">';

        ###
        # administration menu
        ###
        $admin_menu = array();
        array_push($admin_menu, array("nav_links" => "static_file_editor.php", "friendly_name" => "Edit static config files", "grouping" => "", "icon" => "editor-static-files"));
        array_push($admin_menu, array("nav_links" => "show_attr.php",
                                      "friendly_name" => "Attributes",
                                      "grouping" => "",
                                      "icon" => "attributes",
                                      "nav_alias" => array("modify_attr.php", "delete_attr.php"),
                                      ));
        array_push($admin_menu, array("nav_links" => "show_class.php",
                                      "friendly_name" => "Classes",
                                      "grouping" => "",
                                      "icon" => "classes",
                                      "nav_alias" => array("modify_class.php", "delete_class.php"),
                                      ));
        # create output
        create_menu($admin_menu);

echo '</div>';

?>