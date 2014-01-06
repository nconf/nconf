<?php

require_once 'include/head.php';

// delay normaly short (given from config)
// but if there is a special message, the message should be read, so put the delay few seconds higher
$redirecting_delay = REDIRECTING_DELAY;

// will be added to url redirection, for knowing about naming attribute information on attribute overview
$naming_attr_message = "";

# when naming_attr = yes we must set mandatory to yes (should come from select boxes, but this code here will ensure that.
if (isset($_POST['naming_attr']) AND $_POST['naming_attr'] == "yes") {
    $_POST['mandatory'] = "yes";
    $mandatory = $_POST['mandatory'];
    message($debug, 'attribute mandatory is set to "yes" (because this attr is saved as naming_attr)');
} elseif (!empty($_POST['mandatory'])) {
    $mandatory = $_POST['mandatory'];
} elseif (!empty($_POST['HIDDEN_mandatory'])) {
    $mandatory = $_POST['HIDDEN_mandatory'];
    $_POST['mandatory'] = $_POST['HIDDEN_mandatory'];
}

// read mandatory values
$attr_id = $_POST['attr_id'];
$attr_name = escape_string($_POST['attr_name']);
$friendly_name = escape_string($_POST['friendly_name']);
$visible = $_POST['visible'];
$write2conf = $_POST['conf'];

# take attr class from form if it is an ADD, else it gets the class from the DB (attention: this should only be so if class cant be modified)
if ($attr_id == "new") {
    $fk_id_class = $_POST['fk_id_class'];
    # additional entries needed for ADD
    $datatype = $_POST['datatype'];
    $naming_attr = $_POST['naming_attr'];

    // process non-mandatory values
    if (isset($_POST['fk_show_class_items'])) {
        $fk_show_class_items = $_POST['fk_show_class_items'];
    } else {
        $fk_show_class_items = "NULL";
    }

    if (isset($_POST['link_as_child'])) {
        $link_as_child = $_POST['link_as_child'];
    } else {
        $link_as_child = "no";
    }

} else {
    $query = "SELECT fk_id_class FROM ConfigAttrs WHERE id_attr=$attr_id";
    $fk_id_class = db_handler($query, 'getOne', "get class id");
}

# Get class name for later use (e.g. in link)
$query = 'SELECT config_class FROM ConfigClasses where id_class = "' . $fk_id_class . '"';
$class_name = db_handler($query, "getOne", "get class name");

###
# check if attr already exists in this class
###
$query = 'SELECT id_attr, attr_name, friendly_name FROM ConfigAttrs WHERE fk_id_class="' . $fk_id_class . '" AND attr_name ="' . mysql_real_escape_string($attr_name) . '"';
$result = db_handler($query, "assoc", "Check if attribute name already exists in this class");

# Entry exists?  -> if its a modify, the id should be the same as attr_id, else the user tries to rename it to a existing one, which is not allowed!
if ((!empty($result)) AND (($attr_id == "new") OR ($attr_id != $result["id_attr"]))) {
    //message($error, 'Attribute with name &quot;'.$attr_name.'&quot; already exists for this class! <br>&nbsp;Click for details or go back:<br>');
    //message($error, '<a href="detail_attributes.php?class='.$class_name.'&id='.$result["id_attr"].'">'.$result["attr_name"].'</a>', "list");

    NConf_DEBUG::set('An attribute with the name &quot;' . $attr_name . '&quot; already exists for this class!', 'ERROR');
    NConf_DEBUG::set('Click for details or go back:', 'ERROR');

    $list_item = '<li><a href="detail_admin_items.php?type=attr&class=' . $class_name . '&id=' . $result["id_attr"] . '">' . $result["attr_name"] . '</a></li>';
    $list = '<ul>' . $list_item . '</ul>';
    NConf_DEBUG::set($list, 'ERROR');

    $write2db = "no";

    # When user clicks on a listed item, and goes to delete it, the redirect must know where to go after delete, this would be the add page:
    # this feature is not finished with attributes!
    $_SESSION["after_delete_page"] = $_SERVER["HTTP_REFERER"];
    message($debug, 'Setting after delete page to : ' . $_SERVER["HTTP_REFERER"]);

} else {

    # check mandatory input
    # It divers for ADD or MODIFY !
    if ($attr_id == "new") {
        $title = "Add attribute";

        # mandatory for ADD
        $mandatory_fields = array("attr_name" => "attribute name", 'friendly_name' => "friendly name", 'datatype' => "attribute datatype", 'mandatory' => "mandatory", 'visible' => "visible", 'conf' => "write to conf", 'fk_id_class' => "attribute class", 'naming_attr' => "is naming attribute");
    } else {
        $title = "Modify attribute";

        # mandatory for modify
        $mandatory_fields = array("attr_name" => "attribute name", 'friendly_name' => "friendly name", 'mandatory' => "mandatory", 'visible' => "visible", 'conf' => "write to conf");
    }

    echo NConf_HTML::page_title('', $title);

    # Check mandatory fields
    $write2db = check_mandatory($mandatory_fields, $_POST);

}

