<?php
require_once 'include/head.php';
?>

<!-- jQuery part -->
<script type="text/javascript">


    $(document).ready(function(){

        if ( $("#step2").val() == "step2" ) {
            var host_id = $("#host_ID").val();
            add_service( host_id, "", "add_default_services");
        }else{
            load_service_list("", "service", false);
        }

        load_service_list("", "hostgroup_service", true);


        //// FUNCTIONS
        // list services
        function load_service_list(highlight_service, class_name, run_advanced_service_check){

            // show load icon, if not alredy shown
            $("#"+class_name+"_loading:hidden").show("blind", "slow");

            // load the service list
            $.post("call_file.php?ajax_file=service_list.php&debug=yes", {
                'host_id': $("#host_ID").val(),
                'highlight_service': highlight_service,
                'class': class_name
            }, function(data){

                // this will move the debug information to current page
                $(data).nconf_ajax_debug();

                // show (blind) the service list
                if (highlight_service === ""){
                    $("#"+class_name+"_list").html( $(data).filter("#content") );
                    $("#"+class_name+"_list").show("blind", "slow", function(){
                        $("#"+class_name+"_loading").hide("blind", "slow");
                    });
                }else{
                    $("#"+class_name+"_list").html( $(data).filter("#content") );
                    $("#"+class_name+"_list").find("#added").toggleClass("ui-helper-hidden");
                    $("#"+class_name+"_loading").hide("blind", "slow");
                }
                if ( run_advanced_service_check ){
                    advanced_service_check();
                }

            });

        }


        function advanced_service_check(){
            // first reset all alert stuff
            $("#hostgroup_service_list").add("#service_list").find(".ui-state-error").removeClass("ui-state-error").find(".alert_icons").remove();

            var warn_image = '<img src="img/icon_service_alert.gif" alt="warn" title="Warning: multiple services with same name exist on the same host" class="alert_icons jQ_tooltip">';

            // create advanced_services_direct from the list of "advanced service direct"
            var advanced_services_direct = $("select#toBox_advanced_services").children();

            // Check "normal services" with "advanced service Direct"
            //$("#service_list").each(function(index, searchServiceLine) {
            $("#service_list").add("#hostgroup_service_list").find("table > tbody").children("tr").each(function(index, searchServiceLine) {
                // get the service name (2. td)
                var search_service = $(this).children("td:first").next().contents("a").attr("title");
                // go through advanced_services_direct
                $(advanced_services_direct).each( function(i, advancedServiceDirect) {
                    // check if the service matches with a advanced service
                    // debug help:
                    // alert(search_service + "->" + $(advancedServiceDirect).attr("title"));
                    if ( $(advancedServiceDirect).attr("title") == search_service ){
                        // add some style to found advanced service and the search service
                        $(advancedServiceDirect).add(searchServiceLine).addClass("ui-state-error highlight");
                        // add a warn icon to the search service, only if the text is the only child (prevent mutliple icons)
                        $(searchServiceLine).find("td:first").next().children("a:only-child").after(warn_image);
                    }
                });
            });

            // Check hostgroup services with normal services
            $("#hostgroup_service_list").find("tbody > tr").each(function(index, searchServiceLine) {
                // get the service name 
                var search_service = $(this).children("td:first").next().contents("a").attr("title");
                // go through normal services
                $("tbody > tr", "#service_list").each( function(i, normalService) {
                    // check if the service matches with a advanced service
                    // alert($(normalService).text() + "->" + search_service);
                    if ( $(normalService).text() == search_service ){
                        // add style and a warn icon to the both services, only if the text is the only child (prevent mutliple icons)
                        $(searchServiceLine).add(normalService).addClass("ui-state-error").find("td:first").next().children("a:only-child").after(warn_image);
                    }
                });
            });
            
        }


        function add_service(host_id, service_id, mode){
            // show load icon
            $("#service_loading").show("blind", "slow");
            
            // add service
            $.post("call_file.php?ajax_file=service_add.php&debug=yes", {
                'service_id': service_id,
                'host_id': host_id,
                'username': "<?php echo $_SESSION['userinfos']['username'];?>",
                'mode': mode
            }, function(data){

                // this will move the debug information to current page
                $(data).nconf_ajax_debug();

                // Load list of service
                if (mode == "add_service"){
                    var service_id = $(data).filter("#add_success").html();
                    load_service_list( service_id, "service", true);
                }else{
                    // do not mark a added service
                    load_service_list( "", "service", false);
                }

            });
        }


        // BUTTONS //
        $('button').button();
       
        $('#add').button({
            icons: {
                primary: "ui-icon-circle-plus"
            },
            label: "add"
        }).addClass("ui-button-text-icon-primary")
        .live('click', function() {
            var host_id    = $("#host_ID").val();
            var service_id = $("#add_checkcommand option:selected").val();
            add_service( host_id, service_id, "add_service");
        }); 
       
        $('#save').button({
            icons: {
                primary: "ui-icon-disk"
            },
            label: "Save"
        }).addClass("ui-button-text-icon-primary")
        .live('click', function() {
            $("#advanced_services_loading").fadeIn("slow", function(){
                var host_id    = $("#host_ID").val();

                // select all assigned services
                $("#toBox_advanced_services option").each( function() {
                    $(this).attr("selected","selected");
                });
                // save the selected services
                var service_id = $("#toBox_advanced_services").val();
                
                // unselect toBox
                $("#toBox_advanced_services option").each( function() {
                    $(this).removeAttr("selected");
                });

                //alert(host_id+" : " + service_id);
                
                // if no item is selected, javascript would send "null", this should be removed
                if (!service_id){
                    var service_id = '';
                }

                // save assignments
                $.post("call_file.php?ajax_file=advanced_service.php&debug=yes", {
                    'host_id': $("#host_ID").val(),
                    'advanced_services[]': service_id,
                    'username': "<?php echo $_SESSION['userinfos']['username'];?>"
                }, function(data){
                    // this will move the debug information to current page
                    $(data).nconf_ajax_debug();
                    //alert(data);
                    var advanced_services_status = $(data).filter("#clone_success").html();
                    if (advanced_services_status == "ok"){
                        //alert("services assigned");
                        $("#advanced_services_loading").fadeOut("slow");
                    }else{
                        $("#advanced_services_loading").fadeOut("slow");
                    }
                    advanced_service_check();
                        
                });
            });
            
        }); 


        
        // clone //
        $('.clone').live('click', function() {
            // show load icon
            $("#service_loading").show("blind", "slow");
            
            // clone service
            $.post("call_file.php?ajax_file=service_clone.php&debug=yes", {
                'action': "cloneONhost",
                'item': "service",
                'host_id': $("#host_ID").val(),
                'service_id': $(this).attr("id"),
                'username': "<?php echo $_SESSION['userinfos']['username'];?>"
            }, function(data){
                // this will move the debug information to current page
                $(data).nconf_ajax_debug();

                var service_id = $(data).filter("#clone_success").html();
                load_service_list( service_id, "service", true);

                //$("#result").html( $(data).not("#clone_success") );
            });

        });


        // generate the help buttons with help text
        $.nconf_help_admin("direct");


    });
