<?php
require_once 'include/head.php';


?>

<!-- jQuery part -->
<script type="text/javascript">
    $(document).ready(function(){
        // highlight the previouse applied template
        $('.previously_applied').hover(function() {
            $("td[id='"+$(this).attr("id")+"_first']").toggleClass("ui-state-highlight");
        });
    });

</script>

<?php


// Set previous (referer) page if that actual item in detail view will be deleted
if ( empty($_SERVER["HTTP_REFERER"]) AND isset($_SESSION["after_delete_page"]) ) {
    message($debug, "referer not set, seems to be from a delete operation, go to after_delete_page");
    $from_url = $_SESSION["after_delete_page"];
}elseif ( isset($_SERVER["HTTP_REFERER"]) AND preg_match('/detail\.php/', $_SERVER["HTTP_REFERER"]) ){
    message($debug, "detail.php matched");
    $from_url = $_SERVER["HTTP_REFERER"];
}elseif( isset($_SERVER["HTTP_REFERER"]) AND preg_match('/modify_item\.php/', $_SERVER["HTTP_REFERER"]) ){
    # coming from editing, do still a forward to the after_delete_page
    $from_url = $_SESSION["after_delete_page"];
}elseif( isset($_SERVER["HTTP_REFERER"]) AND preg_match('/'.preg_quote("add_item_step2.php").'/', $_SERVER["HTTP_REFERER"]) ){
    # coming from an add (entry exists), do still a forward to the after_delete_page
    $from_url = $_SESSION["after_delete_page"];
}elseif( !empty($_SERVER["HTTP_REFERER"]) ){
    message($debug, "not from detail.php or modify, setting referer");
    $_SESSION["after_delete_page"] = $_SERVER["HTTP_REFERER"];
    $from_url = $_SERVER["HTTP_REFERER"];
}else{
    # direct opening of this file, or no referer, go back to overview.php
    $from_url = "index.php";
}

set_page();

if ( empty($_GET["id"]) ){
    NConf_DEBUG::set("No id", 'ERROR');
}else{
    // Be sure ID it is an integer - fixes injecting issues
    $item_id = (int) $_GET["id"];
    $item_class = db_templates("class_name", $item_id);
    $item_name = db_templates("naming_attr", $item_id);
}
// end / exit page if error
if ( NConf_DEBUG::status('ERROR') ) {
    echo NConf_HTML::exit_error();
}

# cache template id's to detect repetitive templates
$template_cache = array();
$template_cache_local = array();


# History Tab-View
require_once 'include/tabs/history.php';


