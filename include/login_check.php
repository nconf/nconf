<?php
# USER login default
# for other methods, expand this file and configure it in the config part
# --> AUTH_TYPE

# information what is needed after this script:
# - check username and pw
# - set $_SESSION['group'] to GROUP_USER or GROUP_ADMIN
# - optional parameters
#   - $_SESSION['username'] for "welcome username, and history entries"

NConf_DEBUG::open_group("Authentication");
# authentication type
message($debug, "Authentication type: ".AUTH_TYPE);
message($debug, "Encryption type: ".PASSWD_ENC);

# Handle loginname
if ( defined("AUTH_METHOD") AND AUTH_METHOD == "basic") {
    message($debug, "Auth method: ".AUTH_METHOD);
    $user_loginname = $_SERVER['PHP_AUTH_USER'];
    $_POST["password"] = $_SERVER['PHP_AUTH_PW'];
}else{
    $user_loginname = $_POST["username"];
}



# prepare password function
function prepare_password ($password, $clean = FALSE){
    # if encryption is also in password, it has to be in UPPERCASE ( {crypt} -> {CRYPT}, {MD5} etc...
    if ( preg_match('/(^\{.*\})(.*)/', $password, $matched) ){
        # will find [0]:whole string, [1]:crypt type, [2]:password
        $crypt = strtoupper($matched[1]);
        $pw = $matched[2];

        if ($crypt == "{CLEAR}" OR $clean == TRUE){
            # {Clear} info is not needed. cut away!
            $password = $pw;
        }else{
            $password = $crypt.$pw;
        }
    }

    return $password;

}

# See what encryption is supported, perhaps for later use...
# (PHP 5 >= 5.1.2, PECL hash >= 1.1)
#NConf_DEBUG::set(hash_algos(), 'DEBUG', "Available hash algorithms");
#
# echo("DES is " . CRYPT_STD_DES."<br>Extended DES is ".CRYPT_EXT_DES."<br>MD5 is ".CRYPT_MD5."<br>BlowFish is ".CRYPT_BLOWFISH);


