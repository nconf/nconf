<?php
// Functions

# make info, error or debug text
$debug = 'debug';
$info = 'info';
$error = 'error';
$critical = 'critical';
function message($LEVEL, $text, $mode = "standard"){
/*
    $variable = '';
    switch($mode){
        case "standard":
            $variable  .= "$text<br>";
            break;
        case "grouptitle":
            $variable  .= "<hr><br><h2><b>".$text."</b></h2><br>";
            break;
        case "list":
            $variable  .= "-&nbsp;$text<br>";
            break;


        case "overwrite":
            $variable  = "$text<br>";
            break;
        case "ok":
            $variable  .= "<font color='green'><b>[ OK ]</b></font>&nbsp;$text<br><br>";
            break;
        case "failed":
            $variable  .= "<font color='red'><b>[ FAILED ]</b></font>&nbsp;$text<br>";
            break;
        case "red":
            $variable  .= "<font color='red'>&nbsp;$text</font><br>";
            break;
        case "nomatch":
            $variable  .= "<font color='orange'><b>[ NO MATCH ]</b></font>&nbsp;$text<br>";
            break;
        default:
            $variable  .= "$text<br>"; 
    }
    
    */
    $TITLE = '';

    switch($mode){
        case "grouptitle":
            break;
        case "list":
            $text  = "-&nbsp;".$text;
            break;


        default:
        case "standard":
    }

    NConf_DEBUG::set($text, $LEVEL, $TITLE);
}


function escape_string($string){
    # Strip slashes if magic_quotes_gpc is ON (DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 6.0.0.)
    # Reverse magic_quotes_gpc/magic_quotes_sybase effects on those vars if ON.
    if (get_magic_quotes_gpc() ){
        message('DEBUG', "magic_quotes_gpc is ON: using stripslashes to correct it");
        $string = stripslashes($string);
    }
    
    # Make a safe string
    $escaped_string = mysql_real_escape_string($string);
    return $escaped_string;
}


function set_page(){
    // set source page for comeback
    $URL = basename($_SERVER['REQUEST_URI']);
    $URL = str_replace("?clear=1", "", $URL);
    $URL = str_replace("&clear=1", "", $URL);
    $_SESSION["go_back_page"] = $URL;
    message('DEBUG', "set sourcepage for edit: $URL");
    return $URL;
}

function define_colgroup($type = ''){
    if ($type == "multi_modify"){
        return '
        <colgroup>
            <col width="260">
            <col width="10">
            <col width="260">
            <col width="10">
            <col width="230">
        </colgroup>
        ';
    }else{
        return '
        <colgroup>
            <col width="160">
            <col width="260">
            <col width="10">
            <col width="260">
            <col width="10">
            <col width="70">
        </colgroup>
        ';
    }
}



# attribute order handler
# this function sets a new order
# it also handles dublicate order and change theme to the correct position
function set_attr_order($attr_id, $attr_order, $attr_class, $old_order = 'new'){
    # detect if it is a move up or down (uppest = 1, down = 7,8,9...)
    if ($old_order == "new"){
        $mode = "up";
    }elseif($old_order > $attr_order){
        $mode = "up";
    }elseif($old_order < $attr_order){
        $mode = 'down';
    }

    # loop trhu attrs
    do {
        $items_to_change = db_handler('SELECT id_attr, ordering, fk_id_class FROM ConfigAttrs WHERE fk_id_class="'.$attr_class.'" AND id_attr <> "'.$attr_id.'" AND ordering = "'.$attr_order.'"', "array", "GET items with identic order");

        # Debug
        //var_dump($items_to_change);

        if ( !empty($items_to_change) AND ( count($items_to_change) == 1) ){
            # change vars for next loop
            $attr_id = $items_to_change[0]["id_attr"];

            # calculate new order position
            # AND increase / decrease order for next run
            if ($mode == "up"){
                $attr_order++;
            }elseif($mode == "down"){
                $attr_order--;
            }
            # change order
            $query = 'UPDATE ConfigAttrs SET ordering='.$attr_order.' WHERE id_attr = '.$attr_id.' AND fk_id_class='.$attr_class;
            db_handler($query, "insert", "UPDATE: increased ordering of an other item with same order number");

        }elseif ( !empty($items_to_change) AND ( count($items_to_change) > 1) ){
            # dublicates ?
            message('ERROR', "There are entries with duplicate ordering numbers");
            #List all duplicate items
            foreach ($items_to_change as $item){
                message('ERROR', 'Order number '.$attr_order.': '.$item["id_attr"]);
            }

            return;
        }

    }while( !empty($items_to_change) AND count($items_to_change == 0) );

}


function attr_order($id, $mode){
    // Get old order and class
    $result_assoc = db_handler("SELECT ordering, fk_id_class FROM ConfigAttrs WHERE id_attr=$id", "assoc", "GET order and class of attr");
    $old_order  = $result_assoc["ordering"];
    $attr_class = $result_assoc["fk_id_class"];

    // Select next attr to change with
    // Make query right to the mode up/down
    if ($mode == "up") {
        $query = 'SELECT id_attr, ordering AS dest_order FROM ConfigAttrs WHERE ordering < '.$old_order.' AND fk_id_class='.$attr_class.' ORDER BY ordering DESC LIMIT 1';
    }elseif ($mode == "down"){
        $query = 'SELECT id_attr, ordering AS dest_order FROM ConfigAttrs WHERE ordering > '.$old_order.' AND fk_id_class='.$attr_class.' ORDER BY ordering ASC LIMIT 1';
    }
    $result_assoc = db_handler($query, "assoc", "GET new order (and the attr_id of destination)");
      $dest_attr  = $result_assoc["id_attr"];
      $dest_order = $result_assoc["dest_order"];

    // IF there is an attribute to change position
    if ($result_assoc){
        // change attributes order
        $query = 'UPDATE ConfigAttrs SET ordering='.$old_order.' WHERE ordering = '.$dest_order.' AND fk_id_class='.$attr_class;
        db_handler($query, "insert", "UPDATE: move all attributes with destination ordering");
        $query = 'UPDATE ConfigAttrs SET ordering='.$dest_order.' WHERE id_attr = '.$id.' AND fk_id_class='.$attr_class;
        db_handler($query, "insert", "UPDATE: move selected attribute");

    }

}

function class_order($id, $mode){
    // Get old order and class
    $result_assoc  = db_handler("SELECT ordering, grouping, nav_privs FROM ConfigClasses WHERE id_class=$id", "assoc", "GET order and grouping of class");
      $old_order  = $result_assoc["ordering"];
      $group  = $result_assoc["grouping"];
      $nav_priv  = $result_assoc["nav_privs"];

    // Select next class to change with
    // Make query right to the mode up/down
    if ($mode == "up") {
        $query = 'SELECT id_class, ordering AS dest_order FROM ConfigClasses WHERE ordering < '.$old_order.' AND grouping="'.$group.'" AND nav_privs="'.$nav_priv.'" ORDER BY ordering DESC LIMIT 1';
    }elseif ($mode == "down"){
        $query = 'SELECT id_class, ordering AS dest_order FROM ConfigClasses WHERE ordering > '.$old_order.' AND grouping="'.$group.'"AND nav_privs="'.$nav_priv.'" ORDER BY ordering ASC LIMIT 1';
    }
    $result_assoc = db_handler($query, "assoc", "GET new order (and the id_class of destination)");
      $dest_id  = $result_assoc["id_class"];
      $dest_order = $result_assoc["dest_order"];

    // IF there is an class to change position
    if ($result_assoc){
        // change class order
        $query = 'UPDATE ConfigClasses SET ordering='.$old_order.' WHERE id_class='.$dest_id;
        db_handler($query, "insert", "UPDATE: move other class with destination ordering");
        $query = 'UPDATE ConfigClasses SET ordering='.$dest_order.' WHERE id_class = '.$id;
        db_handler($query, "insert", "UPDATE: move selected class");

    }

}








# Mandatory fields
# write to db only if mandatory fields are ok!
function check_mandatory($mandatory, &$values2check){
    $write2db = "yes";

    foreach ($mandatory AS $var_name => $friendly_name){
        # Get the value which should be checked
        if ( !isset($values2check[$var_name]) ){
            # no value/array found
            $check_value = '';
            # do nothin here
        }elseif ( is_array($values2check[$var_name]) ){
            $check_value = $values2check[$var_name][0];
        }else{
            $check_value = $values2check[$var_name];
        }

        # Check 
        if ( ( isset($check_value) ) AND ( $check_value != "") ){
            NConf_DEBUG::set($friendly_name, 'DEBUG', "ok");
        }else{
            NConf_DEBUG::set($friendly_name, 'ERROR', "mandatory field empty");
            $write2db = 'no';
        }
    }
    return $write2db;
}


# History insert entry
#   action = created, added, assigned, unassigned, modified, removed
#   name = "name" of object
#   value = "value" of object
#   fk_id_item = OPTIONAL
#   user = "user", will be taken from SESSION, otherwise is unknown(but should not be)
function history_add($action, $name, $value, $fk_id_item = 'NULL', $feature = '', $feature_values = ''){
    # User handling
    if ( !empty($_SESSION["userinfos"]["username"]) ){
        $user = $_SESSION["userinfos"]["username"];
    }else{
        $user = "unknown";
    }
    # Feature's
    switch($feature){
        # Resolve assignment looks up the real value behind the id(foreign keys)
        # It doesn't matter if there is only one ore multiple id's
        case "resolve_assignment":
            #if comma seperated, make array
            if ( is_array($value) ){
                $ids = $value;
            }else{
                $ids = explode(",", $value);
            }

            # get entries
            $value_array = array();
            foreach ($ids as $id){
                $value_array[] = db_templates("naming_attr", $id);
            }

            # make string for history entry
            $value = implode(",", $value_array);
        break;
        case "add_service":
            $hostname = db_templates("naming_attr", $feature_values);
        break;

    }

    # Do not write password attributes plaintext in history
    if (is_numeric($name) ){
        $attr_datatype = db_templates("attr_datatype", $name);
        if ($attr_datatype == "password"){
            # Overwrite the password
            $value = PASSWD_HIDDEN_STRING;
        }
    }

    # if name is integer, look for attr name
    if (is_numeric($name) ){
        $name = db_templates("friendly_attr_name", $name);
    }

    # add the hostname to service entries (services are not unique)
    if ($name == "service" AND !empty($fk_id_item) ){
        if ( !empty($hostname) ){
            $value = $hostname.': '.$value;
        }else{
            if ( db_templates("class_name", $fk_id_item) == "host" ){
                $hostname_of_service = db_templates("naming_attr", $fk_id_item);
                $value = $hostname_of_service.': '.$value;
            }else{
                $hostname_of_service = db_templates("get_linked_item", $fk_id_item, "host_name");
                $value = $hostname_of_service[0]["attr_value"].': '.$value;
            }
        }
    }

    # Insert into History
    $query = "INSERT INTO `History` (user_str, action, attr_name, attr_value, fk_id_item) VALUES ( '$user', '$action', '$name', '$value', $fk_id_item)";

    db_handler($query, 'affected', 'Add to History');
}



# reloads the db connection and selects the db
# (must be user after auth by sql)
function relaod_nconf_db_connection(){
    $dbh = mysql_connect(DBHOST,DBUSER,DBPASS);
    mysql_select_db(DBNAME);
}


