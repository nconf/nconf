<?php

if ($_POST["action"] == "clone2hosts"){

//    if ( isset($_SESSION["cache"]["clone_service"]) ) unset($_SESSION["cache"]["clone_service"]);
    # Check mandatory fields
    $arr_mandatory = array("destination_host_ids" => "destination host", "service_id" => "service id");
    $write2db = check_mandatory($arr_mandatory,$_POST);
    if ($write2db == "yes"){
        # source host id
        $source_host_id = $_POST["source_host_id"];

        # service
        $service_tpl["fk_id_item"] = $_POST["service_id"];

        # service name of clone (if set or possible)
        if ( empty($_POST["new_service_name"]) ){
            $basic_service_name = db_templates("naming_attr", $service_tpl["fk_id_item"]);
        }else{
            $basic_service_name = $_POST["new_service_name"];
        }
        # destination hosts
        $destination_host_ids = $_POST["destination_host_ids"];


    }else{ # else of write2db

        # error
        NConf_DEBUG::set( "check mandatory data", 'ERROR', "FAILED");
    }

}elseif($_POST["action"] == "cloneONhost"){
    # set values
    $source_host_id = $_POST["host_id"];
    $destination_host_ids[] = $_POST["host_id"];
    $service_tpl["fk_id_item"] = $_POST["service_id"];
    $basic_service_name = db_templates("naming_attr", $service_tpl["fk_id_item"]);
    $write2db = "yes";
}

# set attribute id of attr_hostname
$service_tpl["fk_id_attr"] = db_templates("get_attr_id", "service", "host_name");



# clone service for all destination hosts
if ($write2db == "yes"){

    # feedback preparation
    $item_name = db_templates("naming_attr", $service_tpl["fk_id_item"]);
    $feedback = '<h2>&nbsp;Clone service: '.$item_name.'</h2>';

    //# iterate through all destination servers
    foreach ($destination_host_ids as $destination_host_id){
        # create a new item_id for each cloned service
        $query = 'INSERT INTO ConfigItems (id_item,fk_id_class) VALUES (NULL,(SELECT id_class FROM ConfigClasses WHERE config_class="service"))';
        $new_service_id = db_handler($query, "insert_id", "create a new item_id for cloned service");

        # link new service to cloned host
        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) 
                    VALUES ('.$new_service_id.','.$destination_host_id.','.$service_tpl["fk_id_attr"].')';
        $result = db_handler($query, "insert", "link new service to cloned host");
        if ($result){
            message ($debug, 'Successfully linked service '.$new_service_id.' to host '.$destination_host_id);
        }else{
            message ($error, 'Error linking service '.$new_service_id.' to host '.$destination_host_id.' '.$query);
        }



        # check the service name, it should not be the same as the source service

        # get all service names of destination server
        $existing_service_names = db_templates("get_services_from_host_id", $destination_host_id);


        # when service name does not exist, we can add service with its name
        # otherwise we have to create an other name:
        $new_service_name = $basic_service_name;
        if ( in_array($new_service_name, $existing_service_names) ){
            $new_service_name = $new_service_name.'_clone';
            if ( in_array($new_service_name, $existing_service_names) ){
                # service name with "_clone" also already exists
                # create a service name with "_clone" and a number, until we found a service name which is not used
                $i = 1;
                do{
                    $i++;
                    $try_service_name = $new_service_name.$i;
                }while( in_array($try_service_name, $existing_service_names) );
                # found a services name, which does not exist
                $new_service_name = $try_service_name;
            }
        }

        $service_name_attr_id = db_templates("get_attr_id", "service", "service_description");
        # set service name
        $query =   'INSERT INTO ConfigValues
                        (attr_value, fk_id_attr, fk_id_item)
                    VALUES
                        ("'.$new_service_name.'", "'.$service_name_attr_id.'", '.$new_service_id.' )
                    ON DUPLICATE KEY UPDATE
                        attr_value="'.$new_service_name.'"
                    ';

        $insert = db_handler($query, "insert", 'Set name of cloned service to "'.$new_service_name.'"');
        if ($insert){
            // add create entry
            history_add("created", "service", $new_service_name, $new_service_id);
            // add service_name field
            history_add("added", $service_name_attr_id, $new_service_name, $new_service_id);
            // add entry for host
            history_add("added", "service", $new_service_name, $destination_host_id);
        }

            


        # clone basic data of original service onto new service
        $query = 'INSERT INTO ConfigValues (fk_id_attr,attr_value,fk_id_item)
                    SELECT id_attr,attr_value,'.$new_service_id.' FROM ConfigAttrs,ConfigValues,ConfigItems
                        WHERE id_attr=fk_id_attr
                            AND id_item=fk_id_item
                            AND id_item='.$service_tpl["fk_id_item"].'
                            AND naming_attr = "no"
                        ORDER BY ordering';

        $result = db_handler($query, "insert", "clone basic data of original service onto new service");

        # HISTORY add :basic data
        $query = 'SELECT id_attr,attr_value FROM ConfigAttrs,ConfigValues,ConfigItems
                        WHERE id_attr=fk_id_attr
                            AND id_item=fk_id_item
                            AND id_item='.$service_tpl["fk_id_item"].'
                            AND naming_attr = "no"
                        ORDER BY ordering';
        $basic_entries = db_handler($query, "array", "for history: get basic data");
        foreach ($basic_entries AS $entry){
            history_add("added", $entry["id_attr"], $entry["attr_value"], $new_service_id);
        }


        # clone items linked to original service onto new service
        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr,cust_order)
                    SELECT '.$new_service_id.',fk_item_linked2,fk_id_attr,cust_order FROM ItemLinks 
                        WHERE fk_id_item='.$service_tpl["fk_id_item"].' 
                            AND fk_item_linked2 <> '.$source_host_id.'
                        ORDER BY fk_item_linked2';

        $result = db_handler($query, "insert", "clone items linked to original service onto new service");

        # HISTORY add :linked items
        $query = 'SELECT fk_item_linked2,fk_id_attr,cust_order FROM ItemLinks
                        WHERE fk_id_item='.$service_tpl["fk_id_item"].'
                            AND fk_item_linked2 <> '.$source_host_id.'
                        ORDER BY fk_item_linked2';
        $linked_items = db_handler($query, "array", "for history: get linked items");
        foreach ($linked_items AS $entry){
            history_add("assigned", $entry["fk_id_attr"], $entry["fk_item_linked2"], $new_service_id, "resolve_assignment");
        }

