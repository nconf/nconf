<?php
require_once 'include/head.php';

set_page();

# go to show attribute page, when modify was ok
$HTTP_referer = 'show_attr.php';
$_SESSION["go_back_page_ok"] = $HTTP_referer;
message($debug, "set go_back_page_ok : ".$_SESSION["go_back_page_ok"]);

# Choose title
if(isset($_GET['id'])){
  $id = $_GET['id'];
  $title = "Modify attribute";
}else{
  $id = "new";
  $title = "Add attribute";
}


if ($id != "new"){
    # get entries
    $query = 'SELECT * FROM ConfigAttrs WHERE id_attr="'.$id.'"';
    $attr_entry = db_handler($query, "assoc", "get entries");

}else{
    # new entry does not have predefined values like the modify part
    $attr_entry = array("attr_name" => "",
                        "friendly_name" => "",
                        "description" => "",
                        "datatype" => "text",
                        "max_length" => "1024",
                        "poss_values" => "",
                        "predef_value" => "",
                        "mandatory" => "",
                        "ordering" => "",
                        "visible" => "",
                        "write_to_conf" => "",
                        "naming_attr" => "",
                        "link_as_child" => "no",
                        "link_bidirectional" => "no",
                        "fk_show_class_items" => "",
                        "fk_id_class" => "");
}


# Check cache
if ( isset($_SESSION["cache"]["use_cache"]) ){
    if ( isset($_SESSION["cache"]["modify_attr"][$id]) ){
        # Cache
        foreach ($_SESSION["cache"]["modify_attr"][$id] as $key => $value) {
            $attr_entry[$key] = $value;
        }
    }
    unset($_SESSION["cache"]["use_cache"]);
}


?>



<!-- jQuery part -->
<script type="text/javascript">
    $(document).ready(function(){
        /* both selectinos are now allowed:
        $("select[name='link_as_child']").change(function() {
            if (this.value == "yes"){
                $("select[name='link_bidirectional'] option[value='no']").attr('selected', 'selected');
                $("select[name='link_bidirectional']").attr('disabled', 'disabled');
            }else{
                $("select[name='link_bidirectional']").removeAttr("disabled");
            }
        });

        $("select[name='link_bidirectional']").change(function() {
            if (this.value == "yes"){
                $("select[name='link_as_child'] option[value='no']").attr('selected', 'selected');
                $("select[name='link_as_child']").attr('disabled', 'disabled');
            }else{
                $("select[name='link_as_child']").removeAttr("disabled");
            }
        });
        */


        // autocomplete for attributes (select, assigns...)
        var predef_field = $("#predef_value_autocomplete").autocomplete({
            minLength: 0,
            disabled: true,

        }).click(function() {
            // close if already visible
            if ( predef_field.autocomplete( "widget" ).is( ":visible" ) ) {
                predef_field.autocomplete( "close" );
                return;
            }

            if ( $("select[name='datatype']").val() == "select"){
                var availableTags = $("#poss_values_autocomplete").val().split(/::/);
                $("#predef_value_autocomplete").autocomplete( "option", "source", availableTags ).autocomplete("enable");

                // pass empty string as value to search for, displaying all results
                predef_field.autocomplete( "search", "" );
                predef_field.focus();
            }else{
                predef_field.autocomplete( "option", "source", "" ).autocomplete("disable");
            }
        });


        // Adcaned linking options
        $("select[name='datatype']").change(function() {

            if ( this.value == "text" || this.value == "select" ||  this.value == "password" ){
                $('#fieldset_special_links').fadeTo('slow', 0.5, function() {
                    // Animation complete.
                });
            }else{
                $('#fieldset_special_links').fadeTo('slow', 1, function() {
                    // Animation complete.
                });
            }

        });


        // apply change events already on start
        $("select[name='datatype']").change();


        // nagios attribute help
        $.getScript('include/js/jquery_plugins/jquery.nconf_help_nagios.js', function() {
            $("input[name='attr_name']").add("select#class_name").bind('change', function(){
                $.nconf_help_get_nagios_documentation($("select#class_name option:selected").text(), $("input[name='attr_name']").val());
            }).change();
        });

        // generate the help buttons with help text
        $.nconf_help_admin();


    });
</script>





