<?php
require_once 'include/head.php';

set_page();

?>
<!-- jQuery part -->
<script type="text/javascript">
    $(document).ready(function(){
        $("#loading").hide();

        $("#check_command_select").change(function() {
            $("form").find('input[type=submit]').prepend('<input type="hidden" name="check_command_changed" value="true">');
            $("form").find('input[type=submit]').click();
        });

        // mode for multimodify
        $("input[name='replace_mode']").change(function() {
            if ($("input[name='replace_mode']:checked").val() == 'replace'){
                $("#info_box").hide("blind", "slow", function(){
                    $("#mode_add_title").hide();
                    $("#mode_add_title").prev().show();
                    $("#info_box").show("blind", "slow");
                });
            }else if ($("input[name='replace_mode']:checked").val() == 'add'){
                $("#info_box").hide("blind", "slow", function(){
                    $("#mode_add_title").prev().hide();
                    $("#mode_add_title").show();
                    $("#info_box").show("blind", "slow");
                });
            }
        });
    
        /* resizable feature */    
        $('div.multipleSelectBoxControl.ui-nconf-content').each(function () {
            $(this).resizable({
                handles: "e",
                minWidth: 530
            });
        });
    });

</script>

<?php


// Autocomplete
if ( defined('AUTO_COMPLETE_PIKETT') AND AUTO_COMPLETE_PIKETT != "0" ){
    //Get Pikett email and pager list
    include('include/modules/sunrise/autocomplete/pikett_users.php');
    // Create pikett / pager list for autocomplete
    $prepare_status = js_Autocomplete_prepare('emaillist', $emaillist);
    $prepare_status = js_Autocomplete_prepare('pagerlist', $pagerlist);
}


###
# Use Session cache only if back button was clicked
###
# otherwise delete cache:
if ( !isset($_POST["back"]) AND !isset($_SESSION["cache"]["use_cache"]) ){
    if ( isset($_SESSION["cache"]["handle"]) ) unset($_SESSION["cache"]["handle"]);
    if ( isset($_SERVER["HTTP_REFERER"]) ) $_SESSION["go_back_page_ok"] = $_SERVER["HTTP_REFERER"];
    // remove use_cache
}
if ( isset($_SESSION["cache"]["use_cache"]) ){
    unset($_SESSION["cache"]["use_cache"]);
}


###
# coming from modify item service
###
# Check for coming from "modify item service" (<edit>) and performing an "clone service"
# if true, we have to change the go back page ok (otherwise the session check will fail)
if ( !empty($_SESSION["go_back_page_ok"]) && (strpos($_SESSION["go_back_page_ok"], "cloneONhost") !== FALSE) ){
    $hostID   = db_templates("hostID_of_service", $_GET["id"]);
    $_SESSION["go_back_page_ok"] = 'modify_item_service.php?item=service&id='.$hostID;
}

# The replace mode is only for multimodify so disable it generally
$replace_mode = 0;


