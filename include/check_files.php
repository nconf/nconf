<?php

###
# Check filesystem if directorites or files are 
# - writable / not writable
# - readable / not readable
# - exists / not exists
# Each check can be also checked to the contrary with additional option
###

# writeable
$check_file_writable        = array('config/', 'output/', 'temp/', 'static_cfg/');
$check_file_not_writable    = array();

#readable
$check_file_readable        = array();
$check_file_not_readable    = array();

# exist
$check_file_exists          = array();

# for security reasons the following files should be removed
$check_file_not_exists      = array('call_ajax.php', 'config/.file_accounts');



### run the check

#check_file('check-function', VARIABLE or ARRAY to check, check should be positiv [TRUE] or negativ [FALSE]
check_file('is_writable', $check_file_writable, TRUE, "File/Directory is not writable: ");
check_file('is_writable', $check_file_not_writable, FALSE, "File/Directory should not be writable: ");
check_file('is_readable', $check_file_readable, TRUE, "File/Directory is not readable: ");
check_file('is_readable', $check_file_not_readable, FALSE, "File/Directory should not be readable: ");
check_file('file_exists', $check_file_exists, TRUE, "File/Directory is missing: ");
check_file('file_exists', $check_file_not_exists, FALSE, "File/Directory still exists, please remove it: ");


# if one or more checks fail, the script will stop in include/head.php with an error message / box
?>