<!-- page content -->
<form name="form1" action="modify_attr_write2db.php" method="post">
<div style="width: 520px; position: relative;">
    <?php
    $detail_navigation = '<a class="button_back jQ_tooltip" title="back" href="'.$_SESSION["go_back_page_ok"].'"></a>';
    echo '<div id="ui-nconf-icon-bar">'.$detail_navigation.'</div>';
    echo NConf_HTML::page_title('', $title);

    $colgroup = '<colgroup>
            <col width="186">
            <col width="20">
            <col>
            <col width="20">
        </colgroup>';
    ?>
    <!--<form name="form1" action="modify_attr_write2db.php" method="post" onclick="check_input()" onkeydown="check_input()" onreset="check_input()">-->
    <input type=hidden name=attr_id value="<?php echo $id; ?>">

    <fieldset id="page_content">
    <legend>Main</legend>
        <table width="500">
            <?php echo $colgroup; ?>
            <tr><td>Nagios-specific attribute name</td>
                <td></td>
                <td><input type=text name=attr_name maxlength=60 value="<?php echo htmlspecialchars($attr_entry["attr_name"]); ?>"></td>
                <td class="attention center">*</td>
            </tr>
        </table>
        <div>
            <table width="500" id="nagios_documentation_row" style="display: none;">
            <?php echo $colgroup; ?>
                <tr>
                    <td>Nagios documentation help</td>
                    <td>
                    </td>
                    <td><input type="hidden" name="nagios_documentation"></td>
                    <td></td>
                </tr>
            </table>
        </div>
        <table width="500">
            <?php echo $colgroup; ?>
            <tr><td>friendly name (displayed in GUI)</td>
                <td></td>
                <td><input type=text name=friendly_name maxlength=80 value="<?php echo htmlspecialchars($attr_entry["friendly_name"]); ?>"></td>
                <td class="attention center">*</td>
            </tr>
            <tr><td>description, example or help-text</td>
                <td></td>
                <td><input type=text name=description maxlength=250 value="<?php echo htmlspecialchars($attr_entry["description"]); ?>"></td>
                <td class="attention center"></td>
            </tr>
        </table>
    </fieldset>
    <fieldset>
    <legend>Class and datatype</legend>
        <table width="500">
            <?php echo $colgroup; ?>
            <tr><td>attribute belongs to class</td>
                <td></td>
                <td><select id="class_name" name="fk_id_class"
                    <?php if ($id != "new") echo "disabled"; ?>
                    >
<?php
    $query = mysql_query("SELECT id_class,config_class FROM ConfigClasses ORDER BY config_class");

    while($entry = mysql_fetch_row($query)){
        echo '<option value='.$entry[0];
            if ( $entry[0] == $attr_entry["fk_id_class"] ) echo " selected";
        echo " >$entry[1]</option>";
    }
?>
                </select></td>
                <td class="attention center">*</td>
            </tr>
            <tr><td colspan=4>
                </td></tr>
            <tr><td>attribute datatype</td>
                <td></td>
                <td><select name="datatype" onChange="check_input(document.form1.datatype.value)"
                    <?php if ($id != "new") echo "disabled"; ?>
                    >
                    <option value="text" <?php if($attr_entry["datatype"] == "text") echo " selected"; ?> >text</option>
                    <option value="password" <?php if($attr_entry["datatype"] == "password") echo " selected"; ?> >password</option>
                    <option value="select" <?php if($attr_entry["datatype"] == "select") echo " selected"; ?> >select</option>
                    <option value="assign_one" <?php if($attr_entry["datatype"] == "assign_one") echo " selected"; ?> >assign_one</option>
                    <option value="assign_many" <?php if($attr_entry["datatype"] == "assign_many") echo " selected"; ?> >assign_many</option>
                    <option value="assign_cust_order" <?php if($attr_entry["datatype"] == "assign_cust_order") echo " selected"; ?> >assign_cust_order</option>
                    </select></td>
                <td class="attention center">*</td>
            </tr>
            <tr><td>item(s) to be assigned</td>
                <td></td>
                <td><select name="fk_show_class_items" disabled>
<?php
    $query = mysql_query("SELECT id_class,config_class FROM ConfigClasses ORDER BY config_class");

    while($entry = mysql_fetch_row($query)){
        //echo "<option value=$entry[0]>$entry[1]</option>";
        echo '<option value='.$entry[0];
            if ( $entry[0] == $attr_entry["fk_show_class_items"] ) echo " selected";
        echo " >$entry[1]</option>";
    }
