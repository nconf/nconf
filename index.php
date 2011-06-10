<?php
###
###  WELCOME TO NConf, configuration files are located here : config/..
###
## do not change anything other
##

# look if configuration files exist
if (file_exists('config/nconf.php') ){
    require_once 'main.php';
    require_once 'include/head.php';

    # for all pages show the login or if logged in only version info
    require_once("include/login_form.php");

    require_once 'include/foot.php';
}else{
    # NConf not yet installed/configured
    $nconfdir = dirname( $_SERVER["SCRIPT_FILENAME"] );
    require_once('config.orig/authentication.php');
    require_once('include/functions.php');
    require_once('include/includeAllClasses.php');
    require_once('config.orig/nconf.php');
    require_once 'include/head.php';
    
}



###
### Finish
### anything is loaded until here
###
?>
