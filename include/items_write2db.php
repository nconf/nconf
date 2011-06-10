<?php
            while ( $attr = each($items2write) ){

                # only entries with attribute id in the key will be accepted
                if ( is_int($attr["key"]) ){

                    # Get name of attribute:
                    $attr_name = db_templates("friendly_attr_name", $attr["key"]);
                    if ( $attr_name ){
                        NConf_DEBUG::open_group($attr_name);

                        if ($handle_action == "multimodify") $HIDDEN_selected_attr = $attr_name;
                    }

                    if ( is_array($attr["value"]) ){
                        # modify assign_one/assign_many in ItemLinks
                        # get datatype for handling assign_cust_order
                        $attr_datatype = db_templates("attr_datatype", $attr["key"]);

                        # Check if the values are modifyied, only save changed values
                        if ( !isset($old_linked_data[$attr["key"]]) ){
                            //$old_linked_data[$attr["key"]] = array("0" => "");
                            $old_linked_data[$attr["key"]] = array();
                        }

                        # if no value is selected there comes an empty 0 (zero) item, remove this
                        if ( isset($attr["value"][0]) AND empty($attr["value"][0]) ){
                            unset($attr["value"][0]);
                        }

                        NConf_DEBUG::set($old_linked_data[$attr["key"]], 'DEBUG', "old data");
                        NConf_DEBUG::set($attr["value"], 'DEBUG', "new data");

                        # check replace_mode
                        $replace_mode = (!empty($_POST["replace_mode"]) ) ? $_POST["replace_mode"] : "replace";

                        # Assigned items
                        if ($attr_datatype == "assign_cust_order" AND $replace_mode == "replace"){
                            # compare arrays with additional index check
                            $diff_array = array_diff_assoc($attr["value"] ,$old_linked_data[$attr["key"]]);
                        }else{
                            # normal compare of arrays
                            # also cust_order items in "add" mode need normal diff, because we just want to know difference, not exact position compares
                            $diff_array = array_diff($attr["value"] ,$old_linked_data[$attr["key"]]);
                        }

                        # Unassigned items
                        if ($attr_datatype == "assign_cust_order" AND $replace_mode == "replace"){
                            # compare arrays with additional index check
                            $diff_array2 = array_diff_assoc($old_linked_data[$attr["key"]], $attr["value"]);
                        }else{
                            if ($handle_action == "multimodify" AND $replace_mode == "add"){
                                # Mode: "add" in the multimodify GUI will not replace the values, it will just add the additional ones.
                                # so do not make a diff for deletion
                                $diff_array2 = array();
                            }else{
                                # normal compare of arrays
                                $diff_array2 = array_diff($old_linked_data[$attr["key"]], $attr["value"]);
                            }
                        }
                        if ( !empty($diff_array2) ){
                            while ( $attr_removed = each($diff_array2) ){
                                history_add("unassigned", $attr_name, $attr_removed["value"], $id, "resolve_assignment");
                                $edited = TRUE;
                            }
                        }

                        NConf_DEBUG::set($diff_array, 'DEBUG', 'diff 1: "assigned data"');
                        NConf_DEBUG::set($diff_array2, 'DEBUG', 'diff 2: "unassigned data"');

                        if ( (count($diff_array) OR count($diff_array2) ) != 0 ){
                            message ($info, "Attribute '$attr_name' has changed.");
                            #if ($handle_action == "multimodify") $info_summary["ok"][] = $name;
                        }else{
                            if ($handle_action == "multimodify") $info_summary["ignored"][] = $name;
                            message ($debug, 'no changes in this attribute');
                            NConf_DEBUG::close_group();
                            ########## CONTINUE IF ATTRIBUTE WAS NOT CHANGED   ############
                            continue;
                        }


                        ###########################
                        ### Delete old links
                        ###########################
                        message ($debug, '<b>Delete old links</b>');

                        # special handling for advanced services (coming from modify_item_service page)
                        if ( isset($advanced_services) AND $_GET["ajax_file"] == "advanced_service.php"){
                            # force swaping the data
                            $lac_OR_bidirectional = TRUE;
                        }else{
                            # check link_as_child & link_bidirectional
                            $lac_OR_bidirectional = check_link_as_child_or_bidirectional($attr["key"], $class_id);
                        }

                        if ($handle_action == "multimodify" AND $replace_mode == "add"){
                            # do no deletions
                        }else{
                            if ( $lac_OR_bidirectional ){
                                # interchange data

                                $delete_query_lac = 'DELETE FROM ItemLinks
                                        WHERE fk_id_attr="'.$attr["key"].'"
                                        AND fk_item_linked2="'.$id.'"';
                                db_handler($delete_query_lac, "delete", "Delete link as child");
                            }else{
                                $delete_query = 'DELETE FROM ItemLinks
                                        WHERE fk_id_attr="'.$attr["key"].'"
                                        AND fk_id_item="'.$id.'"';
                                db_handler($delete_query, "delete", "Delete (not link as child)");
                            }
                        }

                        ###########################
                        ### Insert new links
                        ###########################
                        message ($debug, '<b>Insert new links</b>');

                        # counter for assign_cust_order
                        if ( $attr_datatype == "assign_cust_order" AND $replace_mode == "add" ){
                            # on add mode we put the items behind the current ones
                            $cust_order = count($old_linked_data[$attr["key"]]);
                        }else{
                            $cust_order = 0;
                        }
                        # save assign_one/assign_many/assign_cust_order in ItemLinks

                        if ($handle_action == "multimodify" AND $replace_mode == "add"){
                            # take only diff assignes for additional add
                            $add_items = $diff_array;
                        }else{
                            # take send data for replace/overrides and normal add's
                            $add_items = $attr["value"];
                        }
                        //while ( $many_attr = each($attr["value"]) ){
                        while ( $many_attr = each($add_items) ){
                            # if value is empty go to next one
                            if (!$many_attr["value"]){
                                if ($handle_action == "multimodify") $info_summary["ok"][] = $name;
                                continue;
                            }else{
                                # if the circumstances are correct, link as child / bidirectional (change values)
                                # if link_as_child or (bidirectional + not same class)
                                # this is given from previouse check (where links are deleted)
                                ###
                                # make variables easier for vice versa sql query
                                if ($lac_OR_bidirectional){
                                    $id_1 = $many_attr["value"];
                                    $id_2 = $id;
                                }else{
                                    $id_1 = $id;
                                    $id_2 = $many_attr["value"];
                                }
                                # attr id
                                $fk_id_attr = $attr["key"];

                                
                                # check if value is already linked vice versa
                                $query_vv = 'SELECT fk_id_item FROM ItemLinks
                                            WHERE fk_id_attr = '.$fk_id_attr.'
                                                AND fk_id_item = '.$id_2.'
                                                AND fk_item_linked2 = '.$id_1.';';
                                $vice_versa_exists = db_handler($query_vv, "num_rows", 'VICE VERSA-check');

                                # prevent linking item with himself
                                # this is possible on multimodify because it should be available for others
                                $links_himself = FALSE;

                                # only check when multimodify:
                                if ($handle_action == "multimodify" AND ($id_1 === $id_2) ){
                                    $links_himself = TRUE;
                                }

                                # evaluate checks bevore linking items
                                if ( ($vice_versa_exists === 0) AND ($links_himself === FALSE) ){
                                    $query = 'INSERT INTO ItemLinks
                                        (fk_id_item, fk_item_linked2, fk_id_attr, cust_order)
                                        VALUES
                                        ('.$id_1.', '.$id_2.', '.$fk_id_attr.', '.$cust_order.')';

                                    if (DB_NO_WRITES != 1) {
                                        $result = db_handler($query, "result", 'link "'.$id_1.'" with '.$id_2);
                                        if ($result){
                                            // ok
                                            history_add("assigned", $attr_name, $many_attr["value"], $id, "resolve_assignment");
                                            if ($handle_action == "multimodify") $info_summary["ok"][] = $name;
                                            $edited = TRUE;
                                        }else{
                                            message ($error, 'Error while linking '.$id_1.' with '.$id_2.':'.$query);
                                            if ($handle_action == "multimodify") $info_summary["failed"][] = $name;
                                        }
                                    }
                                }else{
                                    if ($vice_versa_exists !== 0){
                                        // Item is already linked (vice versa)
                                        NConf_DEBUG::set( "already linked", 'DEBUG', "Skiping item");
                                        if ($handle_action == "multimodify") $info_summary["ignored"][] = $name;
                                    }elseif($links_himself === TRUE){
                                        NConf_DEBUG::set( "cannot link item with himself", 'DEBUG', "Skiping item");
                                        if ($handle_action == "multimodify") $info_summary["ignored"][] = $name;
                                    }
                                }


                            }

                            # increase assign_cust_order if needed
                            if ($attr_datatype == "assign_cust_order") $cust_order++;

                        }


                    }else{
                        # Lookup datatype
                        $query = 'SELECT ConfigValues.attr_value, ConfigAttrs.datatype FROM `ConfigAttrs`, ConfigValues
                                    WHERE ConfigAttrs.id_attr = "'.$attr["key"].'"
                                    AND ConfigValues.fk_id_attr = ConfigAttrs.id_attr
                                    AND ConfigValues.fk_id_item = "'.$id.'"';

                        $check = db_handler($query, "assoc", "Lookup value and datatype");
                        if ($check == FALSE){
                            $check["datatype"] = db_templates("attr_datatype", $attr["key"]);
                        }
                        
                        # Check if the value has changed
                        if ( !isset($check["attr_value"]) OR ($check["attr_value"] != $attr["value"]) ){
                            if ($check["datatype"] == "password"){
                                // IF Password field is a encrypted, do not save
                                if ( preg_match( '/^{.*}/', $attr["value"]) ){
                                    message ($info, "encrypted field will not be saved");
                                    continue;
                                }elseif ( (PASSWD_DISPLAY == 0) AND  ( strpos($attr["value"], PASSWD_HIDDEN_STRING) !== false) ){
                                    // Passwort was displayed as "hidden" like "********", do not save
                                    message ($info, "passwd was hidden and not modified");
                                    continue;
                                }else{
                                    $insert_attr_value = encrypt_password($attr["value"]);
                                }
                            }else{
                                // modify text/select
                                $insert_attr_value = escape_string($attr["value"]);
                            }

                            # only multimodify:
                            if ($handle_action == "multimodify"){
                                # check for service name (dublicates are not allowed, so generate an name which is not already used in this host)
                                if ( isset($items2write[$id_naming_attr]) AND $config_class == "service" ){
                                    # check the service name, it should not be the same as the source service

                                    # get all service names of destination server
                                    $host_ID = db_templates("hostID_of_service", $id);
                                    $existing_service_names = db_templates("get_services_from_host_id", $host_ID);


                                    # when service name does not exist, we can add service with its name
                                    # otherwise we have to create an other name:
                                    $new_service_name = $insert_attr_value;
                                    if ( in_array($new_service_name, $existing_service_names) ){
                                        $service_name_changed = TRUE;
                                        # create a service name with "_" and a number, until we found a service name which is not used
                                        $new_service_name = $insert_attr_value.'_';
                                        $i = 1;
                                        do{
                                            $i++;
                                            $try_service_name = $new_service_name.$i;
                                        }while( in_array($try_service_name, $existing_service_names) );
                                        # found a services name, which does not exist
                                        $new_service_name = $try_service_name;
                                    }
                                    # give the service name back for writing to db
                                    $insert_attr_value = $new_service_name;
                                }
                            }


                            # save value to DB
                            $query =   'INSERT INTO ConfigValues
                                            (attr_value, fk_id_attr, fk_id_item)
                                        VALUES
                                            ("'.$insert_attr_value.'", "'.$attr["key"].'", '.$id.' )
                                        ON DUPLICATE KEY UPDATE
                                            attr_value="'.$insert_attr_value.'"
                                        ';

                            $insert = db_handler($query, "insert", 'Insert entry');
                            if ($insert){
                                message ($debug, 'Successfully added ('.stripslashes($insert_attr_value).')');
                                if ($handle_action == "multimodify") $info_summary["ok"][] = $name;
                                history_add("modified", $attr["key"], $insert_attr_value, $id);
                                $edited = TRUE;
                            }else{
                                message ($error, 'Error while adding '.stripslashes($insert_attr_value).':'.$query);
                                if ($handle_action == "multimodify") $info_summary["failed"][] = $name;
                            }
                        }else{
                            // The data value has not changed, so no saving is needed
                            //echo 'The value is not different, so no change is needed.<br><br>';
                            if ($handle_action == "multimodify") $info_summary["ignored"][] = $name;
                        }
                    }
                }else{
                    continue;
                }

                NConf_DEBUG::close_group();

            } // while

?>
