<?php

###
# Check if all must-have constants and arrays exist / are defined in the configuration
###

# configure constants here
$mandatory_constants       = array('NCONFDIR','NAGIOS_BIN','TEMPLATE_DIR','DEBUG_MODE','DB_NO_WRITES','REDIRECTING_DELAY','ALLOW_DEPLOYMENT','PASSWD_ENC','PASSWD_DISPLAY','PASSWD_HIDDEN_STRING','DBHOST','DBNAME','DBUSER','DBPASS','AUTH_ENABLED','AUTH_TYPE','GROUP_ADMIN','SELECT_VALUE_SEPARATOR');

$mandatory_constants_empty = array('OS_LOGO_PATH','AUTH_FEEDBACK_AS_WELCOME_NAME','GROUP_USER','GROUP_NOBODY','LDAP_SERVER','LDAP_PORT','BASE_DN','USER_REPLACEMENT','GROUP_DN','ADMIN_GROUP','USER_GROUP','AUTH_SQLQUERY_USER','AUTH_SQLQUERY_ADMIN');

# configure arrays here
$mandatory_arrays          = array();
$mandatory_arrays_empty    = array('STATIC_CONFIG', 'SUPERADMIN_GROUPS', 'ONCALL_GROUPS');

# run the check
#check_var('constant' or php check-function, VARIABLE or ARRAY to check, allow_empty_value[TRUE/FALSE])
check_var('constant', $mandatory_constants, FALSE);
check_var('constant', $mandatory_constants_empty, TRUE);
check_var('is_array', $mandatory_arrays, FALSE);
check_var('is_array', $mandatory_arrays_empty, TRUE);

# if one or more checks fail, the script will stop in include/head.php with an error message / box
?>