# NEW DB HANDLER templates
function db_templates($template, $value = '', $search = '', $filter = '', $output_type = ''){
    $value  = escape_string($value);
    $search = escape_string($search);
    $filter = escape_string($filter);
    /*
    if ( empty($value) ){
        message ('DEBUG', "no value for lookup in db_template");
        return;    
    }
    */

    switch($template){
        case "naming_attr":     # : is old get_value(attr_name)
            $query = 'SELECT attr_value
                        FROM ConfigValues, ConfigAttrs
                        WHERE fk_id_attr=id_attr
                        AND naming_attr="yes"
                        AND fk_id_item="'.$value.'"';
            $output = db_handler($query, 'getOne', "select naming_attr name");
            break;
        case "get_naming_attr_from_class":
            $query = 'SELECT id_attr
                        FROM ConfigAttrs,ConfigClasses
                        WHERE naming_attr="yes"
                            AND id_class=fk_id_class
                            AND config_class="'.$value.'"';
            $output = db_handler($query, "getOne", "naming_attr ID of $value:");
            break;
        case "get_id_of_item":
            $query = 'SELECT fk_id_item
                        FROM ConfigValues, ConfigAttrs
                        WHERE fk_id_attr = id_attr
                            AND id_attr = "'.$value.'"
                            AND attr_value = "'.$search.'"';
            $output = db_handler($query, "getOne", "item ID of $search:");
            break;
        case "get_id_of_class":
            $query = 'SELECT id_class FROM ConfigClasses WHERE config_class = "'.$value.'"';
            $output = db_handler($query, "getOne", "class id");
            break;
        case "get_classid_of_item":
            $query = 'SELECT fk_id_class FROM ConfigItems WHERE id_item = "'.$value.'"';
            $output = db_handler($query, "getOne", "Get class_id of item");
            break;
        case "get_id_of_hostname_service":
            $query = 'SELECT id_item,attr_value AS servicename,
                    (SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                        WHERE fk_item_linked2=ConfigValues.fk_id_item
                            AND id_attr=ConfigValues.fk_id_attr
                            AND naming_attr="yes"
                            AND fk_id_class = id_class
                            AND config_class="host"
                            AND ItemLinks.fk_id_item=id_item) AS hostname
                    FROM ConfigItems,ConfigValues,ConfigAttrs
                    WHERE id_item=fk_id_item
                        AND id_attr=fk_id_attr
                        AND id_attr="'.$value.'"
                    HAVING CONCAT(hostname,":",servicename) LIKE "'.$search.'"';
            $output = db_handler($query, "getOne", "item ID of $search:");
            break;
        case "lookup_ConfigClasses":
            $query = 'SELECT '.$value.'
                        FROM ConfigClasses
                        WHERE id_class="'.$search.'"';
            $output = db_handler($query, 'getOne', 'Lookup ConfigClasses: select "'.$value.'" of id_attr="'.$search.'"');
            break;
        case "attr_name":
            $query = 'SELECT attr_name
                        FROM ConfigAttrs
                        WHERE id_attr="'.$value.'"';
            $output = db_handler($query, 'getOne', "select attr name");
            break;
        case "attr_datatype":
            $query = 'SELECT datatype FROM `ConfigAttrs`
                        WHERE id_attr = "'.$value.'"';
            $output = db_handler($query, "getOne", "Lookup datatype");
            break;
        case "friendly_attr_name":
            $query = 'SELECT friendly_name
                        FROM ConfigAttrs
                        WHERE id_attr="'.$value.'"';
            $output = db_handler($query, 'getOne', "select attr name");
            break;
        case "class_name":
            $query = 'SELECT config_class
                        FROM ConfigClasses,ConfigItems
                        WHERE id_class = fk_id_class
                        AND id_item = "'.$value.'"';
            $output = db_handler($query, 'getOne', "select class name");
            break;
        case "class_friendly_name":
            $query = 'SELECT friendly_name
                        FROM ConfigClasses
                        WHERE config_class = "'.$value.'"';
            $output = db_handler($query, 'getOne', "select class name");
            break;
        case "get_value":
            $query = 'SELECT attr_value
                        FROM ConfigAttrs,ConfigValues,ConfigItems
                        WHERE id_attr=fk_id_attr
                        AND id_item=fk_id_item
                        AND ConfigAttrs.visible="yes" 
                        AND id_item="'.$value.'"
                        AND attr_name = "'.$search.'"
                        ORDER BY ConfigAttrs.ordering';

            $output = db_handler($query, "getOne", "get $search of item with id $value");
            break;

        case "get_linked_item":
            $query = 'SELECT attr_value, ConfigValues.fk_id_item'
                . ' FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses'
                . ' WHERE fk_item_linked2 = ConfigValues.fk_id_item'
                . ' AND id_attr = ItemLinks.fk_id_attr'
                . ' AND ConfigAttrs.visible = "yes"'
                . ' AND fk_id_class = id_class'
                . ' AND ('
                . ' SELECT naming_attr'
                . ' FROM ConfigAttrs'
                . ' WHERE id_attr = ConfigValues.fk_id_attr'
                . ' ) = "yes"'
                . ' AND ItemLinks.fk_id_item ="'.$value.'"';
                if ( !empty($search) ){
                    $query .= ' AND attr_name = "'.$search.'"';
                }
                $query .= ' ORDER BY ConfigAttrs.friendly_name DESC , attr_value';
            if ( empty($output_type) ) $output_type = 'array';
            $output = db_handler($query, $output_type, "get linked $search of item with id $value");
            break;
        # with link as child attrs: (contacts of contactgroup)
        case "get_linked_item_2":
                $query = 'SELECT attr_value'
                . ' FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses'
                . ' WHERE ItemLinks.fk_id_item = ConfigValues.fk_id_item'
                . ' AND id_attr = ItemLinks.fk_id_attr'
                . ' AND ConfigAttrs.visible = "yes"'
                . ' AND fk_id_class = id_class'
                . ' AND ('
                . ' SELECT naming_attr'
                . ' FROM ConfigAttrs'
                . ' WHERE id_attr = ConfigValues.fk_id_attr'
                . ' ) = "yes"'
                . ' AND fk_item_linked2 ="'.$value.'"'
                . ' AND attr_name = "'.$search.'"'
                . ' ORDER BY ConfigAttrs.friendly_name DESC , attr_value';
            $output = db_handler($query, "array", "get linked $search of item with id $value");
            break;

/*
something for later use... other query for link_as_child attrs....
        case "get_linked_item":
            if ($link_as_child == "yes"){
                $query = 'SELECT attr_value'
                . ' FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses'
                . ' WHERE ItemLinks.fk_id_item = ConfigValues.fk_id_item'
                . ' AND id_attr = ItemLinks.fk_id_attr'
                . ' AND ConfigAttrs.visible = "yes"'
                . ' AND fk_id_class = id_class'
                . ' AND ('
                . ' SELECT naming_attr'
                . ' FROM ConfigAttrs'
                . ' WHERE id_attr = ConfigValues.fk_id_attr'
                . ' ) = "yes"'
                . ' AND fk_item_linked2 ="'.$value.'"'
                . ' AND attr_name = "'.$search.'"'
                . ' ORDER BY ConfigAttrs.friendly_name DESC , attr_value';
            }else{
                $query = 'SELECT attr_value'
                . ' FROM ConfigValues, ItemLinks, ConfigAttrs, ConfigClasses'
                . ' WHERE fk_item_linked2 = ConfigValues.fk_id_item'
                . ' AND id_attr = ItemLinks.fk_id_attr'
                . ' AND ConfigAttrs.visible = "yes"'
                . ' AND fk_id_class = id_class'
                . ' AND ('
                . ' SELECT naming_attr'
                . ' FROM ConfigAttrs'
                . ' WHERE id_attr = ConfigValues.fk_id_attr'
                . ' ) = "yes"'
                . ' AND ItemLinks.fk_id_item ="'.$value.'"'
                . ' AND attr_name = "'.$search.'"'
                . ' ORDER BY ConfigAttrs.friendly_name DESC , attr_value';
            }
            $output = db_handler($query, "array", "get linked $search of item with id $value");
            break;
*/
        case "get_id_from_hostname":
            $query = 'SELECT fk_id_item'
                . ' FROM ConfigValues, ConfigAttrs, ConfigClasses'
                . ' WHERE fk_id_attr=id_attr'
                . ' AND attr_value="'.$value.'"'
                . ' AND id_class=fk_id_class'
                . ' AND config_class="host"';
            $output = db_handler($query, 'getOne', "get_id_from_hostname");
            break;
        case "get_attr_id":
            $query = 'SELECT id_attr FROM ConfigAttrs,ConfigClasses
                        WHERE attr_name="'.$search.'"
                        AND id_class=fk_id_class
                        AND config_class="'.$value.'"';
            $output = db_handler($query, 'getOne', "Get attr_id where attr_name = $search and class = $value");
            break;
        case "host_attr_id":
            $query = 'SELECT id_attr FROM ConfigAttrs,ConfigClasses
                        WHERE attr_name="'.$value.'"
                        AND id_class=fk_id_class
                        AND config_class="host"';
            $output = db_handler($query, 'getOne', "select attr id");
            break;
        case "get_attributes_from_class":
            $query = 'SELECT ConfigAttrs.friendly_name, ConfigAttrs.ordering, id_attr, attr_name, datatype, mandatory, naming_attr
                    FROM ConfigAttrs,ConfigClasses
                        WHERE id_class=fk_id_class
                        AND config_class="'.$value.'"
                        AND ConfigAttrs.visible = "yes"
                        ORDER BY ConfigAttrs.ordering';
            $output = db_handler($query, 'array', "select all attributes of a class");
            break;
        case "get_bidirectional":
            # this is a temporary template, which we could use later for ordering
            # could be usefull when we have an aditional order for bidirectional items
            # this function is used also for the old add and modify GUI
            $query = 'SELECT ConfigAttrs.id_attr,
                                 ConfigAttrs.attr_name,
                                 ConfigAttrs.friendly_name,
                                 ConfigAttrs.datatype,
                                 ConfigAttrs.max_length,
                                 ConfigAttrs.poss_values,
                                 ConfigAttrs.predef_value,
                                 ConfigAttrs.mandatory,
                                 ConfigAttrs.ordering,
                                 ConfigAttrs.visible,
                                 ConfigAttrs.fk_show_class_items,
                                 ConfigAttrs.description
                          FROM ConfigAttrs,ConfigClasses
                          WHERE id_class=fk_id_class
                              AND ConfigClasses.config_class="'.$value.'"
                              AND ConfigAttrs.visible="yes"';
            $query .= ' UNION ';
            $query .= 'SELECT ConfigAttrs.id_attr,
                                 ConfigAttrs.attr_name,
                                 ConfigAttrs.friendly_name,
                                 ConfigAttrs.datatype,
                                 ConfigAttrs.max_length,
                                 ConfigAttrs.poss_values,
                                 ConfigAttrs.predef_value,
                                 ConfigAttrs.mandatory,
                                 ConfigAttrs.ordering,
                                 ConfigAttrs.visible,
                                 ConfigAttrs.fk_id_class,
                                 ConfigAttrs.description
                          FROM ConfigAttrs,ConfigClasses
                          WHERE id_class=fk_show_class_items 
                              AND ConfigClasses.config_class="'.$value.'"
                              AND ConfigAttrs.visible="yes"
                              AND ConfigAttrs.link_bidirectional="yes"
                          ORDER BY ordering';
            $output = db_handler($query, 'result', "select all attributes (UNION bidirectional attrs)");
            break;
        case "get_attributes_with_bidirectional":
            # this is the new template to get ALL attributes, including the bidirectional ones
            # this template could also be used to get informations about ONE attribute (used on multimodify)
            #   for this the $search must be given
            $query = 'SELECT ConfigAttrs.id_attr,
                                 ConfigAttrs.attr_name,
                                 ConfigAttrs.friendly_name,
                                 ConfigAttrs.datatype,
                                 ConfigAttrs.max_length,
                                 ConfigAttrs.poss_values,
                                 ConfigAttrs.predef_value,
                                 ConfigAttrs.mandatory,
                                 ConfigAttrs.naming_attr,
                                 ConfigAttrs.ordering,
                                 ConfigAttrs.visible,
                                 ConfigAttrs.fk_id_class,
                                 ConfigAttrs.fk_show_class_items,
                                 ConfigAttrs.description,
                                 ConfigAttrs.link_bidirectional
                          FROM ConfigAttrs,ConfigClasses
                          WHERE id_class=fk_id_class
                              AND ConfigClasses.config_class="'.$value.'"
                              AND ConfigAttrs.visible="yes"';
                # if search is given, limit result to selected attr (needed on multy modify)
                if ( !empty($search) ) $query .= ' AND ConfigAttrs.attr_name="'.$search.'"';
                $query .= ' ORDER BY ordering';
            if ( !empty($search) ){
                # got one attribute
                $output = db_handler($query, 'array', 'get details of attribute (part 1): "'.$search.'"');
            }else{
                # got all attributes
                $output = db_handler($query, 'array', 'select all attributes (part 1)');
            }

            // if class=service put the check_params to last position
            if ($value == "service"){
                $output_tmp = $output;
                // clear output array
                $output = array();
                foreach ($output_tmp AS $attr){
                    if ($attr["attr_name"] == "check_params"){
                        $check_params = $attr;
                        NConf_DEBUG::set( 'Caching check_params attribute' , "DEBUG"); 
                    }else{
                        array_push($output, $attr);
                    }
                }
            }

            # query for bidirectional entries from other classes
            $query = 'SELECT ConfigAttrs.id_attr,
                                 ConfigAttrs.attr_name,
                                 ConfigAttrs.friendly_name,
                                 ConfigAttrs.datatype,
                                 ConfigAttrs.max_length,
                                 ConfigAttrs.poss_values,
                                 ConfigAttrs.predef_value,
                                 ConfigAttrs.mandatory,
                                 ConfigAttrs.naming_attr,
                                 ConfigAttrs.ordering,
                                 ConfigAttrs.visible,
                                 ConfigAttrs.fk_id_class,
                                 ConfigAttrs.fk_id_class AS fk_show_class_items,
                                 ConfigAttrs.description,
                                 ConfigAttrs.link_bidirectional
                          FROM ConfigAttrs,ConfigClasses
                          WHERE id_class=fk_show_class_items 
                              AND ConfigClasses.config_class="'.$value.'"
                              AND ConfigAttrs.visible="yes"
                              AND ConfigAttrs.link_bidirectional="yes"';
                # if search is given, limit result to selected attr (needed on multy modify)
                if ( !empty($search) ) $query .= ' AND ConfigAttrs.attr_name="'.$search.'"';
                $query .= ' ORDER BY ordering';

            if ( !empty($search) ){
                # got one attribute
                $output_bidirectional = db_handler($query, 'array', 'get details of attribute bidirectional (part 2): "'.$search.'"');
            }else{
                # got all attributes
                $output_bidirectional = db_handler($query, 'array', 'select all attributes bidirectional (part 2)');
            }


            # generate final output (merge the 2 arrays)
            foreach ($output_bidirectional AS $bidi_attr){
                array_push($output, $bidi_attr);
            }

            // if class=service put the check_params to last position
            if ( $value == "service" AND !empty($check_params) ){
                array_push($output, $check_params);
                NConf_DEBUG::set( 'pushing check_params to end of attribute list' , "DEBUG"); 
            }

            $content = NConf_HTML::swap_content($output, '<b>Output</b> merged array (part 1 + part 2)', FALSE, FALSE);
            message('DEBUG', $content);

            break;
        case "hostID_of_service":
            $query = 'SELECT fk_item_linked2
                        FROM ItemLinks, ConfigItems, ConfigClasses
                        WHERE id_item = fk_item_linked2
                        AND fk_id_class = id_class
                        AND config_class = "host"
                        AND fk_id_item = "'.$value.'"';
            $output = db_handler($query, 'getOne', "select host_ID of service");
            break;

        case "get_services_from_host_id":
            if ( empty($search) ) $search = 'service';
            $query = 'SELECT ConfigValues.fk_id_item AS id, attr_value AS name
                FROM ConfigValues, ConfigAttrs, ItemLinks, ConfigClasses
                WHERE id_attr = ConfigValues.fk_id_attr
                AND naming_attr = "yes"
                AND ConfigValues.fk_id_item = ItemLinks.fk_id_item
                AND fk_item_linked2 = "'.$value.'"
                AND fk_id_class = id_class
                AND config_class = "'.$search.'"
                ORDER BY attr_value';
            $output = db_handler($query, 'array_2fieldsTOassoc', 'Get '.$search.'s from Item ID "'.$value.'"');
            break;


        case "servicegroup_id":
            $query = 'SELECT id_attr
                    FROM ConfigAttrs,ConfigClasses
                    WHERE attr_name = "members"
                    AND id_class=fk_id_class
                    AND config_class = "servicegroup"';
            $output = db_handler($query, 'getOne', "Get servicegroup id");
            break;


        case "mandatory":
            $query = 'SELECT  ConfigAttrs.id_attr, ConfigAttrs.friendly_name
                    FROM ConfigAttrs,ConfigClasses
                    WHERE id_class=fk_id_class
                    AND ConfigClasses.config_class="'.$value.'"
                    AND ConfigAttrs.visible="yes"
                    AND ConfigAttrs.mandatory="yes" ';
            # multi modify only needs feedback about one(the selected attribute)
            if ( !empty($search) ) $query .= 'AND ConfigAttrs.id_attr = "'.$search.'"';
            $query .= 'ORDER BY ConfigAttrs.id_attr';

            $output = db_handler($query, 'array_2fieldsTOassoc', "get mandatory fields");
            break;


        case "vererben":
            $query = 'SELECT fk_item_linked2 AS item_id,attr_name
                FROM ItemLinks,ConfigAttrs,ConfigClasses
                WHERE id_attr=fk_id_attr
                AND id_class=fk_id_class
                AND fk_id_item="'.$value.'"
                HAVING ((SELECT config_class FROM ConfigItems,ConfigClasses
                        WHERE id_class=fk_id_class
                            AND id_item=item_id) = "timeperiod"
                    OR (SELECT config_class FROM ConfigItems,ConfigClasses
                        WHERE id_class=fk_id_class
                            AND id_item=item_id) = "contactgroup")
                    ORDER BY item_id
                    ';
            $output = db_handler($query, 'result', "inheritance");
            break;


        case "linked_as_child":
            # get entries linked as child
            $query = 'SELECT DISTINCT attr_value,ItemLinks.fk_id_item AS item_id,
                          (SELECT config_class FROM ConfigItems,ConfigClasses 
                              WHERE id_class=fk_id_class AND id_item=item_id) AS config_class,
                          (SELECT ConfigAttrs.friendly_name
                              FROM ConfigValues,ConfigAttrs
                              WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                                  AND id_attr=fk_id_attr
                                  AND naming_attr="yes") AS friendly_name
                        FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
                        WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                            AND id_attr=ItemLinks.fk_id_attr
                            AND ConfigAttrs.visible="yes"
                            AND fk_id_class=id_class
                            AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                            AND ItemLinks.fk_item_linked2="'.$value.'"';
                if ( !empty($search) AND $search == "link_as_child"){
                    $query .= 'AND ConfigAttrs.link_as_child="yes"';
                }elseif ( !empty($search) AND $search == "link_bidirectional"){
                    $query .= 'AND ConfigAttrs.link_bidirectional="yes"';
                }

            $query .= ' ORDER BY friendly_name DESC,attr_value';
            # look for output selection
            if ( empty($output_type) ) $output_type = 'result';
            $output = db_handler($query, $output_type, "get entries linked as child");
            break;


        case "get_name_of_services":
            # this will take "default_service_name" if not empty, else attr_value
            $query = 'SELECT fk_id_item AS item_ID,
                        attr_value AS check_name,
                        (SELECT attr_value
                        FROM ConfigValues, ConfigAttrs
                        WHERE ConfigValues.fk_id_item = item_ID
                            AND ConfigAttrs.id_attr = ConfigValues.fk_id_attr
                            AND ConfigAttrs.attr_name = "default_service_name"
                        ) AS default_service_name,
                        IFNULL(
                            NULLIF(
                                (SELECT attr_value
                                FROM ConfigValues, ConfigAttrs
                                WHERE ConfigValues.fk_id_item = item_ID
                                    AND ConfigAttrs.id_attr = ConfigValues.fk_id_attr
                                    AND ConfigAttrs.attr_name = "default_service_name"
                                )
                            , "")
                        , attr_value) AS service_name
                    FROM ConfigValues,ConfigAttrs,ConfigClasses 
                    WHERE id_attr=fk_id_attr 
                    AND id_class=fk_id_class 
                    AND config_class="checkcommand"
                    AND naming_attr="yes"'; 
            if ( empty($search) ) $search = 'service_name';
            if ( !empty($value) ){
                // handle search for 1 service
                $query .= ' AND fk_id_item = "'.$value.'"';
                $query .= ' ORDER BY '.$search;
                $output = db_handler($query, 'assoc', "get_name_of_services");
            }else{
                // gives all service names back
                $query .= ' ORDER BY '.$search;
                $output = db_handler($query, 'array', "get_name_of_services");
            }

            break;

        case "hostgroup_services":
                $query = "SELECT fk_item_linked2 AS hostgroup_id,
                    (SELECT attr_value FROM ConfigValues, ConfigAttrs 
                        WHERE ConfigValues.fk_id_attr=ConfigAttrs.id_attr 
                        AND naming_attr='yes' 
                        AND ConfigValues.fk_id_item=hostgroup_id) AS hostgroup_name,
                    fk_id_item AS advanced_service_id,
                    (SELECT attr_value FROM ConfigValues, ConfigAttrs 
                        WHERE ConfigValues.fk_id_attr=ConfigAttrs.id_attr 
                        AND naming_attr='yes' 
                        AND ConfigValues.fk_id_item=advanced_service_id) AS advanced_service_name,
                    (SELECT attr_value FROM ConfigValues, ConfigAttrs
                        WHERE ConfigValues.fk_id_attr=ConfigAttrs.id_attr
                        AND attr_name='service_description'
                        AND ConfigValues.fk_id_item=advanced_service_id) AS advanced_service_description
                  FROM ItemLinks, ConfigItems, ConfigClasses
                  WHERE ItemLinks.fk_id_item=ConfigItems.id_item
                    AND ConfigItems.fk_id_class=ConfigClasses.id_class
                    AND ConfigClasses.config_class='advanced-service'
                    HAVING (SELECT ConfigClasses.config_class FROM ConfigClasses, ConfigItems 
                            WHERE ConfigItems.fk_id_class=ConfigClasses.id_class ";
                    if ( !empty($search) ) $query .= " AND hostgroup_id = '".$search."' ";
                    $query .= "
                            AND ConfigItems.id_item=hostgroup_id)='hostgroup'
                    AND (SELECT fk_item_linked2 FROM ItemLinks, ConfigItems, ConfigClasses, ConfigAttrs 
                        WHERE ItemLinks.fk_id_item=ConfigItems.id_item
                        AND ConfigItems.fk_id_class=ConfigClasses.id_class
                        AND ItemLinks.fk_id_attr=ConfigAttrs.id_attr
                        AND ConfigClasses.config_class='host'
                        AND ConfigAttrs.attr_name='members'
                        AND ItemLinks.fk_item_linked2=hostgroup_id
                        AND ItemLinks.fk_id_item=".$value.") IS NOT NULL 
                        ORDER BY hostgroup_name, advanced_service_name";
            $output = db_handler($query, "array", "Get advanced services inherited over hostgroup");
            break;

        case "template_inheritance":
            # search with host id
            $query = 'SELECT attr_value, fk_item_linked2 AS item_id
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                        AND attr_name = "'.$filter.'"
                        AND ItemLinks.fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                            WHERE id_attr=fk_id_attr
                                AND attr_name="'.$search.'"
                                AND fk_id_item = "'.$value.'")
                                ORDER BY cust_order,ordering';
            $output = db_handler($query, 'array', 'normal: '.$filter.'__'.$search);
            break;

        case "template_inheritance_collector_monitor":
            # search with collector id
            $query = 'SELECT attr_value, fk_item_linked2 AS item_id
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                        AND attr_name = "'.$search.'"
                        AND ItemLinks.fk_id_item="'.$value.'"
                        ORDER BY cust_order,ordering';
            $output = db_handler($query, 'array', 'coll_mon: '.$search);
            break;

        case "template_inheritance_direct":
            # direct host templates
            $query = 'SELECT attr_value,fk_item_linked2 AS item_id
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                        AND attr_name="use"
                        AND ItemLinks.fk_id_item="'.$value.'"
                            ORDER BY cust_order,ordering';
            $output = db_handler($query, 'array', "direct <i>(templates on item)</i>");
            break;


        case "get_linked_data":
            ########
            # Get variables for check which one has changed (for history entries)
            # GET linked data for checking if they has changed (array entries)
            # get linked entries (ItemLinks) for passed id
            $query_old_linked_data = 'SELECT id_attr,attr_value,fk_item_linked2
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                    AND id_attr=ItemLinks.fk_id_attr
                    AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                    AND ItemLinks.fk_id_item='.$value.'
                    ORDER BY
                    ConfigAttrs.friendly_name DESC,
                    ItemLinks.cust_order
                    ';

            $output = db_handler($query_old_linked_data, "result", "get linked entries");
            break;
        case "get_linked_data_childs":
            # get entries linked as child (ItemLinks) for passed id   (without the childs saved in the parents!)
            $query_old_linked_child_data = 'SELECT id_attr,attr_value,ItemLinks.fk_id_item
                FROM ConfigValues,ItemLinks,ConfigAttrs
                WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                AND id_attr=ItemLinks.fk_id_attr
                AND ConfigAttrs.visible="yes"
                AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                AND ItemLinks.fk_item_linked2='.$value.'
                AND ConfigAttrs.attr_name <> "parents"
                ORDER BY ConfigAttrs.friendly_name DESC';

            $output = db_handler($query_old_linked_child_data, "result", "get linked as child entries");
            break;

        case "get_checkcommand_of_service":
            $query = 'SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                           WHERE fk_id_attr=id_attr
                           AND attr_name="check_command"
                           AND fk_id_item='.$value;
            $output = db_handler($query, "getOne", "get checkcommand of service $value");
            break;

        case "get_command_param_count_of_checkcommand":
            $command_query = 'SELECT attr_value FROM ConfigValues,ConfigAttrs
                                       WHERE id_attr=fk_id_attr
                                       AND attr_name="command_param_count"
                                       AND fk_id_item='.$value;
            $output = db_handler($command_query, "getOne", "Get command_param_count");
            break;
            
        case "get_default_checkcommand_params":
            $query = 'SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses
                                  WHERE id_attr=fk_id_attr
                                  AND attr_name="default_params"
                                  AND id_class=fk_id_class
                                  AND config_class="checkcommand"
                                  AND fk_id_item="'.$value.'"';

            $default_params = db_handler($query, "getOne", "Read default checkcommand params");
            if ($default_params == ""){
                $output = "!";
            }else{
                # escape the string for mysql (field contains: " ' \ etc. )
                $output =  escape_string($default_params);
            }
            break;
            

    }

    return $output;


}

