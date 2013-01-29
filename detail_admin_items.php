<?php

require_once 'include/head.php';
//set_page();

# Get ID
if ( !empty($_REQUEST["id"]) ){
    // Be sure ID it is an integer - fixes injecting issues
    $id = (int) $_REQUEST["id"];
}else{
    NConf_DEBUG::set("No id", 'ERROR');
}

#determine if class or attribute
if ( !empty($_REQUEST["type"]) ){
    $type = $_REQUEST["type"];
}else{
    NConf_DEBUG::set("This type does not exist", 'ERROR');
}



// end / exit page if error
if ( NConf_DEBUG::status('ERROR') ) {
    echo NConf_HTML::exit_error();
}


if ($type == "class") {



    ########################################
    ## configure user friendly names here ##
    ########################################
    $user_friendly_names = array(
        "id_attr" 	=> "Attribute ID",
        "id_class" 	=> "Class ID",
        "attr_name"	=> "Nagios-specific attribute name",
        "config_class"	=> "Nagios-specific class name",
        "friendly_name" 	=> "Friendly name (shown in GUI)",
        "description" 	=> "description, example or help-text",
        "datatype" 	=> "Data type",
        "max_length" 	=> "max. text-field length (chars)",
        "poss_values" 	=> "Possible values",
        "predef_value" 	=> "Predefined value",
        "mandatory" 	=> "Is attribute mandatory",
        "ordering" 	=> "Ordering position",
        "nav_visible" 	=> "Is Class visible in Navigation",
        "visible" 	=> "Is attribute visible",
        "write_to_conf" 	=> "write attribute to configuration",
        "naming_attr" 	=> "naming attribute",
        "link_as_child" 	=> "link selected item(s) as children",
        "fk_show_class_items" 	=> "items of class to be assigned",
        "fk_id_class" 	=> "attribute belongs to class",
        "grouping" 	=> "Navigation Group",
        "nav_links" 	=> "Configure Links",
        "nav_privs" 	=> "Viewable by",
        "out_file" 	    => "generated filename",
        "nagios_object" => "Nagios object definition"
    );
    ########################################
    ########################################

    $HTTP_referer = 'show_class.php';

    #query
    $query = 'SELECT * FROM ConfigClasses WHERE id_class = '.$id;



}elseif($type == "attr"){

    ########################################
    ## configure user friendly names here ##
    ########################################
    $user_friendly_names = array(
        "id_attr"   => "Attribute ID",
        "attr_name" => "Nagios-specific attribute name",
        "friendly_name"     => "Friendly name (shown in GUI)",
        "description"   => "description, example or help-text",
        "datatype"  => "Data type",
        "max_length"    => "max. text-field length (chars)",
        "poss_values"   => "Possible values",
        "predef_value"  => "Predefined value",
        "mandatory"     => "Is attribute mandatory",
        "ordering"  => "Ordering position",
        "visible"   => "Is attribute visible",
        "write_to_conf"     => "write attribute to configuration",
        "naming_attr"   => "naming attribute",
        "link_as_child"     => "link selected item(s) as children",
        "link_bidirectional"    => "link selected item(s) as bidirectional",
        "fk_show_class_items"   => "items of class to be assigned",
        "fk_id_class"   => "attribute belongs to class",
    );
    ########################################
    ########################################

    $HTTP_referer = 'show_attr.php?class='.$_GET["class"];

    # query
    $query = 'SELECT id_attr, attr_name, ConfigAttrs.friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ConfigAttrs.ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional,
            (SELECT ConfigClasses.config_class FROM ConfigClasses WHERE id_class= fk_show_class_items) AS fk_show_class_items,
            ConfigClasses.config_class AS fk_id_class
                FROM ConfigAttrs, ConfigClasses
                WHERE id_attr ='.$id.'
                AND fk_id_class = ConfigClasses.id_class';

}

# display detail page

$_SESSION["go_back_page_ok"] = $HTTP_referer;
message($debug, "url : ".$_SESSION["go_back_page_ok"]);


// clear cache , if not cleared
if ( isset($_SESSION["cache"]["modify_class"]) ) unset($_SESSION["cache"]["modify_class"]);

echo '<div style="position: absolute; min-width: 350px;">';

    echo '<div class="ui-nconf-header ui-widget-header ui-corner-tl ui-corner-tr ui-helper-clearfix">';

        echo '<div><h2>Details</h2></div>';
        echo '<div id="ui-nconf-icon-bar">';

            if(!isset($_GET["xmode"])){
                if ($type == "attr"){
                    echo '<a href="modify_attr.php?id='.$id.'">'.ICON_EDIT.'</a>';
                    echo '<a href="delete_attr.php?id='.$id.'">'.ICON_DELETE.'</a>';
                }elseif($type == "class"){
                    echo '<a href="modify_class.php?id='.$id.'">'.ICON_EDIT.'</a>';
                    echo '<a href="delete_class.php?id='.$id.'">'.ICON_DELETE.'</a>';
                }
            }
        echo '</div>';

    echo '</div>';
    echo '<div class="ui-nconf-content ui-widget-content ui-corner-bottom">';

        echo '<table class="ui-nconf-table ui-nconf-max-width">';


        # get entries
        $entries = db_handler($query, "array", "Get Details of $type");
        foreach($entries[0] as $title=>$value){
            // Change the titles for more user friendly titles
            $title = strtr($title, $user_friendly_names);

            // Display the row
            echo '<tr>';
                echo '<td class="color_list2" width="200">'.$title.':</td>';
                echo '<td class="color_list1 highlight">'.$value.'</td>';
            echo '</tr>';
        }



        echo '</table>';

    echo '</div>';

mysql_close($dbh);
require_once 'include/foot.php';

?>
