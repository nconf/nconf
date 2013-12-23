<?php
require_once 'include/head.php';
?>

<!-- jQuery part -->
<script type="text/javascript">
    $(document).ready(function(){
        // show inheritance table when clicking a host
        $('.accordion_title').click(function() {
            var clicked_element = $(this);
            clicked_element.next(".accordion_content").toggle('blind', function(){
                clicked_element.children("span").toggleClass("ui-icon-triangle-1-e ui-icon-triangle-1-s");
            });
            return false;
        }).next().hide();


        // Toggle bold text when checkbox changes
        $("input:checkbox").change( function(){
            $(this).parent("td").nextAll('[name="checkbox_toggle"]').toggleClass("bold red");
        });

        $('[name="checkbox_toggle_all"]').click(function() {
            $(this).closest("thead").next("tbody").find('input:checkbox').each( function(){
                var checkbox = $(this);
                checkbox.attr('checked', !checkbox.attr('checked'));
                checkbox.change();
            })
        });

        // generate the help buttons with help text
        $.nconf_help_admin("direct");

    });
</script>
<!-- END of jQuery part -->

<?php

if (DB_NO_WRITES == 1) {
    message($info, "DB_NO_WRITES = 1: No DB inserts or modifications will be performed");
}

if( ( isset($_POST["HIDDEN_config_class"]) ) AND ($_POST["HIDDEN_config_class"] != "") ){
    $config_class = $_POST["HIDDEN_config_class"]; 
}

if( ( isset($_POST["HIDDEN_modify_id"]) ) AND ($_POST["HIDDEN_modify_id"] != "") ){
    $id = $_POST["HIDDEN_modify_id"]; 
}



// DISABLE SUBMIT IF USER WANTS MODIFY AN ADMIN ACOUNT
if ( (isset($_POST["deny_modification"]) AND ($_POST["deny_modification"] == TRUE))
OR (
    ($_SESSION["group"] != "admin")
    AND ($config_class == "contact")
    AND (
          ( isset($_POST["ID_nc_permission"]) )
          AND ( isset($_POST[$_POST["ID_nc_permission"]]) AND ($_POST[$_POST["ID_nc_permission"]] == "admin")
        )
    )
   )
){
    // Disable the submit button and add message
    include('include/stop_user_modifying_admin_account.php');
}




