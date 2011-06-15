<?php
require_once 'include/head.php';


# Check mandatory fields
$mandatory = array("item" => "Missing GET-parameter \"item\" (item class)", "id_str" => "Missing GET-parameter \"id_str\" (item name)");

$mandatory_check = check_mandatory($mandatory,$_GET);

if ($mandatory_check == "yes"){
    # Get naming attr of class
    $naming_attr = db_templates("get_naming_attr_from_class", $_GET["item"]);
    if (!$naming_attr){
        message($error, 'Could not find class "'.$_GET["item"].'".');
    }else{
        # Lookup ID of item
        if ( !empty($_GET["id_str"]) ){
            # services need other lookup
            if ($_GET["item"] == "service"){
                $id = db_templates("get_id_of_hostname_service", $naming_attr, $_GET["id_str"]);
            }else{
                $id = db_templates("get_id_of_item", $naming_attr, $_GET["id_str"]);
            }
        }
        
        if (!$id){
            message($error, 'Could not find any '.$_GET["item"].' item named "'.$_GET["id_str"].'".');
        }else{

            /* handle of other destination pages is disabled for security reason
            # handle dest_url
            if ( !empty($_GET["dest_url"]) ){
                $dest_url = $_GET["dest_url"];
            }else{
                $dest_url = "detail.php";
            }
            */
            # Destination site should alway be detail.php
            $dest_url = "detail.php";

            # Go to login page, and redirect it to called page
            $url = $dest_url.'?id='.$id;
            # Redirect to dest_url page
            echo '<meta http-equiv="refresh" content="0; url='.$url.'">';
            message($info, '<b>redirecting to:</b> <a href="'.$url.'">'.$url.'</a>');
        }
    }
}
# print error message
echo NConf_DEBUG::show_debug('ERROR', TRUE);

# show info
echo NConf_DEBUG::show_debug('INFO', TRUE);

require_once 'include/foot.php';

?>
