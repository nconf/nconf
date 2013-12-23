<?php
require_once 'include/head.php';

if ( isset($_SESSION["cache"]["clone"]) ) unset($_SESSION["cache"]["clone"]);

if (DB_NO_WRITES == 1) {
    message($info, "DB_NO_WRITES = 1: No DB inserts or modifications will be performed");
}

#############
# Check for existing entry
$query = 'SELECT fk_id_item,attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses
                WHERE id_attr=fk_id_attr
                    AND attr_name="host_name"
                    AND id_class=fk_id_class
                    AND config_class="host"
            AND attr_value = "'.$_POST["hostname"].'"';

$result = mysql_query($query);

# Entry not existing, lets try to clone...
echo NConf_HTML::page_title('host', 'Clone host');

#############
# Entry exists ?
if (mysql_num_rows($result)){
    NConf_DEBUG::set('An item with the name &quot;'.$_POST["hostname"].'&quot; already exists!', 'ERROR');
    NConf_DEBUG::set('For its details click the link below or go back:', 'ERROR');
    $list_items = '';
    while($entry = mysql_fetch_assoc($result)){
        $list_items .= '<li><a href="detail.php?id='.$entry["fk_id_item"].'">'.$entry["attr_value"].'</a></li>';
    }
    $list = '<ul>'.$list_items.'</ul>';
    NConf_DEBUG::set($list, 'ERROR');

    if ( NConf_DEBUG::status('ERROR') ) {
        echo '<table><tr><td>';
            echo NConf_HTML::show_error();
            echo "<br><br>";
            echo NConf_HTML::back_button($_SESSION["go_back_page"]);

            // Cache
            // TODO: cache handling
            $_SESSION["cache"]["use_cache"] = TRUE;
            foreach ($_POST as $key => $value) {
                $_SESSION["cache"]["handle"][$key] = $value;
            }
        echo '</td></tr></table>';
    }

}else{

    ?>
    <table>
        <tr>
            <td>
    <?php

#############
    # check mandatory entries
    #$write2db = "no";
    $arr_mandatory = array("template_id","hostname", "ip");

    $write2db = "yes";
    foreach ($arr_mandatory as $mandatory){
        if ( ( isset($_POST[$mandatory]) ) AND ( $_POST[$mandatory] != "") ){
            message($debug, "$mandatory: ok");
        }else{
            $message = "$mandatory: mandatory field";
            NConf_DEBUG::set($message, "ERROR", "");
            message($info, SELECT_EMPTY_FIELD, "overwrite");
            $write2db = "no";
        }
    }


#############
    # write to DB
    if ($write2db == "yes"){
        # generate new item_id
        $query = 'INSERT INTO ConfigItems (id_item,fk_id_class) VALUES (NULL,(SELECT id_class FROM ConfigClasses WHERE config_class="host"))';
        $new_host_id = db_handler($query, "insert_id", "insert new host");
        if ($result){
            history_add("created", "host", $_POST["hostname"], $new_host_id);
            message ($debug, 'Successfully added new host');
        }else{
            message ($error, 'Error while adding new host');
        }


#############
        # insert data deferring from template
        $hostname_id = db_templates("host_attr_id", "host_name");
        $alias_id = db_templates("host_attr_id", "alias"); // the alias (in host class)means "FQDN" as friendlyname
        $ip_id = db_templates("host_attr_id", "address");

        $query = 'INSERT INTO ConfigValues (attr_value,fk_id_item,fk_id_attr) 
                    VALUES ("'.$_POST["hostname"].'",'.$new_host_id.', '.$hostname_id.'),
                           ("'.$_POST["alias"].'",'.$new_host_id.', '.$alias_id.'),
                           ("'.$_POST["ip"].'",'.$new_host_id.', '.$ip_id.')';

        $result = db_handler($query, "result", "insert data deferring from template");

        if ($result){
            # add to history
            history_add("added", $hostname_id, $_POST["hostname"], $new_host_id);
            history_add("added", $alias_id, $_POST["alias"], $new_host_id);
            history_add("added", $ip_id, $_POST["ip"], $new_host_id);
        }

#############

        # clone basic data
        NConf_DEBUG::open_group("clone basic data");
        $query = 'INSERT INTO ConfigValues (fk_id_attr,attr_value,fk_id_item)
                    SELECT id_attr,attr_value,'.$new_host_id.' FROM ConfigAttrs,ConfigValues,ConfigItems
                        WHERE id_attr=fk_id_attr
                            AND id_item=fk_id_item
                            AND (attr_name <> "host_name" AND attr_name <> "alias" AND attr_name <> "address")
                            AND id_item='.$_POST["template_id"].'
                        ORDER BY ordering';

        $result = db_handler($query, "result", "clone basic data");
        if ($result){
            # enter basic data to history
            $query = 'SELECT id_attr,attr_value,'.$new_host_id.' FROM ConfigAttrs,ConfigValues,ConfigItems
                        WHERE id_attr=fk_id_attr
                            AND id_item=fk_id_item
                            AND (attr_name <> "host_name" AND attr_name <> "alias" AND attr_name <> "address")
                            AND id_item='.$_POST["template_id"].'
                        ORDER BY ordering';
            $b_data = db_handler($query, "array", "get basic data");
            foreach($b_data as $data){
                history_add("added", $data["id_attr"], $data["attr_value"], $data["$new_host_id"]);
            }
        }

#############
        # clone items linked as child (except services) e.g. other hosts
        NConf_DEBUG::open_group("clone items linked as child");
        $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr,cust_order)
                    SELECT fk_id_item,'.$new_host_id.',fk_id_attr,cust_order FROM ItemLinks,ConfigItems,ConfigClasses
                        WHERE id_item = fk_id_item
                        AND id_class = fk_id_class
                        AND config_class <> "service"
                        AND fk_item_linked2 = '.$_POST["template_id"].' ORDER BY fk_id_item';

        $result = db_handler($query, "result", "cloned data linked as child");
        if ($result){
            # enter linked CHILD items to history
            $query = 'SELECT fk_id_item,'.$new_host_id.',fk_id_attr FROM ItemLinks,ConfigItems,ConfigClasses
                        WHERE id_item = fk_id_item
                        AND id_class = fk_id_class
                        AND config_class <> "service"
                        AND fk_item_linked2 = '.$_POST["template_id"].' ORDER BY fk_id_attr,cust_order';
            $l_child_data = db_handler($query, "array", "get linked CHILD items for history");
            foreach($l_child_data as $data){
                # ! Attention because the entry is a CHILD, the assigment has to be logged in the history of the parent entry !!!
                # so "$new_host_id" and "fk_id_item" is simply switched
                history_add("assigned", $data["fk_id_attr"], $data["$new_host_id"], $data["fk_id_item"], "resolve_assignment");
            }
        }

#############

        # insert deferring parent hosts
        if(  ( isset($_POST["parents"]) ) && 
             ( ($_POST["parents"][0] != "CLONE-PARENTS") AND ($_POST["parents"][0] != "") )
        ){
            # Fetch attr_id from attribute "parents"
            $attr_parents_id = db_templates("host_attr_id", "parents");

            $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) VALUES ';
            $counter = 0;
            foreach ($_POST["parents"] as $parent){
                if($counter != 0){
                    $query .= ",";
                }
                $query .= '('.$new_host_id.','.$parent.','.$attr_parents_id.')';
                $counter++;
                history_add("assigned", $attr_parents_id, $parent, $new_host_id, "resolve_assignment");
            }

            $result = db_handler($query, "insert", "insert data deferring parent hosts");
        }

