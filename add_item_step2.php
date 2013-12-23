<?php
require_once 'include/head.php';

$step2 = "no";

if (DB_NO_WRITES == 1) {
    message($info, "DB_NO_WRITES = 1: No DB inserts or modifications will be performed");
}

if( ( isset($_POST["config_class"]) ) AND ($_POST["config_class"] != "") ){
    $config_class = $_POST["config_class"]; 
}

// DENY USER ADDING AN ADMIN ACCOUNT
if( ($_SESSION["group"] != "admin")
    AND ($config_class == "contact")
    AND ( isset($_POST[$_POST["ID_nc_permission"]]) AND ($_POST[$_POST["ID_nc_permission"]] == "admin") )
){
    // Cache other infos
    foreach ($_POST as $key => $value) {
        $_SESSION["cache"]["handle"][$key] = $value;
    }
    unset($_SESSION["cache"]["handle"][$_POST["ID_nc_permission"]]);

    include('include/stop_user_modifying_admin_account.php');
}

# Implode the splitet fields (exploded in handle_item.php)
if(  isset($_POST["exploded"]) ){
    prepare_check_command_params($_POST["exploded"]);
}




echo '
<table>
    <tr>
        <td>';

// Check if submit is allowed
if ( isset($_SESSION["submited"]) AND !empty($_POST["check_command_changed"]) ){
    // Cache
    $_SESSION["cache"]["use_cache"] = TRUE;
    foreach ($_POST as $key => $value) {
        $_SESSION["cache"]["handle"][$key] = $value;
    }

    # for debugging
    //echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$_SESSION["go_back_page"].'">';
    echo '<meta http-equiv="refresh" content="0; url='.$_SESSION["go_back_page"].'">';
    NConf_DEBUG::set('<a href="'.$_SESSION["go_back_page"].'"> handle_item (back) </a>', 'INFO', "<br>redirecting to");

    
}elseif ( isset($_SESSION["submited"]) ){

    // Write2DB (feedback: $step2)
    require_once 'include/add_item_write2db.php';
    unset($_SESSION["submited"]);

}else{
    message($error, 'Submited data is not allowed to be resent');
    if ( isset($_SESSION["created_id"]) ){
        $id = $_SESSION["created_id"];
        $step2 = "yes";
    }
}

# Content of Page

echo NConf_HTML::page_title($config_class, 'Add '.$config_class);

if ( !empty($_POST["check_command_changed"]) ){
    # do nothing, just go back
}elseif ($step2 == "yes") {
    // Host was added, go to service page

    NConf_DEBUG::set('Proceeding to add services.', 'INFO');

    // session $_SESSION["created_id"] should be checked for none F5 re-adding
    // the step2 in url is only for not working session users...
    $url = 'modify_item_service.php?id='.$id.'&step2=true';
    echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$url.'">';

    echo NConf_DEBUG::show_debug('INFO', TRUE);

    // add extra line (br) for more space
    // after echo because we want the redirect only in the footer
    NConf_DEBUG::set('<a href="'.$url.'"> add services </a>', 'INFO', "<br>redirecting to");

}else{

    // normal way to add items

    if ( isset($_SESSION["cache"]["handle"]) ) unset($_SESSION["cache"]["handle"]);

    
    if ( NConf_DEBUG::status('ERROR') ) {
        echo NConf_HTML::show_error();
        echo "<br><br>";
        echo NConf_HTML::back_button($_SESSION["go_back_page"]);

        // Cache
        $_SESSION["cache"]["use_cache"] = TRUE;
        foreach ($_POST as $key => $value) {
            $_SESSION["cache"]["handle"][$key] = $value;
        }

    }else{
        // Item added, go back to overview of its class

        
        $url = 'overview.php?class='.$config_class;
        echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$url.'">';
        // add extra line (br) for more space

        echo NConf_DEBUG::show_debug('INFO', TRUE);

        NConf_DEBUG::set('<a href="'.$url.'"> overview </a>', 'INFO', "<br>redirecting to");
    }


}


echo '      </td>
        </tr>
    </table>
';

mysql_close($dbh);

require_once 'include/foot.php';
?>