?>
                </select></td>
                <td class="attention center">*</td>
            </tr>
            <tr><td>list of possible values separated by &quot;<b><?php echo SELECT_VALUE_SEPARATOR; ?></b>&quot;</td>
                <td></td>
                <td><input type=text id=poss_values_autocomplete name=poss_values value="<?php echo htmlspecialchars($attr_entry["poss_values"]); ?>"></td>
                <td class="attention center">*</td>
            </tr>
            <tr><td>pre-defined value(s)</td>
                <td></td>
                <td>
                    <input type=text name=predef_value id=predef_value_autocomplete class="ui-autocomplete-input" maxlength=1024 value="<?php echo htmlspecialchars($attr_entry["predef_value"]); ?>"></td>
                <td class="attention center"></td>
            </tr>
            <tr><td>max. text-field length (chars)</td>
                <td></td>
                <td><input type=text name=max_length maxlength=4 style="width:60px" value="<?php echo $attr_entry["max_length"]; ?>"></td>
                <td class="attention center"></td>
            </tr>
        </table>
    </fieldset>
    <fieldset id="fieldset_special_links">
        <legend>Advanced linking options</legend>
        <table>
            <?php echo $colgroup; ?>
            <tr><td>link selected item(s) as children?</td>
                <td></td>
                <td>
                    <select name="link_as_child" style="width:60px" disabled> 
                        <option <?php if($attr_entry["link_as_child"] == "no") echo " selected"; ?> value="no">no</option>
                        <option <?php if($attr_entry["link_as_child"] == "yes") echo " selected"; ?> value="yes">yes</option>
                    </select>
                </td>
                <td class="attention center"></td>
            </tr>
            <tr><td>link selected item(s) bi-directionally?</td>
                <td></td>
                <td>
                    <select name="link_bidirectional" style="width:60px" disabled> 
                        <option <?php if($attr_entry["link_bidirectional"] == "no") echo " selected"; ?> value="no">no</option>
                        <option <?php if($attr_entry["link_bidirectional"] == "yes") echo " selected"; ?> value="yes">yes</option>
                    </select>
                </td>
                <td class="attention center"></td>
            </tr>
        </table>
    </fieldset>
    <fieldset>
        <legend>Display and output</legend>
        <table>
            <?php echo $colgroup; ?>
            <tr><td>attribute is mandatory?</td>
                <td></td>
                <td>
                    <select name=mandatory style="width:60px">
                        <option <?php if($attr_entry["mandatory"] == "no") echo " selected"; ?> >no</option>
                        <option <?php if($attr_entry["mandatory"] == "yes") echo " selected"; ?> >yes</option>
                    </select>
                <input type="hidden" name="HIDDEN_mandatory" value="<?php echo $attr_entry["mandatory"]; ?>">
                </td>
                <td class="attention center"></td>
            </tr>
            <tr><td>attribute is visible?</td>
                <td></td>
                <td>
                    <select name=visible style="width:60px">
                        <option <?php if($attr_entry["visible"] == "yes") echo " selected"; ?> >yes</option>
                        <option <?php if($attr_entry["visible"] == "no") echo " selected"; ?> >no</option>
                    </select>
                </td>
                <td class="attention center">*</td>
            </tr>
            <tr><td>write attribute to configuration?</td>
                <td></td>
                <td>
                    <select name=conf style="width:60px">
                        <option <?php if($attr_entry["write_to_conf"] == "yes") echo " selected"; ?> >yes</option>
                        <option <?php if($attr_entry["write_to_conf"] == "no") echo " selected"; ?> >no</option>
                    </select>
                </td>
                <td class="attention center">*</td>
            </tr>
            <tr><td>ordering</td>
                <td></td>
                <td><input type=text name=ordering maxlength=2 style="width:60px" value="<?php echo $attr_entry["ordering"]; ?>"></td>
                <td class="attention center"></td>
            </tr>
            <tr><td>naming attribute?</td>
                <td></td>
                <td>
                    <select name=naming_attr style="width:60px" onChange="check_naming_attr()"
                    <?php if ($id != "new") echo "disabled"; ?>
                    >
                        <option <?php if($attr_entry["naming_attr"] == "no") echo " selected"; ?> >no</option>
                        <option <?php if($attr_entry["naming_attr"] == "yes") echo " selected"; ?> >yes</option>
                    </select>
                </td>
                <td class="attention center">*</td>
            </tr>
        </table>
    </fieldset>

        <table>
            <?php echo $colgroup; ?>
            <tr>
                <td>
                    <br>
                </td>
            </tr>
            <tr>
                <td>
                    <div id=buttons>
                        <input type="Submit" value="Submit" name="submit" align="middle">&nbsp;&nbsp;
                        <?php
                            echo '<input type="button" value="Reset" name="submit" align="middle" onclick="location.reload(true);">';
                        ?>
                    </div>
                </td>
            </tr>
        </table>



