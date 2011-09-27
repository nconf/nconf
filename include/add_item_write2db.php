<?php


$step2 = "";

/*
# Check for existing entry
$query = 'SELECT id_attr
            FROM ConfigAttrs,ConfigClasses
            WHERE naming_attr="yes"
                AND id_class=fk_id_class
                AND config_class="'.$config_class.'"
         ';
$id_naming_attr = db_handler($query, "getOne", "get naming_attr ID");

$query = 'SELECT attr_value, fk_id_item
            FROM ConfigValues
            WHERE fk_id_attr='.$id_naming_attr.'
            AND attr_value = "'.escape_string($_POST[$id_naming_attr]).'"
        ';
$result = db_handler($query, "result", "Check if entry already exists");        

# Entry exists ?
if ( (mysql_num_rows($result)) AND ($config_class != "service") ){
*/

# naming attr of class
$id_naming_attr = db_templates("get_naming_attr_from_class", $config_class);

# Check for existing entry
if ($config_class == "service"){

    # get id of attr host_name
    $check_command_attr_id = db_templates("get_attr_id", $config_class, "host_name");
    $host_id = $_POST[$check_command_attr_id][0];
    NConf_DEBUG::set($host_id, 'DEBUG', "ID of selected host");

    $query = 'SELECT ConfigValues.fk_id_item, attr_value
            FROM ConfigValues, ConfigAttrs, ItemLinks, ConfigClasses
            WHERE id_attr = ConfigValues.fk_id_attr
            AND naming_attr = "yes"
            AND ConfigValues.fk_id_item = ItemLinks.fk_id_item
            AND fk_item_linked2 = "'.$host_id.'"
            AND fk_id_class = id_class
            AND config_class = "service"
            AND attr_value = "'.escape_string($_POST[$id_naming_attr]).'"';
    }elseif($config_class == "nagios-collector" OR $config_class == "nagios-monitor"){
        $query = 'SELECT attr_value, fk_id_item
                        FROM ConfigItems, ConfigValues, ConfigAttrs, ConfigClasses
                        WHERE id_item = fk_id_item
                        AND id_attr = fk_id_attr
                        AND naming_attr = "yes"
                        AND ConfigItems.fk_id_class = id_class
                        AND (
                            config_class = "nagios-monitor"
                            OR config_class = "nagios-collector"
                            )
                        AND attr_value="'.escape_string($_POST[$id_naming_attr]).'"';
}else{
    $query = 'SELECT attr_value, fk_id_item
            FROM ConfigValues
            WHERE fk_id_attr='.$id_naming_attr.'
            AND attr_value = "'.escape_string($_POST[$id_naming_attr]).'"
        ';
}
$result = db_handler($query, "result", "does entry already exist");
        
# Entry exists ?
if ( mysql_num_rows($result) ){

    NConf_DEBUG::set('An item with the name &quot;'.$_POST[$id_naming_attr].'&quot; already exists!', 'ERROR');
    NConf_DEBUG::set('Click for details or go back:', 'ERROR');

    $list_items = '';
    while($entry = mysql_fetch_assoc($result)){
        $list_items .= '<li><a href="detail.php?id='.$entry["fk_id_item"].'">'.$entry["attr_value"].'</a></li>';
    }
    $list = '<ul>'.$list_items.'</ul>';
    NConf_DEBUG::set($list, 'ERROR');

    # When user clicks on a listed item, and goes to delete it, the redirect must know where to go after delete, this would be the add page:
    $_SESSION["after_delete_page"] = $_SERVER["HTTP_REFERER"];
    message($debug, 'Setting after delete page to : '.$_SERVER["HTTP_REFERER"]);

    $write2db = "no";
}else{
    #entry not existing

    # Check mandatory fields
    $m_array = db_templates("mandatory", $config_class);
    $write2db = check_mandatory($m_array,$_POST);


    # check oncall groups when class is host, service or advanced-service
    if ($config_class    == "host"
    	OR $config_class == "service"
    	OR $config_class == "advanced-service"
    ){
        #if failed do not allow write2db
        if ( oncall_check() == FALSE ){
            $write2db = 'no';
        }
    }





    if ($write2db == "yes"){
        ################
        #### write to db
        ################

        # get class id
        $class_id = db_templates("get_id_of_class", $config_class);

        $query = 'INSERT INTO ConfigItems
                    (id_item, fk_id_class)
                    VALUES
                    (NULL, "'.$class_id.'" )
                    ';

        if (DB_NO_WRITES != 1) {
            $insert = db_handler($query, "insert", "Insert");
            if (!$insert){
                message ($error, 'Error while adding entry to ConfigItems:'.$query);
            }
        }

        if ( $insert ){
            # Get ID of insert:
            $id = mysql_insert_id();

            # add item CREATED to history
            if ($config_class == "service"){
                history_add("created", $config_class, $_POST[$id_naming_attr], $id, "add_service", $host_id);
            }else{
                history_add("created", $config_class, $_POST[$id_naming_attr], $id);
            }
            

            while ( $attr = each($_POST) ){
                // only add attributes (which have int(id) as attr key
                if ( is_int($attr["key"]) ){
                    // add attribute
                    add_attribute($id, $attr["key"], $attr["value"]);
                }
            }

            ////
            // Handle not visible attributes
            // lookup visible=no attributes
            $attrs_visible_no = read_attributes($config_class, 'no');

            // add attributes (visible=no)
            foreach( $attrs_visible_no AS $attribute_key => $attribute_value ){
                NConf_DEBUG::set( $attribute_key." -> ".$attribute_value, 'DEBUG', "Add attribute");
                $result = add_attribute($id, $attribute_key, $attribute_value);
            }



            if (DB_NO_WRITES != 1) {
                NConf_DEBUG::set( 'Successfully added <b>'.escape_string($_POST[$id_naming_attr]).'</b>', 'INFO');
            }

            # cache
            if (isset($_SESSION["cache"]["modify"])) unset($_SESSION["cache"]["modify"]);

            if ($config_class == "host") {
                $_SESSION["created_id"] = $id;
                $step2 = "yes";
            }
            if ($id) { $_SESSION["created_id"] = $id; }

        }
    }
    # end of write2db

}# END Entry exists ?

?>
