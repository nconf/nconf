<?php
include_once('main.php');
echo '<div class="box_content" width="100%">';
echo '<table class="simpletable" border=0 frame=box rules=none cellspacing=2 cellpadding=1 width="100%">';

/*
$notification_period_attribute_id = db_templates("get_attr_id", "host", "notification_period");
$contact_groups_attribute_id = db_templates("get_attr_id", "host", "contact_groups");
*/

if ($_GET["type"] == "basic"){

    # Titel
    echo '<tr>
            <td>
                <b>timeperiod details</b>
            </td>
        </tr>
    ';

    # get basic entries
    $query = 'SELECT ConfigAttrs.friendly_name,attr_value, ConfigAttrs.datatype
                            FROM ConfigAttrs,ConfigValues,ConfigItems
                            WHERE id_attr=fk_id_attr
                            AND id_item=fk_id_item
                            AND ConfigAttrs.visible="yes"
                            AND id_item='.$_GET["id"].'
                            ORDER BY ConfigAttrs.ordering';

    $result = db_handler($query, "result", "get basic entries");
    if ($result){
        while($entry = mysql_fetch_assoc($result)){
            echo '<tr>';
                echo '<td style="vertical-align:text-top" width="150" class="color_list2">&nbsp;'.$entry["friendly_name"].':&nbsp;</td>';
                if ( $entry["datatype"] == "password" ){
                    $password = show_password($entry["attr_value"]);
                    // show password
                    echo '<td class="color_list1 highlight">&nbsp;'.$password.'</td>';

                }else{
                    // Link handling
                    if ( preg_match( '/^http*/', $entry["attr_value"]) ){
                        # Link
                        echo '<td class="color_list1 highlight">&nbsp;<a target="_blank"href="'.$entry["attr_value"].'">'.$entry["attr_value"].'</a></td>';
                    }else{
                        # normal text
                        echo '<td class="color_list1 highlight" style="word-break:break-all;word-wrap:break-word">&nbsp;'.$entry["attr_value"].'</td>';
                    }
                }
            echo '</tr>';
        }
    }


}elseif ($_GET["type"] == "contacts"){

    # Titel
    echo '<tr>
            <td>
                <b>contacts</b>
            </td>
        </tr>
    ';

    $contacts = db_templates("get_linked_item_2", $_GET["id"], "members");
    foreach ($contacts as $contact){
        echo '<tr>';
            echo '<td class="color_list1 highlight" style="word-break:break-all;word-wrap:break-word">&nbsp;'.$contact["attr_value"].'</td>';
        echo '</tr>';
    }
    
}


echo '</table></div>';

?>
