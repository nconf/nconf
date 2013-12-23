<?php
if ( empty($_POST["host_id"]) ){
    NConf_DEBUG::set("failed, received not all required infos", 'ERROR');
    exit;
}

$host_ID = $_POST["host_id"];
// mark added services
$added_service[] = !empty($_POST["highlight_service"]) ? $_POST["highlight_service"] : array();

# get class or take service as default
if ( !empty($_POST["class"]) ){
    $class = $_POST["class"];
}else{
    $class = "service";
}

if( $class == "advanced-service" ) {
    $list_title = 'Unassign an advanced service from this host<br>';
}elseif( $class == "hostgroup_service" ){
    $list_title = '&nbsp;List of advanced services inherited over hostgroups<br>';
}elseif( $class == "service" ){
    $list_title = '&nbsp;Edit services directly linked to a host<br>';
}


////
// Content of Page
////

echo '<div id="content">';

echo $list_title;

if ( $class == "service" ){
    echo '<table class="ui-widget ui-nconf-table" width="328">';
    echo '<colgroup>';
    echo '<col width="30">';
    echo '<col>';
    echo '<col width="30">';
    echo '<col width="30">';
    echo '<col width="30">';
    echo '</colgroup>';
}elseif( $class == "hostgroup_service" ){
    echo '<table class="ui-widget ui-nconf-table" width="328">';
    echo '<colgroup>';
    echo '<col width="0">';
    echo '<col>';
    echo '<col width="100">';
    echo '</colgroup>';
}else{
    # for advanced service, but not used until now
    echo '<table class="ui-widget ui-nconf-table" width="328">';
}
    echo '<thead class="ui-state-default">';
        echo '<tr>';
            if ( $class == "service" OR $class == "hostgroup_service"){
                echo '<th></th>';
            }
            echo '<th>'.FRIENDLY_NAME_SERVICES.'</th>';
            if ( $class == "service" ){
                echo '<th colspan="3" class="center">'.FRIENDLY_NAME_ACTIONS.'</th>';
            }elseif ( $class == "hostgroup_service" ){
                echo '<th>'.FRIENDLY_NAME_HOSTGROUP.'</th>';
            }
        echo '</tr>'; 
    echo '</thead>';

    echo '<tbody class="ui-widget-content">';

    if ($class == "hostgroup_service"){
        $services = db_templates("hostgroup_services", $host_ID);
    }else{
        $query = 'SELECT
                ConfigValues.fk_id_item AS id,
                attr_value AS entryname,
                (SELECT attr_value
                    FROM ConfigValues,ConfigAttrs
                    WHERE id_attr=fk_id_attr
                        AND attr_name="service_enabled"
                        AND fk_id_item=id) AS service_enabled
                FROM ConfigValues, ConfigAttrs, ConfigClasses, ItemLinks
                WHERE id_attr = ConfigValues.fk_id_attr
                AND naming_attr = "yes"
                AND id_class = fk_id_class
                AND config_class = "'.$class.'"
                AND ConfigValues.fk_id_item = ItemLinks.fk_id_item
                AND fk_item_linked2 = '.$host_ID.'
                ORDER BY entryname
             ';
        $services = db_handler($query, "array", "Get ".$class." of host with its service_enabled status");
    }

    # show message if no services found
    if ( count($services) == 0 ){
        if ( $class == "hostgroup_service" ){
            echo '<tr class="color_list1 highlight"><td colspan=9>'.TXT_NOTHING_FOUND_ADVANCED_SERVICES_INHERITED.'</td></tr>';
        }elseif( $class == "service" ){
            echo '<tr class="color_list1 highlight"><td colspan=9>'.TXT_NOTHING_FOUND_SERVICES_DIRECTLY.'</td></tr>';
        }else{
            echo '<tr class="color_list1 highlight"><td colspan=9>'.TXT_NOTHING_FOUND.'</td></tr>';
        }
    }else{
        $count = 1;
        foreach ($services AS $entry){
            $added = '';
            if ( $class == "service" AND in_array($entry["id"], $added_service) ){
                // jQuery color:
                $bgcolor = "ui-state-highlight ui-helper-hidden";
                $added = 'id="added" ';
            }elseif((1 & $count) == 1){
                $bgcolor = "color_list1";
            }else{
                $bgcolor = "color_list2";
            }

            # red background disabled, because we want it only for critical services (name-conflicts)
            /*
            # class for not active services
            if( !empty($entry["service_enabled"]) AND $entry["service_enabled"] === "no"){
                echo '<tr class="ui-state-error highlight" '.$added.'>';
            }else{
                echo '<tr class="'.$bgcolor.' highlight" '.$added.'>';
            }
            */
            echo '<tr class="'.$bgcolor.' highlight" '.$added.'>';

            if ($class == "service"){
                echo '<td class="center">';
                    if ( !empty($entry["service_enabled"]) AND $entry["service_enabled"] === "no"){
                        echo ICON_SERVICE_DISABLED;
                    }else{
                        echo ICON_SERVICE;
                    }
                echo '</td>';
                echo '<td><a href="detail.php?id='.$entry["id"].'" title="'.$entry["entryname"].'">'.$entry["entryname"].'</a></td>';
                echo '<td align="center"><a href="handle_item.php?item=service&id='.$entry["id"].'">'.ICON_EDIT.'</a></td>';
                echo '<td><div align=center><a href="delete_item.php?item=service&ids='.$entry["id"].'&from=modify_item_service.php?id='.$host_ID.'">'.ICON_DELETE.'</a></div></td>';
                echo '<td><div align=center><a class="clone" id="'.$entry["id"].'" href="#">'.ICON_CLONE.'</a></div></td>';
            }elseif( $class == "advanced-service" ){
                echo '<td><a href="detail.php?id='.$entry["id"].'">'.$entry["entryname"].'</a></td>';
            }elseif( $class == "hostgroup_service" ){
                // compare service_name with service_description
                $entry["title"] = $entry["advanced_service_name"];
                if ( !empty($entry["advanced_service_description"]) AND ($entry["advanced_service_name"] != $entry["advanced_service_description"]) ){
                    $entry["advanced_service_name"] = $entry["advanced_service_name"] . ' (' . $entry["advanced_service_description"] . ')';
                    $entry["title"] = $entry["advanced_service_description"];
                }
                echo '<td></td>';
                echo '<td><a href="detail.php?id='.$entry["advanced_service_id"].'" title="'.$entry["title"].'">'.$entry["advanced_service_name"].'</a></td>';
                echo '<td><a href="detail.php?id='.$entry["hostgroup_id"].'">'.$entry["hostgroup_name"].'</a></td>';
            }

            echo "</tr>\n";

            $count++;
        }
    }

echo '</tbody>';
echo '</table>';

echo '</div>';

mysql_close($dbh);


?>
