<?php
    require_once 'main.php';

    history_add("general", "config", "generating...");

    // predefine status as OK
    $status = "OK";

    // check if "temp" dir is writable
    if(!is_writable(NCONFDIR."/temp/")){
        $content = "Could not write to 'temp' folder. Cannot generate config.";
        NConf_DEBUG::set($content, 'ERROR');
        echo NConf_HTML::limit_space(
            NConf_HTML::show_error('Error')
        );
        exit;
    }

    // check if "output" dir is writable
    if(!is_writable(NCONFDIR."/output/")){
        $content = "Could not write to 'output' folder. Cannot store generated config.";
        NConf_DEBUG::set($content, 'ERROR');
        echo NConf_HTML::limit_space(
            NConf_HTML::show_error('Error')
        );
        exit;
    }

    // check if generate_config script is executable
    if(!is_executable(NCONFDIR."/bin/generate_config.pl")){
        $content = "Could not execute generate_config script. <br>The file '".NCONFDIR."/bin/generate_config.pl' is not executable.";
        NConf_DEBUG::set($content, 'ERROR');
        echo NConf_HTML::limit_space(
            NConf_HTML::show_error('Error')
        );
        exit;
    }

    // check if the Nagios / Icinga binary is executable
    exec(NAGIOS_BIN,$bin_out);
    if(!preg_match('/Nagios|Icinga/',implode(' ',$bin_out))){
        $content = "Error accessing or executing Nagios / Icinga binary '".NAGIOS_BIN."'. <br>Cannot run the mandatory syntax check.";
        NConf_DEBUG::set($content, 'ERROR');
        echo NConf_HTML::limit_space(
            NConf_HTML::show_error('Error')
        );
        exit;
	}

    // check if existing "output/NagiosConfig.tgz" is writable
    if(file_exists(NCONFDIR."/output/NagiosConfig.tgz" and !is_writable(NCONFDIR."/output/NagiosConfig.tgz"))){
        $content = "Cannot rename ".NCONFDIR."/output/NagiosConfig.tgz. Access denied.";
        NConf_DEBUG::set($content, 'ERROR');
        echo NConf_HTML::limit_space(
            NConf_HTML::show_error('Error')
        );
        exit;
    }

    // check if static config folder(s) are readable
    foreach ($STATIC_CONFIG as $static_folder){
        if(!is_readable($static_folder)){
            $content = "<br>Could not access static config folder '".$static_folder."'.";
            $content .= "<br>Check your \$STATIC_CONFIG array in 'config/nconf.php'.";
            NConf_DEBUG::set($content, 'ERROR');
            echo NConf_HTML::limit_space(
                NConf_HTML::show_error('Error')
            );
            exit;
        }
    }

    // fetch all monitor and collector servers from DB
    $servers = array();
    $query = "SELECT fk_id_item AS item_id,attr_value,config_class
                  FROM ConfigValues,ConfigAttrs,ConfigClasses
                  WHERE id_attr=fk_id_attr
                      AND naming_attr='yes'
                      AND id_class=fk_id_class
                      AND (config_class = 'nagios-collector' OR config_class = 'nagios-monitor') 
                  ORDER BY attr_value";

    $result = db_handler($query, "result", "fetch all monitor and collector servers from DB");

    while ($entry = mysql_fetch_assoc($result) ){
        $renamed = preg_replace('/-|\s/','_',$entry["attr_value"]);

        if($entry["config_class"] == 'nagios-collector'){
            $renamed = preg_replace('/Nagios|Icinga/i','collector',$renamed);
        }
        array_push($servers, $renamed);
    }

    # GENERATE CONFIG
    echo NConf_HTML::title('Generate config log:');

    echo '<div>
            <pre>';
            //system(NCONFDIR."/bin/generate_config.pl");
            $command = NCONFDIR."/bin/generate_config.pl";
            $output = array();
            exec($command, $output);


            // print each line
            foreach ($output AS $line){
                // Filter some lines:
                if ( empty($line)) continue;
                if ( strpos($line, "Copyright")) continue;
                if ( strpos($line, "Initializing")) continue;

                // Look for error
                if ( strstr($line, "ERROR") ){
                    $status = "error";
                }

                // print lines
                echo "<br>$line";
            }

            
    echo    '</pre>
        </div>';

        
    // create tar file
    system("cd ".NCONFDIR."/temp; tar -cf NagiosConfig.tar global ".implode(" ", $servers));

    // add folders with static config to tar file           
    foreach ($STATIC_CONFIG as $static_folder){
       if(!is_empty_folder($static_folder) and is_empty_folder($static_folder) != "error"){
           $last_folder = basename($static_folder);
           system("cd ".$static_folder."; cd ../; tar -rf ".NCONFDIR."/temp/NagiosConfig.tar ".$last_folder);
       }
    }

    // compress tar file
    system("cd ".NCONFDIR."/temp; gzip NagiosConfig.tar; mv NagiosConfig.tar.gz NagiosConfig.tgz");

    echo '<br><br>';
    echo NConf_HTML::title('Running syntax check:');

    //$icon_count = 1;
    echo '<div class="ui-accordion ui-widget ui-helper-reset ui-accordion-icons ui-nconf-accordion-list">';


    ### SYNTAX CHECK
    # now run tests on all generated files
    $details = '';
    $break = '&nbsp;&nbsp;-&nbsp;&nbsp;';
    foreach ($servers as $server){
        $server_str = preg_replace("/\./", "_", $server);

        # run test
        exec(NAGIOS_BIN." -v ".NCONFDIR."/temp/test/".$server.".cfg",$srv_summary[$server]);

        $total_msg = '';
        $count=0;
        $i = 0;
        foreach($srv_summary[$server] as $line){
            if( preg_match("/^Total/",$line) ){
                # add splitter between messages
                $total_msg .= ( $i > 0 ) ? $break : '';
                $i++;
                $total_msg .= $line;
                $count++;
                if( preg_match("/Errors/",$line) && !preg_match('/Total Errors:\s+0/',$line)){
                    $status = "error";
                }
            }
        }
        if($count==0){
            $total_msg .= "Error generating config";
            $status = "error";
        }


        $total_msg = '<span class="notBold accordion_header_right">'.$total_msg.'</span>';
        // print server info
        $title = '<span class="ui-icon ui-icon-triangle-1-e"></span><a href="#">'.$server_str.$total_msg.'</a>';
        echo NConf_HTML::title($title, 3, 'class="accordion_title ui-accordion-header ui-helper-reset ui-state-default ui-corner-top ui-corner-bottom"');
        echo '<div class="accordion_content ui-widget-content ui-corner-bottom monospace" style="display: none;">';
            foreach($srv_summary[$server] as $line){
                if ( preg_match("/^Error:/",$line) ){
                    echo '<span class="red">'.$line.'</span>';
                }elseif ( preg_match("/^Warning:/",$line) ){
                    echo '<span class="orange">'.$line.'</span>';
                }else{
                    echo $line;
                }
                echo '<br>';
            }
            echo '<br>';
        echo '</div>';
        
    }


    echo '</div><br>';

    if($status == "OK"){
        history_add("general", "config", "generated successfully");

        // Move generated config to "output" dir
        if(file_exists(NCONFDIR."/output/NagiosConfig.tgz")){
            system("mv ".NCONFDIR."/output/NagiosConfig.tgz ".NCONFDIR."/output/NagiosConfig.tgz.".time());
        }
        system("mv ".NCONFDIR."/temp/NagiosConfig.tgz ".NCONFDIR."/output/");
        system("rm -rf ".NCONFDIR."/temp/*");

        if(ALLOW_DEPLOYMENT == 1){
            echo NConf_HTML::title('Deploy generated config:');

            // check  if new deployment is configured
            $deployment_config = NCONFDIR.'/config/deployment.ini';
            $deployment_info = FALSE;
            if ( !file_exists($deployment_config) ){
                $deployment_info = TRUE;
            }elseif( is_readable($deployment_config) ){
                $ini_array = parse_ini_file($deployment_config, TRUE);
                if ( empty($ini_array) ){
                    $deployment_info = TRUE;
                }
            }
            if ($deployment_info){
                $content = 'The generated configuration has been written to the "nconf/output/" directory.<br>
                            To set up more sophisticated deployment functionality, please edit your "config/deployment.ini" file accordingly.<br>
                            For a complete list of available deployment options, refer to the online documentation on 
                            <a href="http://www.nconf.org" target="_blank">www.nconf.org</a>.';
                echo NConf_HTML::limit_space(
                    NConf_HTML::show_highlight('Note', $content)
                );
            }else{
                // Show deployment button
                echo "<form method=\"POST\" action=\"call_file.php?module_file=deployment/main.php\" id=buttons>";
                    echo '<input type=hidden name=status value="'.$status.'">';
                    echo '<br><input type="submit" name="submit" value="Deploy" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">';
                echo "</form><br>";
            }

        }else{
            // Simply show success message
            echo "<b>Changes updated successfully.</b><br><br>";
        }

    }else{
        history_add("general", "config", "generate failed with syntax errors");
        // Remove generated config - syntax check has failed
        if(DEBUG_MODE == 1){
            // Move generated config to "output" dir, but tag it as FAILED
            system("mv ".NCONFDIR."/temp/NagiosConfig.tgz ".NCONFDIR."/output/NagiosConfig_FAILED.tgz.".time());
        }
        // Remove generated config
        system("rm -rf ".NCONFDIR."/temp/*");
        $content = "Deployment not possible due to errors in configuration.";
        echo NConf_HTML::limit_space(
            NConf_HTML::show_error('Error', $content)
        );
    }

    mysql_close($dbh);

?>
