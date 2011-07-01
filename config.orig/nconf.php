<?php
##
## NConf configuration
##

#
# Application settings
#

# The directory where NConf is located
define('NCONFDIR',     $nconfdir);
# manuall installation instruction:
# please replace the  $nconfdir  with the path to nconf as follows:
#define('NCONFDIR',     "/var/www/nconf");

# The path to the directory with the OS logo icons
define('OS_LOGO_PATH', "img/logos");

# The path to the Nagios binary. The binary is needed in order to run tests on the generated config.
# This path should either point to the original binary (if Nagios is installed on the same host), 
# to a copy of the binary (copy it to the bin/ folder), or to a symbolic link. Make sure the binary 
# is executable for the webserver user.
define('NAGIOS_BIN',   "/var/www/nconf/bin/nagios");

#
# Update check
# If you don't want NConf to check for updates, you can disable it here.
# When enabled, NConf will access http://update.nconf.org and will display information about the latest available version.
# This is done over your browser, so the server where NConf is running on does not need Internet access.
# NO information about your environment and NO NConf related info will be sent or made available to others!
#
define('CHECK_UPDATE', 1);

#
# Design template
#
define('TEMPLATE_DIR', "nconf_fresh");

#
# Debug
#
define("DEBUG_MODE",     0); # [0|1]
define("DB_NO_WRITES",   0); # [0|1]
define("DEBUG_GENERATE", 3); # [1=ERROR|2=WARN|3=INFO|4=DEBUG|5=TRACE]

#
# Automatic forwarding when deleting or modifying (seconds)
#
define('REDIRECTING_DELAY', "1");

#
# Config deployment
#
define('ALLOW_DEPLOYMENT', 1);

#
# Static Nagios configuration files
#
# CAUTION: Static files will not be syntax checked by NConf! 
# List of folders containing additional files that you would like to make editable within NConf (basic text editing). 
# All folders listed here will be included in the output file, together with the generated config. 
# We recommend you to copy your static files into the 'nconf/static_cfg' folder.
$STATIC_CONFIG = array("static_cfg");

# If security permits it, you could make your active Nagios configuration editable in NConf directly. 
# We discourage users from doing this though, because there is a risk that they could accidentally damage their Nagios configuration.
#$STATIC_CONFIG = array("static_cfg", "/etc/nagios");

#
# These groups will always be added to any host or service, regardless of what is linked in the GUI. 
#
$SUPERADMIN_GROUPS = array ("+admins");

#
# List of mandatory contact groups for all hosts and services. User won't be able to save changes if 
# he hasn't assigned at least one of these groups. If empty, no contact group is mandatory.
#
$ONCALL_GROUPS = array ();

###
###  PASSWORD ATTRIBUTES 
###
###  Default password attribute encryption
###  Will be used when writing passwords to database, and is also used by certain auth modules (login)
###  !!!   If you change this value, you have to update all the passwort attributes already set, 
###  !!!   because the old value will remain in db with the previous encryption.
###  !!!   Otherwise you won't be able to log in because the encryption is different.
###
###  possible values: [clear|crypt|md5|sha]
###

### clear text / no encryption / plain text
define('PASSWD_ENC', "clear");

### CRYPT
#define('PASSWD_ENC', "crypt");

### MD5
#define('PASSWD_ENC', "md5");

### SHA
#define('PASSWD_ENC', "sha");

#
# display password
# 1: display password attr as is
# 0: pwd will be replaced with chars defined in next constant (PASSWD_HIDDEN_STRING) (so password can't be seen)
#
define('PASSWD_DISPLAY', 0);

#
# if PASSWD_DISPLAY is 0 "AND" PASSWD_ENC is "clear", show pw as defined here
#
define('PASSWD_HIDDEN_STRING', "********");

###
###  end of PASSWORD ATTRIBUTES
###



#
# standard quantity on overview
# this will set the default amount of entries shown on the overview
#
define('OVERVIEW_QUANTITY_STANDARD', "25");

#
# value separator for attributes of type "select"
#
define('SELECT_VALUE_SEPARATOR', "::");

?>
