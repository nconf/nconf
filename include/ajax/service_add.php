<?php
/*
echo "<pre>";
print_r($_POST);
echo "</pre>";
*/

if ( !isset($_POST["mode"])
    OR !isset($_POST["host_id"])
    OR ( ! (isset($_POST["service_id"]) OR $_POST["mode"] == "add_default_services") )
    ){

    echo '<div id="ajax_debug"> failed, received not all required infos </div>';
    exit;
}

$host_ID = $_POST["host_id"];
$checkcommand = array();

if ( $_POST["mode"] == "add_service" ){
    //$checkcommand[$_POST["service_id"]] = $_POST["service_name"];
    $checkcommand[] = $_POST["service_id"];
}elseif( $_POST["mode"] == "add_default_services" ){
    // add default services (host presets)

    // get selected host-preset of added host
    $query = 'SELECT fk_item_linked2
                FROM ItemLinks,ConfigAttrs,ConfigClasses
                WHERE id_attr=fk_id_attr
                AND attr_name="host-preset"
                AND id_class=fk_id_class
                AND config_class="host"
                AND fk_id_item="'.$host_ID.'"';

    $hosttemplate_ids = db_handler($query, "array_direct", "Get selected host-presets of added host");
    $checkcommand = array();
    foreach ( $hosttemplate_ids AS $hosttemplate_id ) {
        if ( !empty($hosttemplate_id) ){
            // Get checkcommands of selected host-preset
            $query = 'SELECT ItemLinks.fk_id_item
                FROM ConfigValues,ConfigAttrs,ItemLinks,ConfigClasses
                WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                AND id_attr=ConfigValues.fk_id_attr
                AND naming_attr="yes"
                AND id_class=fk_id_class
                AND config_class="checkcommand"
                AND fk_item_linked2='.$hosttemplate_id;

            $checkcommand = array_merge($checkcommand, db_handler($query, "array_direct", "Get checkcommands of selected host-preset"));
        }
    }
}


