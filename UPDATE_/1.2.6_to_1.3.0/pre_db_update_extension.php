<?php
# pre DB update Extension for v.1.3.0 upgrade
# Allows Users to migrate some special attributes before the update is updated
$pre_status = FALSE;
echo '</table><table width="400">';
if ( isset($_POST["submit"]) AND $_POST["submit"] == "Migrate" ){
    # Migrate logic

    $status = $_POST["submit"];
    switch ($status) {
        case 'Migrate':
        # run migration
            echo table_row_description('Runing migration...', '');
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
                echo table_row_description('FAILED', 'Unfortunately the migration failed. Please check the error log');
                $pre_status = FALSE;
            }else{
                echo table_row_description('Done...', 'The update will now proceed...');
                $pre_status = TRUE;
            }
        break;
      
      default:
        
        break;
    }
}else{
    # Show information and migrate button
    echo table_row_description('Migrate special data', 'There is some migration which you can execute before the DB is updated.');
    echo table_row_description('', '<input type="Submit" value="Migrate" name="submit" align="middle">');
}

?>