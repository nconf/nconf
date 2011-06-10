
<!-- jQuery part -->
<script type="text/javascript">
    $(document).ready(function(){
        $("#loading").hide();


        // loading icon on ajax requests
        $("#loading").ajaxStart(function(){
            $(this).show();
        });
        $("#loading").ajaxStop(function(){
            $(this).hide();
        });


    });

</script>


<?php

    echo NConf_HTML::ui_box_header("Configuration Deployment");
    echo NConf_HTML::ui_box_content();

    echo '<div style="height: 20px;">
            <div id="loading">
                <img src="img/working_small.gif"> in progress...
            </div>
          </div>';

    // Load deployment class and create object
    require_once("class.deployment.php");
    require_once("class.deployment.modules.php");

    // Load the NConf Deployment class
    // It loads all the modules and handles the deployment basic stuff
    $deployment = new NConf_Deployment();

    // Loads the configuration of the user
    // nconf/conf/deployment.ini
    $deployment->import_config();

    if ( NConf_DEBUG::status('ERROR') ) {
        // Show error if set
        echo NConf_HTML::limit_space( NConf_HTML::show_error() );
    }else{
        // Start deploying the files
        $deployment->run_deployment();
    }

    echo '</div>';

?>