if (  ( is_array($checkcommand) ) AND !empty($checkcommand)  ){
    // go thru each service to add
    // each checkcommand (only the ID is used)
    // the name will be taken by a function at the "handle service name" part
    //foreach ( $checkcommand AS $checkcommand_ID => $checkcommand_name ){
    foreach ( $checkcommand AS $checkcommand_ID ){
        //NConf_DEBUG::group_begin('add_service'.$checkcommand_ID, "add service ".$checkcommand_ID);

        // Generate new item_id for service
        $query = 'INSERT INTO ConfigItems (fk_id_class) 
                    VALUES ((SELECT id_class
                                FROM ConfigClasses
                                WHERE config_class="service"))
                 ';

        $insert = db_handler($query, "insert", "Generate new item_id for service");
        if ( $insert ){

            // Get generated ID
            $new_service_ID = mysql_insert_id();

            // Link new service with host        
            $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) 
                        VALUES ('.$new_service_ID.','.$host_ID.',
                            (SELECT id_attr FROM ConfigAttrs,ConfigClasses 
                            WHERE id_class=fk_id_class 
                            AND config_class="service" 
                            AND attr_name="host_name"))
                     ';
            db_handler($query, "insert", "Link new service with host");


            ////
            // Get all attributes with predefined values (visible=yes)
            $attrs_visible_yes = read_attributes('service', 'yes');


            ////
            // handle service name
            // additional name handling for existing service names
            
            // get service name (looks also for default_service_name)
            // this template gives back an array!
            $service_name = db_templates("get_name_of_services", $checkcommand_ID);
            //NConf_DEBUG::set( $service_name, 'DEBUG', "get service name");

            // get all service names of destination server
            $existing_service_names = db_templates("get_services_from_host_id", $host_ID);

            if ( in_array($service_name["service_name"], $existing_service_names) ){
                $new_service_name = $service_name["service_name"].'_';
                $i = 1;
                do{
                    $i++;
                    $try_service_name = $new_service_name.$i;
                }while( in_array($try_service_name, $existing_service_names) );
                # found a services name, which does not exist
                $new_service_name = $try_service_name;
                // move value back
                $service_name["service_name"] = $new_service_name;
            }

            // Get naming attr ID of attribute "service_description" (=service name) (its also the naming attr)
            $service_name_id = db_templates("get_naming_attr_from_class", "service");

            //remove from attrs_visible_yes
            unset($attrs_visible_yes[$service_name_id]);

            // Set name of service
            $query = 'INSERT INTO ConfigValues (attr_value, fk_id_item, fk_id_attr) 
                       VALUES ( "'.$service_name["service_name"].'"
                                ,'.$new_service_ID.'
                                ,'.$service_name_id.'
                              )
                     ';
            $status = db_handler($query, "insert", "Set name of service");
            if ($status){
                // add create entry
                history_add("created", "service", $service_name["service_name"], $new_service_ID);
                // add service_name field
                history_add("added", $service_name_id, $service_name["service_name"], $new_service_ID);
                // add entry for host
                history_add("added", "service", $service_name["service_name"], $host_ID);
            }




            ////
            // This will add the service (given per $_POST) with the server
            $check_command_attr_id = db_templates("get_attr_id", "service", "check_command");

            //remove from attrs_visible_yes
            unset($attrs_visible_yes[$check_command_attr_id]);
            


            ////
            // default checkcommand params handling
            // Link service with checkcommand
            $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) 
                        VALUES ( '.$new_service_ID.'
                                ,'.$checkcommand_ID.'
                                ,'.$check_command_attr_id.'
                                )
                     ';
            db_handler($query, "insert", "Link service with checkcommand");

            $check_params_attr_id = db_templates("get_attr_id", "service", "check_params");

            //remove from attrs_visible_yes
            unset($attrs_visible_yes[$check_params_attr_id]);


            // Read default checkcommand params
            $default_params = db_templates("get_default_checkcommand_params", $checkcommand_ID);

            // Set default checkcommand params
            $query = 'INSERT INTO ConfigValues (fk_id_item,attr_value,fk_id_attr)
                       VALUES(  '.$new_service_ID.'
                             , "'.$default_params.'"
                             , '.$check_params_attr_id.'
                             )
                     ';

            $status = db_handler($query, "insert", "set default checkcommand params");
            if ($status) history_add("added", $check_params_attr_id, $default_params, $new_service_ID);



            ////
            // Timeperiods
            $query = 'SELECT fk_item_linked2 AS item_id,attr_name
                        FROM ItemLinks,ConfigAttrs,ConfigClasses
                        WHERE id_attr=fk_id_attr
                            AND id_class=fk_id_class
                            AND fk_id_item="'.$host_ID.'"
                            HAVING (SELECT config_class FROM ConfigItems,ConfigClasses 
                                    WHERE id_class=fk_id_class 
                                        AND id_item=item_id) = "timeperiod"';

            $result = db_handler($query, "result", "select timeperiods");
            if ($result){
                if ( mysql_num_rows($result) > 0 ){
                    while ($timeperiod = mysql_fetch_assoc($result)){
                        // get timeperiods attr id
                        $timeperiod_id = db_templates("get_attr_id", "service", $timeperiod["attr_name"]);
                        //remove from attrs_visible_yes
                        unset($attrs_visible_yes[$timeperiod_id]);
                        

                        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) 
                                    VALUES ('.$new_service_ID.'
                                           ,'.$timeperiod["item_id"].'
                                           ,'.$timeperiod_id.'
                                           )
                                 ';

                        $status = db_handler($query, "insert", "insert timeperiod");
                        if ($status) {
                            history_add("assigned", $timeperiod_id, $timeperiod["item_id"], $new_service_ID, "resolve_assignment");
                        }
                    }
                }


            }else{
                message ($debug, '[ FAILED ]');
            }    

            ////
            // Link contactgroups of service with same as his host

            // Link service with same contactgroups as host
            $query = 'SELECT fk_item_linked2
                        FROM ItemLinks,ConfigAttrs 
                        WHERE id_attr=fk_id_attr
                        AND attr_name="contact_groups"
                        AND fk_id_item="'.$host_ID.'"
                     ';

            $result = db_handler($query, "result", "Link service with same contactgroups as host (select)");
            if ($result){
                if ( mysql_num_rows($result) > 0 ){
                    while ($contactgroup_ID = mysql_fetch_row($result)){
                        // get contact_groups attr id
                        $contact_groups_id = db_templates("get_attr_id", "service", "contact_groups");
                        //remove from attrs_visible_yes
                        unset($attrs_visible_yes[$contact_groups_id]);
                        

                        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) 
                                    VALUES ( '.$new_service_ID.'
                                            ,'.$contactgroup_ID[0].'
                                            ,'.$contact_groups_id.'
                                           )
                                 ';

                        $status = db_handler($query, "insert", "Link service with same contactgroups as host (insert)");
                        if ($status) {
                            history_add("assigned", $contact_groups_id, $contactgroup_ID[0], $new_service_ID, "resolve_assignment");
                        }
                    } // END while
                }

            }else{
                message ($debug, '[ FAILED ]');
            }


            ////
            // Add other attributes (visible=yes)
            foreach( $attrs_visible_yes AS $attribute_key => $attribute_value ){
                NConf_DEBUG::set( $attribute_key." -> ".$attribute_value, 'DEBUG', "Add attribute");
                $result = add_attribute($new_service_ID, $attribute_key, $attribute_value);
            }

            ////
            // Handle not visible attributes
            // lookup visible=no attributes
            $attrs_visible_no = read_attributes('service', 'no');

            // add attributes (visible=no)
            foreach( $attrs_visible_no AS $attribute_key => $attribute_value ){
                NConf_DEBUG::set( $attribute_key." -> ".$attribute_value, 'DEBUG', "Add attribute");
                $result = add_attribute($new_service_ID, $attribute_key, $attribute_value);
            }


            // give feedback for jQuery
            // add service to list of added services
            echo '<div id="add_success">'.$new_service_ID.'</div>';
        

        }// END if ( $insert ){
        
        //NConf_DEBUG::group_end();

    }// END while

}else{
    // give feedback for jQuery
    echo "No service to add";
} // END is_array($_POST["checkcommands"])


?>