</div>
</form>




<?php
# Script part for modify attribute
if ($id != "new"){
?>

    <script type="text/javascript">
    <!--
        // execute once on load
        //check_input();
        <?php
            echo 'check_input("'.$attr_entry["datatype"].'");';
        ?>
        // also check the naming_attr
        check_naming_attr();

        function check_input(datatype){
            if (!datatype){
                datatype = "text";
            }
            //var datatype = "<?php echo $attr_entry["datatype"] ?>";
            switch (datatype) {
            //switch (document.form1.datatype.value) {
                case "text":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = false;
                break;
                case "password":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = true;
                    document.form1.max_length.disabled = false;
                    document.form1.conf.value = "no";
                break;
                case "select":
                    document.form1.poss_values.disabled = false;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                break;
                case "assign_one":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                break;
                case "assign_many":
                case "assign_cust_order":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                break;
            }
        }
        function check_naming_attr(){
            if (document.form1.naming_attr.value == "yes"){
                document.form1.mandatory.value = "yes";
                document.form1.mandatory.disabled = true;
            }else{
                document.form1.mandatory.disabled = false;
            }
        }
    //-->
    </script>

<?php
# Script part for add attribute
}else{
?>

    <script type="text/javascript">
    <!--
        // execute once on load
        //check_input();
        <?php
            echo 'check_input("'.$attr_entry["datatype"].'");';
        ?>
        // also check the naming_attr
        check_naming_attr();

        function check_input(datatype){
            if (!datatype){
                datatype = "text";
            }
            //var datatype = "<?php echo $attr_entry["datatype"] ?>";
            switch (datatype) {
            //switch (document.form1.datatype.value) {
                case "text":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = false;
                    document.form1.fk_show_class_items.disabled = true;
                    document.form1.link_as_child.disabled = true;
                    document.form1.link_bidirectional.disabled = true;
                break;
                case "password":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = true;
                    document.form1.max_length.disabled = false;
                    document.form1.fk_show_class_items.disabled = true;
                    document.form1.link_as_child.disabled = true;
                    document.form1.link_bidirectional.disabled = true;
                    document.form1.conf.value = "no";
                break;
                case "select":
                    document.form1.poss_values.disabled = false;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                    document.form1.fk_show_class_items.disabled = true;
                    document.form1.link_as_child.disabled = true;
                    document.form1.link_bidirectional.disabled = true;
                break;
                case "assign_one":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                    document.form1.fk_show_class_items.disabled = false;
                    document.form1.link_as_child.disabled = false;
                    document.form1.link_bidirectional.disabled = false;
                break;
                case "assign_many":
                case "assign_cust_order":
                    document.form1.poss_values.disabled = true;
                    document.form1.predef_value.disabled = false;
                    document.form1.max_length.disabled = true;
                    document.form1.fk_show_class_items.disabled = false;
                    document.form1.link_as_child.disabled = false;
                    document.form1.link_bidirectional.disabled = false;
                break;
            }
        }
        function check_naming_attr(){
            if (document.form1.naming_attr.value == "yes"){
                document.form1.mandatory.value = "yes";
                document.form1.mandatory.disabled = true;
            }else{
                document.form1.mandatory.disabled = false;
            }
        }
    //-->
    </script>


<?php
} // end of add or modify script path
?>


