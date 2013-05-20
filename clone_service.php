<?php

require_once 'include/head.php';

?>

<!-- jQuery part -->
<script type="text/javascript">

    $.fn.nconf_ajax_debug = function() {
        return this.each(function(){
            // Handle ERROR information

            // Handle DEBUG information
            $(this).filter("#ajax_error").appendTo( $("#jquery_error") );
            $("#ajax_error").parent("#jquery_error").show();

            // Handle DEBUG information
            $(this).filter("#ajax_debug").prependTo( $("#jquery_console") );
            $("#jquery_console_parent").show();

        });

    };


    function clone_service(host_id, services, new_service_name){
        $("#toBox option").each( function() {
            $(this).attr("selected","selected");
        });
        var destination_host_ids = $("#toBox").val();
        $.post("call_file.php?ajax_file=service_clone.php&debug=yes", {
            'action': "clone2hosts",
            'item': "service",
            'source_host_id': host_id,
            'destination_host_ids[]': destination_host_ids,
            'service_id': services,
            'new_service_name': new_service_name,
            'username': "<?php echo $_SESSION['userinfos']['username'];?>"
        }, function(data){
            // this will move the debug information to current page
            $(data).nconf_ajax_debug();

            var service_id = $(data).filter("#clone_success").html();
            $(data).filter("#clone_success").appendTo( $("#clone_feedback") );

            $(data).filter("#clone_error").appendTo( $("#clone_error_content") );
            if ( $("#clone_error_content > #clone_error").length > 0){
                $("#clone_error").show("slow");
            }

        });

    }



    $(document).ready(function(){
        // hide continue button
        $( "#continue" ).hide();

        // display progressbar
        $( "#progressbar" ).progressbar({ value: 0 });

        // define some events for the new service name
        $('#services_fromBox, #services_toBox, #services_toBox, span > img').mouseleave(function() {
            $('#services_fromBox').change();
        });
        $('#new_service_name').mouseenter(function() {
            $('#services_fromBox').change();
        });

        // define change event for new service name
        $('#services_fromBox').change(function () {
            // disable new service name, if more than 1 service is selected
            if ( $('#services_toBox option').length > "1" ){
                $('#new_service_name').attr("disabled", "disabled");
                $('#new_service_name').parent().parent().fadeTo('slow', 0.5, function() {
                    // Animation complete.
                });
            }else{
                $('#new_service_name').removeAttr("disabled");
                $('#new_service_name').parent().parent().fadeTo('slow', 1, function() {
                    // Animation complete.
                });
            }
        });

        // continue button
        $('#continue').button({
            icons: {
                primary: "ui-icon-circle-arrow-e"
            }
        }).addClass("ui-button-text-icon-primary");

        // clone action
        $('#clone').button({
            icons: {
                primary: "ui-icon-circle-plus"
            }
        }).addClass("ui-button-text-icon-primary")
        .click( function() {
            var host_id    = $("#host_ID").val();
            var service_id = $("#services_toBox").val();
            var service_count = $("#services_toBox option").length;
            var bar_step = 100/(service_count);
            var bar = 0;
            if ( service_count > "0" && $("#toBox option").length > "0" ){
                $('#clone').unbind("click");
                $("#clone_error").hide("slow");
                $("#feedback").show("slow");

                // handle new service name
                var new_service_name = "";
                if (service_count == "1"){
                    new_service_name = $("#new_service_name").val()
                }
                $("#services_toBox option").each( function(i, val) {
                    // iterate over all services (right select box)
                    var service_id = $(this).val();
                    clone_service( host_id, service_id, new_service_name);
                    bar = bar+bar_step;
                    if (bar >= "99"){
                        // reached 100% disable clone button and show continue button
                        $( "#progressbar > .ui-progressbar-value" ).addClass("ui-corner-right");
                        $('#clone').fadeTo('slow', 0.5)
                        $( "#progressbar > .ui-progressbar-value" ).animate({"width": bar+"%"}, "normal", function() {
                                $( "#continue, #clone_feedback" ).fadeTo('slow', 1);
                        });
                    }else{
                        $( "#progressbar > .ui-progressbar-value" ).animate({"width": bar+"%"}, "normal");
                    }
                });

            }else{
                $("#clone_error_content").html("Please choose at least one service and one destination host!");
                $("#clone_error").show("slow");
            }
            
            // prevent click action    
            return false;

            
            
        });

        
    });



</script>

<!-- END of jQuery part -->


<?php