###
# Handle add / modify / multimodify
###
if ( !empty($_GET["id"]) ){
    # modify item

    NConf_DEBUG::set("", 'DEBUG', "collected data of selected item", TRUE);


    if(isset($_GET["xmode"])){

        # Special mode to allow ordinary users to change on-call settings
        # get id_item based on contact name
        $query = 'SELECT fk_id_item FROM ConfigValues,ConfigAttrs,ConfigClasses 
                    WHERE id_attr = fk_id_attr 
                        AND naming_attr = "yes" 
                        AND id_class = fk_id_class 
                        AND config_class = "contact" 
                        AND attr_value = "'.$_GET["xmode"].'"';

        $qres = mysql_query($query);
        $entry = mysql_fetch_assoc($qres);

        $_GET["id"] = $entry["fk_id_item"];
        $item_class = "contact";

    }else{
        # get $item_class
        $item_class = db_templates("class_name", $_GET["id"]);
        if (!$item_class){
            NConf_DEBUG::set("This item does not exist", 'ERROR');
        }
    }





    # get basic entries (ConfigValues) for passed id
    $query = mysql_query('SELECT id_attr,attr_value
                            FROM ConfigAttrs,ConfigValues,ConfigItems
                            WHERE id_attr=fk_id_attr
                            AND id_item=fk_id_item
                            AND visible="yes" 
                            AND id_item='.$_GET["id"].'
                            ORDER BY ordering');

    $item_data = array();
    while($entry = mysql_fetch_assoc($query)){
        $item_data[$entry["id_attr"]] = $entry["attr_value"];
    }

    # get linked entries (ItemLinks) for passed id
    $query2 = 'SELECT id_attr,attr_value,fk_item_linked2 
                    FROM ConfigValues,ItemLinks,ConfigAttrs 
                    WHERE fk_item_linked2=ConfigValues.fk_id_item 
                    AND id_attr=ItemLinks.fk_id_attr 
                    AND ConfigAttrs.visible="yes" 
                    AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes" 
                    AND ItemLinks.fk_id_item='.$_GET["id"].'
                    ORDER BY
                    ConfigAttrs.friendly_name DESC,
                    ItemLinks.cust_order
                    ';
    $result2 = db_handler($query2, "result", "linked entries");

    $item_data2 = array();
    while($entry2 = mysql_fetch_assoc($result2)){
        $item_data2[$entry2["id_attr"]][$entry2["fk_item_linked2"]] = $entry2["attr_value"];
    }


    # get entries linked as child (ItemLinks) for passed id
    $query3 = 'SELECT id_attr,attr_value,ItemLinks.fk_id_item
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                    AND id_attr=ItemLinks.fk_id_attr
                    AND ConfigAttrs.visible="yes"
                    AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                    AND ItemLinks.fk_item_linked2='.$_GET["id"].'
                    ORDER BY ConfigAttrs.friendly_name DESC';

    $result3 = db_handler($query3, "result", "Linked as child");

    while($entry3 = mysql_fetch_assoc($result3)){
        $item_data2[$entry3["id_attr"]][$entry3["fk_id_item"]] = $entry3["attr_value"];

    }

    if ($item_class == "service" OR $item_class == "advanced-service"){
        # get id of attr check_command
        $check_command_attr_id = db_templates("get_attr_id", $item_class, "check_command");
        $host_name_attr_id = db_templates("get_attr_id", $item_class, "host_name");
    }


    # debug infos
    NConf_DEBUG::set($item_data,  'DEBUG', "item_data");
    NConf_DEBUG::set($item_data2, 'DEBUG', "item_data2");
    NConf_DEBUG::close_group();
    $handle_action = "modify";
    $form_action = 'modify_item_write2db.php';

}elseif (isset($_GET["type"]) AND $_GET["type"] == "multimodify"  ){
    ###
    # multimodify
    ###

    # handle configuration
    $handle_action = "multimodify";
    $form_action = 'multimodify_attr_write2db.php';

    ###
    # some URL handling
    ###

    # set overview page to goto after modification
    $_SESSION["go_back_page_ok"] = $_SESSION["after_delete_page"];

    $url = basename($_SERVER['REQUEST_URI']);
    # url for select attribute
    $form_action_attr_select = $url;

    # check cache
    if ( !empty($_SESSION["cache"]["handle"]) ){
	    if ( isset($_SESSION["cache"]["handle"]["HIDDEN_config_class"]) ){
  			$_POST["class"] = $_SESSION["cache"]["handle"]["HIDDEN_config_class"];
  		}
  		if ( isset($_SESSION["cache"]["handle"]["HIDDEN_config_class"]) ){
  			$_POST["ids"] = $_SESSION["cache"]["handle"]["HIDDEN_modify_ids"];
  		}
  		if ( isset($_SESSION["cache"]["handle"]["HIDDEN_selected_attr"]) ){
  			$_POST["attr"] = $_SESSION["cache"]["handle"]["HIDDEN_selected_attr"];
  		}
  	}

    # check class
    if ( empty($_POST["class"]) ){
        NConf_DEBUG::set("No class set", 'ERROR');
    }else{
        $item_class = $_POST["class"];
    }

    # check ids
    if ( empty($_POST["advanced_items"]) AND empty($_POST["ids"])) {
        message($error, "No items selected to write to");
    }

	# replace mode
	if ( isset($_SESSION["cache"]["handle"]["replace_mode"]) ){
		if ($_SESSION["cache"]["handle"]["replace_mode"] == "add") {
			$replace_mode = 2;
		}elseif ($_SESSION["cache"]["handle"]["replace_mode"] == "replace") {
			$replace_mode = 1;
		}
	}else{
		$replace_mode = 2;
	}



}else{
    # add item
    
    if( empty($_GET["item"]) ){
        NConf_DEBUG::set("No config_class set", 'ERROR');
    }

    $handle_action = "add";
    $form_action = 'add_item_step2.php';
    $item_class = $_GET["item"];

    $class_ID   = db_templates('get_id_of_class', $item_class);
    if (!$class_ID){
        NConf_DEBUG::set("This class does not exist", 'ERROR');
    }

    # get id of attr check_command
    $check_command_attr_id = db_templates("get_attr_id", $item_class, "check_command");
    
    if ( !empty($_GET[$check_command_attr_id]) ){
        $_SESSION["cache"]["handle"][$check_command_attr_id][0] = $_GET[$check_command_attr_id];
    }
}

if ( empty($item_class) ) $item_class = '';
NConf_DEBUG::set($handle_action, 'DEBUG', 'Handle action: ');
NConf_DEBUG::set($item_class, 'DEBUG', 'Handle class: ');


#########################################################################
#########################################################################

###
# Title
###
$item_name  = db_templates("naming_attr", $_GET["id"]);
echo NConf_HTML::page_title($item_class);
echo '<div class="ui-nconf-header ui-widget-header ui-corner-tl ui-corner-tr ui-helper-clearfix">';
    echo '<div>';
        if ($handle_action == "add"){
            $title = ' Add new '.$item_class;
        }else{
            $title = ucfirst($handle_action);
        }
        echo '<h2 class="page_action_title">'.$title.' <span class="item_name">'.$item_name.'</span></h2>';
    echo '</div>';

echo '</div>';

# content block
echo '<div class="ui-nconf-content ui-widget-content ui-corner-bottom">';


// end / exit page if error
if ( NConf_DEBUG::status('ERROR') ) {
    echo NConf_HTML::exit_error();
}



###
# Form header for multimodify (attribute selection)
###
if ($handle_action == "multimodify"){
    # naming attr
    if ($item_class != "service"){
        message($info, "the naming attribute is not listed, because it's not allowed to have multiple identical naming attributes");
    }

    # output for select attribute
    $result_array = db_templates("get_attributes_with_bidirectional", $item_class);

    echo '<fieldset class="inline ui-widget-content">';
    echo '<legend><b>Select the attribute which you want to modify </b></legend>';
    echo '<form name="select_attr" action="'.$form_action_attr_select.'" method="post">';

    # add selected items and class
    echo '<input name="class" type="hidden" value="'.$item_class.'">';
    if ( empty($_POST["ids"]) ){
        $id_items = implode(",", $_POST["advanced_items"]);
    }else{
        $id_items = $_POST["ids"];
    }
    echo '<input name="ids" type="hidden" value="'.$id_items.'">';

    echo '<div id=buttons>';
        echo '<select name="attr" onchange="document.select_attr.submit()">';
        foreach ($result_array AS $attr ){
            # Naming attributes should not be the same on multiple items, so its not allowed to modify it on multiple items
            if ($attr["naming_attr"] == "no" OR $item_class == "service" ){
                echo '<option value="'.$attr["attr_name"].'"';
                if (!empty($_POST["attr"]) AND $_POST["attr"] == $attr["attr_name"]) echo " SELECTED";
                echo '>'.$attr["friendly_name"].'</option>';
            }
        }
        echo '</select>';
        echo '&nbsp;<input type="Submit" value="next" name="send" align="middle">';
    echo '</div>';

    echo '</form>';
    echo '</fieldset>';
}





if( 
    ( (isset($item_class) AND !empty($item_class)) )
    AND (
         $handle_action != "multimodify" OR ( $handle_action == "multimodify" AND !empty($_POST["attr"]) )
        )
  ){

    echo '<div style="height: 20px;">
            <div id="loading">
                <img src="img/working_small.gif"> in progress...
            </div>
          </div>';

    ###
    # Form start
    ###
    echo '<form name="handle_item" action="'.$form_action.'" method="post" onsubmit="multipleSelectOnSubmit()">';
    
    if ($handle_action == "add"){
        # add
        echo '<input name="config_class" type="hidden" value="'.$item_class.'">';
    }else{
        # modify / multimodify
        echo '<input name="HIDDEN_config_class" type="hidden" value="'.$item_class.'">';
        if ($handle_action == "modify") echo '<input name="HIDDEN_modify_id" type="hidden" value="'.$_GET["id"].'">';
        if ($handle_action == "multimodify"){
            echo '<input name="HIDDEN_modify_ids" type="hidden" value="'.$_POST["ids"].'">';
            echo '<input name="HIDDEN_selected_attr" type="hidden" value="'.$_POST["attr"].'">';
        }
    }


        //$notification_period_attribute_id = db_templates("get_attr_id", "host", "notification_period");
        //$check_period_attribute_id        = db_templates("get_attr_id", "host", "check_period");
        $contact_groups_attribute_id      = db_templates("get_attr_id", "host", "contact_groups");
        $class_ID   = db_templates('get_id_of_class', $item_class);

        NConf_DEBUG::open_group("prepare data");
        
        # bidirectional class check
        if ($handle_action == "multimodify"){
            $result_array = db_templates("get_attributes_with_bidirectional", $item_class, $_POST["attr"]);
            # set special Fieldset
            echo '<fieldset class="inline ui-widget-content">';
            echo '<legend><b>New value to write</b></legend>';
        }else{
            $result_array = db_templates("get_attributes_with_bidirectional", $item_class);
        }
        
        NConf_DEBUG::close_group();

        if ( count($result_array) == 0){
            // warn if class contains no attrbibutes
            NConf_DEBUG::set('There are no attributes defined for this class.', 'ERROR');
        }

        if ( NConf_DEBUG::status('ERROR') ) {
            $content  = NConf_HTML::show_error();
            $content .= "<br><br>";
            if (!empty($_SESSION["after_delete_page"]) ){
                $link = $_SESSION["after_delete_page"];
            }else{
                $link = "index.php";
            }
            $content .= NConf_HTML::back_button($link);

            echo NConf_HTML::limit_space($content);

            mysql_close($dbh);
            require_once 'include/foot.php';

            exit;

        }

        // seems not really nice, disabled for new theme
        //echo '<table border="0" style="table-layout:fixed; width:770px">';
        echo '<table>';

        # predefine col width
        echo define_colgroup();

        foreach ($result_array AS $entry ){
            if( ($handle_action != "modify") AND !empty($entry["predef_value"]) ){
                # add / multimodify
                $item_data[$entry["id_attr"]] = $entry["predef_value"];
                // debug must be redone with message:
                NConf_DEBUG::set($entry["predef_value"], 'DEBUG', 'predefined value of '.$entry["attr_name"].' ('.$entry["id_attr"].')');
            }


            ###
            # check for bidirectional attribute
            ###
            # if they are of "assign_one" types, they must be displayed as assign_many!
            if( $class_ID != $entry["fk_id_class"] AND
                ( $entry["link_bidirectional"] == "yes" AND $entry["datatype"] == "assign_one" )
                ){
                $entry["datatype"] = "assign_many";
                message($debug, '<b>Bidirectional</b> Changed output of bidirectional item from assign_one to assign_many');
            }


            # assign_many needs special tr class for setting margin
            if($entry["datatype"] == "assign_many"
                OR $entry["datatype"] == "assign_cust_order" ){
                //echo '<tr class="assign_many">'.$command_args;
                echo '<tr class="assign_many">';
            }else{
                //echo '<tr>'.$command_args;
                echo '<tr>';
            }

            
            # set special Fieldset for check_params
            if ( ($item_class == "service" OR $item_class == "advanced-service") AND $entry["attr_name"] == "check_params"){
                #do nothing here, print title later if really needed
            }else{
                echo '<td class="middle">'.$entry["friendly_name"].'</td>';
            }

            # check if items being displayed are "services"
            if(isset($entry["fk_show_class_items"])){
                $srvquery = mysql_query('SELECT config_class FROM ConfigClasses WHERE id_class='.$entry["fk_show_class_items"]);
                $srv = mysql_fetch_assoc($srvquery);
            }



            ### process "text" fields
            if ($entry["datatype"] == "text"){
                # check special case for check_params
                if ( ($item_class == "service" OR $item_class == "advanced-service") AND $entry["attr_name"] == "check_params"){
                    # check_param stuff

                    NConf_DEBUG::open_group("params for check command (service parameters)");

                    # get id of check_command
                    if ($handle_action == "add"){
                        if ( !empty($_SESSION["cache"]["handle"][$check_command_attr_id][0]) ){
                            $check_command_id = $_SESSION["cache"]["handle"][$check_command_attr_id][0];
                        }elseif( !empty($check_command_first_id) ){
                            # When adding a new service, this will help displaying the correct command_param for the first check_command
                            # This is only needed for not changed check_command select field
                            $check_command_id = $check_command_first_id;
                        }
                        $command_param_count = db_templates("get_command_param_count_of_checkcommand", $check_command_id);
                        
                        # Read default checkcommand params of selected check command and override predefined value (which makes more sense here)
                        $default_params = db_templates("get_default_checkcommand_params", $check_command_id);
                        $item_data[$entry["id_attr"]] = $default_params;
                        
                    }elseif( $handle_action == "modify" AND !empty($_GET["id"]) ){
                        $check_command_id = db_templates("get_checkcommand_of_service", $_GET["id"]);
                        $command_param_count = db_templates("get_command_param_count_of_checkcommand", $check_command_id);
                    }elseif( $handle_action == "multimodify" AND !empty($_POST["ids"]) ){
                        # process multivalue fields
                        # special
                        # its riski to allow to multimodify the check_command of mutliple (perhaps different) services
                        # perhaps more checks are needed here
                        $service_items = explode(",", $id_items);
                        $command_param_count_array = array();
                        $most_counts = 0;
                        foreach ($service_items AS $service_item){
                            $temp_check_command_id = db_templates("get_checkcommand_of_service", $service_item);
                            $command_param_count = db_templates("get_command_param_count_of_checkcommand", $temp_check_command_id);
                            $command_param_count_array[] = $command_param_count;
                            if ($command_param_count > $most_counts){
                                $most_counts = $command_param_count;
                                $check_command_id = $temp_check_command_id;
                            }
                        }
                        NConf_DEBUG::set($command_param_count_array, "DEBUG", "Command param counters");
                        # check if count is different for selected services
                        $command_param_count_unique = array_unique($command_param_count_array);
                        if (count($command_param_count_unique) > 1){
                            $warning_check_command_arguments = TXT_MULTIMODIFY_PARAMS_OF_CHECK_COMMAND_DIFFER;
                        }
                        $command_param_count = $most_counts;
                        
                    }

                    if ($command_param_count == "0"){
                            # no command syntax if param count == 0
                            # display no attribute but value of hidden attribute must be "!"
                            echo '
                                    <td>
                                        <input name="'.$entry["id_attr"].'" type="hidden" value="!">
                                    </td>';

                            # continue with next attribute (normaly these are bidirectional ones, param count is the last of normal attributes)
                            # but this will ignore all the next text/select etc logic
                            echo '</tr>';
                            continue;

                    }elseif( $command_param_count  > 0 ){
                        echo '<td class="middle">'.$entry["friendly_name"].'</td>';
                        echo '<td colspan=3>';
                        echo '<fieldset class="inline ui-widget-content">';
                        echo '<legend><b>service parameters</b></legend>';

                        if (  isset( $_SESSION["cache"]["handle"][$entry["id_attr"]] )
                                AND empty( $_SESSION["cache"]["handle"][$entry["id_attr"]]["check_command_changed"] )
                           ){
                            $value = $_SESSION["cache"]["handle"][$entry["id_attr"]];
                        }elseif ( isset($item_data[$entry["id_attr"]]) ){
                            $value = $item_data[$entry["id_attr"]];
                        }else{
                            $value = "";
                        }
                        
                        # command
                        $commands_split = explode("!", $value);

                        #
                        # Handle  \!  in commands
                        # Nagios allows to put \! in commands, so do not split that
                        # Put commands back together if a \! was split bevore
                        #
                        $commands_array = array();
                        $command = '';
                        foreach($commands_split as $command_part){
                            $command .= $command_part;
                            # if command ends with a backslash (\) the next command must be attached with a !
                            if ( preg_match("/\\\\$/", $command) ){
                                # if there is a backslash at the end, add the !
                                $command .= "!";
                            }else{
                                # command doesn't end with a backslash (\) so put it in array
                                $commands_array[] = $command;
                                $command = '';
                            }
                        }


                        # get syntax of arguments
                        # Get command syntax
                        $command_query = 'SELECT attr_value FROM ConfigValues,ConfigAttrs
                                                       WHERE id_attr=fk_id_attr
                                                       AND attr_name="command_syntax"
                                                       AND fk_id_item='.$check_command_id;
                        $cmd_syntax_string = db_handler($command_query, "getOne", "Get command syntax");
                        $cmd_syntax = explode(",", $cmd_syntax_string);
                        
                        # generate html output
                        echo '<table class="ui-nconf-max-width">';
                        for ($i = 1; $i <= $command_param_count; $i++){
                            # If not set make empty because of php offset failure
                            if ( !isset($commands_array[$i]) ){
                                $commands_array[$i] = '';
                            }
                            if ( !isset($cmd_syntax[$i]) ){
                                $cmd_syntax[$i] = $i;
                            }
                            echo '<tr>';
                                echo '<td align=right>ARG'.$i.': </td>
                                      <td colspan=2>
                                        <input name="exploded['.$entry["id_attr"].'][]" type=text maxlength='.$entry["max_length"].' value="'.htmlspecialchars($commands_array[$i]).'">
                                      </td>';
                                # remove ARG1 etc from description
                                $syntax_description = preg_replace('/ARG\d+\s*=\s*/i', '', $cmd_syntax[$i-1]);
                                echo '<td>'.$syntax_description.'</td>';
                            echo '</tr>';
                        }
                        echo '</table>';

                        # unset $command_param_count for next loop
                        unset($command_param_count);
                        NConf_DEBUG::close_group();

                    }
                    #close special td and fieldset
                    echo '</td></fieldset>';



                }else{
                    ###
                    # normal text case
                    ###

                    if (  ( isset($_SESSION["cache"]["handle"][$entry["id_attr"]]) )  ){
                        $value = $_SESSION["cache"]["handle"][$entry["id_attr"]];
                    }elseif ( isset($item_data[$entry["id_attr"]]) ){
                        $value = $item_data[$entry["id_attr"]];
                    }else{
                        $value = "";
                    }
                    
                    //special auto complete
                    //if ($entry["attr_name"] == "email" OR $entry["attr_name"] == "pager"){
                    if ( preg_match("/[email|pager]/", $entry["attr_name"]) ){
                        echo '<td>
                                <input id="'.$entry["attr_name"].'" name="'.$entry["id_attr"].'" type=text maxlength='.$entry["max_length"].' value="'.htmlspecialchars($value).'">
                              </td>';
                    }else{
                        echo '<td>
                                <input name="'.$entry["id_attr"].'" type=text maxlength='.$entry["max_length"].' value="'.htmlspecialchars($value).'">
                              </td>';
                    }

                }

            # process "password" fields
            }elseif($entry["datatype"] == "password"){
                if (  ( isset($_SESSION["cache"]["handle"][$entry["id_attr"]]) )  ){
                    $value = $_SESSION["cache"]["handle"][$entry["id_attr"]];
                }elseif ( isset($item_data[$entry["id_attr"]]) ){
                    $value = $item_data[$entry["id_attr"]];
                    $value = show_password($value);
                }else{
                    $value = "";
                }
                echo '<td>
                        <input name="'.$entry["id_attr"].'" type=password maxlength='.$entry["max_length"].' value="'.htmlspecialchars($value).'">
                      </td>';


            # process "select" fields
            }elseif($entry["datatype"] == "select"){
                // ADMIN users only
                if (  ($_SESSION["group"] != "admin") AND ( in_array($entry["attr_name"], $ADMIN_ONLY) )  ){
                    echo '<input name="'.$entry["id_attr"].'" type="HIDDEN" value="'.$entry["predef_value"].'">';
                }

                $dropdown = preg_split("/".SELECT_VALUE_SEPARATOR."/", $entry["poss_values"]);
                echo '<td><select name="'.$entry["id_attr"].'" size="0"';

                // ADMIN users only
                if (  ($_SESSION["group"] != "admin") AND ( in_array($entry["attr_name"], $ADMIN_ONLY) )  ){
                    echo " DISABLED";
                }

                echo '>';
                
                if ($entry["mandatory"] == "no"){
                    echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';
                }

                foreach ($dropdown as $menu){

                    echo "<option";
                    if ( isset($_SESSION["cache"]["handle"][$entry["id_attr"]]) ){
                        if ( $menu == $_SESSION["cache"]["handle"][$entry["id_attr"]] ){
                            echo " SELECTED";
                        }
                    }elseif (  ( isset($item_data[$entry["id_attr"]]) ) AND ($menu == $item_data[$entry["id_attr"]])  ){
                        echo " SELECTED";
                    }
                    echo ">$menu</option>";
                }
                echo "</select></td>";

            # process "assign_one" fields
            }elseif($entry["datatype"] == "assign_one"){
                if ($srv["config_class"] == "service"){
                    $query2 = 'SELECT id_item,attr_value,
                                                (SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                                                        AND id_attr=ConfigValues.fk_id_attr
                                                        AND naming_attr="yes"
                                                        AND fk_id_class = id_class
                                                        AND config_class="host"
                                                        AND ItemLinks.fk_id_item=id_item) AS hostname
                                                FROM ConfigItems,ConfigValues,ConfigAttrs
                                                    WHERE id_item=fk_id_item
                                                        AND id_attr=fk_id_attr
                                                        AND naming_attr="yes"';
                    if ($handle_action == "modify"){
                        $query2 .= '                    AND id_item <> '.$_GET["id"];
                    }
                    $query2 .= '                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY hostname,attr_value';
                }else{
                    $query2 = 'SELECT id_item,attr_value
                                                FROM ConfigItems,ConfigValues,ConfigAttrs
                                                    WHERE id_item=fk_id_item
                                                        AND id_attr=fk_id_attr
                                                        AND naming_attr="yes"';
                    if ($handle_action == "modify"){
                        $query2 .= '                    AND id_item <> '.$_GET["id"];
                    }
                    $query2 .= '                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY attr_value';
                }
                $result2 = db_handler($query2, "result", "assign_one");

                if ($handle_action == "add" AND ($item_class == "service" OR $item_class == "advanced-service") AND $entry["id_attr"] == $check_command_attr_id){
                    # special for check_command
                    $check_command_first_id = db_handler($query2.' LIMIT 1', "getOne", "get id of first checkcommand for check params / arguments");
                    echo '<td><select id="check_command_select" name="'.$entry["id_attr"].'[]">';
                }elseif($handle_action == "modify" AND ($item_class == "service" OR $item_class == "advanced-service") AND ($entry["id_attr"] == $check_command_attr_id OR $entry["id_attr"] == $host_name_attr_id) ){
                    # modify service should have disabled check_command and hostname
                    echo '<td><select name="'.$entry["id_attr"].'[]" disabled=disabled>';
                }else{
                    echo '<td><select name="'.$entry["id_attr"].'[]">';
                }
                
                if ($entry["mandatory"] == "no"){
                    echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';
                }
                while($menu2 = mysql_fetch_assoc($result2)){
                //NConf_DEBUG::set($menu2["id_item"].'+++'.$menu2["attr_value"], 'DEBUG', "id attr ".$entry["id_attr"]." : value @ itemdata(idattr)".$item_data[$entry["id_attr"]]);
                //NConf_DEBUG::set(NConf_HTML::swap_content($item_data, "hmmm"), 'DEBUG', "hmmm");

                    echo '<option value='.$menu2["id_item"];
                    if ( isset($_SESSION["cache"]["handle"][$entry["id_attr"]]) ) {
                            if ( $_SESSION["cache"]["handle"][$entry["id_attr"]][0] == $menu2["id_item"] ){
                                echo " SELECTED";
                            }
                    }else{
                        # not in cache, handle "add" and "modify" different
                        if ($handle_action != "modify"){
                            # add / multimodify
                            if ( is_array($entry["predef_value"]) ){
                                if ($menu2["id_item"] == $entry["predef_value"][0]) echo ' SELECTED';
                            }else{
                                if ($menu2["attr_value"] == $entry["predef_value"]) echo ' SELECTED';
                            }

                        }else{
                            # modify
                            if( isset($item_data2[$entry["id_attr"]][$menu2["id_item"]]) ) {
                                echo ' SELECTED';
                            }
                        }
                    }

                    if ($srv["config_class"] == "service"){
                        echo '>'.$menu2["hostname"].': '.$menu2["attr_value"].'</option>';
                    }else{
                        echo '>'.$menu2["attr_value"].'</option>';
                    }
                }
                echo '</select></td>';
            # process "assign_many" fields
            }elseif($entry["datatype"] == "assign_many"){
                if ($srv["config_class"] == "service"){
                    if ($handle_action != "modify"){
                        # add / multimodify
                        $query2 = 'SELECT id_item,attr_value,
                                                (SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                                                    AND id_attr=ConfigValues.fk_id_attr
                                                    AND naming_attr="yes"
                                                    AND fk_id_class = id_class
                                                    AND config_class="host"
                                                    AND ItemLinks.fk_id_item=id_item) AS hostname
                                                FROM ConfigItems,ConfigValues,ConfigAttrs
                                                WHERE id_item=fk_id_item
                                                    AND id_attr=fk_id_attr
                                                    AND naming_attr="yes"
                                                    AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY hostname,attr_value';
                    }else{
                        # modify
                        $query2 = 'SELECT id_item,attr_value,
                                    (SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                        WHERE fk_item_linked2=ConfigValues.fk_id_item
                                            AND id_attr=ConfigValues.fk_id_attr
                                            AND naming_attr="yes"
                                            AND fk_id_class = id_class
                                            AND config_class="host"
                                            AND ItemLinks.fk_id_item=id_item) AS hostname
                                    FROM ConfigItems,ConfigValues,ConfigAttrs
                                    WHERE id_item=fk_id_item
                                        AND id_attr=fk_id_attr
                                        AND naming_attr="yes"
                                        AND id_item <> '.$_GET["id"].'
                                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                        AND (SELECT fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses
                                                WHERE id_attr=fk_id_attr
                                                AND id_class=fk_id_class
                                                AND config_class="'.$item_class.'"
                                                AND (attr_name="parents" OR attr_name="dependent_service_description")
                                                AND fk_item_linked2="'.$_GET["id"].'"
                                                AND fk_id_item=id_item) IS NULL
                                    ORDER BY hostname,attr_value';
                    }
                }else{
                    if ($handle_action != "modify"){
                        # add / multimodify
                        $query2 = 'SELECT id_item,attr_value
                                                FROM ConfigItems,ConfigValues,ConfigAttrs
                                                WHERE id_item=fk_id_item
                                                    AND id_attr=fk_id_attr
                                                    AND naming_attr="yes"
                                                    AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY attr_value';
                    }else{
                        # modify
                        $query2 = 'SELECT id_item,attr_value
                                    FROM ConfigItems,ConfigValues,ConfigAttrs
                                    WHERE id_item=fk_id_item
                                        AND id_attr=fk_id_attr
                                        AND naming_attr="yes"
                                        AND id_item <> '.$_GET["id"].'
                                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                        AND (SELECT fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses
                                                WHERE id_attr=fk_id_attr
                                                AND id_class=fk_id_class
                                                AND config_class="'.$item_class.'"
                                                AND (attr_name="parents" OR attr_name="dependent_service_description")
                                                AND fk_item_linked2="'.$_GET["id"].'"
                                                AND fk_id_item=id_item) IS NULL
                                    ORDER BY attr_value';
                    }
                }

                $result2 = db_handler($query2, "result", "assign_many");
                echo '<td colspan=3>
                        <div class="select-container">
                          <select id="fromBox_'.$entry["id_attr"].'" name="from_'.$entry["id_attr"].'[]" style="'.CSS_SELECT_MULTI.'" multiple ';
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }*/
                echo '>';
                
                # split predef value
                $predef_value = preg_split("/".SELECT_VALUE_SEPARATOR."/", $entry["predef_value"]);
                $selected_items = array();
                while($menu2 = mysql_fetch_assoc($result2)){
                    // SELECTED
                    if ( isset($_SESSION["cache"]["handle"][$entry["id_attr"]]) ) {
                        if ( in_array($menu2["id_item"], $_SESSION["cache"]["handle"][$entry["id_attr"]]) ){
                            $selected_items[] = $menu2;
                            continue;
                        }
                    }else{
                        if ($handle_action != "modify"){
                            # add / multimodify
                            # select predefined values
                            if ( is_array($predef_value) ){
                                if ( in_array($menu2["attr_value"], $predef_value) ){
                                    $selected_items[] = $menu2;
                                    continue;
                                }

                            }
                        }else{
                            # modify
                            if ( isset($item_data2[$entry["id_attr"]]) AND is_array($item_data2[$entry["id_attr"]]) ){
                                if ( array_key_exists($menu2["id_item"], $item_data2[$entry["id_attr"]] ) ){
                                    $selected_items[] = $menu2;
                                    continue;
                                }
                            }
                        }

                    }

                    echo '<option value='.$menu2["id_item"];

                    if ($srv["config_class"] == "service"){
                        echo '>'.$menu2["hostname"].': '.$menu2["attr_value"].'</option>';
                    }else{
                        echo '>'.$menu2["attr_value"].'</option>';
                    }
                }
                echo '</select>';

                # fill "selected items" with session or predefiend data
                echo '<select multiple name="'.$entry["id_attr"].'[]" id="toBox_'.$entry["id_attr"].'"';
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }*/
                echo '>';
                foreach ($selected_items AS $selected_menu){
                    echo '<option value='.$selected_menu["id_item"];
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="getText(this, \'contacts\')"';
                    }*/

                    // END of SELECTED

                    if ($srv["config_class"] == "service"){
                        echo '>'.$selected_menu["hostname"].': '.$selected_menu["attr_value"].'</option>';
                    }
                    else{   
                        echo '>'.$selected_menu["attr_value"].'</option>';
                    }
                }
                echo '</select>';
                echo '</div>';
                # assign_cust_order handling
                $assign_cust_order = ($entry["datatype"] == "assign_cust_order") ? 1 : 0;
                echo '
                <script type="text/javascript">
                    createMovableOptions("fromBox_'.$entry["id_attr"].'","toBox_'.$entry["id_attr"].'",530,145,"available items","selected items","livesearch",'.$assign_cust_order.','.$replace_mode.');
                </script>
                ';
                

                echo '</td>';


            # process "assign_cust_order" fields
            }elseif($entry["datatype"] == "assign_cust_order"){

                if ($srv["config_class"] == "service"){
                    if ($handle_action != "modify"){
                        # add / multimodify
                        $query2 = 'SELECT id_item,attr_value,
                                                (SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                                                    AND id_attr=ConfigValues.fk_id_attr
                                                    AND naming_attr="yes"
                                                    AND fk_id_class = id_class
                                                    AND config_class="host"
                                                    AND ItemLinks.fk_id_item=id_item) AS hostname
                                                FROM ConfigItems,ConfigValues,ConfigAttrs
                                                WHERE id_item=fk_id_item
                                                    AND id_attr=fk_id_attr
                                                    AND naming_attr="yes"
                                                    AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY hostname,attr_value';
                    }else{
                        #modify
                        $query2 = 'SELECT id_item,attr_value,
                                    (SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                        WHERE fk_item_linked2=ConfigValues.fk_id_item
                                            AND id_attr=ConfigValues.fk_id_attr
                                            AND naming_attr="yes"
                                            AND fk_id_class = id_class
                                            AND config_class="host"
                                            AND ItemLinks.fk_id_item=id_item) AS hostname
                                    FROM ConfigItems,ConfigValues,ConfigAttrs
                                    WHERE id_item=fk_id_item
                                        AND id_attr=fk_id_attr
                                        AND naming_attr="yes"
                                        AND id_item <> '.$_GET["id"].'
                                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                        AND (SELECT fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses
                                                WHERE id_attr=fk_id_attr
                                                AND id_class=fk_id_class
                                                AND config_class="'.$item_class.'"
                                                AND (attr_name="parents" OR attr_name="dependent_service_description")
                                                AND fk_item_linked2="'.$_GET["id"].'"
                                                AND fk_id_item=id_item) IS NULL
                                    ORDER BY hostname,attr_value';
                    }
                }else{
                    if ($handle_action != "modify"){
                        # add / multimodify
                        $query2 = 'SELECT id_item,attr_value
                                                FROM ConfigItems,ConfigValues,ConfigAttrs
                                                WHERE id_item=fk_id_item
                                                    AND id_attr=fk_id_attr
                                                    AND naming_attr="yes"
                                                    AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                                ORDER BY attr_value';
                    }else{
                        # modify
                        $query2 = 'SELECT id_item,attr_value
                                    FROM ConfigItems,ConfigValues,ConfigAttrs
                                    WHERE id_item=fk_id_item
                                        AND id_attr=fk_id_attr
                                        AND naming_attr="yes"
                                        AND id_item <> '.$_GET["id"].'
                                        AND ConfigItems.fk_id_class='.$entry["fk_show_class_items"].'
                                        AND (SELECT fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses
                                                WHERE id_attr=fk_id_attr
                                                AND id_class=fk_id_class
                                                AND config_class="'.$item_class.'"
                                                AND (attr_name="parents" OR attr_name="dependent_service_description")
                                                AND fk_item_linked2="'.$_GET["id"].'"
                                                AND fk_id_item=id_item) IS NULL
                                    ORDER BY attr_value';
                    }
                }

                $result2 = db_handler($query2, "result", "assign_cust_order");
                
                # split predef value
                $predef_value = preg_split("/".SELECT_VALUE_SEPARATOR."/", $entry["predef_value"]);
                $selected_items = array();

                # generate base array
                $base_array = array();
                $search_array = array();
                while($entry_row = mysql_fetch_assoc($result2)){
                    $base_array[$entry_row["id_item"]] = $entry_row;
                    # we need a simpler array for searching when using predef_value:
                    $search_array[$entry_row["id_item"]] = $entry_row["attr_value"];
                }

                if ( isset($_SESSION["cache"]["handle"][$entry["id_attr"]]) ) {
                    if ( isset($_SESSION["cache"]["handle"][$entry["id_attr"]]) ) {
                        foreach ($_SESSION["cache"]["handle"][$entry["id_attr"]] as $key => $value){
                            if ( array_key_exists($value, $base_array) ){
                                $selected_items[] = $base_array[$value];
                                unset($base_array[$value]);
                            }
                        }
                    }
                }else{
                    if ($handle_action != "modify"){
                        # add / multimodify
                        # load predefined items, prepare arrays (this needs the special search_array
                        $predef_values = preg_split("/".SELECT_VALUE_SEPARATOR."/", $entry["predef_value"]);
                        if ( isset($predef_values) AND is_array($predef_values) ){
                            foreach ($predef_values as $value){
                                $key = array_search($value, $search_array);
                                if ( $key !== FALSE){
                                    $selected_items[] = $base_array[$key];
                                    unset($base_array[$key]);
                                }
                            }
                        }

                    }else{
                        # modify
                        # load selected items, prepare arrays
                        if ( isset($item_data2[$entry["id_attr"]]) AND is_array($item_data2[$entry["id_attr"]]) ){
                            foreach ($item_data2[$entry["id_attr"]] as $key => $value){
                                if ( array_key_exists($key, $base_array) ){
                                    $selected_items[] = $base_array[$key];
                                    unset($base_array[$key]);
                                }
                            }
                        }
                    }

                }

                # generate base options
                echo '<td colspan=3>
                    <div class="select-container">
                        <select id="fromBox_'.$entry["id_attr"].'" name="from_'.$entry["id_attr"].'[]" style="'.CSS_SELECT_MULTI.'" multiple ';
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }*/
                echo '>';
                foreach($base_array as $menu2){
                    echo '<option value='.$menu2["id_item"];

                    if ($srv["config_class"] == "service"){
                        echo '>'.$menu2["hostname"].': '.$menu2["attr_value"].'</option>';
                    }else{
                        echo '>'.$menu2["attr_value"].'</option>';
                    }
                }
                echo '</select>';

                # fill "selected items" with session or predefiend data
                echo '<select multiple name="'.$entry["id_attr"].'[]" id="toBox_'.$entry["id_attr"].'"';
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="attachInfo(this, \'contacts\')"';
                    }*/
                echo '>';
                foreach ($selected_items AS $selected_menu){
                    echo '<option value='.$selected_menu["id_item"];
                    /*# Load ajax info for PRIO's
                    if ($entry["id_attr"] == $contact_groups_attribute_id){
                        echo ' onmouseover="getText(this, \'contacts\')"';
                    }*/

                    // END of SELECTED

                    if ($srv["config_class"] == "service"){
                        echo '>'.$selected_menu["hostname"].': '.$selected_menu["attr_value"].'</option>';
                    }
                    else{   
                        echo '>'.$selected_menu["attr_value"].'</option>';
                    }
                }
                echo '</select>';
                echo '</div>';
                # assign_cust_order handling
                $assign_cust_order = ($entry["datatype"] == "assign_cust_order") ? 1 : 0;
                echo '
                <script type="text/javascript">
                    createMovableOptions("fromBox_'.$entry["id_attr"].'","toBox_'.$entry["id_attr"].'",500,145,"available items","selected items","livesearch",'.$assign_cust_order.','.$replace_mode.');
                </script>
                ';
                

                echo '</td>';


            }




            # display "*" for mandatory fields
            echo '<td class="mark_as_mandatory">';
                if ($entry["mandatory"] == "yes"){
                    if ( ($entry["datatype"] == "assign_many") OR ($entry["datatype"] == "assign_cust_order") ) echo '<br>';
                    echo '*';
                }else{
                    echo '&nbsp;';
                }
            echo '</td>';

            # display attr descripton
            echo '<td valign="top" class="desc middle"';
                if ( ($entry["datatype"] != "assign_many") AND ($entry["datatype"] != "assign_cust_order") ) echo 'colspan=3';
                echo '>';
                if ( !empty($entry["description"]) ){
                    echo $entry["description"];
                }else{
                    echo '&nbsp;';
                }
                echo '</td>';




            echo "</tr>\n";

            // DISABLE SUBMIT IF USER WANTS MODIFY AN ADMIN ACOUNT
            if ( ($item_class == "contact") AND ($_SESSION["group"] != "admin")
            AND ( $entry["attr_name"] == "nc_permission" AND
                    (!empty($item_data[$entry["id_attr"]]) AND  $item_data[$entry["id_attr"]] == "admin") )  ){
                // Disable the submit button and add message
                $deny_modification = TRUE;
                echo '<input type="hidden" name="deny_modification" value="TRUE">';
                message($info, TXT_SUBMIT_DISABLED4USER, "red");
            }

            // Take ID from nc_permission for check in write2db script (if user tries to hack)
            if ($entry["attr_name"] == "nc_permission"){
                echo '<input type="hidden" name="ID_nc_permission" value="'.$entry["id_attr"].'">';
            }


        } // END of while