# special links
# they should not both be yes (already checked, not possible with javascript)
if (isset($_POST['link_as_child'])) {
    $link_as_child = $_POST['link_as_child'];
} else {
    $link_as_child = 'no';
}
if (isset($_POST['link_bidirectional'])) {
    $link_bidirectional = $_POST['link_bidirectional'];
} else {
    $link_bidirectional = 'no';
}

/* Is now allowed:
 if ( $link_as_child == "yes" && $link_bidirectional == "yes" ){
 $write2db = "no";
 message($error, 'You cannot have both special linkings on "yes"');
 }
 */

// process non-mandatory values
if (isset($_POST['description'])) {
    $description = escape_string($_POST['description']);
} else {
    $description = "NULL";
}

if (isset($_POST['poss_values'])) {
    $poss_values = escape_string($_POST['poss_values']);
} else {
    $poss_values = "";
}

if (isset($_POST['predef_value'])) {
    $predef_value = escape_string($_POST['predef_value']);
} else {
    $predef_value = "";
}

if (isset($_POST['max_length'])) {
    $max_length = $_POST['max_length'];
} else {
    $max_length = "";
}

if ($_POST['ordering'] != "") {
    $ordering = $_POST['ordering'];
} else {
    $query = "SELECT MAX(ordering) FROM ConfigAttrs WHERE fk_id_class=$fk_id_class";
    $max_ord = db_handler($query, 'getOne', "get highest ordering number in class");
    $ordering = $max_ord + 1;
}