##
##
##
##############################################################################################
if (AUTH_TYPE == "file"){
    # Read file
    $filename = "config/.file_accounts.php";
    if ( (file_exists($filename)) AND ( $file = fopen($filename, "r") ) ){
        while ( $row = fgets($file) ) {
            # Do not use commented rows(#) or blank rows
            if ( $row != "" AND !preg_match("/^\s*(#|\/\*|\*\/|<\?|\?>)/", $row) ){
                $user = explode("::", $row);
                # check uppercase crypt part, remove {CLEAR} if exists
                $password = prepare_password($user[1], TRUE);
    
                $user_array[$user[0]] = array("password" => $password,     "group" => $user[2],   "name" => $user[3]);
            }
        }
        fclose($file);
        # Authentification
        if ( isset($user_array["$user_loginname"]) ){
            message($debug, "existing pw is: ".$user_array[$user_loginname]["password"]);
            $user_pwd = encrypt_password($_POST["password"], FALSE, $user_array[$user_loginname]["password"]);
            if ( $user_array[$user_loginname]["password"] == $user_pwd ){
                #pw ok, set group
                $_SESSION['group']      = $user_array[$user_loginname]["group"];
 
                # get Welcome name
                if ( (AUTH_FEEDBACK_AS_WELCOME_NAME == 1) AND !empty($user_array[$user_loginname]["name"]) ){
                    $_SESSION["userinfos"]['username']   = $user_array[$user_loginname]["name"];
                }else{
                    $_SESSION["userinfos"]['username']   = $user_loginname;
                }
            }else{
                #PW not ok, login failed
                message('ERROR', TXT_LOGIN_FAILED);
            }
        }else{
            #User not found
            message('ERROR', TXT_LOGIN_FAILED);
        }
    
    }else{
        #FILE not found
        message('ERROR', "Account-file not found : $filename");
    }


##############################################################################################

}elseif (AUTH_TYPE == "sql"){
    ##########
    # login check function
    //function auth_by_sql($username, $passwd, $sqlquery){
    function auth_by_sql($sqlquery, $login = FALSE){
        # Connect to external database if given
        if ( defined("AUTH_DBNAME") ){
            # if AUTH config is given, use it
            $auth_db_link = mysql_connect(AUTH_DBHOST,AUTH_DBUSER, AUTH_DBPASS, TRUE);
            mysql_select_db(AUTH_DBNAME, $auth_db_link);
            $result = db_handler($sqlquery, 'getOne', "Authentication by sql");
            mysql_close($auth_db_link);
        }else{
            # otherwise just use the NConf DB connection
            $result = db_handler($sqlquery, 'getOne', "Authentication by sql using NConf DB");
        }

        if ($result AND $login === TRUE) {
            # get Welcome name
            if ( (AUTH_FEEDBACK_AS_WELCOME_NAME == 1) AND !empty($result) ){
                $_SESSION["userinfos"]['username'] = $result;
            }else{
                $_SESSION["userinfos"]['username']   = $_POST["username"];
            }
            return TRUE;
        }elseif ($result AND $login === FALSE) {
            return $result;
        }else{
            return FALSE;
        }

    }
    ##########

    if (PASSWD_ENC == "crypt"){
       $sql_get_password = 'SELECT attr_value AS user_password, fk_id_item AS user_id
                FROM ConfigAttrs, ConfigValues, ConfigClasses
                WHERE id_attr = fk_id_attr
                AND id_class = fk_id_class
                AND config_class = "contact"
                AND attr_name = "user_password"
                HAVING fk_id_item = ( 
                    SELECT fk_id_item
                    FROM ConfigAttrs, ConfigValues, ConfigClasses
                    WHERE id_attr = fk_id_attr
                    AND id_class = fk_id_class
                    AND config_class = "contact"
                    AND fk_id_item = user_id
                    AND attr_name = "contact_name"
                    AND attr_value = "'.escape_string($user_loginname).'" )'; 
        $user_password = auth_by_sql($sql_get_password);
        # clean {CRYPT}
        $password = prepare_password($user_password, TRUE);
        # encrypt password with saved password (as salt)
        $user_pwd = encrypt_password($_POST["password"], TRUE, $password);
        
    }else{
        $user_pwd = encrypt_password($_POST["password"]);
    }

    # Prepare querys
    $auth_sqlquery_USER = AUTH_SQLQUERY_USER;
    $auth_sqlquery_USER = str_replace("!!!USERNAME!!!", $user_loginname, $auth_sqlquery_USER);
    $auth_sqlquery_USER = str_replace("!!!PASSWORD!!!", $user_pwd, $auth_sqlquery_USER);
    if ( defined("AUTH_SQLQUERY_ADMIN") ){
        $auth_sqlquery_ADMIN = AUTH_SQLQUERY_ADMIN;
        $auth_sqlquery_ADMIN = str_replace("!!!USERNAME!!!", $user_loginname, $auth_sqlquery_ADMIN);
        $auth_sqlquery_ADMIN = str_replace("!!!PASSWORD!!!", $user_pwd, $auth_sqlquery_ADMIN);
    }

    # Authentification
    if ( ( defined("AUTH_SQLQUERY_ADMIN") ) AND auth_by_sql($auth_sqlquery_ADMIN, TRUE) ){
        $_SESSION['group'] = GROUP_ADMIN;
        NConf_DEBUG::set("admin", 'DEBUG', 'Group permissions:');
    }elseif ( auth_by_sql($auth_sqlquery_USER, TRUE) ){
        $_SESSION['group'] = GROUP_USER;
        NConf_DEBUG::set("user", 'DEBUG', 'Group permissions:');
    }else{
        message('ERROR', TXT_LOGIN_FAILED);
    }

    # needed database reload, otherwise the connection is lost
    relaod_nconf_db_connection();

##############################################################################################

}elseif (AUTH_TYPE == "ldap") {
    $ldapconnection = ldap_connect(LDAP_SERVER, LDAP_PORT);
    ldap_set_option($ldapconnection, LDAP_OPT_PROTOCOL_VERSION, 3);

    # Check ldap connection
    if($ldapconnection) {
        NConf_DEBUG::set("success", 'DEBUG', 'ldap connection');

        # Try to logon user to ldap
        $ldap_user_dn = str_replace(USER_REPLACEMENT,$user_loginname,BASE_DN);
        NConf_DEBUG::set($ldap_user_dn, 'DEBUG', 'ldap user dn');

        $user_pwd = $_POST["password"];
        $ldap_response = @ldap_bind($ldapconnection, $ldap_user_dn, $user_pwd);
        if($ldap_response and $user_loginname and $user_pwd) {
            NConf_DEBUG::set("success", 'DEBUG', 'ldap bind');
            # If user login was successfull, look for group
            # admins are in group : ADMIN_GROUP
            # normal nconf user are in group : USER_GROUP
            # all other do not have access

            # AdminUsers
            $sr = ldap_search($ldapconnection, GROUP_DN, ADMIN_GROUP);
            $results = ldap_get_entries($ldapconnection,$sr);
            # debug
            $debug_entry = NConf_HTML::swap_content($results, "<b>LDAP</b> ldap_get_entries:", FALSE, FALSE);
            message($debug, $debug_entry);

            
            $Admin_user_array = $results[0]["memberuid"];
            # remove field count
            unset($Admin_user_array["count"]);


            # BasicUsers
            $sr = ldap_search($ldapconnection, GROUP_DN, USER_GROUP);
            $results = ldap_get_entries($ldapconnection,$sr);
            $Basic_user_array = $results[0]["memberuid"];
            # remove field count
            unset($Basic_user_array["count"]);


            # Users Infos
            $justthese = array("cn");
            //$justthese = array("cn", "description", "uid");
            $sr = ldap_read($ldapconnection, $ldap_user_dn, "(objectclass=*)", $justthese);
            $results = ldap_get_entries($ldapconnection,$sr);

            # get Welcome name
            if ( (AUTH_FEEDBACK_AS_WELCOME_NAME == 1) AND !empty($results[0]["cn"][0]) ){
                $_SESSION["userinfos"]["username"]  = $results[0]["cn"][0];
            }else{
                $_SESSION["userinfos"]['username']  = $user_loginname;
            }

            //$_SESSION["userinfos"]["useremail"] = $results[0]["description"][0];
            //$_SESSION["userinfos"]["uid"]       = $results[0]["uid"][0];
     
            #Check if user is in Basic userlist
            #or in Admin userlist
            if (in_array($user_loginname, $Admin_user_array) ){
                $_SESSION['group'] = GROUP_ADMIN;
                message($info, $_SESSION["group"].' access granted', "yes");
            }elseif (in_array($user_loginname, $Basic_user_array) ){
                $_SESSION['group'] = GROUP_USER;
                message($info, $_SESSION["group"].' access granted', "yes");
            }else{
                message('ERROR', TXT_LOGIN_NOT_AUTHORIZED);
            }
            

        } else {

            NConf_DEBUG::set("failed", 'DEBUG', 'ldap bind');
            message('ERROR', TXT_LOGIN_FAILED);

        }


    } else {

        NConf_DEBUG::set("could not connect", 'DEBUG', 'ldap connection');
        message('ERROR', "Cannot connect to LDAP server");

    }


}elseif (AUTH_TYPE == "ad_ldap") {
    $ldapconnection = ldap_connect(AD_LDAP_SERVER, AD_LDAP_PORT);
    NConf_DEBUG::set(AD_LDAP_SERVER, 'DEBUG', 'AD LDAP SERVER');
    ldap_set_option($ldapconnection, LDAP_OPT_PROTOCOL_VERSION, 3);

    # Try to logon user to ldap
    $ldap_user_dn = str_replace(USER_REPLACEMENT,$user_loginname,AD_BASE_DN);
    NConf_DEBUG::set($ldap_user_dn, 'DEBUG', 'ldap user dn');

    $user_pwd = $_POST["password"];
    $ldap_response = @ldap_bind($ldapconnection, $ldap_user_dn, $user_pwd);

    if($ldap_response and $user_loginname and $user_pwd) {
        NConf_DEBUG::set("success", 'DEBUG', 'ldap bind');
        # If user login was successfull, look for group
        # admins are in group : ADMIN_GROUP
        # normal nconf user are in group : USER_GROUP
        # all other do not have access

        // for filter (read just some attributes )
        //$justthese = array("cn", "description", "uid");
        //NConf_DEBUG::set($userfilter, 'DEBUG', 'userfilter');

        # check if user is member of admin group
        $admin_group_dn = AD_ADMIN_GROUP;
        if ( AD_GROUP_DN != "" ){
            $admin_group_dn .= ','.AD_GROUP_DN;
        }
        NConf_DEBUG::set($admin_group_dn, 'DEBUG', 'admin_group_dn');

        if (AD_ADMIN_GROUP == "" AND AD_USER_GROUP == ""){
            $userattrs = ldap_search($ldapconnection, $ldap_user_dn, "(objectclass=*)" );
            $userattrs_result = ldap_get_entries($ldapconnection, $userattrs);
            NConf_DEBUG::set($userattrs_result, 'DEBUG', 'user authenticated (limited)' );
            NConf_DEBUG::set("please have a look at the content in the previous message to get more information about the user (look for the memberof attribute to get the groups of the authenticated user)", 'DEBUG', "information for the admin");
        }else{
            $userattrs = ldap_search($ldapconnection, $ldap_user_dn, '('.AD_GROUP_ATTRIBUTE.'='.$admin_group_dn.')' );
            $userattrs_result = ldap_get_entries($ldapconnection, $userattrs);
            NConf_DEBUG::set($userattrs_result, 'DEBUG', 'check "admin" group permission');
            if ($userattrs_result["count"] == 1){
                # user identified as admin
                $_SESSION['group'] = GROUP_ADMIN;
                NConf_DEBUG::set('', 'INFO', $_SESSION["group"].' access granted');

            }else{
                # check if user is member of admin group
                $user_group_dn = AD_USER_GROUP;
                if ( AD_GROUP_DN != "" ){
                    $user_group_dn .= ','.AD_GROUP_DN;
                }
                NConf_DEBUG::set($user_group_dn, 'DEBUG', 'user_group_dn');

                # user was not in admin group, check for user membership
                $userattrs = ldap_search($ldapconnection, $ldap_user_dn, '('.AD_GROUP_ATTRIBUTE.'='.$user_group_dn.')' );
                $userattrs_result = ldap_get_entries($ldapconnection, $userattrs);
                NConf_DEBUG::set($userattrs_result, 'DEBUG', 'check "user" group permission');
                if ($userattrs_result["count"] == 1){
                    $_SESSION['group'] = GROUP_USER;
                    NConf_DEBUG::set('', 'INFO', $_SESSION["group"].' access granted');
                }else{
                    NConf_DEBUG::set(TXT_LOGIN_NOT_AUTHORIZED, 'ERROR');
                }
            }

        }

        # Users Infos
        # get Welcome name
        if ( (AUTH_FEEDBACK_AS_WELCOME_NAME == 1) AND !empty($userattrs_result[0][AD_USERNAME_ATTRIBUTE][0]) ){
            $_SESSION["userinfos"]["username"]  = $userattrs_result[0][AD_USERNAME_ATTRIBUTE][0];
        }else{
            $_SESSION["userinfos"]['username']  = $user_loginname;
        }

    } else {

        NConf_DEBUG::set("Can not connect to active directory server", 'DEBUG', 'ldap bind');
        NConf_DEBUG::set(ldap_error($ldapconnection), 'DEBUG', "error message") ;
        NConf_DEBUG::set(TXT_LOGIN_FAILED, 'ERROR');

    }



}else{
    # no AUTH TYPE matched.. cant login :
    
    NConf_DEBUG::set("No authentication type set in config, login restricted", 'ERROR');

}


// Log to history
if (!empty($_SESSION["group"]) ){
    history_add("general", "login", "access granted (".$_SESSION['group'].")");
}else{
    history_add("general", "login", "access denied (user: ".$user_loginname.")");
}
if ( defined("LOG_REMOTE_IP_HISTORY") AND LOG_REMOTE_IP_HISTORY == 1 ){
    if ( !empty($_SERVER['REMOTE_HOST']) ){
        history_add("general", "login-info", "REMOTE_HOST: (".$_SERVER['REMOTE_HOST'].")");
    }elseif( !empty($_SERVER['REMOTE_ADDR']) ){
        history_add("general", "login-info", "REMOTE_ADDR: (".$_SERVER['REMOTE_ADDR'].")");
    }
}
NConf_DEBUG::close_group();
?>
