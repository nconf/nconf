<?php
require_once 'include/head.php';

$lock_file = 'temp/generate.lock';
$status = check_file('file_exists', $lock_file, TRUE, "File/Directory still exists, please remove it: "); 

# Title
echo NConf_HTML::page_title('generate-config', "Generate Nagios config");

# Container for dynamic AJAX content
echo '<div class="ajax_content"></div>';


if ( $status ){
    # lock file exists

    $lock_file_age = ( time() - filemtime($lock_file) );
    NConf_DEBUG::set($lock_file_age.' seconds', '', "lock file last set since");
    NConf_DEBUG::set(" should be available in " . (600-$lock_file_age) . " seconds", '', 'Next execution');
    NConf_DEBUG::set("Remove the 'generate.lock' in the 'temp' directory", '', 'Force remove lock?');

    # check if file is older than 10min(600sec) so replace it (should not be, because file will be removed)
    # but this will prevent lock file to stay there
    if ( $lock_file_age < 600 ){
        # some one other is generating the config
        NConf_DEBUG::set('Someone else is already generating the configuration.', 'ERROR');
        

        # close page and cancel action
        if ( NConf_DEBUG::status('ERROR') ) {
            echo NConf_HTML::exit_error();
        }

    }else{
        # remove lock file
        $unlink_status = unlink($lock_file);
        if (!$unlink_status){
            NConf_DEBUG::set('removing old lock failed', 'ERROR');
        }
    }
    # if file is older, script will continue, and try to get lock again

}


# create lock
$generate_lock_handle = fopen($lock_file, 'w');
$status = flock($generate_lock_handle, LOCK_EX | LOCK_NB); //lock the file


?>

<!-- jQuery part -->
<script type="text/javascript">

    // jQuery execute generate
    $(document).ready(function(){

    // disable interaction until page is loaded
    var dialog_loading = $('<div class="center"></div>')
        .html('<br><img src="img/working.gif"><h2>Please stand by...</h2><br><br><div id="progressbar"></div>')
        .dialog({
            autoOpen: true,
            title: "Generating config",
            closeOnEscape: false,
            open: function(event, ui) {
                $(".ui-dialog-titlebar-close").hide();
                $(".ui-dialog-titlebar").toggleClass("ui-corner-all ui-corner-top");
            },
            modal: true,
            draggable: false,
            resizable: false,
            height: 161
        });


        // accordion style enhanced to handle all items
        $(".accordion_title").live("click hover", function(event) {
            $(this).nconf_accordion_list(event);
        });

        // set the duration of one progress animation
        var progress_step_animation_duration = 5000;
        var current_percent = 0;
        // progressbar
        var refreshId = setInterval(function() {
            $.ajax({
                url: "temp/generate.lock",
                cache: false,
                dataType: "text",
                type: "POST",
                success: function(data){
                    // create bar if not exist
                    $( "#progressbar" ).not(".ui-progressbar").progressbar({
                        value: 0
                    });

                    // move bar
                    var percent = parseInt( $.trim(data) );
                    //alert("current: "+ current_percent + "   -> read from file: " + percent);
                    if (current_percent >= percent){
                        // progress each interval, even if no feedback
                        // progress status from file must be smaller or match the current value
                        // otherwise the status should be taken on else
                        current_percent += 1;
                        
                        
                    }else{
                        // progress status is biger, go to that current state
                        if ( percent > 100 ){
                            // max size is 100 %
                            percent = 100;
                        }else if( !(percent > 0 ) ){
                            // set percent to 0 if not between 0-100
                            percent = 0;
                        }
                        // save current percent for next interval check
                        current_percent = percent;
                    }

                    var percent_width = current_percent + "%";
                    $( "#progressbar > .ui-progressbar-value" ).animate({width: percent_width}, progress_step_animation_duration, 'linear', function(){
                        if ( percent == 100 ){
                            dialog_loading.dialog('close');
                            clearInterval (refreshId);
                        }
                    });
                }
            });
        }, 5000);




        $("#maincontent > .ajax_content").load("call_file.php", {
            'ajax_file': "exec_generate_config.php" ,
            'debug': "yes",
            'username': "<?php echo $_SESSION['userinfos']['username'];?>"
        }, function(data){
            // generate finished, so close progress bar
            clearInterval (refreshId);
            if ( $("#progressbar").is(".ui-progressbar") ){
                $( "#progressbar > .ui-progressbar-value" ).stop(true).animate({width: "100%"}, "slow", 'linear', function(){
                    dialog_loading.dialog('close');
                });
            }else{
                dialog_loading.dialog('close');
            }
        });
    });

</script>
<!-- END of jQuery part -->

<?php

//mysql_close($dbh);
require_once 'include/foot.php';

?>

