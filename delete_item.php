<?php
require_once 'include/head.php';


if (DB_NO_WRITES == 1) {
    message($info, TXT_DB_NO_WRITES);
}

if ( !empty($_REQUEST["class"]) ){
    $class = $_REQUEST["class"];
}elseif( !empty($_REQUEST["item"]) ){
    $class = $_REQUEST["item"];
}

# delete item count
$deleted_items = 0;

# Title
echo NConf_HTML::page_title('', 'Delete Items');

if(  ( ( isset($_POST["delete"]) ) AND ($_POST["delete"] == "yes") ) AND
     ( ( isset($_POST["ids"]) ) AND ($_POST["ids"] != "") )
  ){

    # make ids as array
    if ( !empty($_POST["ids"]) ){
        $ids = explode(",", $_POST["ids"]);
    }

    foreach ($ids as $id){

        $item_name  = db_templates("naming_attr", $id);
        $item_class = db_templates("class_name", $id);

        # Delete Services if item = host
        if ($item_class == "host"){
            # when deleting a host, go back to host overview (the session var could link to the service list)
            $_SESSION["after_delete_page"] = 'overview.php?class=host';

            # Select all linked services
            $result = db_templates("get_services_from_host_id", $id);
            if ( !empty($result) ){
                if ( count($result) > 0 ){
                    foreach ($result AS $service_ID => $service_name){
                        $query = 'DELETE FROM ConfigItems
                                    WHERE id_item='.$service_ID
                                 ;
                        if (DB_NO_WRITES != 1) {
                            $result_delete = db_handler($query, "delete", "Delete entry");
                            if ( $result_delete ){
                                message ($debug, '', "ok");
                                history_add("removed", "service", $service_name, $id);
                            }else{
                                message ($debug, '', "failed");
                            }
                        }
                    }
                }


            }else{
                message ($debug, '', "failed");
            }    

        } //END $item_class == "host"


        # Services: Bevore deleting the entry, check the host ID, for later history entry
        if ($class == "service"){
            $Host_ID = db_templates("hostID_of_service", $id);
        }

        // Delete entry
        $query = 'DELETE FROM ConfigItems
                    WHERE id_item='.$id;

        $result = db_handler($query, "result", "Delete entry");
        if ( $result ){
            # increase deleted items
            if (mysql_affected_rows() > 0){
                $deleted_items++;
            }

            message ($debug, '', "ok");

            # Special service handling
            if ( ($class == "service") AND !empty($Host_ID) ){
                # Enter also the Host_ID of the deleted service into the History table
                history_add("removed", $class, $item_name, $Host_ID);
            }else{
                # Enter normal deletion, which object is deleted, without a "parent / linked" id
                history_add("removed", $class, $item_name );
            }

            // Go to next page without pressing the button (also have a look if class is not host, otherwise go back to overview )
            if ( !empty($_POST["from"]) AND $class != "host" ){
                $url = $_POST["from"];
            }else{
                $url = $_SESSION["after_delete_page"];
            }
                
        }elseif (DB_NO_WRITES != 1){
            message($error, 'Error deleting '.$id.':'.$query);
        }
    

    } // foreach

    # Feedback of delete action
    if ($deleted_items > 0){
        //echo TXT_DELETED.' '.$deleted_items.' item(s)<br>';
        NConf_DEBUG::set(TXT_DELETED.' '.$deleted_items.' item(s)', 'INFO');
    }else{
        NConf_DEBUG::set('No item(s) deleted', 'INFO');
    }

    echo NConf_DEBUG::show_debug('INFO', TRUE);

    if ( !NConf_DEBUG::status('ERROR') ){
        echo '<meta http-equiv="refresh" content="'.(REDIRECTING_DELAY+1).'; url='.$url.'">';
        NConf_DEBUG::set('<a href="'.$url.'"> [ this page ] </a>', 'INFO', "<br>redirecting to");
    }


}elseif( !empty($_GET["ids"])
    OR (
        ( isset($_GET["type"]) AND $_GET["type"] == "multidelete"  )
        AND !empty($_POST["advanced_items"])
       )
    ){

    // Go to next page without pressing the button (also have a look if delete comes from detailview
    if ( isset($_GET["from"]) AND ($_GET["from"] != "") ){
        $url = $_GET["from"];
    }elseif ( !empty($_SESSION["after_delete_page"]) ){
        $url = $_SESSION["after_delete_page"];
    }

    # make ids as array
    if ( !empty($_GET["ids"]) ){
        $ids = explode(",", $_GET["ids"]);
    }elseif (!empty($_POST["advanced_items"]) ){
        $ids = $_POST["advanced_items"];
    }
    # list of ids to send with confirmation of deletion
    $id_list = implode(",", $ids);

    foreach ($ids as $id ){
        // CHECK IF A USER TRIES TO DELETE AN ADMIN
        if ( !empty($id) ) $nc_id = $id;
        if ($_SESSION["group"] != "admin" AND $class == "contact"){
            $nc_permission_query = 'SELECT attr_value FROM ConfigValues, ConfigAttrs WHERE fk_id_attr=id_attr AND fk_id_item="'.$nc_id.'" AND attr_name = "nc_permission"';
            $nc_permission = db_handler($nc_permission_query, "getOne", "Look for nc_permissions of user");
            if ($nc_permission == "admin"){
                // Disable the submit button and add message
                include('include/stop_user_modifying_admin_account.php');
            }
            
        }

    }


    # Modify question when deleting services
    if ($class == "service"){
        $content = "Do you really want to delete these services? The hosts will not be deleted.";
    }else{
        $content = "Do you really want to delete these item(s) ?";
    }

    // Buttons
    $content_button = '
        <form name="delete_item" action="delete_item.php?item='.$class.'" method="post">
            <input type="hidden" name="ids" value="'.$id_list.'">
            <input type="hidden" name="from" value="'.$url.'">
            <input type="hidden" name="delete" value="yes">
            <div id=buttons><br>';

        $content_button .= '<input type="Submit" value="Delete" name="submit" align="middle" ';
        if(DB_NO_WRITES == 1) $content_button .=  'disabled';
        $content_button .= '>&nbsp';

        $content_button .= '<input type=button onClick="window.location.href=\''.$_SESSION["go_back_page"].'\'" value="Back">';
    $content_button .= '</form>';

    echo NConf_HTML::limit_space(
        NConf_HTML::show_highlight('WARNING', $content.$content_button)
    );


    if(DB_NO_WRITES == 1) echo NConf_DEBUG::show_debug('INFO', TRUE);

    foreach ($ids as $id) {

        # Delete Services if item = host
        $item_class = db_templates("class_name", $id);

        if ($item_class == "host"){

            # WARN services linked to host
            $get_srv_query = 'SELECT attr_value, ConfigValues.fk_id_item AS item_id,"service" AS config_class,
                            "service name" AS friendly_name
                            FROM ConfigValues, ConfigAttrs, ConfigClasses, ItemLinks
                            WHERE id_attr = ConfigValues.fk_id_attr
                            AND naming_attr = "yes"
                            AND id_class = fk_id_class
                            AND config_class = "service"
                            AND ConfigValues.fk_id_item = ItemLinks.fk_id_item
                            AND fk_item_linked2 = '.$id.'
                            ORDER BY attr_value';

            $result = db_handler($get_srv_query, "result", "get services linked to host");
            # prepare services
            $services = array();
            while($entry = mysql_fetch_assoc($result)){
                $services[] = array(
                            "id" => $entry["item_id"],
                            "name" => $entry["attr_value"],
                            "type" => "service" ) ;

            }


        }

        # Lookup class and name of item
        $item_class = db_templates("class_name", $id);
        $item_name  = db_templates("naming_attr", $id);

        # on service items we want to group it by their associated hostname
        if ($item_class == "service"){
            # service deletions

            # get host name of service
            $hostID   = db_templates("hostID_of_service", $id);
            $hostname = db_templates("naming_attr", $hostID);
            # create hostname entrie
            if ( !isset($entries[$hostname]) ){
                $entries[$hostname] = array(
                            "id" => $hostID,
                            "name" => $hostname,
                            "title" => "",
                            "status" => "open" );
            }

            # add the service to the host branch
            $services = array(
                        "id" => $id,
                        "name" => $item_name,
                        "type" => "service" ) ;
            $entries[$hostname]["childs"][] = $services;
        }else{
            # any other deletion:
            if (!empty($services) ){
                # for host trees with services (class = host)
                $entries[] = array(
                                "id" => $id,
                                "name" => $item_name,
                                "title" => $item_class.": ",
                                "status" => "open",
                                "childs" => $services );
            }else{
                # for single (any other) classes
                $entries[] = array(
                                "id" => $id,
                                "name" => $item_name,
                                "title" => $item_class.": ",
                                "type" => $item_class);
            }
        }

    }//foreach

    # service deletion: sort the array on hostnames
    ksort($entries);

    # Modify text on top tree element (root) when deleting services
    if ($item_class == "service"){
        $items = "services";
    }else{
        $items = "items";
    }
    $tree_view = array( "root" => array(
                        "id" => "root",
                        "status" => 'open',
                        "name" => "The following $items will be deleted",
                        "type" => "parent",
                        "childs" => $entries) );

    # display tree
    echo '<br><div>';
        displayTree_list($tree_view);
    echo '</div>';



}else{
    NConf_DEBUG::set("No item to delete", "ERROR");
}


if ( NConf_DEBUG::status('ERROR') ) {
    echo NConf_HTML::limit_space( NConf_HTML::show_error() );
    echo "<br><br>";
    echo NConf_HTML::back_button($_SESSION["go_back_page"]);
}



mysql_close($dbh);

require_once 'include/foot.php';
?>
