<?php
# Extension for v.1.3.0 upgrade
# Allows Users to migrate some special attributes.
echo '</table><table width="500">';

if ( !isset($_POST["migrate"]) ){
    # Show information and migrate button
    echo table_row_description('Migrate special data', 'There is some migration which you can execute.');
    echo table_row_description('', '<input type="Submit" value="Migrate" name="migrate" align="middle">');
}else{
    # Migrate logic

    $status = $_POST["migrate"];
    switch ($status) {
        case 'Migrate':
        # run migration
            echo table_row_description('Runing migration...');
            $feedback = '<pre>';
            //system(NCONFDIR."/bin/generate_config.pl");
            $command = NCONFDIR."/bin/covert-timeperiods_collectors.pl -h";
            $output = array();
            exec($command, $output);

            // print each line
            foreach ($output AS $line){
                // Filter some lines:
                if ( empty($line)) continue;

                // Look for error
                if ( strstr($line, "ERROR") ){
                    $status = "error";
                }

                // print lines
                $feedback .= "<br>$line";
            }
                
            $feedback .= '</pre>';
                  
            # Print feedback
            echo table_row_description('', $feedback);
            echo table_row_description('Done...', 'You can now proceed.');
        
        break;
      
      default:
        
        break;
    }
}

?>