</script>

<!-- END of jQuery part -->




<?php

$URL = set_page();


if (DB_NO_WRITES == 1) {
    message($info, TXT_DB_NO_WRITES);
}

// host id
if( !empty($_GET["id"]) ){
    $host_ID = $_GET["id"]; 
}
// set host_ID for jQuery
echo '<input id="host_ID" type="hidden" value="'.$host_ID.'">';





// Prevent re-adding the host preset services when browser refreshing
if ( !empty($_SESSION["created_id"]) AND $_SESSION["created_id"] == $host_ID){
// If there are problems, comment out the line bevore and use instead the next one: (uncomment it)
#if( !empty($_GET["step2"]) ){
    $step2 = "step2";
    // remove info, to do not a reading on refreshing
    unset($_SESSION["created_id"]);
}else{
    $step2 = "";
}

echo '<input id="step2" type="hidden" value="'.$step2.'">'; 




# load advanced tab for services
require_once 'include/tabs/service.php';


////
// Content of this page
echo '<div style="width: 510px;" class="relative">';


// Page output begin
echo NConf_HTML::page_title("service", '');

////
// Title 
$item_name = db_templates("naming_attr", $host_ID);
$title = '<div>
  <h3 class="page_action_title">
    Services & advanced services of <span class="item_name">'.$item_name.'</span>
  </h3>
</div>';

echo $title;

# nav buttons
$detail_navigation =  '<a class="button_back jQ_tooltip" title="host details" href="detail.php?id='.$host_ID.'"></a>';
$detail_navigation .= '<a href="overview.php?class=host"><button class="button_overview jQ_tooltip" title="hosts overview"></button></a>';
if (!empty($detail_navigation) ){
$detail_navigation = '<div id="ui-nconf-icon-bar">'
        .$detail_navigation.
        '</div>';
}