# get (old) data of an modified item
function get_linked_data($id){
    $old_linked_data = array();

    $result_old_linked_data = db_templates("get_linked_data", $id);
    while($entry2 = mysql_fetch_assoc($result_old_linked_data)){
        $old_linked_data[$entry2["id_attr"]][] = $entry2["fk_item_linked2"];
    }

    $result_old_linked_data_childs = db_templates("get_linked_data_childs", $id);
    while($entry3 = mysql_fetch_assoc($result_old_linked_data_childs)){
        $old_linked_data[$entry3["id_attr"]][] = $entry3["fk_id_item"];
    }

    NConf_DEBUG::set($old_linked_data, 'DEBUG', 'Array of linked data');
    return $old_linked_data;
}



###
# NEW DB HANDLER
###
function db_handler($query, $output = "result", $debug_title = "query"){

    # Remove beginning spaces
    $query = trim($query);

    if ( (DB_NO_WRITES == 1) AND ( !preg_match("/^SELECT/i", $query) ) ){
        message ('INFO', "DB_NO_WRITES activated, no deletions or modifications will be performed");
    }else{
        $result = mysql_query($query);
        // new DEBUG output
        $debug_query        = NConf_HTML::text_converter("sql_uppercase", $query);
        $debug_query_output = NConf_HTML::swap_content($debug_query, 'Query', FALSE, FALSE);
        $debug_data_result  = '<br>(Result output not yet defined)';

        if ( $result ){
            # Output related stuff

            # not already implemented, or replaced functions:
            //if ($output == "getOne") $output = "1st_field_data";

            switch($output){

                case "affected":
                case "insert":
                case "update":
                case "delete":
                    $affected = mysql_affected_rows();
                    if ( $affected > 0 ){
                        //message('DEBUG', "# affected rows: $affected", "ok");
                        $return = $affected;
                    }else{
                        // needed for inserts ??:
                        //message('DEBUG', "# affected rows: $affected", "nomatch");
                        $return = "yes";
                    }
                    # DEBUG output with new API module:
                    $debug_data_result  = NConf_HTML::swap_content($return, 'Result: affected rows: '.$affected.')', FALSE, TRUE);

                    break;
                case "getOne":
                    $first_row = mysql_fetch_row($result);
                    $return = $first_row[0];
                    # DEBUG output with new API module:
                    $debug_data_result  = NConf_HTML::text('<b>Result: getOne:</b>'.$return);
                    break;
                
                case "result":
                    $return = $result;
                    # DEBUG output with new API module:
                    $debug_data_result  = NConf_HTML::text('<b>Result:</b>'.$return);
                    break;
                
                case "query":
                    $return = $query;
                    # DEBUG output with new API module:
                    $debug_data_result  = NConf_HTML::text('<b>Result: see query above</b>');
                    break;
                
                case "insert_id":
                    $new_id = mysql_insert_id();
                    $return = $new_id;
                    # DEBUG output with new API module:
                    $debug_data_result  = NConf_HTML::text('<b>Result: last query generated ID:</b>'.$return);
                    break;

                case "num_rows":
                    $result = mysql_num_rows($result);
                    $return = $result;
                    # DEBUG output with new API module:
                    $debug_data_result  = NConf_HTML::text('<b>Result: number of rows:</b>'.$return);
                    break;
            
                case "assoc":
                    $result = mysql_fetch_assoc($result);
                    $return = $result;
                    # DEBUG output with new API module:
                    $debug_data_result  = NConf_HTML::swap_content($return, 'Result: assoc array:', FALSE, TRUE);
                    break;
            
                case "array":
                    $i = 0;
                    $rows = array();
                    while ($row  = mysql_fetch_assoc($result) ){
                        $rows[$i] = $row;
                        $i++;
                    }
                    $count = count($rows);
                    $return = $rows;

                    # DEBUG output with new API module:
                    $debug_data_result  = NConf_HTML::swap_content($return, 'Result array (rows: '.$count.')', FALSE, TRUE);
                    break;

                case "array_direct":
                    $i = 0;
                    $rows = array();
                    while ($row  = mysql_fetch_row($result) ){
                        $rows[$i] = $row[0];
                        $i++;
                    }
                    $count = count($rows);
                    $return = $rows;
                    # DEBUG output with new API module:
                    $debug_data_result  = NConf_HTML::swap_content($return, 'Result array_direct (rows: '.$count.')', FALSE, TRUE);
                    break;

                case "array_2fieldsTOassoc":
                    $rows = array();
                    while ($row  = mysql_fetch_row($result) ){
                        $rows[$row[0]] = $row[1];
                    }
                    $count = count($rows);
                    $return = $rows;
                    # DEBUG output with new API module:
                    $debug_data_result  = NConf_HTML::swap_content($return, 'Result array_2fieldsTOassoc (rows: '.$count.')', FALSE, TRUE);
                    break;
                # Failed on output case
                default:
                    message('ERROR', "db_handler failed on output case");
                    return FALSE;
                
            }

            // Debug and result return
            $debug_entry = NConf_HTML::swap_content($debug_query_output.$debug_data_result, "<b>SQL</b> ".$debug_title, FALSE, FALSE, 'debbug_query');
            message('DEBUG', $debug_entry);
            return $return;


        }else{
            // makes an open debug entry with mysql_error info
            $debug_entry = NConf_HTML::swap_content($debug_query_output.'<br><b>mysql error:</b>'.mysql_error(), '<b class="attention" >SQL</b> '.$debug_title, TRUE, FALSE, 'debbug_query color_warning');
            NConf_DEBUG::set($debug_entry, 'DEBUG');
        }

    }
}


