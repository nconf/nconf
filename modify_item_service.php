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

            var warn_image = '<img src="img/icon_service_alert.gif" alt="warn" title="Warning: advanced service with the same name exists" class="alert_icons jQ_tooltip">';

            // create search_base from the list of "advanced service direct"
            var search_base = $("select#toBox_advanced_services").children();

            // Check "normal services" & "hostgroup service" with "advanced service Direct"
            $("#service_list").add("#hostgroup_service_list").find("tbody > tr").each(function(index, searchServiceLine) {
                // get the service name (2. td)
                var search_service = $(this).children("td:first").next().contents("a").text();
                // go through search_base
                $(search_base).each( function(i, advancedServiceDirect) {
                    // check if the service matches with a advanced service
                    if ( $(advancedServiceDirect).text() == search_service ){
                        // add some style to found advanced service and the search service
                        $(advancedServiceDirect).add(searchServiceLine).addClass("ui-state-error highlight");
                        // add a warn icon to the search service, only if the text is the only child (prevent mutliple icons)
                        $(searchServiceLine).find("td:first").next().children("a:only-child").after(warn_image);
                    }
                });
            });


            
            // Check hostgroup services with "advanced service Direct"
            $("#hostgroup_service_list").find("tbody > tr").each(function(index, searchServiceLine) {
                // get the service name 
                var search_service = $(this).children("td:first").next().contents("a").text();
                // go through normal services
                $("tr", "#service_list").each( function(i, normalService) {
                    // check if the service matches with a advanced service
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





// Prevent re-adding the host preset services when browser refreshin
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


////
// Title 
$item_name = db_templates("naming_attr", $host_ID);
$title = '<div>&nbsp;Services &amp; advanced services of '.$item_name.'</div>';


# nav buttons
$detail_navigation =  '<a class="button_back jQ_tooltip" title="host details" href="detail.php?id='.$host_ID.'"></a>';
$detail_navigation .= '<a href="overview.php?class=host"><button class="button_overview jQ_tooltip" title="hosts overview"></button></a>';
if (!empty($detail_navigation) ){
$detail_navigation = '<div id="ui-nconf-icon-bar">'
        .$detail_navigation.
        '</div>';
}


echo NConf_HTML::ui_box_header($title.$detail_navigation);

# box for direct host services
    $output = '<div style="float: left; margin-right: 5px;">';
        $output .= NConf_HTML::title('Services (directly linked)');
    $output .= '</div>';
    $output .= '<div name="help_services"></div>';
    $output .= '<div style="clear: both"></div>';

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
    $output .= '</div>';

    $output .= '<div><img id="service_loading" src="img/working_small.gif" alt="loading"></div>';

    // place for help box (only distance from top)
    $output .= '<div id="page_content"></div>';


    ////
    // Place for service list
    $output .= '<br><div id="service_list" style="display:none"></div><br>';

    // Finish button removed, link now in toolbar
    //$output .= '<br><br>';
    //$output .= '<button onClick="window.location.href=\'overview.php?class=host\'">Finish</button>';

echo NConf_HTML::ui_box_content($output);
unset($output);


###
# ADVANCED SERVICE
###


    # save button
    $output = '<div style="position: absolute; right: 10px;">
                    <div style="float: left">
                        <img id="advanced_services_loading" src="img/working_small.gif" alt="loading" style="display:none; margin-right: 5px;">
                    </div>
                    <div style="float: right">
                        <button id="save"></button>
                    </div>
                </div>';
    $output .= '<div style="float: left; margin-right: 5px;">';
        $output .= NConf_HTML::title('Advanced services (directly linked)');
    $output .= '</div>';
    $output .= '<div name="help_advanced_services"></div>';
    $output .= '<div style="clear: both"></div>';

    // get all advanced services
    $query = 'SELECT id_item,
                attr_value AS service_name
                FROM ConfigItems,ConfigValues,ConfigAttrs,ConfigClasses
                WHERE id_item=fk_id_item
                    AND id_attr=fk_id_attr
                    AND naming_attr="yes"
                    AND ConfigItems.fk_id_class=id_class
                    AND config_class="advanced-service"
                ORDER BY service_name ASC';

    $service_names = db_handler($query, "array", "get all advanced services"); 


    $output .= '<br><select id="fromBox_advanced_services" name="from_advanced_services[]" style="'.CSS_SELECT_MULTI.'" multiple >';

    $services = db_templates("get_services_from_host_id", $host_ID, "advanced-service");

    foreach ($service_names AS $advanced_service) {
        if ( array_key_exists($advanced_service["id_item"], $services ) ){
            $selected_items[] = $advanced_service;
            continue;
        }
        $output .= '<option value='.$advanced_service["id_item"].'>'.$advanced_service["service_name"].'</option>';
    }
    
    $output .= '</select>';

    # fill "selected items" with session or predefiend data
    $output .= '<select multiple name="advanced_services[]" id="toBox_advanced_services">';

    if ( !empty($selected_items) ){
        foreach ($selected_items AS $selected_menu){
            $output .= '<option value='.$selected_menu["id_item"];
            $output .= '>'.$selected_menu["service_name"].'</option>';
        }
    }
    $output .= '</select><br>';

    $output .= '
                <script type="text/javascript">
                    createMovableOptions("fromBox_advanced_services","toBox_advanced_services",500,145,"available items","selected items","livesearch");
                </script>
                ';


//    $output .= '<div class="loading"><img id="advanced_services_loading" src="img/working_small.gif" alt="loading" style="display:none"></div>';





    echo NConf_HTML::ui_box_content($output);
    unset($output);


###
# HOSTGROUP SERVICES
###


    # box for direct host services
    $output = '<div style="float: left; margin-right: 5px;">';
        $output .= NConf_HTML::title('Advanced services (inherited over hostgroups)');
    $output .= '</div>';
    $output .= '<div name="help_hostgroup_services"></div>';
    $output .= '<div style="clear: both"></div>';
    
    // progress indicator / loading
    $output .= '<img id="hostgroup_service_loading" src="img/working_small.gif" alt="loading" style="display:none">';

    // Place for hostgroup service list
    $output .= '<br><div id="hostgroup_service_list" style="display:none"></div><br>';

    echo NConf_HTML::ui_box_content($output);


echo '</div>';

//echo '<div id="result"> </div>';
?>

<!-- Help text content -->
<div id="help_text" style="display: none">
    <div id="help_services" title="services">
        <p>Some text about normal services</p>
    </div>

    <div id="help_advanced_services" title="Advanced Services">
        <div>
        <p>
        Angelo should update this , instead of counting pixels....
        so this text looks nice, but is just spam :D
        </p>

        <p>
        <strong>text</strong><br>
        This datatype is used for simple text attributes. A maximum length may be specified.
        </p>
        <p>
        <strong>password</strong><br>
        This datatype is used for password attributes. Several encryption methods are available. Passwords will not be displayed in the GUI.
        </p>
        <p>
        <strong>select</strong><br>
        This datatype creates a drop-down menu. A list of possible values must be specified.
        </p>
        <p>
        <strong>assign_one</strong><br>
        This datatype creates a drop-down menu that allows an item of any class to be assigned to another one (the selected item will be linked as &quot;parent item&quot; by default).
        </p>
        </div>
    </div>

    <div id="help_hostgroup_services" title="Hostgroup services">
        <p>Some text about Hostgroup services</p>
    </div>

</div>

<?php

mysql_close($dbh);
require_once 'include/foot.php';
?>