# box for direct host services
    $box1 = '<div style="float: left; margin-right: 5px;">';
        $box1 .= NConf_HTML::title('Services (directly linked)');
    $box1 .= '</div>';
    $box1 .= '<div name="help_services"></div>';
    $box1 .= '<div style="clear: both"></div>';
    
echo NConf_HTML::ui_box_header($box1.$detail_navigation);

    $output .= '<div>';
    // Service select field
    $output .= '<fieldset class="inline">';
    $output .= '<legend>Add additional services to host</legend>';

        $output .= '<table>';
        $output .= '<tr>
                    <td>
                        <select id="add_checkcommand" name="add_checkcommand">';
                            // create service name list (also checks for default service name)
                            # use check_name or service_name as ordering
                            $service_order = "check_name";
                            $service_names = db_templates('get_name_of_services', '', $service_order);
                            NConf_DEBUG::set( $service_names, 'DEBUG', " get service names for add selection");
                            
                            foreach ($service_names AS $checkcommand) {
                                if ( !empty($checkcommand["default_service_name"]) ){
                                    if ($service_order == "check_name"){
                                        $output .= '<option value='.$checkcommand["item_ID"].'>'.$checkcommand["check_name"].' ('.$checkcommand["default_service_name"].')</option>';
                                    }else{
                                        $output .= '<option value='.$checkcommand["item_ID"].'>'.$checkcommand["default_service_name"].' ('.$checkcommand["check_name"].')</option>';
                                    }
                                }elseif( !empty($checkcommand["check_name"]) ){
                                    $output .= '<option value='.$checkcommand["item_ID"].'>'.$checkcommand["check_name"].'</option>';
                                }
                            }
                    $output .= "</select>";

                    $output .= '</td>';
                    $output .= '<td>';
                        $output .= '<button id="add"></button>';
                    $output .= '</td>';

        $output .= '</tr>';
        $output .= '</table>';

    $output .= '</fieldset>';
    $output .= '<div><img id="service_loading" src="img/working_small.gif" alt="loading"></div>';
    $output .= '</div>';


    // place for help box (only distance from top)
    $output .= '<div id="page_content"></div>';


    ////
    // Place for service list
    $output .= '<br><div id="service_list" style="display:none"></div>';

    // Finish button removed, link now in toolbar
    //$output .= '<br><br>';
    //$output .= '<button onClick="window.location.href=\'overview.php?class=host\'">Finish</button>';

echo NConf_HTML::ui_box_content($output);
unset($output);


###
# ADVANCED SERVICE
###


    $box2 = '<div style="float: left; margin-right: 5px;">';
        $box2 .= NConf_HTML::title('Advanced services (directly linked)');
    $box2 .= '</div>';
    $box2 .= '<div name="help_advanced_services"></div>';
    $box2 .= '<div style="clear: both"></div>';
    echo NConf_HTML::ui_box_header($box2);
    
    # save button
    $advanced_save_button = '<div style="position: absolute; right: 10px; padding-top: 5px;">
                    <div style="float: left">
                        <img id="advanced_services_loading" src="img/working_small.gif" alt="loading" style="display:none; margin-right: 5px;">
                    </div>
                    <div style="float: right">
                        <button id="save"></button>
                    </div>
                </div>';
    echo $advanced_save_button;
    // get all advanced services
    $query = 'SELECT id_item,
                attr_value AS service_name,
                ( SELECT attr_value
                    FROM ConfigValues, ConfigAttrs
                    WHERE ConfigValues.fk_id_attr = ConfigAttrs.id_attr
                    AND attr_name = "service_description"
                    AND ConfigValues.fk_id_item = id_item ) AS service_description
                FROM ConfigItems,ConfigValues,ConfigAttrs,ConfigClasses
                WHERE id_item=fk_id_item
                    AND id_attr=fk_id_attr
                    AND naming_attr="yes"
                    AND ConfigItems.fk_id_class=id_class
                    AND config_class="advanced-service"
                ORDER BY service_name ASC';

    $service_names = db_handler($query, "array", "get all advanced services"); 


    $output .= '<select id="fromBox_advanced_services" name="from_advanced_services[]" style="'.CSS_SELECT_MULTI.'" multiple >';

    $services = db_templates("get_services_from_host_id", $host_ID, "advanced-service");

    foreach ($service_names AS $advanced_service) {
        // Title attribute is needed for later name conflict checks(over jQuery)
        // First set the title attribute to the service name
        $advanced_service["title"] = $advanced_service["service_name"];
        // compare service_name with service_description
        if ( !empty($advanced_service["service_description"]) AND ($advanced_service["service_name"] != $advanced_service["service_description"]) ){
            // display the description in brackets behind the service name
            $advanced_service["service_name"] = $advanced_service["service_name"] . ' (' . $advanced_service["service_description"] . ')';
            // Override service title with service description, which is needed for later conflict checks
            $advanced_service["title"] = $advanced_service["service_description"];
        }

        // move already selected items
        if ( array_key_exists($advanced_service["id_item"], $services ) ){
            $selected_items[] = $advanced_service;
            continue;
        }
        $output .= '<option value="'.$advanced_service["id_item"].'" title="'.$advanced_service["title"].'">'.$advanced_service["service_name"].'</option>';
    }
    
    $output .= '</select>';

    # fill "selected items" with session or predefiend data
    $output .= '<select multiple name="advanced_services[]" id="toBox_advanced_services">';

    if ( !empty($selected_items) ){
        foreach ($selected_items AS $selected_menu){
            $output .= '<option value="'.$selected_menu["id_item"].'" title="'.$selected_menu["title"].'">';
            $output .= $selected_menu["service_name"].'</option>';
        }
    }
    $output .= '</select>';

    $output .= '
                <script type="text/javascript">
                    createMovableOptions("fromBox_advanced_services","toBox_advanced_services",490,145,"available items","selected items","livesearch");
                </script>
                ';

    echo NConf_HTML::ui_box_content($output, "advanced-service-direct");
    unset($output);