# Normal detail page
echo '<div style="width: 500px;" class="relative">';
echo NConf_HTML::page_title($item_class, $item_name);

    echo '<div class="ui-nconf-header ui-widget-header ui-corner-tl ui-corner-tr">';

        echo '<div><h2 class="page_action_title">Details of <span class="item_name">'.$item_name.'</span></h2></div>';
        echo '<div id="ui-nconf-icon-bar">';
		
			// tool bar of details view
			$output = '';
			
			// Edit
			$output .= ( !isset($_GET["xmode"]) ) ?  '<a href="handle_item.php?item='.$item_class.'&amp;id='.$item_id.'">'.ICON_EDIT.'</a>' : '';
			// Clone
			$output .= ( $item_class == "host" ) ? '<a href="clone_host.php?class='.$item_class.'&amp;id='.$item_id.'">'.ICON_CLONE.'</a>' : '';
			// Delete
			$output .= ( !isset($_GET["xmode"]) ) ?  '<a href="delete_item.php?item='.$item_class.'&amp;ids='.$item_id.'&amp;from='.$from_url.'">'.ICON_DELETE.'</a>' : '';
			// Services
			$output .= ( $item_class == "host" ) ? '<a href="modify_item_service.php?id='.$item_id.'">'.ICON_SERVICES.'</a>' : '';
			// History
			$output .= '<a href="history.php?item='.$item_class.'&amp;id='.$item_id.'&amp;from='.$from_url.'">'.ICON_HISTORY.'</a>';
			// Parent child
			$output .= ( $item_class == "host" ) ? '<a href="dependency.php?id='.$item_id.'">'.ICON_PARENT_CHILD.'</a>' : '';
			
			echo $output;
			
        echo '</div>';
    echo '</div>';
    echo '<div class="ui-nconf-content ui-widget-content ui-corner-bottom">';

    $colgroup = '<colgroup>
                    <col width="150">
                    <col>
                 </colgroup>';


    echo '<table class="ui-nconf-table ui-nconf-max-width">';
    echo $colgroup;
    

    echo '<tbody>';

    # get basic entries
    $query = 'SELECT ConfigAttrs.friendly_name,attr_value, ConfigAttrs.datatype
                            FROM ConfigAttrs,ConfigValues,ConfigItems
                            WHERE id_attr=fk_id_attr
                            AND id_item=fk_id_item
                            AND ConfigAttrs.visible="yes" 
                            AND id_item='.$item_id.'
                            ORDER BY ConfigAttrs.ordering';

    $result = db_handler($query, "array", "get basic entries");

    $basic_result = array();
    # show or hide password if exists:
    foreach ($result AS $attributes){
        if ($attributes["datatype"] == "password"){
            # check how to display password
            $attributes["attr_value"] = show_password($attributes["attr_value"]);
        }
        array_push($basic_result, $attributes);
    }

    # print an overview of the icon if available
    if ($item_class == "os" and $basic_result[1]["attr_value"] != "" and is_readable(OS_LOGO_PATH."/".$basic_result[1]["attr_value"])) {
        array_push($basic_result, array('friendly_name' => 'icon preview', 'attr_value' => "<img src=".OS_LOGO_PATH."/".$basic_result[1]["attr_value"].">"));
    }

    echo table_output($basic_result, $item_class);


    # get linked entries
    $query2 = 'SELECT ConfigAttrs.friendly_name,attr_value,fk_item_linked2 AS item_id,
                            (SELECT config_class FROM ConfigItems,ConfigClasses
                                WHERE id_class=fk_id_class AND id_item=item_id) AS config_class
                            FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
                            WHERE fk_item_linked2=ConfigValues.fk_id_item
                                AND id_attr=ItemLinks.fk_id_attr
                                AND ConfigAttrs.visible="yes"
                                AND fk_id_class=id_class
                                AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)="yes"
                                AND ItemLinks.fk_id_item='.$item_id.'
                            ORDER BY
                                ConfigAttrs.friendly_name DESC,
                                ItemLinks.cust_order,
                                attr_value';

    $result = db_handler($query2, "array", "get linked entries");
    echo table_output($result, $item_class, "This item is linked to");


    # get entries
    $result = db_templates("linked_as_child", $item_id, '', '', 'array');
    echo table_output($result, $item_class, "Child items linked");


    echo '</table>';
    echo '</div>';


    ###
    # template inheritance
    if ($item_class == "host" OR $item_class == "service" OR $item_class == "advanced-service"){
        # do not output content directly, so we can evaluate if there are templates
        $output = '';

        NConf_DEBUG::open_group("template inheritance");
        $output .= 'Templates are applied in the following order:';
        $output .= '<table class="ui-nconf-table ui-nconf-max-width">';
        $output .= $colgroup;


        # create tables
        if ($item_class == "host"){
            $direct_host_templates = db_templates("template_inheritance_direct", $item_id);
            if ( !empty($direct_host_templates) ){
                $output .= '<tr><td colspan=2><br><b>directly linked to host</b></td></tr>';
                $output .= table_output($direct_host_templates, 'template_inheritance');
            }

            # notification period inheritance
            $p2 = db_templates("template_inheritance", $item_id, "notification_period", "host_template");
            $output .= table_output($p2, 'template_inheritance', '"notification period"');

            # check period templates
            $p3 = db_templates("template_inheritance", $item_id, "check_period", "host_template");
            $output .= table_output($p3, 'template_inheritance', '"check period"');

            #collector templates
            $monitored_by_id = db_templates("get_linked_item", $item_id, "monitored_by");
            if ( !empty($monitored_by_id[0]["fk_id_item"]) ){
                # only if host is monitored

                $p4_col = db_templates("template_inheritance_collector_monitor", $monitored_by_id[0]["fk_id_item"], "host_template");
                $output .= table_output($p4_col, 'template_inheritance', '<b>Nagios-collector</b> ("monitored by")');

                # monitor templates
                $nagios_monitor_query = 'SELECT id_item FROM ConfigItems, ConfigClasses WHERE id_class=fk_id_class AND config_class="nagios-monitor";';
                $nagios_monitors = db_handler($nagios_monitor_query, 'array_direct', "get ids of all nagios-monitors");
                if ( !empty($nagios_monitors) ){
                    $output .= '<tr><td colspan=2><br><b>inherited from Nagios-monitor</b></td></tr>';
                    foreach ($nagios_monitors AS $monitor_id){
                        $p4_mon = db_templates("template_inheritance_collector_monitor", $monitor_id, "host_template");
                        $output .= table_output($p4_mon, 'template_inheritance');
                    }
                }
            }


        }elseif($item_class == "service" OR $item_class == "advanced-service"){
            $direct_service_templates = db_templates("template_inheritance_direct", $item_id);
            if ( !empty($direct_service_templates) ){
                $output .= '<tr><td colspan=2><br><b>directly linked to service</b></td></tr>';
                $output .= table_output($direct_service_templates, 'template_inheritance');
            }

            # notification period inheritance
            $p2 = db_templates("template_inheritance", $item_id, "check_command", "service_template");
            $output .= table_output($p2, 'template_inheritance', '"check command"');

            # notification period inheritance
            $p3 = db_templates("template_inheritance", $item_id, "notification_period", "service_template");
            $output .= table_output($p3, 'template_inheritance', '"notification period"');

            # check period templates
            $p4 = db_templates("template_inheritance", $item_id, "check_period", "service_template");
            $output .= table_output($p4, 'template_inheritance', '"check period"');

            # collector templates
            $host_id = db_templates("hostID_of_service", $item_id);
            $monitored_by_id = db_templates("get_linked_item", $host_id, "monitored_by");
            if ( !empty($monitored_by_id[0]["fk_id_item"]) ){
                # only if host is monitored

                # get collector templates
                $p5_col = db_templates("template_inheritance_collector_monitor", $monitored_by_id[0]["fk_id_item"], "service_template");
                $output .= table_output($p5_col, 'template_inheritance', '<b>Nagios-collector</b> ("monitored by")');

                # get monitor templates
                $nagios_monitor_query = 'SELECT id_item FROM ConfigItems, ConfigClasses WHERE id_class=fk_id_class AND config_class="nagios-monitor";';
                $nagios_monitors = db_handler($nagios_monitor_query, 'array_direct', "get ids of all nagios-monitors");
                if ( !empty($nagios_monitors) ){
                    $output .= '<tr><td colspan=2><br><b>inherited from Nagios-monitor</b></td></tr>';
                    foreach ($nagios_monitors AS $monitor_id){
                        $p5_mon = db_templates("template_inheritance_collector_monitor", $monitor_id, "service_template");
                        $output .= table_output($p5_mon, 'template_inheritance');
                    }
                }
            }

        }

        # close template inheritance
        $output .= '</table>';
        $output .= '</div>';

        # template cache debug
        NConf_DEBUG::set($template_cache, 'DEBUG', "template cache");
        NConf_DEBUG::close_group();

        #print the template inheritace box
        echo NConf_HTML::ui_box_header('Template inheritance');

        if ( count($template_cache) == 0 ){
            $output = 'no templates inherited';
        }

        echo NConf_HTML::ui_box_content($output);
    }