#############
        # Clone linked data / parents
        NConf_DEBUG::open_group("clone linked data");
        if( (isset($_POST["parents"][0])) AND ($_POST["parents"][0] == "CLONE-PARENTS") ){

            # clone all items linked
            $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr,cust_order)
                        SELECT '.$new_host_id.',fk_item_linked2,fk_id_attr,cust_order
                            FROM ItemLinks WHERE fk_id_item = '.$_POST["template_id"].'
                            ORDER BY fk_item_linked2';
            $history_query = 'SELECT '.$new_host_id.',fk_item_linked2,fk_id_attr
                            FROM ItemLinks WHERE fk_id_item = '.$_POST["template_id"].'
                            ORDER BY fk_item_linked2';
        }else{

            # clone items linked (except for parent hosts)
            $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr,cust_order)
                        SELECT '.$new_host_id.',fk_item_linked2,fk_id_attr,cust_order
                            FROM ItemLinks WHERE fk_id_item = '.$_POST["template_id"].'
                                AND ((SELECT attr_name FROM ConfigAttrs WHERE id_attr=fk_id_attr) <> "parents"
                                    AND (SELECT config_class FROM ConfigItems,ConfigClasses WHERE id_item=fk_item_linked2 AND id_class=fk_id_class) <> "host")
                                ORDER BY fk_item_linked2';
            $history_query = 'SELECT '.$new_host_id.',fk_item_linked2,fk_id_attr
                            FROM ItemLinks WHERE fk_id_item = '.$_POST["template_id"].'
                                AND ((SELECT attr_name FROM ConfigAttrs WHERE id_attr=fk_id_attr) <> "parents"
                                    AND (SELECT config_class FROM ConfigItems,ConfigClasses WHERE id_item=fk_item_linked2 AND id_class=fk_id_class) <> "host")
                                ORDER BY fk_id_attr,cust_order';
        }

        $result = db_handler($query, "result", "cloned linked data");
        if ($result){
            # enter linked items to history
            $l_data = db_handler($history_query, "array", "get linked items for history");
            foreach($l_data as $data){
                history_add("assigned", $data["fk_id_attr"], $data["fk_item_linked2"], $data["$new_host_id"], "resolve_assignment");
            }
        }

