<?php

###
# call all the functions, make the dependency view
###
function generate_tree ($id, $mode = ''){
    # selected item

    # get childs
    $child_array = get_childs($id, $mode);

    # get information of selected item
    $query = 'SELECT DISTINCT attr_value as name,ItemLinks.fk_id_item AS item_id,
                  (SELECT config_class FROM ConfigItems,ConfigClasses
                      WHERE id_class=fk_id_class AND id_item=item_id) AS type,
                  (SELECT attr_value
                    FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses
                    WHERE ConfigValues.fk_id_item = ItemLinks.fk_item_linked2
                    AND id_attr = ConfigValues.fk_id_attr
                    AND attr_name = "icon_image"
                    AND id_class = fk_id_class
                    AND config_class = "os"
                    AND ItemLinks.fk_id_item = item_id
                  ) AS os_icon
                FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
                WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                    AND id_attr=ItemLinks.fk_id_attr
                    AND ConfigAttrs.visible="yes"
                    AND fk_id_class=id_class
                    AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                    AND ItemLinks.fk_id_item="'.$id.'"
                ORDER BY config_class DESC,attr_value';

    $selected_item_info = db_handler($query, "assoc", "Get informations on selected item");

    # get informations about host
    # prepend inforomation from selected host to the top of the list
    array_unshift($child_array, get_informations($id) );

    # set values of selected item AND put all childs (generated bevore) in it.
    $root_item = array( $id => array(
                        "id" => $id,
                        "selected" => TRUE,
                        "status" => 'open',
                        "name" => $selected_item_info["name"],
                        "type" => $selected_item_info["type"],
                        "os_icon" => $selected_item_info["os_icon"],
                        "childs" => $child_array) );


    # get parents
    $parents_flat = get_parents($id);
    # make the parents array ordered top2down
    $parents_flat = array_reverse($parents_flat);

    # prepare list (if there are parents call the prepare function
    if (!empty($parents_flat) ){
        $tree = prepare_dependency($parents_flat, $root_item);
    }else{
        $tree = $root_item;
    }

    ## Display the top tree item
    # check for parent loop error
    if (isset($tree[0]["status"]) AND $tree[0]["status"] == "loop_error"){
        # make a error item at the top
        $dependency_tree = array( "root" => array(
                        "id" => "root",
                        "status" => 'open',
                        "name" => TXT_DEPVIEW_ERROR_LOOP,
                        "type" => "warn",
                        "childs" => $tree) );

    }else{
        # make a root tree at the top
        $dependency_tree = array( "root" => array(
                        "id" => "root",
                        "status" => 'open',
                        "name" => "Top level",
                        "type" => "parent",
                        "childs" => $tree) );
    }

//echo "<pre>";
//var_dump($tree);
//echo "</pre>";

        
    echo '<div>';
        displayTree_list($dependency_tree);
    echo '</div>';



}








###
# get vars
###

# get xmode ( for nagios view)
if ( isset($_GET["xmode"]) ) {
    $xmode = $_GET["xmode"];
}else{
    $xmode = '';
}

# ID of selected item
if ( isset($_GET["id"]) ) {
    $id = $_GET["id"];
}elseif ( isset($_POST["id"]) ) {
    $id = $_POST["id"];
}


# set class
$class = "host";



###
# VIEW
###