#########

        echo '</table>';


        # close fieldset on multimodify        
        if ($handle_action == "multimodify"){
            echo '</fieldset>';
        }
        echo '<br><br>';
        

        # some add item specific
        if ($handle_action == "add"){
            # Tell the Session, send db query is ok (we are coming from formular)
            $_SESSION["submited"] = "yes";
        }

        if ( isset($deny_modification) AND ($deny_modification == TRUE) ){
            // DENIED
            echo '<div id=buttons>';
            echo '<input type="Submit" value="Submit" name="'.strtolower($handle_action).'" align="middle" DISABLED>';
            echo ' <input type="button" value="Reset" align="middle" onclick="location.reload(true);">';
            echo '</div>';
            echo '<br>';
            echo NConf_DEBUG::show_debug('INFO', TRUE);
        }else{
            // ALLOWED
            echo '<div id=buttons>';
            echo '<input type="Submit" value="Submit" name="'.strtolower($handle_action).'" align="middle">';
            echo ' <input type="button" value="Reset" align="middle" onclick="location.reload(true);">';
            echo '</div>';
        }

        ###
        # multimodify, show on which elements it will write
        ###
        if ( $handle_action == "multimodify" ){
            # Displays info part if available
            if ( !empty($warning_check_command_arguments) ){
                echo "<br>";
                echo NConf_HTML::limit_space(
                    NConf_HTML::show_highlight("Attention", $warning_check_command_arguments )
                    );
            }

            # add hidden title for mode change on assign_many's (replace or add)
            $content = '<h2 id="mode_add_title" style="display: none">Old value(s) will remain unchanged. The selected values will additionally be added to the following item(s):</h2>';

            # handle the size for the information box containing the items it writes on
            if ( ($entry["datatype"] == "assign_many") OR ($entry["datatype"] == "assign_cust_order") ){
                $content .= '<div style="overflow: auto; width: 380px; max-height: 235px;">';
            }else{
                $content .= '<div style="overflow: auto; width: 380px; max-height: 400px;">';
            }
                $content .= '<ul>';

                $array_ids = explode(",", $_POST["ids"]);
                foreach ($array_ids as $item_ID){
                    # Title of id
                    $name = db_templates("naming_attr", $item_ID);
                    if ($item_class == "service"){
                        # get host name of service
                        $hostID   = db_templates("hostID_of_service", $item_ID);
                        $hostname = db_templates("naming_attr", $hostID);
                        $name = $hostname.":".$name;
                    }
                    if ( !empty($name) OR $name === "0" ){
                        $content .= "<li>$name</li>";
                    }
                }
                $content .= '</ul>';

            echo '<br>';
            echo '<div id="info_box">';
            $title = 'Old value will be overwritten for the following item(s):';
            echo NConf_HTML::limit_space(
                NConf_HTML::show_highlight($title, $content)
                );
            echo '</div>';
        }

        echo '</form>';

}

if ( NConf_DEBUG::status('ERROR') ) {
    echo NConf_HTML::show_error();
}



// Run the Autocomplete function
if ( isset($prepare_status) AND ($prepare_status == 1) ){
    # only run if prepare was ok
    js_Autocomplete_run('email', 'emaillist');
    js_Autocomplete_run('pager', 'pagerlist');
}

# close content box
echo '</div>';

mysql_close($dbh);
require_once 'include/foot.php';
?>