# Modify = from modify, write to db
# vererben = assign changes to linked services
if ( isset($_POST["modify"]) ){
    // Content of this page
    echo '<div style="width: 510px;">';

    # Implode the splitet fields (exploded in handle_item.php)
    if(  isset($_POST["exploded"]) ){
        prepare_check_command_params($_POST["exploded"]);
    }

    # get old data
    $old_linked_data = get_linked_data($id);



    echo NConf_HTML::page_title($config_class);
    
    // Get name of the modified item
    $item_name  = db_templates("naming_attr", $_POST["HIDDEN_modify_id"]);
    echo '<h2 class="page_action_title">Modify <span class="item_name">'.$item_name.'</span></h2>';


    $id_naming_attr = db_templates("get_naming_attr_from_class", $config_class);
    # Check for existing entry
    if ($config_class == "service"){
        $host_id = db_templates("hostID_of_service", $id);
        $query = 'SELECT ConfigValues.fk_id_item, attr_value
                FROM ConfigValues, ConfigAttrs, ItemLinks, ConfigClasses
                WHERE id_attr = ConfigValues.fk_id_attr
                AND naming_attr = "yes"
                AND ConfigValues.fk_id_item = ItemLinks.fk_id_item
                AND fk_item_linked2 = "'.$host_id.'"
                AND fk_id_class = id_class
                AND config_class = "service"
                AND attr_value = "'.escape_string($_POST[$id_naming_attr]).'"
                AND ConfigValues.fk_id_item <> "'.$id.'"';
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
                        AND attr_value="'.escape_string($_POST[$id_naming_attr]).'"
                        AND fk_id_item <> '.$id ;
    }elseif($config_class == "checkcommand" OR $config_class == "misccommand"){
        $query = 'SELECT attr_value, fk_id_item
                FROM ConfigItems, ConfigValues, ConfigAttrs, ConfigClasses
                WHERE id_item = fk_id_item
                AND id_attr = fk_id_attr
                AND naming_attr = "yes"
                AND ConfigItems.fk_id_class = id_class
                AND (
                    config_class = "checkcommand"
                    OR config_class = "misccommand"
                    )
                AND attr_value="'.escape_string($_POST[$id_naming_attr]).'"
                AND fk_id_item <> '.$id ;
    }else{
        $query = 'SELECT attr_value, fk_id_item
                FROM ConfigValues
                WHERE fk_id_attr='.$id_naming_attr.'
                AND attr_value = "'.escape_string($_POST[$id_naming_attr]).'"
                AND fk_id_item <> '.$id ;
    }
    $result = db_handler($query, "result", "does entry already exist");
            
    # Entry exists ?
    if ( mysql_num_rows($result) ){
        NConf_DEBUG::set('An item with the name &quot;'.$_POST[$id_naming_attr].'&quot; already exists!', 'ERROR');
        NConf_DEBUG::set('For its details click the link below or go back:', 'ERROR');

         $list_items = '';
        while($entry = mysql_fetch_assoc($result)){
            $list_items .=  '<li><a href="detail.php?id='.$entry["fk_id_item"].'">'.$entry["attr_value"].'</a></li>';
        }
        $list = '<ul>'.$list_items.'</ul>';
        NConf_DEBUG::set($list, 'ERROR');
    }else{
        #entry not existing, lets try to modify:

        if ($config_class == "host") {
            # Vererben ?
            $vererben1_result = db_templates("vererben", $id);
            while($row = mysql_fetch_assoc($vererben1_result)){
                $vererben1[$row["item_id"]] = $row["attr_name"];
            }
        }


        

        ?>
        <table>
            <tr>
                <td>
        <?php

        # Check mandatory fields
        NConf_DEBUG::open_group("check mandatory");
        $m_array = db_templates("mandatory", $config_class);

        # special case for
        # - class: service
        # - attributes: checkcommand and hostname
        # they will not be editable on "modify" so do not make theme mandatory
        if ($config_class == "service"){
            # get id of attr check_command and host_name
            $check_command_attr_id = db_templates("get_attr_id", $config_class, "check_command");
            $host_name_attr_id = db_templates("get_attr_id", $config_class, "host_name");
            if (isset( $m_array[$check_command_attr_id]))   unset($m_array[$check_command_attr_id]);
            if (isset( $m_array[$host_name_attr_id]))       unset($m_array[$host_name_attr_id]);
            NConf_DEBUG::set($m_array, 'DEBUG', "after special case for service");
        }elseif ($config_class == "advanced-service"){
            # - class: advanced-service
            $check_command_attr_id = db_templates("get_attr_id", $config_class, "check_command");
            if (isset( $m_array[$check_command_attr_id]))   unset($m_array[$check_command_attr_id]);
            NConf_DEBUG::set($m_array, 'DEBUG', "after special case for advanced-service");
        }
        $write2db = check_mandatory($m_array,$_POST);
        NConf_DEBUG::close_group();


        # check oncall groups when class is host, service or advanced-service
	    if ($config_class    == "host"
	    	OR $config_class == "service"
	    	OR $config_class == "advanced-service"
	    ){
            # if failed do not allow write2db
            if ( oncall_check() == FALSE ){
                $write2db = 'no';
            }
        }


        if ($write2db == "yes" AND !NConf_DEBUG::status('ERROR') ){
            ################
            #### write to db
            ################

            # get class id
            $class_id = db_templates("get_id_of_class", $config_class);

            # history entry status for "edited"
            $edited = FALSE;

            $handle_action = 'modify';
            $items2write = $_POST;
            require_once('include/items_write2db.php'); // needs $items2write

            if (DB_NO_WRITES != 1) {
                # history entry "edited"
                if ($edited){
                    history_add("edited", $config_class, $_POST[$id_naming_attr], $id);
                }

                // this info has a newline (<br>) because other messages are in front
                if ( !NConf_DEBUG::status("ERROR") ){
                    NConf_DEBUG::set( '<br>Successfully modified <b>'.escape_string($_POST[$id_naming_attr]).'</b>', 'INFO');
                }

                // show infos
                echo NConf_DEBUG::show_debug('INFO', TRUE);
                echo '<br>';

                // inheritance from host to services
                if ($config_class == "host") {
                    $name = db_templates("naming_attr", $id);
                    # Vererben ?
                    $vererben2_result = db_templates("vererben", $id);
                    while($row = mysql_fetch_assoc($vererben2_result)){
                        $vererben2[$row["item_id"]] = $row["attr_name"];
                    }
         
                    # Ask for make the changes also to the linked services
                    if ( (!empty($vererben1) AND !empty($vererben2) ) AND ($vererben1 !== $vererben2) ) {
                        # get preview of possible attributes for inheritance from host to its services
                        # user can choose which attributes should inherit (apply) or not
                        $preview[$name] = inheritance_HostToService($id, "preview");
                        # print in info box
                        if ( !empty($preview) ){
                            echo '<form name="vererben" action="'.$_SERVER["PHP_SELF"].'" method="post">';
                                $update_button = '<input name="HIDDEN_config_class" type="hidden" value="'.$config_class.'">';
                                $update_button .= '<input name="HIDDEN_modify_id" type="hidden" value="'.$_POST["HIDDEN_modify_id"].'">';
                                $update_button .= '<br><div id=buttons>';
                                $update_button .= '<input type="Submit" value="yes" name="vererben" align="middle">';
                                $update_button .= '&nbsp;<input type=button name="no" onClick="window.location.href=\''.$_SESSION["go_back_page_ok"].'\'" value="no">';
                                $update_button .= '</div>';
                                //message ($info, TXT_UPDATE_SERVICES.'<br>'.$update_button);

                                echo NConf_HTML::limit_space(
                                    NConf_HTML::show_highlight('Attention', TXT_UPDATE_SERVICES.'<br>'.$update_button), 'width="310"'
                                );

                                echo "<br>";

                                # show inheritance table and help box
                                echo '<div style="float: left; margin-right: 5px;">';
                                    echo NConf_HTML::title('Show detailed inheritance information');
                                    //$title .= NConf_HTML::title('Detailed host / service diff for '.escape_string($_POST[$id_naming_attr]) );
                                echo '</div>';
                                echo '<div name="help_inheritance"></div>';
                                echo '<div style="clear: both"></div>';
                                // place for help box (only to get distance from top)
                                echo '<div id="page_content"></div>';

                                $inheritance_details = '';
                                foreach ($preview AS $server_name => $service_table){
                                    $title = '<span class="ui-icon ui-icon-triangle-1-e"></span><a href="#">'.$server_name.'</a>';
                                    $inheritance_details .= NConf_HTML::title($title, 3, 'class="accordion_title ui-accordion-header ui-helper-reset ui-state-default"');
                                    $inheritance_details .= '<div class="accordion_content">'.$service_table.'</div>';
                                }

                                # print tables
                                echo '<div class="ui-accordion ui-widget ui-helper-reset ui-accordion-icons">';
                                    echo $inheritance_details;
                                echo '</div>';

                            echo '</form>';
                        }

                    }
                }



            }else{
                message ($info, 'Modify '.$config_class.' should work fine.');
            }
            
            # Delete session
            if (isset($_SESSION["cache"]["handle"])) unset($_SESSION["cache"]["handle"]);

            if ( isset($_SESSION["go_back_page_ok"]) AND !isset($update_button) ){
                // Go to next page without pressing the button
                echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$_SESSION["go_back_page_ok"].'">';
                NConf_DEBUG::set('<a href="'.$_SESSION["go_back_page_ok"].'"> [ this page ] (in '.REDIRECTING_DELAY.' seconds)</a>', 'INFO', "<br>redirecting to");
            }
        }
        # end of write2db

        echo '
                    </td>
                </tr>
            </table>';

    } # END Entry exists ?

    # error handling
    if ( NConf_DEBUG::status('ERROR') ) {
        echo NConf_HTML::limit_space(NConf_HTML::show_error());
        echo "<br><br>";
        echo NConf_HTML::back_button($_SESSION["go_back_page"]);

        // use cache
        $_SESSION["cache"]["use_cache"] = TRUE;
        foreach ($_POST as $key => $value) {
            $_SESSION["cache"]["handle"][$key] = $value;
        }
    }else{
        if (isset($_SESSION["cache"]["handle"])) unset($_SESSION["cache"]["handle"]);
    }

    echo '</div>';


}elseif( isset($_POST["vererben"]) ){
    # Handling inheritance to services
    inheritance_HostToService($id, 'apply_inheritance');

    echo NConf_DEBUG::show_debug('INFO', TRUE);

    if ( isset($_SESSION["go_back_page"]) ){
        // Go to next page without pressing the button
        echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$_SESSION["go_back_page_ok"].'">';
        NConf_DEBUG::set('<a href="'.$_SESSION["go_back_page_ok"].'"> [ this page ] </a>', 'INFO', "<br>redirecting to");
    }


}



?>
<!-- Help text content -->
<div id="help_text" style="display: none">
    <div id="help_inheritance" title="host / service inheritance">
        <p>The host / service diff table gives you an overview of attributes being applied from a host onto its services.
        </p>
        <p>
            <strong>update</strong>
            <br>Service attributes, whose value differs from the host value, will be overwritten by default.
            <br>If you don't want to overwrite an attribute on the service side, disable the 'update' checkbox.
        </p>
        <p>
            <strong>service value</strong>
            <br>This will show you the current value on the service side. The red color indicates that the original value will be overwritten.
        </p>
        <p>
            <strong>host value</strong>
            <br>This is the host value which can be inherited to the service. The red color indicates that the host value is not being applied.
        </p>
        <p>
            <i><b>Bold</b> text shows which value is being applied to the service.</i>
        </p>
        </p>
    </div>
</div>

<?php



mysql_close($dbh);
require_once 'include/foot.php';
?>