###
# complex functions
###

function check_link_as_child_or_bidirectional($id_attr, $class_id){
    # check link_as_child & link_bidirectional
    $lac_query = 'SELECT link_as_child, link_bidirectional, fk_id_class
                    FROM ConfigAttrs
                    WHERE id_attr = "'.$id_attr.'"';
    $lac_data = db_handler($lac_query, "assoc", "check link_as_child option");
    NConf_DEBUG::set( $lac_data, "DEBUG", 'get link_as_child / link_bidirectional of attr ('.$id_attr.')' );
    # change data
/*
    if (
        ( isset($lac_data["link_as_child"])       AND $lac_data["link_as_child"] == "yes" )
        OR (
            ( isset($lac_data["link_bidirectional"])  AND $lac_data["link_bidirectional"] == "yes" ) 
            AND (isset($lac_data["fk_id_class"]) AND $lac_data["fk_id_class"] != $class_id )
           )
    ){
*/
    if ( 
        (
            ( $lac_data["fk_id_class"] != $class_id )
            AND ( $lac_data["link_bidirectional"] == "yes" ) 
            AND ( $lac_data["link_as_child"]      == "no" ) 
        )
        OR (
            ( $lac_data["fk_id_class"] == $class_id )
//            AND ( $lac_data["link_bidirectional"] == "no" ) 
            AND ( $lac_data["link_as_child"]      == "yes" ) 
        )
    ){
        NConf_DEBUG::set('link as child / link bidirectional = TRUE');
        return TRUE;
    }else{
        NConf_DEBUG::set('link as child / link bidirectional = FALSE');
        return FALSE;
    }

}


### add_attrbiute
# 
# $id:          id of item
#
# $attr_id:     id of adding attribute
#
# $attr_value:
#   string:     (text,select) text to save
#   array:      (assign_...)  array of linked attribute ids ( array[#,#,#,...] )
#
function add_attribute($id, $id_attr, $attr_value){
    # temporary map vars:
    $attr = array();
    $attr["key"] = $id_attr;
    $attr["value"] = $attr_value;
    

    # get class_id of item    --> for function
    $class_id = db_templates("get_classid_of_item", $id);

    # only handle integer (attribute ids) on "key" element
    if ( is_int($attr["key"]) ){
        # different logic for text / linked items
        if ( !is_array($attr["value"]) ){
            # Add text or select attribute

            # Lookup datatype
            # Password field is a encrypted, do not save
            $datatype = db_templates("attr_datatype", $attr["key"]);
            if ($datatype == "password"){
                $insert_attr_value = encrypt_password($attr["value"]);
            }else{
                # normal text/select
                $insert_attr_value = escape_string($attr["value"]);
            }

            # insert query
            $query = 'INSERT INTO ConfigValues
                (fk_id_item, attr_value, fk_id_attr)
                VALUES
                ("'.$id.'", "'.$insert_attr_value.'", "'.$attr["key"].'")
                ';

            if (DB_NO_WRITES != 1) {
                $result_insert = db_handler($query, "insert", "Insert");
                if ( $result_insert ){
                    NConf_DEBUG::set("Added attr ".$insert_attr_value, 'DEBUG', "Add attribute");
                    # add value ADDED to history
                    history_add("added", $attr["key"], $insert_attr_value, $id, "get_attr_name");
                }else{
                    message ('ERROR', 'Error when adding '.$attr["value"], "failed");
                }
            }

        }elseif( is_array($attr["value"]) ){
            # add assign attrbibutes

            # counter for assign_cust_order
            $cust_order = 0;
            $attr_datatype = db_templates("attr_datatype", $attr["key"]);

            # save assign_one/assign_many/assign_cust_order in ItemLinks
            while ( $many_attr = each($attr["value"]) ){
                # if value is empty go to next one
                if (!$many_attr["value"]){
                    continue;
                }else{

                    # create insert query
                    $check = check_link_as_child_or_bidirectional($attr["key"], $class_id);
                    if ( $check === TRUE ){
                        $query = 'INSERT INTO ItemLinks
                            (fk_id_item, fk_item_linked2, fk_id_attr, cust_order)
                            VALUES
                            ('.$many_attr["value"].', '.$id.', '.$attr["key"].', '.$cust_order.')
                            ';
                    }else{
                        $query = 'INSERT INTO ItemLinks
                            (fk_id_item, fk_item_linked2, fk_id_attr, cust_order)
                            VALUES
                            ('.$id.', '.$many_attr["value"].', '.$attr["key"].', '.$cust_order.')
                            ';
                    }    

                    if (DB_NO_WRITES != 1) {
                        $result_insert = db_handler($query, "insert", "Insert");
                        if ( $result_insert ){
                            history_add("assigned", $attr["key"], $many_attr["value"], $id, "resolve_assignment");
                            message ('DEBUG', '', "ok");
                            //message ('DEBUG', 'Successfully linked "'.$many_attr["value"].'" with '.$attr["key"]);
                        }else{
                            message ('ERROR', 'Error when linking '.$many_attr["value"].' with '.$attr["key"].':'.$query);
                        }
                    }

                    # increase assign_cust_order if needed
                    if ($attr_datatype == "assign_cust_order") $cust_order++;

                }
            }
        }
    }

} // end of function add_attribute




