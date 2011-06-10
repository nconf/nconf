<?php

if ($_GET["action"] == "clone2hosts"){
    require_once 'include/head.php';


    if ( isset($_SESSION["cache"]["clone_service"]) ) unset($_SESSION["cache"]["clone_service"]);

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
        // Cache
        foreach ($_POST as $key => $value) {
            $_SESSION["cache"]["clone_service"][$key] = $value;
        }

        // Error message
        echo NConf_DEBUG::show_debug('ERROR', TRUE, $_SESSION["go_back_page"]);

    }

}

# get/set attribute id of attr host_name
$service_tpl["fk_id_attr"] = db_templates("get_attr_id", "service", "host_name");




# clone service for all destination hosts
if ($write2db == "yes"){

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

            


        # clone basic data of original service onto new service
        $query = 'INSERT INTO ConfigValues (fk_id_attr,attr_value,fk_id_item)
                    SELECT id_attr,attr_value,'.$new_service_id.' FROM ConfigAttrs,ConfigValues,ConfigItems
                        WHERE id_attr=fk_id_attr
                            AND id_item=fk_id_item
                            AND id_item='.$service_tpl["fk_id_item"].'
                            AND naming_attr = "no"
                        ORDER BY ordering';

        $result = db_handler($query, "insert", "clone basic data of original service onto new service");

        # Now the service name is also entered, so make history entry
        history_add("added", "service", $new_service_id, $destination_host_id, "resolve_assignment");

        # clone items linked to original service onto new service
        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr,cust_order)
                    SELECT '.$new_service_id.',fk_item_linked2,fk_id_attr,cust_order FROM ItemLinks 
                        WHERE fk_id_item='.$service_tpl["fk_id_item"].' 
                            AND fk_item_linked2 <> '.$source_host_id.'
                        ORDER BY fk_item_linked2';

        $result = db_handler($query, "insert", "clone items linked to original service onto new service");

        # clone items linked as child of original service onto new service
        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr,cust_order)
                    SELECT fk_id_item,'.$new_service_id.',fk_id_attr,cust_order FROM ItemLinks
                        WHERE fk_item_linked2 = '.$service_tpl["fk_id_item"].' ORDER BY fk_id_item';

        $result = db_handler($query, "insert", "clone items linked as child of original service onto new service");

        if ($_GET["action"] == "clone2hosts"){
            $host_name = db_templates("get_value", $destination_host_id, "host_name");
            $host_link = '<a href="modify_item_service.php?id='.$destination_host_id.'"><b>'.$host_name.'</b></a> (new service name is "'.$new_service_name.'")';
            message($info, $host_link);
        }


    }

    if ($_GET["action"] == "clone2hosts"){
        $item_name = db_templates("naming_attr", $source_host_id);
        echo '<h2>&nbsp;Clone Service from host '.$item_name.'</h2>';

        $source_service_name = db_templates("get_value", $_POST["service_id"], "service_description");
        echo 'Successfully cloned service &quot;<b>'.$source_service_name.'</b>&quot; to the following hosts:</b><br><br>';
        echo NConf_DEBUG::show_debug('INFO', TRUE);

        mysql_close($dbh);
        require_once 'include/foot.php';
    }

}else{
    # no write
    # when clone2hosts show also the footer
    if ($_GET["action"] == "clone2hosts"){
        mysql_close($dbh);
        require_once 'include/foot.php';
    }
}


?>
