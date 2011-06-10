<?php
# V 1.2.7 lookup
# are the new fields existing
$query = 'SHOW COLUMNS FROM ConfigAttrs;';
$check_127_result = mysql_query($query, $dbh);
while ($row = @mysql_fetch_assoc($check_127_result)){
    if ( ($row["Field"] == "link_bidirectional") AND ($row["Type"] == "enum('yes','no')") ){
        $installed_version = "1.2.7";
    }
}

?>