// try history
/*

history_add("added",    fk_id_attr =    ,  attr_value   ,   fk_id_item (service id)

history_add("assigned", fk_id_attr = $timeperiod_id, fk_item_linked2 = $timeperiod["item_id"], fk_id_item = $new_service_ID, "resolve_assignment");

*/



        # clone items linked as child of original service onto new service
        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr,cust_order)
                    SELECT fk_id_item,'.$new_service_id.',fk_id_attr,cust_order FROM ItemLinks
                        WHERE fk_item_linked2 = '.$service_tpl["fk_id_item"].' ORDER BY fk_id_item';

        $result = db_handler($query, "insert", "clone items linked as child of original service onto new service");

        # HISTORY add :linked as child items
        $query = 'SELECT fk_id_item,fk_id_attr,cust_order FROM ItemLinks
                        WHERE fk_item_linked2 = '.$service_tpl["fk_id_item"].' ORDER BY fk_id_item';
        $linkedAsChild_items = db_handler($query, "array", "for history: get linked as child items");
        foreach ($linkedAsChild_items AS $entry){
            history_add("assigned", $entry["fk_id_attr"], $new_service_id, $entry["fk_id_item"], "resolve_assignment");
        }





        # feedback for clone2hosts
        if ($_POST["action"] == "clone2hosts"){
            $host_name = db_templates("get_value", $destination_host_id, "host_name");
            $host_link = '<a href="modify_item_service.php?id='.$destination_host_id.'"><span class="link_with_tag">'.$host_name.'</span></a>';
            NConf_DEBUG::set( "<i>".$new_service_name."</i>", 'INFO', $host_link);
            if ( NConf_DEBUG::status('ERROR') ){
                $service_link = '<a href="detail.php?id='.$new_service_id.'" target="_blank" class="link_with_tag">'.$new_service_name.'</a>';
                NConf_DEBUG::set( $service_link, 'ERROR', "failed with service");
            }
        }

    }

    if($_POST["action"] == "cloneONhost"){
        // give new ID back
        echo '<div id="clone_success">'.$new_service_id.'</div>';
    }

}

# output for clone2hosts
if ($_POST["action"] == "clone2hosts"){

    $source_service_name = db_templates("get_value", $_POST["service_id"], "service_description");
    if ( NConf_DEBUG::status('ERROR') ){
        # error
        $feedback = "Failed, see debug for details.";
        echo '<div id="clone_error">'.$feedback;
        echo NConf_DEBUG::show_debug('INFO', TRUE);
        echo '</div>';
    }else{
        $feedback .= 'Successfully cloned to the following hosts:</b><br>';
        $feedback .= NConf_DEBUG::show_debug('INFO', TRUE);
        echo '<div id="clone_success" class="feedback">'.$feedback.'</div>';
    }
}



?>