//delete cache if not resent from clone
if( !empty($_SESSION["go_back_page"]) AND !preg_match('/^clone/', $_SESSION["go_back_page"]) ){
    message ($debug, 'Cleared clone cache' );
    unset($_SESSION["cache"]["clone_service"]);
}
//set_page();
// Check chache
if ( isset($_SESSION["cache"]["clone_service"]) ){
    $cache = $_SESSION["cache"]["clone_service"];
}elseif( !empty($_GET["service_id"]) ){
    $cache["service_id"] = $_GET["service_id"];
}

# Fetch all hosts
$query = 'SELECT fk_id_item,attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses 
                WHERE id_attr=fk_id_attr 
                    AND naming_attr="yes" 
                    AND id_class=fk_id_class 
                    AND config_class="host" 
                ORDER BY attr_value';
$hosts = db_handler($query, "array_2fieldsTOassoc", "get all hosts");



$host_id = $_GET["id"];
$item_name = db_templates("naming_attr", $host_id);


$title = 'Clone Service from host '.$item_name;
echo NConf_HTML::page_title("service", $title);

echo '
  <br>
    <table>
    ';
    echo define_colgroup();
?>
      <tr><td class="middle"><br>services to clone
          </td>
          <td colspan=3>
              <div class="select-container">

                <?php
                echo '<input id="host_ID" type="hidden" name="source_host_id" value="'.$host_id.'">';
                ?>
                <select multiple name="all_services[]" id="services_fromBox">
                <?php
                $services = db_templates("get_services_from_host_id", $host_id);
                foreach ($services as $service_id => $service_name){
                        echo '<option value="'.$service_id.'">'.$service_name.'</option>';
                }
                ?>
                </select>

                <select multiple name="destination_service_ids[]" id="services_toBox">
                </select>

                <script type="text/javascript">
                createMovableOptions("services_fromBox","services_toBox",500,145,'Available services','Selected services',"livesearch");
                </script>

              </div>
          </td>
          <td valign="top" class="middle attention">*</td>
          <td class="desc">You move elements by clicking on the buttons or by double clicking on select box items</td>

        </tr>

      <tr class="assign_many">
          <td class="middle">name of cloned service</td>
          <td>
              <input id ="new_service_name" name="new_service_name" type=text maxlength=250
                value="<?php if (!empty($cache["new_service_name"])) echo $cache["new_service_name"];?>">
          </td>
          <td colspan="4" class="desc">
            set a new service name (only when cloning 1 service)
          </td>
      </tr>
      <tr>
      </tr>
      <tr><td class="middle"><br>clone service to
          </td>
          <td colspan=3>
              <div class="select-container">
                <select multiple name="all_hosts[]" id="fromBox">
                <?php
                foreach ($hosts as $host_id => $host_name){
                        echo '<option value="'.$host_id.'">'.$host_name.'</option>';
                }
                ?>
                </select>

                <select multiple name="destination_host_ids[]" id="toBox">
                </select>

                <!--</form>-->
                <script type="text/javascript">
                createMovableOptions("fromBox","toBox",500,145,'Available hosts','Selected hosts',"livesearch");
                </script>
               </div>

          </td>
          <td valign="top" class="middle attention">*</td>
          <td class="desc">You move elements by clicking on the buttons or by double clicking on select box items</td>

        </tr>

    </table>

<?php
# Tell the Session, send db query is ok (we are coming from formular)
$_SESSION["submited"] = "yes";



echo '<br>';
echo '<div>';
    echo '<button id="clone" class="ui-buttonset">clone services</button>';
    /*
    echo '<a id="clone" href="#">
            clone services
            </a>';*/
    if (!empty($_SESSION["go_back_page"]) ){
        $link = $_SESSION["go_back_page"];
    }else{
        $link = "index.php";
    }
    echo '<a id="continue" href="'.$link.'">continue</a>';
    echo '<br><br>';
    echo '<div id="clone_error" style="display: none;">';
        echo NConf_HTML::show_error('Error:', '<span id="clone_error_content"></span>');
    echo '</div>';

    echo '<div id="feedback" style="display: none;">';
        echo '<div style="width: 300px">';
            echo '<h2>Progress:</h2>';
            echo '<div>
                    <div id="progressbar"></div>
                  </div>';
            echo '<div style="float: left">0%</div>';
            echo '<div style="float: right">100%</div>';
            
        echo '</div>';
        echo '<div style="clear: both;"></div>';
        echo '<br><h2 class="ui-nconf-header ui-widget-header ui-corner-tl ui-corner-tr ui-helper-clearfix">Feedback:</h2>';
        
        echo '<div id="clone_feedback" class="ui-nconf-content ui-widget-content ui-corner-bottom" style="display: none;"></div>';
    echo '</div>';

    
echo '
    </div>';


mysql_close($dbh);
require_once 'include/foot.php';
?>