#############
        # clone each service individually

        # fetch list of services
        $query = 'SELECT fk_id_item,fk_id_attr FROM ItemLinks,ConfigItems,ConfigClasses
                        WHERE id_item = fk_id_item
                        AND id_class = fk_id_class
                        AND config_class = "service"
                        AND fk_item_linked2 = '.$_POST["template_id"].' ORDER BY fk_id_item';
        $services = db_handler($query, "array", "fetch list of services");

        # iterate through all services
        foreach ($services as $service_tpl){
            NConf_DEBUG::open_group("clone service ".$service_tpl["fk_id_item"]);

            # create a new item_id for each cloned service
            $query = 'INSERT INTO ConfigItems (id_item,fk_id_class) VALUES (NULL,(SELECT id_class FROM ConfigClasses WHERE config_class="service"))';
            $new_service_id = db_handler($query, "insert_id", "create a new item_id for each cloned service");

            # link new service to cloned host
            $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr) 
                        VALUES ('.$new_service_id.','.$new_host_id.','.$service_tpl["fk_id_attr"].')';
            $result = db_handler($query, "insert", "link new service to cloned host");
            if ($result){
                message ($debug, 'Successfully linked service '.$new_service_id.' to host '.$new_host_id);
            }else{
                message ($error, 'Error linking service '.$new_service_id.' to host '.$new_host_id.' '.$query);
            }

            # clone basic data of original service onto new service
            $query = 'INSERT INTO ConfigValues (fk_id_attr,attr_value,fk_id_item)
                        SELECT id_attr,attr_value,'.$new_service_id.' FROM ConfigAttrs,ConfigValues,ConfigItems
                            WHERE id_attr=fk_id_attr
                                AND id_item=fk_id_item
                                AND id_item='.$service_tpl["fk_id_item"].'
                            ORDER BY ordering';

            $result = db_handler($query, "insert", "clone basic data of original service onto new service");

            if ($result){

                # service added
                $service_name = db_templates("naming_attr", $service_tpl["fk_id_item"]);
                $service_name_attr_id = db_templates("get_attr_id", "service", "service_description");
                # created entry 
                history_add("created", "service", $service_name, $new_service_id);
                history_add("added", $service_name_attr_id, $service_name, $new_service_id);
                history_add("added", "service", $new_service_id, $new_host_id, "resolve_assignment");

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
            }


            # clone items linked to original service onto new service
            $query = 'INSERT INTO ItemLinks (fk_id_item,fk_item_linked2,fk_id_attr,cust_order)
                        SELECT '.$new_service_id.',fk_item_linked2,fk_id_attr,cust_order FROM ItemLinks 
                            WHERE fk_id_item='.$service_tpl["fk_id_item"].' 
                                AND fk_item_linked2 <> '.$_POST["template_id"].'
                            ORDER BY fk_item_linked2';

            $result = db_handler($query, "insert", "clone items linked to original service onto new service");

            # HISTORY add :linked items
            $query = 'SELECT fk_item_linked2,fk_id_attr,cust_order FROM ItemLinks
                            WHERE fk_id_item='.$service_tpl["fk_id_item"].'
                                AND fk_item_linked2 <> '.$_POST["template_id"].'
                            ORDER BY fk_item_linked2';
            $linked_items = db_handler($query, "array", "for history: get linked items");
            foreach ($linked_items AS $entry){
                history_add("assigned", $entry["fk_id_attr"], $entry["fk_item_linked2"], $new_service_id, "resolve_assignment");
            }




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

        } //foreach

#############
        # show success message
        echo '<b>Successfully cloned selected host to &quot;'.$_POST["hostname"].'&quot;</b>';
        echo '<br><br>Click for details: ';
        echo '<a href="detail.php?id='.$new_host_id.'">'.$_POST["hostname"].'</a>';

    }else{ # else of write2db

        // Cache
        foreach ($_POST as $key => $value) {
            $_SESSION["cache"]["clone"][$key] = $value;
        } 

        // Error message
        if ( NConf_DEBUG::status('ERROR') ) {
            echo NConf_HTML::show_error();
            echo "<br><br>";
            echo NConf_HTML::back_button($_SESSION["go_back_page"]);

            // Cache
            // TODO: cache handling on clone host
            $_SESSION["cache"]["use_cache"] = TRUE;
            foreach ($_POST as $key => $value) {
                $_SESSION["cache"]["handle"][$key] = $value;
            }
        }

    }# end of write2db

    ?>
                </td>
            </tr>
        </table>
    <?php

} // END Entry exists ?


mysql_close($dbh);

require_once 'include/foot.php';
?>