### read_attributes
# 
# $config_class:    name of class
#
# $visible:         ["no" / "yes"]
# -> selects all, or only the attributes which are visible or not
#
function read_attributes($config_class, $visible = ''){

    $attributes = array();

    // get attributes from class
    $query = 'SELECT id_attr,predef_value,datatype,fk_show_class_items 
                FROM ConfigAttrs,ConfigClasses 
                WHERE id_class=fk_id_class 
                AND config_class="'.$config_class.'"';

    // debug message
    $message = 'Get attributes of class "'.$config_class.'"';

    // filter visible (yes/no)
    if ( !empty($visible) ){
        $query   .= ' AND visible="'.$visible.'" ';
        $message .= ' (visible="'.$visible.'") ';
    }
    $result = db_handler($query, "result", $message);
    
    while($entry = mysql_fetch_assoc($result)){

        if( ($entry["datatype"] == "text") OR ($entry["datatype"] == "select") ){
            // set value
            $attributes[$entry["id_attr"]] = $entry["predef_value"];
            NConf_DEBUG::set('attr id "'.$entry["id_attr"].'" with predefined value "'.$entry["predef_value"].'"', 'DEBUG', "Read attributes");

        }elseif(
               ($entry["datatype"] == "assign_one")
            OR ($entry["datatype"] == "assign_many")
            OR ($entry["datatype"] == "assign_cust_order")
        ){

            if ( $entry["datatype"] != "assign_one" ){
                // split predefined values
                $predef_values = preg_split("/".SELECT_VALUE_SEPARATOR."/", $entry["predef_value"]);
            }else{
                // set predefined value as array, to use same loop as splited values
                $predef_values = array($entry["predef_value"]);
            }
            
            foreach ($predef_values AS $predef_value){
                if ( empty($predef_value) ){
                    // empty values must not be looked up
                    $entry2 = '';
                }else{
                    // lookup for id
                    $query2 = 'SELECT fk_id_item 
                                FROM ConfigValues,ConfigAttrs 
                                WHERE id_attr=fk_id_attr 
                                AND naming_attr="yes" 
                                AND fk_id_class="'.$entry["fk_show_class_items"].'"
                                AND attr_value="'.$predef_value.'";
                    ';
                    $entry2 = db_handler($query2, "getOne", "Load linked item: ( not visible assign_MANY/CUST_ORDER )");
                }
                
                // set values to array ([] = important)
                $attributes[$entry["id_attr"]][] = $entry2;
                NConf_DEBUG::set( 'attr id "'.$entry["id_attr"].'" with predefined value "'.$entry2.'"'
                                    ,'DEBUG'
                                    ,"Read attrbiutes" );
                
            }


        }
        
    } // end of while

    // return result array
    NConf_DEBUG::set($attributes, 'DEBUG', $message);
    return $attributes;

}



###
# checks
###



