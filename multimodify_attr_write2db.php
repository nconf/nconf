<?php
require_once 'include/head.php';
?>

<!-- jQuery part -->
<script type="text/javascript">
    $(document).ready(function(){
        // show inheritance table when clicking a host
        $(".accordion_title").live("click hover", function(event) {
            $(this).nconf_accordion_list(event);
            return false;
        });



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

$service_name_changed = FALSE;

if (DB_NO_WRITES == 1) {
    message($info, "DB_NO_WRITES = 1: No DB inserts or modifications will be performed");
}

# Set info_summary, feedback of modifications
$info_summary["ok"] = array();
$info_summary["failed"] = array();
$info_summary["ignored"] = array();
$preview = array();

# name of selected attribute
if ( !empty($_POST["HIDDEN_selected_attr"]) ){
    $HIDDEN_selected_attr = $_POST["HIDDEN_selected_attr"];
}
if( ( isset($_POST["HIDDEN_config_class"]) ) AND ($_POST["HIDDEN_config_class"] != "") ){
    $config_class = $_POST["HIDDEN_config_class"]; 
}

$array_ids = explode(",", $_POST["HIDDEN_modify_ids"]);

# predefine ask vererben variable:
$ask_vererben = 0;



# ONCALL CHECK
# check oncall groups when try modifying it and class is host, service or advanced-service
# but not if the replace mode is "add"
if ( isset($_POST["multimodify"])
	AND ( $config_class    == "host"
		  OR $config_class == "service"
		  OR $config_class == "advanced-service"
	)
	AND	!( !empty($_POST["replace_mode"])
	       AND $_POST["replace_mode"] == "add"
    )
){
    # get id of contact_group attr
    $contact_group_id = db_templates("get_attr_id", $config_class, "contact_groups");
    if ( isset($_POST[$contact_group_id]) ){
        # if failed do not allow write2db
        $oncall_check = oncall_check();
    }
}

# Check mandatory fields
while ( $attr = each($_POST) ){
    if ( is_int($attr["key"]) ){
        # Check mandatory fields
        $m_array = db_templates("mandatory", $config_class, $attr["key"]);
        if ( check_mandatory($m_array,$_POST) == "no"){
            $write2db = "no";
        }
    }
}

# Give error message if oncall or mandatory check fails
if ( ( isset($oncall_check) AND $oncall_check === FALSE )
  OR ( isset($write2db) AND $write2db == "no")
){
    $content  = NConf_HTML::show_error();
    $content .= "<br><br>";
    $content .= NConf_HTML::back_button($_SESSION["go_back_page"]);

    echo NConf_HTML::limit_space($content);
	
	// Cache
    $_SESSION["cache"]["use_cache"] = TRUE;
    foreach ($_POST as $key => $value) {
    	$_SESSION["cache"]["handle"][$key] = $value;
    }
                

    mysql_close($dbh);
    require_once 'include/foot.php';

    exit;
}
			
# clean existing cache
if (isset($_SESSION["cache"]["handle"])) unset($_SESSION["cache"]["handle"]);



# save attribute to each item
foreach ($array_ids as $id){

    # Reset pointer of $_POST
    reset($_POST);

    # Title of id
    $name = db_templates("naming_attr", $id);
    if ($config_class == "service"){
        # get host name of service
        $hostID   = db_templates("hostID_of_service", $id);
        $hostname = db_templates("naming_attr", $hostID);
        $name = $hostname.":".$name;
    }


    # DISABLE SUBMIT IF USER WANTS MODIFY AN ADMIN ACOUNT
    if ( (isset($_POST["deny_modification"]) AND ($_POST["deny_modification"] == TRUE))
    OR (
        ($_SESSION["group"] != "admin")
        AND ($config_class == "contact")
        AND ( isset($_POST[$_POST["ID_nc_permission"]]) AND ($_POST[$_POST["ID_nc_permission"]] == "admin") )
       )
    ){
        # Disable the submit button and add message
        include('include/stop_user_modifying_admin_account.php');
    }

    # Modify = from multimodify, write to db
    # vererben = assign changes to linked services
    if ( isset($_POST["multimodify"]) ){


        # Implode the splitet fields (exploded in handle_item.php)
        if(  isset($_POST["exploded"]) ){
            prepare_check_command_params($_POST["exploded"]);
        }

        # get old data
        $old_linked_data = get_linked_data($id);


        # Check for existing entry
        $query = 'SELECT id_attr
                    FROM ConfigAttrs,ConfigClasses
                    WHERE naming_attr="yes"
                        AND id_class=fk_id_class
                        AND config_class="'.$config_class.'"
                 ';

        $id_naming_attr = db_handler($query, "getOne", "naming_attr ID:");

        if ( isset($_POST[$id_naming_attr]) AND $config_class != "service"){
            # naming attr not allowed
            message($error, "Naming attribute cannot be modified with multiple items");

        }else{
            # entry is not a naming attr, lets try to modify:
            
            if ($config_class == "host") {
                # Vererben ?
                if ( isset($vererben1) ) unset($vererben1);
                $vererben1_result = db_templates("vererben", $id);
                while($row = mysql_fetch_assoc($vererben1_result)){
                    $vererben1[$row["item_id"]] = $row["attr_name"];
                }
            }

            ################
            #### write to db
            ################

            # get class id
            $class_id = db_templates("get_id_of_class", $config_class);

            # history entry status for "edited"
            $edited = FALSE;

            $handle_action = 'multimodify';
            $items2write = $_POST;
            require('include/items_write2db.php'); // needs $items2write


            # history entry "edited"
            if ($edited){
                history_add("edited", $config_class, $name, $id);
            }


            if ($config_class == "host") {

                # Vererben ?
                if ( isset($vererben2) ) unset($vererben2);
                $vererben2_result = db_templates("vererben", $id);
                while($row = mysql_fetch_assoc($vererben2_result)){
                    $vererben2[$row["item_id"]] = $row["attr_name"];
                }
                if ($vererben1 !== $vererben2) {
                    $ask_vererben = 1;
                }
            }

            ###
            # end of write2db
            ###

            if ( NConf_DEBUG::status('ERROR') ) {
                echo NConf_DEBUG::show_debug('ERROR', TRUE);
                echo "<br><br>";
                echo NConf_HTML::back_button($_SESSION["go_back_page"]);

                // Cache
                $_SESSION["cache"]["use_cache"] = TRUE;
                foreach ($_POST as $key => $value) {
                    $_SESSION["cache"]["handle"][$key] = $value;
                }
            }else{
                if (isset($_SESSION["cache"]["handle"])) unset($_SESSION["cache"]["handle"]);
                
                # get inheritance table for this host
                $preview[$name] = inheritance_HostToService($id, "preview");
            }


        } // END Entry exists ?


    }elseif( isset($_POST["vererben"]) ){

        # Handling inheritance to services
        inheritance_HostToService($id, 'apply_inheritance');


        # Successfully updated all linked services
        $info_summary["ok"][] = $name;

    }




} //for each ID

$_SESSION["go_back_page"] = str_replace("&goto=multimodify", "", $_SESSION["go_back_page"]);


// Content of this page
echo NConf_HTML::page_title($config_class, '');

echo '<div style="width: 510px;">';

#
# Feedback of modifications
#

# if failed or ignored, do not redirect automatic
if ( !empty($info_summary["failed"]) ){
    echo '<div id=buttons>
        <input type=button name="next" onClick="window.location.href=\''.$_SESSION["go_back_page_ok"].'\'" value="Finish">
      </div>
    ';
}

# failed
if ( !empty($info_summary["failed"]) ){
    echo "<h2>Failed to modify $HIDDEN_selected_attr on $config_class:</h2><ul>";
    foreach ($info_summary["failed"] as $item){
        echo "<li>$item</li>";
    }
    echo "</ul>";
}
# ignored
if ( !empty($info_summary["ignored"]) ){
    echo "<h2>Item(s) skipped:</h2>";
    echo 'No changes necessary for the following item(s)';
    echo "<ul>";
    foreach ($info_summary["ignored"] as $item){
        echo "<li>$item</li>";
    }
    echo "</ul>";
}

# ok
if ( !empty($info_summary["ok"]) ){
    if ( isset($_POST["vererben"]) ){
        echo "<h2>Successfully inherited attribute &quot;$HIDDEN_selected_attr&quot; to selected services on $config_class(s):</h2>";
    }else{
        echo NConf_HTML::text("Successfully modified attribute &quot;$HIDDEN_selected_attr&quot; of $config_class(s):", FALSE);
        
    }
    echo "<ul>";
    foreach ($info_summary["ok"] as $item){
        echo "<li>$item</li>";
    }
    echo "</ul>";

}


# show inheritance
if ($config_class == "host") {

    # Ask for make the changes also to the linked services
    if ($ask_vererben) {
        echo '<form name="vererben" action="'.$_SERVER["PHP_SELF"].'" method="post">';
            $update_button = '<input name="HIDDEN_modify_ids" type="hidden" value="'.$_POST["HIDDEN_modify_ids"].'">';
            $update_button .= '<input name="HIDDEN_config_class" type="hidden" value="'.$_POST["HIDDEN_config_class"].'">';
            $update_button .= '<input name="HIDDEN_selected_attr" type="hidden" value="'.$HIDDEN_selected_attr.'">';
            $update_button .= '<br><div id=buttons>';
            $update_button .= '<input type="Submit" value="yes" name="vererben" align="middle">';
            $update_button .= '&nbsp;<input type=button name="no" onClick="window.location.href=\''.$_SESSION["go_back_page_ok"].'\'" value="no">';
            $update_button .= '</div>';

        echo NConf_HTML::limit_space(
            NConf_HTML::show_highlight('Attention', TXT_UPDATE_SERVICES.'<br>'.$update_button)
        );

        echo "<br>";

        # show inheritance tables
        echo '<div style="float: left; margin-right: 5px;">';
            echo NConf_HTML::title('Show detailed inheritance information');
        echo '</div>';
        echo '<div name="help_inheritance"></div>';
        echo '<div style="clear: both"></div>';
        echo '<div id="page_content"></div>';
        $inheritance_details = '';
        foreach ($preview AS $server_name => $service_table){
            $title = '<span class="ui-icon ui-icon-triangle-1-e"></span><a href="#">'.$server_name.'</a>';
            $inheritance_details .= NConf_HTML::title($title, 3, 'class="accordion_title ui-accordion-header ui-helper-reset ui-state-default"');
            $inheritance_details .= '<div class="accordion_content" style="display:none;">'.$service_table.'</div>';
        }
        
        # print tables
        echo '<div class="ui-accordion ui-widget ui-helper-reset ui-accordion-icons">';
            echo $inheritance_details;
        echo '</div>';

        echo '</form>';

    }
}




# Finish button if no action was failed and ask_vererben is not true
if ( !$ask_vererben AND empty($info_summary["failed"]) AND ( !empty($info_summary["ignored"])  OR  !empty($info_summary["ok"]) )  ){
    // Finish button
    echo '<br><br>';
    echo '<button onClick="window.location.href=\''.$_SESSION["go_back_page_ok"].'\'">Finish</button>';
}

echo '</div>';




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
