<?php
##
##  MESSAGE CONFIG FILE
##


define("TXT_HISTORY_SUBTITLE",      "For a more fine-graned history listing, check the details of the individual object.");

define("TXT_LOGIN",                 "Please login with your ".AUTH_TYPE." account.");
define("TXT_LOGIN_FAILED",          "Authentication failed");
define("TXT_LOGIN_NOT_AUTHORIZED",  "User authenticated, but its group is not authorized to access this tool");
define("TXT_GO_BACK_BUTTON",        "Please go back using the back button!");
define("TXT_UPDATE_SERVICES",       "Would you like to update 'check period', 'notification period' <br>and 'contact groups' of all services assigned to this host?");
define("TXT_UPDATE_SERVICES_LOOSING_VALUES", "These values will be removed from its services if you update your services according to the host:");
define("TXT_DELETE_CHILD_SERVICES", "The following services will also be deleted:");
define("TXT_DB_NO_WRITES",          '<font color="red"><b>DB_NO_WRITES = 1:<br>No DB inserts, modifications or deletions will be performed</b></font>');
define("TXT_NO_RESENT",             "Sorry, the submited infos are not allowed to be resent. Go to the form and submit it.");
define("TXT_DELETED",               "Successfully deleted");
define("TXT_SUBMIT_DISABLED4USER",  "Submit disabled, you are not allowed to add/modify/delete Admin users!");

define("TXT_NAMING_ATTR_CONFLICT",  SHOW_ATTR_NAMING_ATTR_CONFLICT." ATTENTION: There is more than one naming attribute defined!".SHOW_ATTR_NAMING_ATTR_CONFLICT);
define("TXT_NAMING_ATTR_MISSED",    SHOW_ATTR_NAMING_ATTR_CONFLICT." ATTENTION: No naming attribute set. A naming attribute is mandatory!".SHOW_ATTR_NAMING_ATTR_CONFLICT);
define("TXT_NAMING_ATTR_LAST",      SHOW_ATTR_NAMING_ATTR_CONFLICT." ATTENTION: <b>Could not unset naming attribute!</b>".SHOW_ATTR_NAMING_ATTR_CONFLICT."<br>A naming attribute is mandatory!<br>To change the naming attribute, choose a new one and select 'naming attribute' => 'yes'; this will update the old one automatically.");
define("TXT_NAMING_ATTR_CHANGED",   SHOW_ATTR_NAMING_ATTR_CONFLICT." ATTENTION: New 'naming attribute' set. Previous one changed, because only one 'naming attribute' is allowed!".SHOW_ATTR_NAMING_ATTR_CONFLICT);
define("TXT_DEPVIEW_ERROR_LOOP",    "Warning: circular parent/child chain detected");

define("CHECKCOMMAND_ORDER_PRETEXT",                    "Current list is orderer by:");
define("CHECKCOMMAND_ORDER_COMMAND_NAME_ASC",           "checkcommand name (ascending)");
define("CHECKCOMMAND_ORDER_COMMAND_NAME_DESC",          "checkcommand name (descending)");
define("CHECKCOMMAND_ORDER_DEFAULT_SERVICE_NAME_ASC",   "default service name (ascending)");
define("CHECKCOMMAND_ORDER_DEFAULT_SERVICE_NAME_DESC",  "default service name (descending)");

define("TXT_MULTIMODIFY_PARAMS_OF_CHECK_COMMAND_DIFFER",  "You are trying to modify the command parameters of multiple services. Some of the services seem to have a differing amount of command params.
<br>This may lead to unexpected results. Be advised that the same parameters will be applied to ALL services, regardless how many parameters their commands expect.
<br>Also note that the parameter description shown above only applies to that service with the largest amount of paramenters.");
define("TXT_NOTHING_FOUND",                             "no items found");
define("TXT_NOTHING_FOUND_SERVICES_DIRECTLY",           "no services directly linked");
define("TXT_NOTHING_FOUND_ADVANCED_SERVICES_INHERITED", "no advanced-services inherited");

?>