echo '</div>';







/*
commented out because we do not want to group the different link types
we want all linked items in one group
if we want to change that, we have to get all normal types, and then group the child or bidirectionals as follows:

# get entries linked as child
$result = db_templates("linked_as_child", $item_id, "link_as_child");
table_output($result, $item_class, "Child items linked");

# get bidirectional entries
$result = db_templates("linked_as_child", $item_id, "link_bidirectional");
table_output($result, $item_class, "Bidirectional items");

*/


function table_output($result, $item_class = '', $title = '', $level = 0){
    # template cache for detect previouse loaded template
    global $template_cache;

    # the local template cache is for detecting endless loops
    global $template_cache_local;


    # handling the local template cache
    #reseting it if level is 0
    if ($level == 0){
        $template_cache_local = array();
    }elseif($level == 20){
        # this is a hardcoded loop stopper, should come into action, but prevents that the page will endless load
        # normaly there will never be templates on 20 level inherited
        return;
    }


    # output will catch the content until return
    $output = '';

    if ( !empty($result) ) {

        if( ( is_array($result) AND !empty($result) ) AND !empty($title) ){
            $output .= '<tr><td colspan=2><br>';
            if ($item_class == 'template_inheritance'){
                $output .= '<b>inherited from</b> '.$title;
            }else{
                $output .= '<b>'.$title.'</b>';
            }
            $output .= '</td></tr>';
        }

        $last_fname = '';
        foreach ($result AS $entry){

            if( !empty($entry["config_class"]) AND $entry["config_class"] == "service"){
                $host_query = 'SELECT attr_value AS hostname
                                      FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                                      WHERE fk_item_linked2=ConfigValues.fk_id_item
                                          AND id_attr=ConfigValues.fk_id_attr
                                          AND naming_attr="yes"
                                          AND fk_id_class = id_class
                                          AND config_class="host"
                                          AND ItemLinks.fk_id_item='.$entry["item_id"];

                $hostname = db_handler($host_query, "getOne", "Get linked hostnames (if service)");
            }

            if ( !empty($entry["friendly_name"]) ){
                $group_name = $entry["friendly_name"];
            }

            // group same attributes
            if( !empty($group_name) AND $last_fname != $group_name){
               $show_fname = $group_name;
               //$bgcolor = 'class="color_list2"';
            }else{
                $show_fname = '';
            }

            $output .= '<tr>';
                //$output .= '<td '.$bgcolor.'>'.$show_fname.'</td>';
                $output .= '<td class="color_list2">'.$show_fname.'</td>';

                # print template (and detect repetitive)
                if ($item_class == 'template_inheritance'){
                    $template_status = apply_template($template_cache, $entry["item_id"]);
                    $local_template_status = apply_template($template_cache_local, $entry["item_id"]);
                    NConf_DEBUG::set($local_template_status, 'DEBUG', "local repetitive status");
                    NConf_DEBUG::set($template_cache_local, 'DEBUG', "local template cache");
                }else{
                    $template_status = FALSE;
                    $local_template_status = FALSE;
                }
                if( !empty($entry["config_class"]) AND $entry["config_class"] == "service" && $item_class != "host"){
                    $output .= '<td class="color_list1 highlight">';
                    $output .= '<a href="detail.php?id='.$entry["item_id"].'">';
                        $output .= $hostname.': '.$entry["attr_value"];
                    $output .= '</td>';
                }else{
                    $level_label = '';
                    for($i=$level; $i>1;$i--){
                        $level_label .= '<div style="width: 9px; display: inline-block;"></div>';
                    }
                    if ($i == 1 AND $level != 0){
                        ## add a mark
                        $level_label .= '<span class="link_with_tag2"></span>';
                    }
                    # mark previously applied templates
                    if ($template_status === "repetitive"){
                        # detect previously applied or template loop
                        if ($local_template_status === "repetitive"){
                            # endless loop
                            $repetitive_text = "(circular template chain detected)";
                            $class = "ui-state-error highlight";
                        }else{
                            $repetitive_text = "(previously applied)";
                            $class = "color_list1 highlight";
                        }
                        $output .= '<td class="'.$class.'">';
                            $output .= $level_label.'<a href="detail.php?id='.$entry["item_id"].'">';
                            $output .= $entry["attr_value"].'</a>';
                            $output .= '<span id="'.$entry["item_id"].'" class="previously_applied" style="float: right;"><i>'.$repetitive_text.'</i></span>';
                        $output .= '</td>';
                    }else{
                        # set id for coming repetitive
                        if ($item_class == 'template_inheritance'){
                            $output .= '<td id="'.$entry["item_id"].'_first" class="color_list1 highlight">';
                        }else{
                            $output .= '<td class="color_list1 highlight">';
                        }
                        if ( !empty($entry["item_id"]) ){
                            $output .= $level_label;
                            $output .= '<a href="detail.php?id='.$entry["item_id"].'">';
                            $output .= $entry["attr_value"].'</a>';
                        }else{
                            $output .= $entry["attr_value"];
                        }
                        $output .= '</td>';
                    }
                }
            $output .= '</tr>';

            if( !empty($group_name) ){
                $last_fname = $group_name;
                $show_fname = '';
                $bgcolor = '';
            }

            # lookup template himself

            if ($item_class == 'template_inheritance'){
                if ($local_template_status === "repetitive"){
                    NConf_DEBUG::set('', 'DEBUG', 'template is repetitive, stopping inheritance to prevent endless loop');
                }else{
                    $template_on_template = db_templates("template_inheritance_direct", $entry["item_id"]);
                    if ( !empty($template_on_template) ){
                        //$output .= '<tr><td colspan=2><br><b>directly linked to service</b></td></tr>';
                        $output .= table_output($template_on_template, 'template_inheritance', '', ++$level);
                    }
                }
            }

        }

    }

    return $output;

}


function apply_template(&$template_cache, $template_id){
    if ( in_array($template_id, $template_cache) ){
        return "repetitive";
    }else{
        array_push($template_cache, $template_id);
        return TRUE;
    }
}



mysql_close($dbh);
require_once 'include/foot.php';

?>
