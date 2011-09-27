<?php
# V 1.3.0 lookup
# are the new fields existing
$query = 'SHOW COLUMNS FROM ConfigAttrs;';
$check_130_result = mysql_query($query, $dbh);
while ($row = @mysql_fetch_assoc($check_130_result)){
    if ( ($row["Field"] == "link_bidirectional") AND ($row["Type"] == "enum('yes','no')") ){
        $installed_version = "1.3.0";
    }
}

?>