if ($xmode == "nagiosview"){
    # View for nagios (just the tree)
    require_once 'main.php';

    # Lookup ID of hostname
    if ( empty($id) AND !empty($_GET["hostname"]) ){
        $id = db_templates("get_id_from_hostname", $_GET["hostname"]);
    }
    

    if ( defined('TEMPLATE_DIR') ){
        # ownstyle and new.css will be removed in a future release
        echo '<link rel="stylesheet" type="text/css" href="design_templates/'.TEMPLATE_DIR.'/new.css">';

        # This is the main CSS and will be the only on in later releases
        echo '<link rel="stylesheet" type="text/css" href="design_templates/'.TEMPLATE_DIR.'/main.css">';
        echo '<link rel="shortcut icon" href="design_templates/'.TEMPLATE_DIR.'/favicon.ico">';
    }

    # overwrite CSS
    echo '
        <style type="text/css">
        <!--
        /* ... styles are defined here ... */
        body {
            background-image: none !important;
        }
        -->
    </style>
    ';

    echo '
    <!-- Load nconf js functions -->
    <script src="include/js/nconf.js" type="text/javascript"></script>
    ';
    
    echo '<link rel="stylesheet" type="text/css" href="design_templates/'.TEMPLATE_DIR.'/main.css">';
    echo '<div style="text-align: left; margin: 10px; margin-right: auto;">';
    ###
    # Content of nagiosview
    ###
    echo '<div class="dependency_info color_list2">';
        echo '<h2 class="color_list3">Show host parent / child relationships</h2>';
            echo VERSION_STRING.'&nbsp;&nbsp;&nbsp;<a target="_new" href="http://www.nconf.org">www.nconf.org</a>';
    echo '</div>';

    $hostname = db_templates("get_value", $id, "host_name");
    echo '<div style="margin-top:20px; margin-bottom:20px;"><h2>parent / child relationships for host \''.$hostname.'\'</h2></div>';

    # Show the tree
    if (!empty($id)){
        generate_tree($id, $xmode);
    }
    echo '</div>';


}else{
    # Normal NConf view

    require_once 'include/head.php';

    // Form action and url handling
    $request_url = set_page();

    /*
        # Get config classes
        #$query = 'SELECT config_class FROM ConfigClasses ORDER BY config_class';
        #$classes = db_handler($query, "array", "Get classes");
        //$classes = array("host", "services");
        $classes = array("host");

        if (!isset($classes)){
            $classes = array();
            message('ERROR', "You didn't select a class");
        }else{
            # Class
            if ( !empty($_GET["class"]) ) {
                $class = $_GET["class"];
            }elseif ( !empty($_POST["class"]) ) {
                $class = $_POST["class"];
            }else{
                $class = $classes[0];
            }
        }
    */

    ###
    # Info/Warning in the top right corner
    ###
    echo NConf_HTML::page_title('dependency', "Host parent / child relationships");

    echo '
    <div class="editor_info">';
        $content = 'This view allows you to graphically browse your host\'s parent / child relationships.';
        echo NConf_HTML::show_highlight('Info', $content);
    echo '</div>';


    echo '<form name="editor" action="dependency.php" method="post">
    <fieldset class="inline ui-widget-content">
    <legend>choose a host</legend>
    <table>';


    ###
    # List class and items
    ###
        /*
        echo '<tr>';
            echo '<td>Class</td>';
        echo '</tr>';
        echo '<tr><td><select name="class" style="width:192px" onchange="document.editor.id.value=\'\'; document.editor.submit()">';
            //echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';
            foreach($classes as $class_item){
                echo '<option value='.$class_item;
                if ( (!empty($class) ) AND ($class == $class_item) ) echo " SELECTED";
                echo '>'.$class_item.'</option>';
            }

            echo '</select>&nbsp;&nbsp;</td>';
        echo '</tr>';
        */
        if ( !empty($class) ){
            echo '<tr>';
                //echo '<td>'.$class.'</td>';
                //echo '<td>Host</td>';
            echo '</tr>';

            if ($class == "host"){
                $query = 'SELECT fk_id_item AS ID,
                        attr_value AS hostname
                        FROM ConfigValues,ConfigAttrs,ConfigClasses
                        WHERE id_attr=fk_id_attr AND naming_attr="yes"
                           AND id_class=fk_id_class
                           AND config_class="'.$class.'"
                        ORDER BY hostname';

            }else{
                
                $query = 'SELECT id_item AS ID,
                                 attr_value AS entryname,
                        (SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                            WHERE fk_item_linked2=ConfigValues.fk_id_item
                                AND id_attr=ConfigValues.fk_id_attr
                                AND naming_attr="yes"
                                AND fk_id_class = id_class
                                AND config_class="host"
                                AND ItemLinks.fk_id_item=id_item) AS hostname
                        FROM ConfigItems,ConfigValues,ConfigAttrs,ConfigClasses
                        WHERE id_item=fk_id_item
                            AND id_attr=fk_id_attr
                            AND naming_attr="yes"
                            AND ConfigItems.fk_id_class=id_class
                            AND config_class="'.$class.'"
                            ORDER BY hostname,entryname';
            }

            $items = db_handler($query, "array", "Get items of the class $class");
            echo '<tr>';
                echo '<td><select name="id" style="width:192px" onchange="document.editor.submit()">';
                //echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';
                echo '<option value="">choose a host...</option>';

                foreach($items as $item_array){
                    # set variables
                    $item_id = $item_array["ID"];
                    $item_name = "";
                    if (isset($item_array["hostname"])){
                        $item_name .= $item_array["hostname"];
                        if (isset($item_array["entryname"]) ) $item_name .= ":";
                    }
                    if (isset($item_array["entryname"]) ) $item_name .= $item_array["entryname"];

                    echo "<option value=$item_id";
                    if ( (isset($id) ) AND ($id == $item_id) ){
                        echo " SELECTED";
                        # set the name of the selected item
                        $main_item_name = $item_name;
                    }
                    echo ">$item_name</option>";
                }

                echo '</select>&nbsp;&nbsp;</td>';
            echo '</tr>';
        }

    echo '</table></fieldset>';
    echo '</form><br>';



    # Show the tree
    if (!empty($id)){
        generate_tree($id);
    }
    

    mysql_close($dbh);
    require_once 'include/foot.php';

}









?>