# checks if a constant is set or a variable matches a specific type (array etc)
# the type can be "constant" or any php function, which checks the var type (is_array, is_numeric, etc...)
# $allow_empty_value will accept empty vars, if you do not want to allow empty vars, call the function with FALSE
function check_var($type, $vars, $allow_empty_value = TRUE){
    $failed = FALSE;

    if ( !is_array($vars) ){
        $vars = array($vars);
    }
    foreach ($vars as $var){
    global $$var;
        if ($type == "constant"){
            if ( !defined($var) ){
                message('CRITICAL', 'The "'.$var.'" constant is not defined. <br>Check your configuration files.<br>');
                $failed = TRUE;
            }else{
                if (!$allow_empty_value){
                    # check if constant is empty
                    if ( constant($var) === ''){
                         message('CRITICAL', 'The "'.$var.'" constant is empty. <br>Check your configuration files.<br>');
                        $failed = TRUE;
                    }
                }
            }
        }else{
            if ( function_exists($type) ){
                # calls the function for checking the var, like is_array, is_string, is_int etc.
                if ( !call_user_func($type, $$var) ){
                    message('CRITICAL', '"'.$type.'" check failed on variable "$'.$var.'". <br>
                            Check your configuration files and make sure it is defined.<br>');
                    $failed = TRUE;
                }else{
                    # check if var is empty
                    if (!$allow_empty_value){
                        if ( empty($$var) ){
                            message('CRITICAL', 'The "$'.$var.'" variable is empty. <br>Check your configuration files.<br>');
                            $failed = TRUE;
                        }
                    }
                }
            }
        }
        
    }

    # return
    if ($failed == TRUE){
        return FALSE;
    }else{
        return TRUE;
    }

}

function check_file($check, $files, $outcome = TRUE, $failed_message = ''){
    $failed = FALSE;

    if ( !is_array($files) ){
        $files = array($files);
    }
    foreach ($files as $file){
        if ( function_exists($check) ){
            # calls the function for checking the var, like is_array, is_string, is_int etc.
            if ( call_user_func($check, $file) != $outcome ){
                # if no message is set
                if ( empty($failed_message) ) $failed_message = '"'.$check.'" function failed for : ';
                NCONF_DEBUG::set($file, 'CRITICAL', $failed_message);
                $failed = TRUE;
            }
        }
        
    }

    # return
    if ($failed == TRUE){
        return FALSE;
    }else{
        return TRUE;
    }

}

##########################################################################################
##################   Special Tools and features   ########################################
##########################################################################################

//js_Autocomplete_run('serverlist', 'cmdbserverlist'){
function js_Autocomplete_run($id, $js_array_name){
    //Only run if feature is activated
    if ( defined('AUTO_COMPLETE') AND AUTO_COMPLETE == 1){
        $content = 'AutoComplete_Create(\''.$id.'\', '.$js_array_name.');';

        $js_code = js_prepare($content);
        echo $js_code;
    }
}


// js_Autocomplete_prepare('cmdbserverlist', $_SESSION["cmdb_serverlist"])
function js_Autocomplete_prepare($js_array_name, $php_array){
    //Only run if feature is activated
    if ( defined('AUTO_COMPLETE') AND AUTO_COMPLETE == 1){

        $comma_i    = 0;
        $temp       = "";
        foreach ($php_array as $key => $wert){
            // Dont put a comma in the FIRST run...
            if ($comma_i > 0){
                $temp .= ",";
            }
            $temp .= "'$wert'";
            $comma_i++;
        }

        if ($temp){
            $content  = "$js_array_name = [";
            $content .= $temp;
            $content .= "].sort();";

            $js_code = js_prepare($content);
            
            echo $js_code;
            message('DEBUG', "js_Autocomplete_prepare", "ok");
            return 1;
        }else{
            message('DEBUG', "js_Autocomplete_prepare", "failed");
            return 0;
        }
    }
}


function js_prepare($content){
    // create js code
    $beginn =  "<script type=\"text/javascript\">\n";
    $end    =  "</script>\n";

    $js_code =  $beginn.$content.$end;
    return $js_code;

}

# compare_hostname($hostname, $_SESSION["cmdb_serverlist"]);
function compare_hostname($hostname, $array){
    if ( (defined('CMDB_SERVERLIST_COMPARE') AND CMDB_SERVERLIST_COMPARE == 1) AND (is_array($array)) ){
        if (COMPARE_IGNORE){
            // Change the hostname befor compare with cmdblist
            $hostname = preg_replace('/\.phs$/', '', $hostname);
            $hostname = preg_replace('/\.2nd$/', '', $hostname);

            // a second search has to be done without the numbers of sites
            $hostname_2 = preg_replace('/-be8-/', '-be-', $hostname);
            $hostname_2 = preg_replace('/-la1-/', '-la-', $hostname_2);
            $hostname_2 = preg_replace('/-zu1-/', '-zu-', $hostname_2);
        }

        // compare status
        if ( in_array($hostname,$array) OR ( isset($hostname_2) AND in_array($hostname_2,$array) )  ){
            // in array
            $compare_status = 1;
        }else{
            // not in array
            $compare_status = 2;
        }
            
        
    }else{
        // Compare feature not activated
        $compare_status = 0;
    }

    return $compare_status;

}


function show_password($password){

    if ( PASSWD_DISPLAY == 1 ){
        //do nothing
    }else{
        // convert password to *******
        $password = PASSWD_HIDDEN_STRING;
    }

    return $password;

}

function genSalt(){
  // *** generate 2 random characters ***
  srand((double)microtime()*1000000);
  $random=rand();
  $rand64="./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
  $salt=substr($rand64,$random%64,1).substr($rand64,($random/64)%64,1);
  $salt=substr($salt,0,2);
  return($salt);
} 

function encrypt_password($password, $EncryptInfoInOutput = TRUE, $existing_password = FALSE){

    switch (PASSWD_ENC){
    case "clear":
        # do nothing
        $encryption_Info = '';
        break;

    case "crypt":
        // old not correct crypt:
        if ( $existing_password !== FALSE AND !empty($existing_password) ){
            $password        = crypt($password, $existing_password);
            NConf_DEBUG::set("Encrypting password using existing password as salt(".$existing_password."): ".$password, 'DEBUG', "encrypt_password");
        }elseif ( $existing_password === FALSE ){
            $password        = crypt($password, genSalt());
            NConf_DEBUG::set("Encrypting password: ".$password, 'DEBUG', "encrypt_password");
        }else{
            NConf_DEBUG::set("error", 'DEBUG', "encrypt_password");
        }
        $encryption_Info = "{CRYPT}";
        break;

    case "md5":
        $password        = md5($password);
        NConf_DEBUG::set("Encrypting password: ".$password, 'DEBUG', "encrypt_password");
        $encryption_Info = "{MD5}";
        break;

    case "sha":
        $password        = sha1($password);
        NConf_DEBUG::set("Encrypting password: ".$password, 'DEBUG', "encrypt_password");
        $encryption_Info = "{SHA1}";
        break;
        
    // this sha1 "raw" mode is needed for Apache htpasswd files. It is an alternative to "crypt"
    case "sha_raw":
        $password        = base64_encode(sha1($password, TRUE));
        NConf_DEBUG::set("Encrypting password: ".$password, 'DEBUG', "encrypt_password");
        $encryption_Info = "{SHA_RAW}";
        break;

    }

    if ($EncryptInfoInOutput){
        $password = $encryption_Info.$password;
    }

return $password;

}



function create_menu($result){
  if ($result){
    NConf_DEBUG::set( $result
                    ,'DEBUG'
                    ,"Menu creation debug" );
                    
    echo '<ul class="nav nav-list">';
    // Generate Menu
    $group_bevore = "";
    $block_i = 0;
    foreach ($result as $nav_class){
        if ($nav_class["grouping"] != $group_bevore){

            echo '</ul>
                </div>';

            // New Block for Group
            echo '<h2 id="nav-'.$nav_class["config_class"].'" class="ui-nconf-header ui-widget-header ui-corner-top pointer"><span>'.$nav_class["grouping"].'</span></h2>';
            echo '<div class="ui-widget-content box_content">';
            echo '<ul class="nav nav-list">';
        }
        $group_bevore = $nav_class["grouping"];

        // prepare links
        $nav_links = explode(";;", $nav_class["nav_links"]);
        $link_i = 0;
        $link_output = "";
        /* perhaps change this to icons ? */
        
        # class icon lookup
        $nav_icon = '';
        $icon = '';
        if ( defined('TEMPLATE_DIR') ){
            /* Icon only if available ? */
            if (!empty($nav_class["icon"]) ){
                $icon = $nav_class["icon"];
            }elseif (!empty($nav_class["config_class"]) ){
                $icon = $nav_class["config_class"];
            }
            //NConf_DEBUG::set($icon, 'DEBUG', "icon");
        }
        $nav_icon = get_image( array( "type" => "design",
                                                     "name" => $icon,
                                                     "size" => 16,
                                                     "tooltip" => $icon,
                                                     "class" => "lighten nav-icon",
                                                     "alt" => ''
                                                     ) );
       
        foreach ($nav_links as $entry){
            $old_link_style = strpos($entry, "::");
            if ($old_link_style !== FALSE){
                $nav_link_details = explode("::", $entry);
                if ( isset($nav_link_details[1]) ){
                    # set all navigation links in permissions list
                    # current user will have access to all of them
                    global $NConf_PERMISSIONS;
                    $NConf_PERMISSIONS->setURL($nav_link_details[1]);
                    
                    # special handling for the first one
                    if ($link_i == 0){
                      $friendly_name_link = '<div class="float_left"><a href="'.$nav_link_details[1].'">'.$nav_icon.$nav_class["friendly_name"].'</a></div>';
                      
                      // define the navigation identifier for marking nav entry as active
                      $url_query = parse_url($nav_link_details[1], PHP_URL_QUERY);
                      $url_query_explode = explode("=", $url_query);
                      $navigation_identifier = $url_query_explode[1];
                      NConf_DEBUG::set($navigation_identifier, 'DEBUG', "navigation identifier");
                      
                      // if no query identifier found use the script name (like for the history entry)
                      if (empty($navigation_identifier)){
                        $navigation_identifier = $nav_link_details[1];
                      }
                      
                    }else{
                      // Don't print any actions anymore (no add icon in menu)
                      continue;
                    }
                    
                    $link_i++;
                    
                    # change "Add" to icon
                    if ($nav_link_details[0] == "Add"){
                      $nav_link_text = get_image( array( "type" => "design",
                                                         "name" => "add",
                                                         "size" => 16,
                                                         "tooltip" => 'Add '.$nav_class["friendly_name"],
                                                         "class" => "lighten"
                                                         ) );
                    }elseif($nav_link_details[0] == "Show"){
                      # do not print the show link anymore, its not linked on the name itself
                      continue;
                    }else{
                      $nav_link_text = $nav_link_details[0];
                    }
                    $link = '<a href="'.$nav_link_details[1].'" >'.$nav_link_text.'</a>';
                }
                $link_output .= "<li>";
                $link_output .= $link;
                $link_output .= "</li>";
            }else{
                // the new case when there is no :: in 
                $friendly_name_link = '<a href="'.$entry.'" >'.$nav_icon.$nav_class["friendly_name"].'</a>';
                $navigation_identifier = $nav_class["nav_links"];
                NConf_DEBUG::set($navigation_identifier, 'DEBUG', "navigation identifier");
            }
        }
        
        /* verify that we have a check and can access this variable here and other places */
        //global $class;
        //NConf_DEBUG::set($class, 'DEBUG', "GLOBAL CLASS");
        if (!empty($_GET["class"])){
          $class = $_GET["class"];
        }elseif(!empty($_GET["item"])){
          $class = $_GET["item"];
        }elseif(!empty($_SERVER['SCRIPT_NAME'])){
          //$class = $icon;
          $class = basename($_SERVER['SCRIPT_NAME']);
                  //NConf_DEBUG::set($class, 'DEBUG', "nav_icon");
          
        }
        //NConf_DEBUG::set(basename($_SERVER['SCRIPT_NAME']), 'DEBUG', "script name");
        // Allow active menu regarding the class only on several pages:
        switch (basename($_SERVER['SCRIPT_NAME']) ) {
            case 'overview.php':
            case 'detail.php':
            case 'clone_host.php':
            case 'handle_item.php':
            case 'delete_item.php':
            case 'modify_item_service.php':
                # do nothing, this allows these pages to set active the current set class    
                break;
            
            default:
                // default: set class to the script name
                unset($class);
                $class = basename($_SERVER['SCRIPT_NAME']);
                break;
        }
        
        $active = (!empty($class) AND $class == $navigation_identifier) ? "active" : '';
        
        // If not yet active, check for "nav_alias" setting, which allows to have subpages having an active navigation item configured on the menu item.
        if (!$active AND !empty($nav_class["nav_alias"]) ){
            foreach ($nav_class["nav_alias"] AS $nav_alias){
                if ( !empty($nav_alias) AND $nav_alias == $class){
                    $active = "active";
                }
            }
        }
        
        // we still need a possibility to allow scripts to mark their "active" menu item, especially for site like :
        // detail_admin_items.php?type=class&id=1 where also attrs has access to.
         
        echo '<li class="'.$active.'">'.$friendly_name_link.'</li>';
    }
    //END foreach

    // Last Block has to be closed :
    echo '</ul>';

  }

}
/* 
 * get_image() function
 * creates images
 * OPTIONS:
 *   - type =   "design"  : images in the design path
 *              "base"    : nconf base images (nconf/img)
 *   - name =   "NAME"    : the name of the icon (like "add")
 *   - size =   "SIZE"    : select a special size of the image ("16" -> add_16.png)
 *   - format = "FORMAT"  : add the format of the file (default: png for .png files)
 *   - output = "path"    : limits the output to the path 
 *   - class =  "CLASS"   : define classes for the image (space separated)
 *   - alt =    "ALT TEXT"  : define an alt text if it should differ than the name (which is default), set NULL for no alt at all
 *   - tooltip = "TOOLTIP TEXT" : the text for the tooltip (activates the tooltip itself)
 */
function get_image(array $options) {
  //NConf_DEBUG::set($options, 'DEBUG', "image options");
  # type
  if ($options["type"] == "design"){
    $path = 'design_templates/'.TEMPLATE_DIR.'/img/';
  }elseif ($options["type"] == "base"){
    $path = 'img/';
  }
  
  # add the name
  $path .= $options["name"];
  
  # add the size
  if (!empty($options["size"])){
    $path .= '_' . $options["size"];
  }
  
  # add the format of the image (.png)
  if (!empty($options["format"])){
    $path .= '.' . $options["format"];
  }else{
    $path .= '.png';
  }
  
  # if file does not exist, return false
  if (!file_exists($path)){
    return FALSE;
  }
  
  # output
  if ($options["output"] == "path"){
    return $path;
  }else{
    $class = (!empty($options["class"])) ? $options["class"] : '';
    
    # alt text
    if (isset($options["alt"])){
      $alt = $options["alt"];
    }else{
      $alt = $options["name"];
    }
  
    if (!empty($options["tooltip"])){
      $image = '<img src="'.$path.'" class="jQ_tooltip '.$class.'" title="'.$options["tooltip"].'" alt="'.$alt.'">';
    }else{
      $image = '<img src="'.$path.'" class="'.$class.'" alt="'.$alt.'">';
    }
    //NConf_DEBUG::set($image, 'DEBUG', "get image");
    return $image;
  }
  
}

###
# oncall check
###

function oncall_check() {
    # make message vars available
    global $ONCALL_GROUPS;
    global $_POST;
    global $config_class;

    # get id of contact_group attr
    $contact_group_id = db_templates("get_attr_id", $config_class, "contact_groups");

    # also check if a must have contact group is selected (at least one entry : [0])
    if ( !empty($ONCALL_GROUPS[0]) ){
        $oncall_group_ids = array();
        foreach ($ONCALL_GROUPS as $oncall_group){
            $oncall_group_query = 'SELECT fk_id_item FROM ConfigValues, ConfigAttrs, ConfigClasses WHERE ConfigValues.fk_id_attr = ConfigAttrs.id_attr AND ConfigAttrs.fk_id_class = ConfigClasses.id_class AND ConfigClasses.config_class = "contactgroup" AND ConfigAttrs.attr_name = "contactgroup_name" AND ConfigValues.attr_value LIKE "'.$oncall_group.'"';
            # add id to array
            $oncall_id = db_handler($oncall_group_query, "getOne", "Select id of must have contact_group (oncall)");
            if ( !empty($oncall_id) ){
                $oncall_group_ids[] = $oncall_id;
            }else{
                message('INFO', "Could not find ONCALL GROUP: $oncall_group");
            }
        }

        if ( !empty($oncall_group_ids[0]) ){
            # a defined oncall group was found, check it
            $oncall_check = FALSE;
            foreach ($oncall_group_ids as $oncall_group_id){
                if(!empty($_POST[$contact_group_id]) AND in_array($oncall_group_id, $_POST[$contact_group_id]) ){
                    # a must have contact group was selected, mark check als OK / TRUE
                    $oncall_check = TRUE;
                }else{
                    # a must have contact group was NOT selected
                }
            }

        }else{
            # defined oncall groups (in config) not found in database, make no restrictions for group_contacts
            message('ERROR', "ONCALL GROUPs defined (in config) not found in the database");
            $oncall_check = FALSE;
        }


        # give feedback
        if($oncall_check == TRUE){
            # a must have contact group was selected, go ahead
            message('DEBUG', "ONCALL GROUP selected");
            return TRUE;
        }else{
            # a must have contact group was NOT selected, stop and give info
            message('ERROR', "You must assign at least one of the following ONCALL GROUPs:");
            foreach ($ONCALL_GROUPS as $oncall_group_id){
                message('ERROR', $oncall_group_id, "list");
            }
            message('INFO', TXT_GO_BACK_BUTTON, "overwrite");
            return FALSE;
        }


    }else{
        # no must have selected contact group, go ahead
        message('DEBUG', "No mandatory ONCALL GROUPs defined");
        return TRUE;
    }



}


# get directories
function getDirectoryTree( $outerDir ){
    $dir_array = Array();
    if(file_exists($outerDir) ){
        $dirs = array_diff( scandir( $outerDir ), Array( ".", "..", ".svn" ) );
        foreach( $dirs as $d ){
            if( is_dir($outerDir."/".$d) ) $dir_array[$d] = $d;
        }
    }
    return $dir_array;
}

# get files
function getFiles( $outerDir ){
    if ( is_dir($outerDir) ){
        $files = array_diff( scandir( $outerDir ), Array( ".", ".." ) );
        $file_array = Array();
        foreach( $files as $f ){
            if( is_file($outerDir."/".$f) ) $file_array[$f] = $f;
        }
        return $file_array;
    }else{
        return FALSE;
    }
}

# check if folder is empty
function is_empty_folder($folder){
    $c=0;
    if(is_dir($folder) ){
        $files = opendir($folder);

        if($files == false){
            return "error";
        }

        while ($file=readdir($files)){$c++;}
        if ($c>2){
            return false;
        }else{
            return true;
        }
    }else{
        return "error";
    } 
}

##########################################################################################
##################   TREE VIEW features   ################################################
##########################################################################################

###
# FUNCTIONS for TREE VIEW
# html code starts @ Row #330#
###
$all_childs = array();
function get_childs($id, $mode, $levels = 0){
    global $all_childs;
    $all_childs[ $id ] = TRUE;

    $childs = array();
    $services = array();
    $child_id = 0;

    # get entries linked as child
    $query = 'SELECT DISTINCT attr_value,ItemLinks.fk_id_item AS item_id,
                  (SELECT config_class FROM ConfigItems,ConfigClasses
                      WHERE id_class=fk_id_class AND id_item=item_id) AS config_class,
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
                    AND ItemLinks.fk_item_linked2="'.$id.'"
                ORDER BY config_class DESC,attr_value';
    $result = db_handler($query, 'result', "get childs from $id");


    while($entry = mysql_fetch_assoc($result)){
        /*
        #special for services
        if($entry["config_class"] == "service"){
            $host_query = 'SELECT attr_value AS hostname FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                           WHERE fk_item_linked2=ConfigValues.fk_id_item
                                               AND id_attr=ConfigValues.fk_id_attr
                                               AND naming_attr="yes"
                                               AND fk_id_class = id_class
                                               AND config_class="host"
                                               AND ItemLinks.fk_id_item='.$entry["item_id"];

            $hostname = db_handler($host_query, "getOne", "Get linked hostnames");
        }
        */

        # set child
        //var_dump($entry);
        if ($entry["config_class"] == "service"){
            if (!isset($childs["services"]["id"]) ){
                $service_tree = array(
                            "id" => "service_$id",
                            "status" => 'closed',
                            "name" => "Services",
                            "type" => "service");
                $childs["services"] = $service_tree;
            }
            $childs["services"]["childs"][$child_id]["parent"]   = $id;
            $childs["services"]["childs"][$child_id]["id"]       = $entry["item_id"];
            $childs["services"]["childs"][$child_id]["name"]     = $entry["attr_value"];
            $childs["services"]["childs"][$child_id]["type"]     = $entry["config_class"];
            # Nagiosview link
            if ($mode == "nagiosview"){
                $link = generate_nagios_service_link($id, $entry["attr_value"]);
                $childs["services"]["childs"][$child_id]["link"] = $link;
            }

        }elseif ($entry["config_class"] == "advanced-service"){
            if (!isset($childs["advanced-services"]["id"]) ){
                $service_tree = array(
                            "id" => "service_$id",
                            "status" => 'closed',
                            "name" => "Advanced-services",
                            "type" => "service");
                $childs["advanced-services"] = $service_tree;
            }
            $childs["advanced-services"]["childs"][$child_id]["parent"]   = $id;
            $childs["advanced-services"]["childs"][$child_id]["id"]       = $entry["item_id"];
            $childs["advanced-services"]["childs"][$child_id]["name"]     = $entry["attr_value"];
            $childs["advanced-services"]["childs"][$child_id]["type"]     = "service";  // advanced services display as services
            # Nagiosview link
            if ($mode == "nagiosview"){
                $link = generate_nagios_service_link($id, $entry["attr_value"]);
                $childs["advanced-services"]["childs"][$child_id]["link"] = $link;
            }

        }elseif ($entry["config_class"] == "host"){
            $childs[$child_id]["parent"]   = $id;
            $childs[$child_id]["id"]       = $entry["item_id"];
            $childs[$child_id]["name"]     = $entry["attr_value"];
            $childs[$child_id]["type"]     = $entry["config_class"];
            $childs[$child_id]["os_icon"]  = $entry["os_icon"];
            # Nagiosview link
            if ($mode == "nagiosview"){
                $link = generate_nagios_pnp_link($id);
//                $link = generate_nagios_pnp_link($id, $entry["attr_value"]);
                $childs[$child_id]["link"] = $link;
            }


            # check if that child is called a second time (prevent a endless looilds)
            if ( !isset($all_childs[ $entry["item_id"] ]) ){
                # save the child and parent combinatino, so that a loop can be prevented
                //$all_childs[ $childs[$child_id]["parent"].":".$childs[$child_id]["id"] ] = TRUE;
                //$all_childs[ $entry["item_id"] ] = TRUE;

                # get childs
                $childs[$child_id]["childs"]   = get_childs($entry["item_id"], $mode, ($levels+1) );

                # get informations about host
                # prepend the Host information to the beginning of the array
                array_unshift($childs[$child_id]["childs"], get_informations($entry["item_id"]) );

                # remove from loop detection
                unset($all_childs[ $entry["item_id"] ]);
            }else{
                # re-iteration
                $childs[$child_id]["childs"] = error_loop($entry["item_id"]);
                $childs[$child_id]["status"] = "open";

            }

        }
        # increase child id
        $child_id++;

    }

    # return child information
    NConf_DEBUG::set($childs, 'DEBUG', "child tree");
    return $childs;

}

function generate_nagios_service_link ($host_id, $servicename){
    # get hostname
    $hostname = db_templates("get_value", $host_id, "host_name");
    if (!empty($_GET["service_link"]) ){
        $link = $_GET["service_link"].'?type=2&host='.$hostname.'&service='.$servicename;
        return $link;
    }else{
        return FALSE;
    }
}

function generate_nagios_pnp_link ($host_id){
    # get hostname
    $hostname = db_templates("get_value", $host_id, "host_name");
    if (!empty($_GET["pnp_link"]) ){
        $link = $_GET["pnp_link"].'?host='.$hostname;
        return $link;
    }else{
        return FALSE;
    }
}

function get_informations ($id){
    # get ip address
    $ipaddress = db_templates("get_value", $id, "address");
    $informations[] = array(
                "name" => $ipaddress,
                "title" => "IP: ",
                "type" => "ipaddress") ;

    # hostgroups
    $hostgroups = db_templates("get_linked_item", $id, "members");
    foreach ($hostgroups as $hostgroup){
        $hg_childs = array();
        $hostgroup_name = $hostgroup["attr_value"];
        $hostgroup_id = $hostgroup["fk_id_item"];
        $hg_informations = array(
                "id" => $hostgroup_id,
                "status" => 'closed',
                "name" => $hostgroup_name,
                "title" => "Hostgroup: ",
                "type" => "hostgroup");

        # look for advanced services on hostgroup
        $hg_services = db_templates("get_services_from_host_id", $hostgroup_id, "advanced-service");
        foreach ($hg_services AS $ad_service_id => $ad_service_name){
            if (!isset($hg_childs["advanced-service"]["id"]) ){
                $hg_tree = array(
                            "id" => "service_$hostgroup_id",
                            "status" => 'closed',
                            "name" => "Advanced-services",
                            "type" => "service");
                $hg_childs["advanced-service"] = $hg_tree;
            }
            $hg_childs["advanced-service"]["childs"][$ad_service_id]["parent"]   = $hostgroup_id;
            $hg_childs["advanced-service"]["childs"][$ad_service_id]["id"]       = $ad_service_id;
            $hg_childs["advanced-service"]["childs"][$ad_service_id]["name"]     = $ad_service_name;
            $hg_childs["advanced-service"]["childs"][$ad_service_id]["type"]     = "service";  // advanced services display as services

            NConf_DEBUG::set($hg_childs["advanced-service"]["childs"], '', 'test');
        }
        
        if ( !empty($hg_childs) ){
            NConf_DEBUG::set($hg_childs, 'DEBUG', "Hostgroup childs");
            $hg_informations["childs"] = $hg_childs;
        }
        $informations[] = $hg_informations;

    }






    # PNP link
    if (!empty($_GET["xmode"]) ){
        $link = generate_nagios_pnp_link($id);
        if ($link){
            $informations[] = array(
                "name" => 'PNP link',
                "link" => $link,
                "type" => "link") ;
        }
    }

    # add it to info array
    $info = array(
        "id" => "info_$id",
        "status" => 'open',
        "name" => "Host info",
        "type" => "info",
        "childs" => $informations);


    return $info;

}


function error_loop ($id){
    # show_reiteration (endless loop)
    $loop[] = array(
        "id" => "loop_$id",
        "name" => TXT_DEPVIEW_ERROR_LOOP,
        "type" => "warn");

    return $loop;

}




###
# This function generates an array of parents, saved for each level of tree 0,1,2,3...
###
$all_parents = array();
function get_parents($id, &$flat = array(), $levels = 0){
    # $all_parents holds the taken parents, so the function will stop getting more parents if one already was fetched
    # (otherwise it will be an endless loop)
    global $all_parents;
    $all_parents[$id] = TRUE;


    $parent_id = 0;

    # get parents
    $sql = 'SELECT ConfigAttrs.friendly_name,attr_value,fk_item_linked2 AS item_id,
                (SELECT config_class FROM ConfigItems,ConfigClasses WHERE id_class=fk_id_class AND id_item=item_id) AS config_class,
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
            WHERE fk_item_linked2=ConfigValues.fk_id_item
                AND id_attr=ItemLinks.fk_id_attr
                AND fk_id_class=id_class
                AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr) ="yes"
                AND ItemLinks.fk_id_item='.$id.'
                AND ConfigAttrs.attr_name = "parents"
            ORDER BY ConfigAttrs.friendly_name DESC,attr_value';

    $result = db_handler($sql, "result", "Recursive get parents");
    while($entry = mysql_fetch_assoc($result)){

        #special for services
        /*
        if($entry["config_class"] == "service"){
            $host_query = 'SELECT attr_value AS hostname FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                           WHERE fk_item_linked2=ConfigValues.fk_id_item
                                               AND id_attr=ConfigValues.fk_id_attr
                                               AND naming_attr="yes"
                                               AND fk_id_class = id_class
                                               AND config_class="host"
                                               AND ItemLinks.fk_id_item='.$entry["item_id"];

            $hostname = db_handler($host_query, "getOne", "Get linked hostnames");
        }*/

        # set parent
        $flat[$levels][$parent_id]["name"]        = $entry["attr_value"];
        $flat[$levels][$parent_id]["id"]          = $entry["item_id"];
        $flat[$levels][$parent_id]["child"]       = $id;
        $flat[$levels][$parent_id]["os_icon"]     = $entry["os_icon"];
        $flat[$levels][$parent_id]["type"]        = $entry["config_class"];

        # check if that parent is called a second time (prevent a endless loop)
        if ( !isset($all_parents[$entry["item_id"]]) ){
            # go get all all parents recursive
            get_parents($entry["item_id"], $flat, ($levels+1) );
        }else{
            # parent loop
            $parent_loop = TRUE;
            $flat[$levels][$parent_id]["status"] = "loop_error";
        }

        # increase parent
        $parent_id++;

    }

    # return parent information
    return ($flat);

}