###
# HOSTGROUP SERVICES
###


    # box for direct host services
    $box3 = '<div style="float: left; margin-right: 5px;">';
        $box3 .= NConf_HTML::title('Advanced services (inherited over hostgroups)');
    $box3 .= '</div>';
    $box3 .= '<div name="help_hostgroup_services"></div>';
    $box3 .= '<div style="clear: both"></div>';
    echo NConf_HTML::ui_box_header($box3);
    
    // progress indicator / loading
    $output .= '<img id="hostgroup_service_loading" src="img/working_small.gif" alt="loading" style="display:none">';

    // Place for hostgroup service list
    $output .= '<div id="hostgroup_service_list" style="display:none"></div>';

    echo NConf_HTML::ui_box_content($output, "advanced-service-inherit");


echo '</div>';

//echo '<div id="result"> </div>';
?>

<!-- Help text content -->
<div id="help_text" style="display: none">
    <div id="help_services" title="Services (directly linked)">
        <p>These are services which are directly linked to a host.<br><br>
        Ordinary services can only be linked to one host at a time and remain bound to that host.<br><br>
        Should one or more services and advanced services with the same name be linked to the same host, then Nagios will give precedence to the item it processes last when it loads the configuration. Processing order is dependent on the order in which the configuration files are listed within your main nagios.cfg file.<br><br>
[ Caution: This behavior might differ depending on what version of Nagios / Icinga you are using. ]
        </p>
    </div>

    <div id="help_advanced_services" title="Advanced services (directly linked)">
        <div>
        <p>
        These are advanced services which are directly linked to one or more hosts. <br><br>
        The difference between ordinary services and advanced services is that advanced services can be linked to more than one host and / or hostgroup simultaneously.<br><br>
        Should one or more services and advanced services with the same name be linked to the same host, then Nagios will give precedence to the item it processes last when it loads the configuration. Processing order is dependent on the order in which the configuration files are listed within your main nagios.cfg file.<br><br>
[ Caution: This behavior might differ depending on what version of Nagios / Icinga you are using. ]
        </p>
        </div>
    </div>

    <div id="help_hostgroup_services" title="Advanced services (inherited over hostgroups)">
        <p>
        These are advanced services which are inherited over hostgroups. Hosts can inherit service definitions from the hostgroups they are part of. This allows you to predefine a host's services globally.<br><br>
        The difference between ordinary services and advanced services is that advanced services can be linked to more than one host and / or hostgroup simultaneously.<br><br>
        Should one or more services and advanced services with the same name be linked to the same host, then Nagios will give precedence to the item it processes last when it loads the configuration. Processing order is dependent on the order in which the configuration files are listed within your main nagios.cfg file.<br><br>
[ Caution: This behavior might differ depending on what version of Nagios / Icinga you are using. ]
        </p>
    </div>

</div>

<?php

mysql_close($dbh);
require_once 'include/foot.php';
?>
