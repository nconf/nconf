<?php
require_once 'include/head.php';
//set_page();

echo NConf_HTML::page_title('', 'Delete class');

if(  ( ( isset($_POST["delete"]) ) AND ($_POST["delete"] == "yes") ) AND
     ( ( isset($_POST["id"]) ) AND ($_POST["id"] != "") )
  ){

    # Delete entry
    $query = 'DELETE FROM ConfigClasses
                WHERE id_class='.$_POST["id"];

    $result = db_handler($query, "result", "Delete Class");
    if ($result) {
        echo TXT_DELETED;
        history_add("removed", "Class", $_POST["class_name"]);

        # set url for redirect
        $url = $_SESSION["go_back_page"];
            
        echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$url.'">';
        message($info, '<b>redirecting to:</b> <a href="'.$url.'"> [ this page ] (in '.REDIRECTING_DELAY.' seconds)</a>');
    }else{
        message ($error, 'Error deleting class '.$_POST["id"].':'.$query);
    }

}else{
    if ( !empty($_SERVER["HTTP_REFERER"]) ){
        $_SESSION["after_delete_page"] = $_SERVER["HTTP_REFERER"];
    }
    # class name
    $query = 'SELECT config_class FROM ConfigClasses where id_class='.$_GET["id"];
    $class_name = db_handler($query, 'getOne', "get class name");

    // Fetch attr name
    $query = 'SELECT attr_name  FROM ConfigAttrs, ConfigClasses WHERE id_class='.$_GET["id"].' AND fk_id_class=ConfigClasses.id_class';
    $attr = db_handler($query, "array", "Get Attrs of this Class");

    if ( isset($attr[0]["attr_name"]) ){

        // warning message
        $content = 'The class you chose to delete contains one or more attributes.
                    <br>If you proceed, all items belonging to this class, all attributes and
                    <br>any asscociated data will be lost!
                    <br><br>Are you <b>ABSOLUTELY SURE</b> you want to proceed?
                <br><br>List of attributes defined for this class:<br>(items using these attributes are not listed here explicitly)
            <br>
                   ';
        $content .= '<ul>';
        foreach($attr as $item){
                    $content .= '<li>'.$item["attr_name"].'</li>';
        }
        $content .= '</ul>';
    }else{
        $content = 'No attributes defined for this class.<br>You may safely delete the &quot;<b>'.$class_name.'</b>&quot; class.';
    }

    // Buttons
    $content_button = '
        <form name="delete_class" action="delete_class.php" method="post">
            <input type="hidden" name="id" value="'.$_GET["id"].'">
            <input type="hidden" name="class_name" value="'.$class_name.'">
            <input type="hidden" name="delete" value="yes">
    ';
    if ( !empty($_GET["from"]) ) $content_button .= '<input type="hidden" name="from" value="'.$_GET["from"].'">';

    $content_button .= '<br><div id=buttons>';
    $content_button .= '<input type="Submit" value="Delete" name="submit" align="middle">&nbsp;';
    $content_button .= '<input type=button onClick="window.location.href=\''.$_SESSION["go_back_page"].'\'" value="Back">';
    $content_button .= '</form>';

    echo NConf_HTML::limit_space(
        NConf_HTML::show_highlight('WARNING', $content.$content_button)
    );



}


mysql_close($dbh);

require_once 'include/foot.php';
?>