# write to db
if ($write2db == "yes") {

    # ADD OR MODIFY
    if ($attr_id == "new") {
        ##
        ## ADD CONTENT ##
        ##

        $query = "INSERT INTO ConfigAttrs (attr_name,friendly_name,description,datatype,max_length,poss_values,predef_value,mandatory,ordering,visible,write_to_conf,naming_attr,link_as_child,link_bidirectional,fk_show_class_items,fk_id_class) VALUES ('$attr_name','$friendly_name','$description','$datatype','$max_length','$poss_values','$predef_value','$mandatory','$ordering','$visible','$write2conf','$naming_attr','$link_as_child','$link_bidirectional',$fk_show_class_items,'$fk_id_class')";

        $result = db_handler($query, "result", "insert");

        if ($result) {
            # Get ID of insert:
            $new_id = mysql_insert_id();

            echo NConf_HTML::text("Successfully added attribute &quot;$attr_name&quot;");

            history_add("created", "Attribute", $attr_name);

            # Check for other itmes with same order number, and change theme
            set_attr_order($new_id, $ordering, $fk_id_class);

            # Delete Cache of modify (if still exist)
            if (isset($_SESSION["cache"]["modify_attr"]))
                unset($_SESSION["cache"]["modify_attr"]);

            # Go to show_attr page and show the class which the new added attribute belongs to
            $url = 'show_attr.php?class=' . $class_name;
            echo '<meta http-equiv="refresh" content="' . REDIRECTING_DELAY . '; url=' . $url . '">';
            NConf_DEBUG::set('<a href="' . $url . '"> [ this page ] (in ' . REDIRECTING_DELAY . ' seconds)</a>', 'INFO', "<br>redirecting to");
        } else {
            echo "<h2>Failed to add attribute &quot;$attr_name&quot;</h2>";
        }

    } else {
        ##
        ## MODIFY CONTENT: ##
        ##

        // 2009-03-04 A. Gargiulo: disabled this code because we don't want users to be able to
        // modify all of the attr params, especially the naming attr (could cause data inconsistency)!

        // read mandatory values
        //$datatype = $_POST['datatype'];
        //$fk_id_class = $_POST['fk_id_class'];
        //$naming_attr = $_POST['naming_attr'];

        // process non-mandatory values
        //if(isset($_POST['fk_show_class_items'])){
        //   $fk_show_class_items = $_POST['fk_show_class_items'];
        //}else{
        //   $fk_show_class_items = "NULL";
        //}

        //if(isset($_POST['link_as_child'])){
        //    $link_as_child = $_POST['link_as_child'];
        //}else{
        //    $link_as_child = "no";
        //}

        // search other naming attr (but not this attrbiute), because there can be only 1 Naming Attribute
        //$old_naming_attr_query = 'SELECT id_attr FROM ConfigAttrs WHERE fk_id_class='.$fk_id_class.' AND naming_attr = "yes" AND id_attr != '.$attr_id;
        //$old_naming_attr_array = db_handler($old_naming_attr_query, "array", "looking up OTHER naming attrs in this class");
        //if($naming_attr == "yes"){
        // nothing hapens here, because all other will be deleted, this will be set
        //}elseif($naming_attr == "no"){
        // if there is no other naming attr
        // select all other attributes in this class, and look if there is one naming attr set to yes.
        // that is needed, otherwise the naming attr can't get "no" because this is the last one (so actual it should be yes)
        //     if ( count($old_naming_attr_array) == 0 ){
        //         $naming_attr = "yes";
        //         message($info, TXT_NAMING_ATTR_LAST);
        //         $naming_attr_message = "&naming_attr=last";
        //     }
        //}

        // UPDATE ConfigAttrs
        //$query = mysql_query("UPDATE ConfigAttrs
        // SET
        //     attr_name = '$attr_name',
        //     friendly_name = '$friendly_name',
        //     description = '$description',
        //     datatype = '$datatype',
        //     max_length = '$max_length',
        //     poss_values = '$poss_values',
        //     predef_value = '$predef_value',
        //     mandatory = '$mandatory',
        //     ordering = '$ordering',
        //     visible = '$visible',
        //     write_to_conf = '$write2conf',
        //     naming_attr = '$naming_attr',
        //     link_as_child = '$link_as_child',
        //     link_bidirectional = '$link_bidirectional',
        //     fk_show_class_items = $fk_show_class_items,
        //     fk_id_class = '$fk_id_class'
        // WHERE
        //     id_attr = $attr_id
        // ");

        # get old ordering number
        $old_ordering = db_handler("SELECT ordering FROM ConfigAttrs WHERE id_attr=$attr_id", "getOne", "GET old ordering number of attr");

        // UPDATE ConfigAttrs
        $query = "UPDATE ConfigAttrs
                SET
                attr_name = '$attr_name',
                friendly_name = '$friendly_name',
                description = '$description',
                max_length = '$max_length',
                poss_values = '$poss_values',
                predef_value = '$predef_value',
                mandatory = '$mandatory',
                ordering = '$ordering',
                visible = '$visible',
                write_to_conf = '$write2conf'
                WHERE
                id_attr = $attr_id";

        $result = db_handler($query, "update", "Modify attribute parameters");

        if ($result) {

            # handle the ordering of the other items
            set_attr_order($attr_id, $ordering, $fk_id_class, $old_ordering);

            echo NConf_HTML::text("Successfully modified attribute &quot;$attr_name&quot;", FALSE);
            if ($naming_attr_message == "&naming_attr=last") {
                echo TXT_NAMING_ATTR_LAST;
            }

            // 2009-03-04 A. Gargiulo: disabled this code because we don't want users to be able to
            // modify all of the attr params, especially the naming attr (could cause data inconsistency)!

            // When succesfully set new entry including new naming attribute, delete the old one
            //if($naming_attr == "yes"){
            //    if ( (count($old_naming_attr_array) != "0") AND $naming_attr == "yes" ){
            //        foreach ($old_naming_attr_array as $attribute){
            //            $update_query = 'UPDATE `ConfigAttrs` SET `naming_attr` = "no" WHERE `id_attr` = '.$attribute["id_attr"];
            //            db_handler($update_query, "insert", "set old naming attr to 'no'");
            //        }
            //        $naming_attr_message = "&naming_attr=changed";
            //    }
            //}

            // Go to next page without pressing the button

            // Delete Cache of modify (if still exist)
            if (isset($_SESSION["cache"]["modify_attr"]))
                unset($_SESSION["cache"]["modify_attr"]);

            // set go back page (show attr ) with defined class
            $_SESSION["go_back_page"] = 'show_attr.php?class=' . $class_name;

            echo '<meta http-equiv="refresh" content="' . $redirecting_delay . '; url=' . $_SESSION["go_back_page"] . $naming_attr_message . '">';
            NConf_DEBUG::set('<a href="' . $_SESSION["go_back_page"] . $naming_attr_message . '"> [ this page ] (in ' . REDIRECTING_DELAY . ' seconds)</a>', 'INFO', "<br>redirecting to");

        } else {
            echo "<h2>Failed to modify attribute &quot;$attr_name&quot;</h2>";
        }

    }

} else {# write to db

    if (isset($_SESSION["cache"]["modify_attr"]))
        unset($_SESSION["cache"]["modify_attr"]);

    if (NConf_DEBUG::status('ERROR')) {
        $_SESSION["cache"]["use_cache"] = TRUE;
        echo NConf_HTML::limit_space(NConf_HTML::show_error());
        echo "<br><br>";
        echo NConf_HTML::back_button($_SESSION["go_back_page"]);

        # Cache
        foreach ($_POST as $key => $value) {
            $_SESSION["cache"]["modify_attr"][$attr_id][$key] = $value;
        }

    } else {
        echo NConf_DEBUG::show_debug('INFO', TRUE);
    }

}

mysql_close($dbh);
require_once 'include/foot.php';
?>