###
# prepare_dependency converts the levels from the flat array into parent groups
###
function prepare_dependency($source, &$root_item, $level = 0){
    # root_item has the selected items information and its childs

    # this runs level array
    $p_array = $source[$level];

    # next level
    $next_level = $level + 1;

    if (!empty($source[$next_level]) AND is_array($source[$next_level]) ){
        # if there is a next level, so go to it
        $result = prepare_dependency($source, $root_item, $next_level);
        # make a subgroup and pack the returning infos into it
        $p_array["group"]["id"]      = $level;
        $p_array["group"]["name"]    = "Parent level";
        $p_array["group"]["status"]  = 'open';
        $p_array["group"]["type"]    = 'parent';
        $p_array["group"]["childs"]  = $result;
    }elseif( !empty($p_array[0]["child"]) AND !empty($root_item[$p_array[0]["child"]]) ){
        # reached last level, the child id exists also in root_item (with its informations)

        # When there are more than one parents, put the child in a subgroup
        if ( count($p_array) > 1 ){
            $p_array["group"]["id"]      = $level;
            $p_array["group"]["name"]    = "Parent level";
            $p_array["group"]["status"]  = 'open';
            $p_array["group"]["type"]    = 'parent';
            $p_array["group"]["childs"][]= $root_item[$p_array[0]["child"]];
        }elseif (count($p_array) == 1 ){
            # there is only one parent, so give the child directly to the parent
            $p_array[0]["childs"][] = $root_item[$p_array[0]["child"]];
        }
    }
    return $p_array;
}