<!-- Help text content -->
<div id="help_text" style="display: none">
    <div id="attr_name" title="attribute name">
        <p>The attribute name as stated in the official Nagios documentation. Attribute names must be unique within their class.</p>
    </div>

    <div id="nagios_documentation" title="Nagios documentation help">
    </div>

    <div id="friendly_name" title="friendly name">
        <p>The attribute name which shall be displayed within the NConf GUI.</p>
    </div>

    <div id="description" title="description">
        <p>A short description or help text for the attribute.</p>
    </div>

    <div id="fk_id_class" title="attribute class">
        <p>The NConf class that this attribute should belong to.</p>
    </div>

    <div id="fk_show_class_items" title="item assignment">
        <p>A class whose items should be listed for assignment, i.e. display a drop-down menue containing items of this specific class, so they may be linked.</p>
        <p>This option is only available for attributes of datatype "assign_".</p>
    </div>

    <div id="poss_values" title="possible values">
        <p>A list of options available in a drop-down menue. Separate each option with the "<strong>::</strong>" characters.</p>
        <p>This option is only available for attributes of datatype "select".</p>
    </div>
    
    <div id="datatype" title="datatypes">
        <div>
        <p>
        The following attribute datatypes exist:
        </p>

        <p>
        <strong>text</strong><br>
        This datatype is used for simple text attributes. A maximum length may be specified.
        </p>
        <p>
        <strong>password</strong><br>
        This datatype is used for password attributes. Several encryption methods are available. Passwords will not be displayed in the GUI.
        </p>
        <p>
        <strong>select</strong><br>
        This datatype creates a drop-down menu. A list of possible values must be specified.
        </p>
        <p>
        <strong>assign_one</strong><br>
        This datatype creates a drop-down menu that allows an item of any class to be assigned to another one (the selected item will be linked as &quot;parent item&quot; by default).
        </p>
        <p>
        <strong>assign_many</strong><br>
        This datatype creates a menu that allows an item of any class to be assigned to one or more other items (the selected items will be linked as &quot;parent items&quot; by default).
        </p>
        <p>
        <strong>assign_cust_order</strong><br>
        This datatype is identical to &quot;assign_many&quot; but can additionally handle the order of how items are assigned.
        </div>
    </div>

    <div id="predef_value" title="predefined value">
        <p>This option allows you to predefine a default value for any attribute. Different options are available depending on the datatype of the attribute:</p>
        <p>For <strong>text</strong> attributes: a predefined text value.</p>
        <p>For <strong>select</strong> attributes: one of the options from the "list of possible values"</p>
        <p>For <strong>assign_one</strong> attributes: an item to be pre-assigned.</p>
        <p>For <strong>assign_many</strong> and <strong>assign_cust_order</strong> attributes: one or more items to be pre-assigned (multiple items separated by "<strong>::</strong>")</p>
    </div>

    <div id="max_length" title="max length">
        <p>The character limit for an attribute within the NConf GUI.</p>
        <p>Possible range: 1 - 1024</p>
        <p>This option is only available for attributes of datatype "text" and "password".</p>
    </div>

    <div id="link_as_child" title="link as child">
        <p>
        This flag determines if items from another class should be linked as an item's children rather than being linked as parent items.</p>
        <p>
        Example: when a user adds hosts to a hostgroup, he intends the hosts to become child items of that hostgroup. By default, items that are selected in a menu will always be linked as parent items. In this specific case, the "link as child" flag needs to be set to prevent the hosts from becoming the parents of the hostgroup (here we want it the other way around).
        </p>
    </div>

    <div id="link_bidirectional" title="link bi-directional">
        <p>
        This flag affects the visibility of "assign_" attributes in the NConf GUI. It is used where users wish to manage the link between two items in more than just one GUI. This flag will make the same attribute available on both sides of the assignment.
        </p>
        <p>
        Example: a user wishes to set the link between contacts and contactgroups using both the contacts GUI and the contactgroups GUI. Setting the "link bi-directional" flag will display the same menu on both sides, even though the attribute actually only exists in one class.
        </p>
    </div>


    <div id="mandatory" title="mandatory attribute">
        <p>
        Defines if this attribute should be mandatory. Users will be forced to set this attribute.
        </p>
    </div>

    <div id="visible" title="attribute visibility">
        <p>
        Defines if this attribute should be visible in the NConf GUI.
        </p>
        <p>Note: this flag will only affect the GUI. Invisible attributes will still be stored in the background. Also, invisible attributes will be written to the Nagios configuration, as long as the "write to configuration" flag is set to "yes".
        </p>
        <p>
        Invisible attributes can be very useful in conjunction with an attribute's "pre-defined value".
        </p>
    </div>

    <div id="conf" title="write to configuration">
        <p>
        Defines if this attribute should be written to the Nagios configuration files. 
        </p>
        <p>
        This flag is most useful to distinguish NConf specific attributes from actual Nagios attributes.
        </p>
    </div>

    <div id="ordering" title="ordering">
        <p>
        Defines the order in which this attribute will be displayed in the NConf GUI. If this field is left blank, NConf will automatically assign the next available order number.
        </p>
    </div>

    <div id="naming_attr" title="naming attribute">
        <p>
        Defines if this attribute is the "naming attribute" of the current class. The "naming attribute" (i.e. the primary key) is used to distinctively identify an object within its class. 
        </p>
        <p>
        There can only be one "naming attribute" in each class. Likewise, there can only be one item with the same name within a class.
        </p>
    </div>



</div>



<?php


mysql_close($dbh);
require_once 'include/foot.php';
?>
