<?php
# V 1.2.4 lookup
$query = 'SELECT id_attr FROM ConfigAttrs LIMIT 1;';
$check_124 = mysql_query($query, $dbh);
if ($check_124){
    $installed_version = "1.2.4";
}

# V 1.2.5 lookup
# are the new fields existing
$query = 'SHOW COLUMNS FROM ConfigClasses;';
$check_125_result = mysql_query($query, $dbh);
while ($row = @mysql_fetch_assoc($check_125_result)){
    if ( ($row["Field"] == "out_file") OR ($row["Field"] == "nagios_object") ){
        $installed_version = "1.2.5";
    }
}

?>
