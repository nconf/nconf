<?php
# pre DB-update extension for v.1.3.0 upgrade
# Allows users to migrate some attributes before the DB is updated
$pre_status = FALSE;
echo '</table><br><table width="400">';
if ( isset($_POST["submit"]) AND $_POST["submit"] == "Convert" ){
    # Convert logic

    $status = $_POST["submit"];
    switch ($status) {
        case 'Convert':
        # run migration
            echo table_row_description('Runing conversion script...', '');
            $feedback = '<pre>';
            $command = NCONFDIR."/bin/convert_timeperiods_collectors.pl";
            $output = array();
            exec($command, $output);

            # print each line
            foreach ($output AS $line){
                # Filter some lines:
                if ( empty($line)) continue;

                # Look for error
                if ( strstr($line, "ERROR") ){
                    $status = "error";
                }

                # print lines
                $feedback .= "<br>$line";
            }
                
            $feedback .= '</pre>';
                  
            # Print feedback
            echo table_row_description('', $feedback);
            if ($status == "error") {
                echo '</table><table width="400">';
                echo table_row_check('Unfortunately there have been errors during conversion! This can have multiple reasons. NConf has not been updated yet.<br>If you wish to debug manually (e.g. run script on command line), exit now.<br><br>If you wish to continue the update without converting your data, click "Next".<br><br>', FALSE );
                # Display Convert button again, to allow users of 1.2.5 or earlier versions to change config and retry convert.
                if ( !empty($_SESSION["base_version"]) AND version_compare($_SESSION["base_version"], "1.2.5", '<=') ){
                    echo table_row_description('1.2.4 or 1.2.5 updater:', 'Looks like you are updating from 1.2.5 or earlier, please read the corresponding README files or the online release notes of the <i>releases your are skipping</i> to add the missing config variables. Then you can try again to convert your data:<br> -> <a href="http://www.nconf.org/dokuwiki/doku.php?id=nconf:download:releasenotes" target="_blank">Link to Release Notes</a>');
                    echo table_row_description('', '<input type="Submit" value="Convert" name="submit" align="middle">');
                }
                $pre_status = FALSE;
            }else{
                echo table_row_description('Done.', 'The update will now proceed...');
                $pre_status = TRUE;
            }
        break;
      
      default:
        
        break;
    }
}else{
    # Show information and migrate button
    echo table_row_description('Convert timeperiods, nagios-collector and monitor parameters:', 
'In NConf 1.3 certain attributes belonging to timeperiods, nagios-collector and nagios-monitor servers have been removed.
Please refer to the "Release Notes" for a complete list of these attributes. -> <a href="http://www.nconf.org/dokuwiki/doku.php?id=nconf:download:releasenotes" target="_blank">Link to Release Notes</a>
<br><br>
If you click "Next", the update process will proceed to remove these attributes. Prior to removing them, you have the chance to "Convert" these parameters into host- and service-templates. 
That way, the original parameters will still be applied to hosts and services, but it will be done using ordinary Nagios inheritance functionality.
<br><br>
<b>If prior to the update you weren\'t using any of the mentioned attributes, or if you don\'t wish for any auto-created templates,
then you do not need to run the conversion. It is optional.</b>
<br><br>
Click "Convert" to run the conversion script.<br>
Click "Next" to proceed without running it.');
    echo table_row_description('', '<input type="Submit" value="Convert" name="submit" align="middle">');
}

?>
