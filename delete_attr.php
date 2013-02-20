<?php

require_once 'include/head.php';

echo NConf_HTML::page_title('', 'Delete Attribute');

if (DB_NO_WRITES == 1) {
    message($info, TXT_DB_NO_WRITES);
}

if(  ( ( isset($_POST["delete"]) ) AND ($_POST["delete"] == "yes") ) AND
     ( ( isset($_POST["id"]) ) AND ($_POST["id"] != "") )
  ){
    
    // Delete entry
    $query = 'DELETE FROM ConfigAttrs
                WHERE id_attr='.$_POST["id"];

    $result = db_handler($query, "result", "Delete entry");
    if ( $result ){
        message ($debug, '', "ok");
        history_add("removed", "Attribute", $_POST["name"]);

        echo TXT_DELETED;

        $url = $_SESSION["go_back_page"];
            
        echo '<meta http-equiv="refresh" content="'.REDIRECTING_DELAY.'; url='.$url.'">';
        NConf_DEBUG::set('<a href="'.$url.'"> [ this page ] (in '.REDIRECTING_DELAY.' seconds)</a>', 'INFO', "<br>redirecting to");
    }else{
        message ($error, 'Error deleting id_attr '.$_POST["id"].':'.$query);
    }
    

}elseif( !empty($_GET["id"]) ){

    

    // Fetch attr name
    $query = 'SELECT attr_name, config_class FROM ConfigAttrs, ConfigClasses WHERE id_attr='.$_GET["id"].' AND fk_id_class=ConfigClasses.id_class';
    $attr = db_handler($query, "assoc", "Fetch attr name");

    // warning message
    $content = 'All &quot;<b>'.$attr["config_class"].'</b>&quot; items will lose their &quot;<b>'.$attr["attr_name"].'</b>&quot; attribute.
            <br>All data associated with this attribute will also be lost.
            This action cannot be undone.';
    $content .= '<br><br>
                 Are you <b>REALLY SURE</b> you want to proceed?<br><br>';

    // Buttons    
    $content_button = '
        <form name="delete_attr" action="delete_attr.php" method="post">
            <input type="hidden" name="id" value="'.$_GET["id"].'">
            <input type="hidden" name="name" value="'.$attr["attr_name"].'">
    ';
    if ( !empty($_GET["from"]) ) $content_button .= '<input type="hidden" name="from" value="'.$_GET["from"].'">';

    $content_button .= '<input type="hidden" name="delete" value="yes">
          <div id=buttons>';
    $content_button .= '<input type="Submit" value="Delete" name="submit" align="middle">&nbsp;';
    $content_button .= '<input type=button onClick="window.location.href=\''.$_SESSION["go_back_page"].'\'" value="Back">';

    $content_button .= '</form>';

    echo NConf_HTML::limit_space(
        NConf_HTML::show_highlight('WARNING', $content.$content_button)
    );



}else{
    NConf_DEBUG::set("No item to delete", "ERROR");
}


if ( NConf_DEBUG::status('ERROR') ) {
    echo NConf_HTML::limit_space( NConf_HTML::show_error() );
    echo "<br><br>";
    echo NConf_HTML::back_button($_SESSION["go_back_page"]);
}



mysql_close($dbh);

require_once 'include/foot.php';
?>