# unique counter is needed, because in parents tree could be more of the same object-id
function displayTree_list($arr, $status = "open", $level = 0, $space = array(), &$unique_counter = 0 ){
    $unique_counter++;
    # $array_size and $array_counter needed for locate the last item
    $array_size = count($arr);
    $array_counter = 0;
    $last_item = FALSE;


    echo '<div style="padding:0px; margin:0px;">';

    foreach($arr as $k=>$v){
        $array_counter++;
        if ($array_size == $array_counter){
            $last_item = TRUE;
            array_push($space, $level);
        }

        # tree open / close? (else it would get status from function call)
        if(!empty($v["status"])) $status = $v["status"];

        echo '<div style="margin-left: 0px; height:18px;">';

        # make spaces or lines, $spaces gives the col numbers which are space
        $tree = '';
        for($i = 0; $i < $level; $i++){
            if (in_array($i, $space)) {
                $tree .= '<img src="'.TREE_SPACE.'">';
            }else{
                $tree .= '<img src="'.TREE_LINE.'">';
            }
        }


        # go through childs
        if(!empty($v["childs"])){
            # +/-
            if ($last_item){
                $tree_plus  = TREE_PLUS_LAST;
                $tree_minus = TREE_MINUS_LAST;
            }else{
                $tree_plus  = TREE_PLUS;
                $tree_minus = TREE_MINUS;
            }

            if ($status == "open"){
                $tree_switch =  '<a href="javascript:swap_tree(\''.$v["id"].$unique_counter.'\', \''.$tree_plus.'\', \''.$tree_minus.'\')">';
                $tree_switch .= '<img src="'.$tree_minus.'" id="swap_icon_'.$v["id"].$unique_counter.'" >';
                $tree_switch .= '</a>';
            }else{
                $tree_switch =  '<a href="javascript:swap_tree(\''.$v["id"].$unique_counter.'\', \''.$tree_plus.'\', \''.$tree_minus.'\')">';
                $tree_switch .= '<img src="'.$tree_plus.'" id="swap_icon_'.$v["id"].$unique_counter.'" >';
                $tree_switch .= '</a>';
            }
        }else{
            if ($last_item){
                $tree_item = TREE_ITEM_LAST;
            }else{
                $tree_item = TREE_ITEM;
            }
            $tree_switch = '<img src="'.$tree_item.'">';
        }

        # standard size of logos in tree
        $icon_size = 'width="18" height="18"';

        # check icon
        if(!empty($v["type"]) ){
            # icon for different types
            if ($v["type"] == "service"){
                $icon_path = TREE_SERVICE;
                # service icons are only 16
            }elseif ($v["type"] == "parent"){
                $icon_path = TREE_PARENT;
            }elseif ($v["type"] == "info"){
                $icon_path = TREE_INFO;
            }elseif ($v["type"] == "warn"){
                $icon_path = TREE_WARNING;
            }elseif ($v["type"] == "host" AND !empty($v["os_icon"]) ){
                $icon_path = OS_LOGO_PATH.'/'.$v["os_icon"];
            }
        }else{
            # no type set, but perhaps still a icon path
            if (!empty($v["os_icon"])){
                $icon_path = $v["os_icon"];
            }else{

            }
        }

        # this variable holds the text (hostname, informations etc.)
        $tree_content_name = '';

        # check if icon exists
        if ( !empty($icon_path) AND file_exists($icon_path) ){
            $tree_item_logo = '<img src="'.$icon_path.'" alt="'.$icon_path.'"'.$icon_size.'>';
        }else{
            $tree_item_logo = '';

            if ( !empty($v["title"]) ){
                $tree_content_name .= $v["title"];
            }
        }

        # Text content of item
        $tree_content = '<span style="margin-left: 5px; height:18px; position: absolute">';

            # mark selected host
            if (!empty($v["selected"]) AND $v["selected"] == TRUE){
                $tree_content_name .= '<b>'.$v["name"].'</b>';
            }else{
                $tree_content_name .= $v["name"];
            }

            if (!empty($v["type"]) AND $v["type"] == "host"){
                # link for hosts
                if (!empty($_GET["xmode"]) ){
                    $tree_content .= '<a href="dependency.php?xmode='.$_GET["xmode"].'&id='.$v["id"];
                    if ( !empty($_GET["pnp_link"]) )    $tree_content .= '&pnp_link='.$_GET["pnp_link"];
                    if ( !empty($_GET["service_link"]) ) $tree_content .= '&service_link='.$_GET["service_link"];
                    $tree_content .= '" style="height:18px;">';
                }else{
                    $tree_content .= '<a href="dependency.php?id='.$v["id"].'" style="height:18px;">';
                }
                $tree_content .= $tree_content_name;
                $tree_content .= '</a>';
            }elseif ( !empty($v["link"]) ){
                # link for services
                if (!empty($_GET["xmode"]) AND !empty($v["link"]) ){
                    $tree_content .= '<a href="'.$v["link"].'" style="height:18px;">';
                    $tree_content .= $tree_content_name;
                    $tree_content .= '</a>';
                }else{
                    $tree_content .= $tree_content_name;
                }
            }else{
                $tree_content .= $tree_content_name;
            }

        $tree_content .= '</span>';


        # print content in choosen order
        echo $tree.$tree_switch.$tree_item_logo.$tree_content;


        echo '</div>';

        if(!empty($v["childs"])){
            echo '<div ';
            if ($status == "open"){
                echo 'style=""';
            }else{
                echo 'style="display:none"';
            }
            echo ' id="'.$v["id"].$unique_counter.'">';
                displayTree_list($v["childs"], $status, ($level+1), $space, $unique_counter);
            echo '</div>';
        }

    }
    echo '</div>';

}

function inheritance_HostToService($host_id, $mode = ''){
    //NConf_DEBUG::open_group('inheritance for host_id: "'.$host_id.'"', 1);
    # Handling inheritance to services

    # check for host data
    if ( $mode == 'apply_inheritance' ){
        if ( empty($_POST["apply_inheritance"][$host_id]) ){
            // continue if service is empty
             NConf_DEBUG::set("not applying for host: ".$host_id, 'DEBUG', 'Inheritance filter');
            return;
        }
    }

    # These services will be modified
    $services = db_templates("get_services_from_host_id", $host_id);

    # array of inherited attributes
    $change_attrs = array("check_period" => "check period", "notification_period" => "notification period", "contact_groups" => "contact groups");

    $class_id = db_templates("get_id_of_class", "service");

    # array for preview functionality
    $preview_array = array();
    # make a diff with each service to detect which items must be linked and which must be removed
    foreach( $services as $service_id => $service_name ){
        NConf_DEBUG::open_group('inheritance for service: "'.$service_name.'"', 1);
        # initial value for history entry "edited"
        $edited = FALSE;
        $preview_array[$service_name] = array();
        if ( $mode == 'apply_inheritance' ){
            if ( empty($_POST["apply_inheritance"][$host_id][$service_id]) ){
                // continue if service is empty
                 NConf_DEBUG::set("not applying for service: ".$service_name, 'DEBUG', 'Inheritance filter');
                continue;
            }
        }
        foreach( $change_attrs as $change_attr => $change_attr_friendly_name ){
            NConf_DEBUG::open_group("attribute: ".$change_attr_friendly_name, 2);
            if ( $mode == 'apply_inheritance' AND empty($_POST["apply_inheritance"][$host_id][$service_id][$change_attr]) ){
                // continue if service is empty
                 NConf_DEBUG::set("not applying for attribute: ".$change_attr_friendly_name, 'DEBUG', 'Inheritance filter');
                continue;
            }

            NConf_DEBUG::open_group("lookup values", 3);

            $attr_id = db_templates("get_attr_id", "service", $change_attr);

            # get current host data
            $new_items = db_templates("get_linked_item", $host_id, $change_attr, '', 'array_2fieldsTOassoc');

            # get current service data
            $current_items = db_templates("get_linked_item", $service_id, $change_attr, '', 'array_2fieldsTOassoc');

            # diff to get items to add
            $diff_array = array_diff($new_items, $current_items);
            
            # diff to get items to remove
            $diff_array2 = array_diff($current_items, $new_items);

            /* debugging:
            echo "<pre>";
            var_dump($diff_array);
            var_dump($diff_array2);
            echo "</pre>";
            */            
            
            if ( $mode == "preview" ){
                $preview_array[$service_id]["service_name"] = $service_name;
                $preview_array[$service_id]["attrs"][$attr_id] = array( "attr_name" => $change_attr,
                                                                        "attr_friendly_name" => $change_attr_friendly_name,
                                                                        "current"    => $current_items,
                                                                        "new"       => $new_items,
                                                                        "differs"   => ( !empty($diff_array) OR !empty($diff_array2) )
                                                                        );
//                $preview_array[$service_id]["attrs"][$attr_id]["differs"] = (!empty($diff_array) OR !empty($diff_array2) );
            }else{
                # make changes in the DB

                // until now, there are no such special attributes:
                //$lac_OR_bidirectional = check_link_as_child_or_bidirectional($change_attr, $class_id);
                // perhaps later there must also be this logic.

                # remove items
                if ( !empty($diff_array2) ){
                    NConf_DEBUG::open_group("remove items");
                    foreach ( $diff_array2 AS $attr_removed_name => $attr_removed_id ){
                        $query = 'DELETE FROM ItemLinks
                                    WHERE fk_id_item='.$service_id.'
                                        AND fk_id_attr = "'.$attr_id.'"
                                        AND fk_item_linked2 = "'.$attr_removed_id.'"
                                 ';
                        db_handler($query, "delete", 'delete linked item "'.$attr_removed_name.'"');
                        history_add("unassigned", $change_attr_friendly_name, $attr_removed_name, $service_id);
                        $edited = TRUE;
                    }
                }
                # add items
                if ( !empty($diff_array) ){
                    NConf_DEBUG::open_group("add items");

                    foreach ( $diff_array AS $attr_add_name => $attr_add_id ){
                        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr)
                                    VALUES ('.$service_id.','.$attr_add_id.', '.$attr_id.')';
                        db_handler($query, "insert", 'insert linked item "'.$attr_add_name.'"');
                        history_add("assigned", $change_attr_friendly_name, $attr_add_name, $service_id);
                        $edited = TRUE;
                    }
                }
            }

        }

        NConf_DEBUG::close_group(2);
        //NConf_DEBUG::open_group('history "edited" entry', 1);

        # history entry "edited"
        if ( $mode == "preview" ){
            # clean service if nothing will change
            if ( empty($preview_array[$service_name]) ){
                unset($preview_array[$service_name]);
            }
        }elseif ($edited){
            history_add("edited", "service", $service_name, $service_id);
        }

    }

    if ($mode == "preview"){
        # print preview

        # create a table with checkboxes for applying inheritance
        $preview = NConf_HTML::table_begin('class="ui-nconf-table ui-widget ui-widget-content ui-nconf-max-width"', array('', 50, 120, 100, 100) );
        $preview .= '<thead class="ui-widget-header">
                      <tr>
                        <th>service</th>
                        <th name="checkbox_toggle_all" class="center pointer">update</th>
                        <th>attribute</th>
                        <th>service value</th>
                        <th>host value</th>
                      </tr>
                     </thead>';
        $bg_class = "even";
        foreach ($preview_array AS $service_id => $service){
            $i = 0;
            $service_name = $service["service_name"];

            # handle background for each service
            if ( $bg_class == "odd"){
                $bg_class = "even";
            }else{
                $bg_class = "odd";
            }
            foreach ($service["attrs"] AS $attribute_id => $values){
                $i++;
                $preview .= '<tr class="'.$bg_class.'">';
                if ($i == 1){
                    $preview .= '<td rowspan="'.count($service["attrs"]).'" class="align_top">'.NConf_HTML::title($service_name).'</td>';
                }
                # check box for applying
                $preview .= '<td class="center">';
                if ( $values["differs"] ){
                    $preview .= '<input type="checkbox" class="pointer" name="apply_inheritance['.$host_id.']['.$service_id.']['.$values["attr_name"].']" value="'.$attribute_id.'" checked=checked>';
                }
                $preview .= '</td>';

                $preview .= '<td>'.$values["attr_friendly_name"].'</td>';
                # current values
                $preview .= '<td name="checkbox_toggle"';
                    // color red
                    if ( $values["differs"] ) $preview .= ' class="red"';
                $preview .= '>';
                    # generate value list
                    $current_values = array_flip($values["current"]);
                    $preview .= implode(", ", $current_values);
                $preview .= "</td>";
                
                # new values
                $preview .= '<td name="checkbox_toggle"';
                    // color green 
                    if ( $values["differs"] ) $preview .= ' class="bold"';
                $preview .= '>';
                    # generate value list
                    $new_values = array_flip($values["new"]);
                    $preview .= implode(", ", $new_values);
                $preview .= "</td>";
                
                $preview .= "</tr>";
                
            }
        }
        $preview .= NConf_HTML::table_end();
        return $preview;

    }elseif ( !NConf_DEBUG::status('ERROR') AND $mode != "preview" ){
        NConf_DEBUG::set('', 'INFO', 'Successfully updated all linked services.');
    }

    return;

}


# prepare checkcommand params
# this will trim the values and put theme together with "!" for nagios
function prepare_check_command_params($values){
    # Implode the splitet fields (exploded in handle_item.php)
    if( is_array($values) ){
        NConf_DEBUG::open_group("params for check command");
        foreach ($values as $field_key => $value_array) {
            # trim values
            NConf_DEBUG::set($value_array, '', "bevore trimming");
            $value_array_trimmed = array();
            foreach ( $value_array as $value ){
                $value_array_trimmed[] = trim($value);
            } 
            NConf_DEBUG::set($value_array_trimmed, '', "after trimming");
            if ( array_diff($value_array, $value_array_trimmed) ){
                NConf_DEBUG::set("trimmed check command params", 'INFO'); 
            }

            # string starts with a "!"
            $imploded = "!";
            # implode the other arguments
            $imploded .= implode("!", $value_array_trimmed);
            # Save it to the POST-var, so the var will be added later in the script
            $_POST[$field_key] = $imploded;
        }
        NConf_DEBUG::close_group();
    }

}


?>
