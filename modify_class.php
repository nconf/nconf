<?php
require_once 'include/head.php';
set_page();


# go to show attribute page, when modify was ok
$HTTP_referer = 'show_class.php';
$_SESSION["go_back_page_ok"] = $HTTP_referer;
message($debug, "set go_back_page_ok : ".$_SESSION["go_back_page_ok"]);


if(isset($_GET['id'])){
  $id = $_GET['id'];
  $title = "Modify class";
}else{
  $id = "new";
  $title = "Add class";
}

if ($id != "new"){
    # get entries
    $query = 'SELECT * FROM ConfigClasses WHERE id_class="'.$id.'"';
    $class_entry = db_handler($query, "assoc", "get entries");

    $old_grouping = $class_entry["grouping"];
    $old_nav_privs = $class_entry["nav_privs"];

}else{
    # new entry does not have predefined values like the modify part
    $class_entry = array("nav_visible" => "",
                        "config_class" => "",
                        "friendly_name" => "",
                        "grouping" => "",
                        "nav_privs" => "",
                        "nav_links" => "",
                        "class_type" => "",
                        "out_file" => "",
                        "nagios_object" => "",
                        "ordering" => "");
    $old_grouping = '';
    $old_nav_privs = '';
}

# new group must be defined in both scenarios
$class_entry["new_group"] = "";


# Check cache
if ( isset($_SESSION["cache"]["modify_class"][$id]) ){
    # Cache
    foreach ($_SESSION["cache"]["modify_class"][$id] as $key => $value) {
        $class_entry[$key] = $value;
    }
}
?>


<!-- jQuery part -->
<script type="text/javascript">
    $(document).ready(function(){

        // Disable the class type select
        var nagios_object = $("input[name='config_class']").val() ;
        switch ( nagios_object ){
            case "host":
            case "hostgroup":
            case "service":
            case "servicegroup":
            case "advanced-service":
            case "host-preset":
            case "nagios-monitor":
            case "nagios-collector":

                $(":input[name=class_type]").attr('disabled', 'disabled');

            break;
        }

        // generate the help buttons with help text
        $.nconf_help_admin();

    });
</script>



<!-- END of jQuery part -->





<!-- page content -->
<form name="form1" action="modify_class_write2db.php" method="post" onreset="check_input()">
<div style="width: 520px; position: relative;">
    <?php
    $detail_navigation = '<a class="button_back jQ_tooltip" title="back" href="'.$_SESSION["go_back_page_ok"].'"></a>';
    echo '<div id="ui-nconf-icon-bar">'.$detail_navigation.'</div>';
    echo NConf_HTML::page_title('', $title);
    ?>
    <input type=hidden name="class_id" value="<?php echo $id; ?>">
    <input type=hidden name="ordering" value="<?php echo $class_entry["ordering"]; ?>">
    <input type=hidden name="old_group" value="<?php echo $old_grouping; ?>">
    <input type=hidden name="old_nav_privs" value="<?php echo $old_nav_privs; ?>">


    <fieldset id="page_content">
    <legend>Main</legend>
        <table width="500">
            <tr>
                <td width="200">class name</td>
                <td width="20"></td>
                <td>
                    <input type=text name=config_class maxlength=60 value="<?php echo $class_entry["config_class"]; ?>">
                </td>
                <td width=20 class="attention center">*</td>
            </tr>
            <tr>
                <td>friendly name (displayed in GUI)</td>
                <td width="20"></td>
                <td>
                    <input type=text name=friendly_name maxlength=80 value="<?php echo $class_entry["friendly_name"]; ?>">
                </td>
                <td width=20 class="attention center">*</td>
            </tr>
            <tr>
                <td>class type</td>
                <td width="20"></td>
                <td>
                    <select name="class_type" class="small_input"> 
                        <option <?php if($class_entry["class_type"] == "global") echo " selected"; ?> >global</option>
                        <option <?php if($class_entry["class_type"] == "monitor") echo " selected"; ?> >monitor</option>
                        <option <?php if($class_entry["class_type"] == "collector") echo " selected"; ?> >collector</option>
                    </select>
                    <!--<div class="attention center" style="float: left; width: 20px;">*</div>-->
                </td>
                <td width=20 class="attention center">*</td>
            </tr>
        </table>
    </fieldset>
</div>
<?php
/*
if ($id != "new"){
    $width = 700;
}else{
    $width = 520;
}
*/
$width = 520;
echo '<div style="width: '.$width.'px;">';
?>
    <fieldset>
    <legend>Navigation</legend>
        <table width="500">
            <tr>
                <td width="200">visible in the navigation?</td>
                <td width="20"></td>
                <td>
                    <select name="nav_visible" id="nav_visible" class="small_input" onchange="check_input()">
                        <option <?php if($class_entry["nav_visible"] == "yes") echo " selected"; ?> >yes</option>
                        <option <?php if($class_entry["nav_visible"] == "no") echo " selected"; ?> >no</option>
                    </select>
                </td>
                <td width=20 class="attention center">*</td>
            </tr>
            <tr>
                <td>class permissions</td>
                <td width="20"></td>
                <td>
                    <select name="nav_privs" class="small_input" onchange="check_input()">
                        <option <?php if($class_entry["nav_privs"] == "admin") echo " selected"; ?> >admin</option>
                        <option <?php if($class_entry["nav_privs"] == "user") echo " selected"; ?> >user</option>
                    </select>
                    <div class="attention center" style="float: left; width: 20px;"></div>
                </td>
            </tr>
            <tr>
                <td>'user' section grouping</td>
                <td width="20"></td>
                <td><select name="selectusergroup" onchange="check_input()">
<?php
    $query = mysql_query("SELECT grouping FROM ConfigClasses WHERE nav_privs = 'user' AND grouping != '' GROUP BY grouping ORDER BY grouping");
    echo '<option value="">&nbsp;</option>';
    while($entry = mysql_fetch_row($query)){
        echo '<option value="'.$entry[0].'"';
            if ( $entry[0] == $class_entry["grouping"] ) echo " selected";
        echo " >$entry[0]</option>";
    }
?>
                </select></td>
                <td width=20 class="attention center"></td>
            </tr>
            <tr>
                <td>'admin' section grouping</td>
                <td width="20"></td>
                <td><select name="selectadmingroup" onchange="check_input()">
<?php
    $query = mysql_query("SELECT grouping FROM ConfigClasses WHERE nav_privs = 'admin' AND grouping != '' GROUP BY grouping ORDER BY grouping");
    echo '<option value="">&nbsp;</option>';
    while($entry = mysql_fetch_row($query)){
        echo '<option value="'.$entry[0].'"';
            if ( $entry[0] == $class_entry["grouping"] ) echo " selected";
        echo " >$entry[0]</option>";
    }
?>
                </select></td>
                <td width=20 class="attention center"></td>
            </tr>
            <tr>
                <td>optional: define a new group</td>
                <td width="20"></td>
                <td>
                    <input type=text name=new_group maxlength=30 value="<?php echo $class_entry["new_group"]; ?>" onkeyup="check_input()">
                </td>
                <td width=20 class="attention center"></td>
            </tr>
            <?php
            // Only display the navigation link string when modifying
            if ($id != "new"){
                echo '<tr>
                        <td width="200">navigation link string</td>
                        <td width="20"></td>
                        <td colspan=2>
                            <input type=text name=nav_links maxlength=512 value="'.$class_entry["nav_links"].'">
                        </td>
                      </tr>';
            }else{
                echo '<tr style="display: none">
                        <td width="200">navigation link string</td>
                        <td width="20"></td>
                        <td colspan=2>
                            <input type=text name=nav_links maxlength=512 value="'.$class_entry["nav_links"].'">
                        </td>
                      </tr>';
            }
            ?>
        </table>
    </fieldset>
</div>
<div style="width: 520px;">
    <fieldset>
    <legend>Nagios specific</legend>
        <table width="500">
            <tr>
                <td width="200">output filename</td>
                <td width="20"></td>
                <td>
                    <input type="text" name="out_file" maxlength="50" value="<?php echo $class_entry["out_file"]; ?>" onkeyup="check_input()">
                </td>
                <td width=20 class="attention center"></td>
            </tr>
            <tr>
                <td>Nagios object definition</td>
                <td width="20"></td>
                <td>
                    <input type="text" name="nagios_object" maxlength="50" value="<?php echo $class_entry["nagios_object"]; ?>" onkeyup="check_input()">
                </td>
                <td width=20 class="attention center"></td>
            </tr>
        </table>
    </fieldset>

        <table>
            <tr>
                <td>
                    <br>
                </td>
            </tr>
            <tr>
                <td>
                    <div id=buttons>
                        <input type="Submit" value="Submit" name="submit" align="middle">&nbsp;&nbsp;
                        <input type="Reset" value="Reset">
                    </div>
                </td>
            </tr>
        </table>

</div>

</form>

<script type="text/javascript">
<!--

    function check_input(){

    //alert(document.form1.nav_visible.options[0].value);
    // disable fields if no is selected (value 1)
        if (document.form1.nav_visible.options[1].selected == true){
            document.form1.selectusergroup.disabled = true;
            document.form1.selectadmingroup.disabled = true;
            document.form1.new_group.disabled = true;
            document.form1.nav_links.disabled = true;
        }else{
            document.form1.new_group.disabled = false;
            document.form1.nav_links.disabled = false;
        //admin selected
            if (document.form1.nav_privs.options[0].selected == true){
                document.form1.selectusergroup.disabled = true;
                document.form1.selectadmingroup.disabled = false;
            }else{
                document.form1.selectusergroup.disabled = false;
                document.form1.selectadmingroup.disabled = true;
            }

            if (document.form1.new_group.value == "") {

            }else{
                document.form1.selectusergroup.disabled = true;
                document.form1.selectadmingroup.disabled = true;
            }
        }

    }

    check_input();



//-->
</script>



<!-- Help text content -->
<div id="help_text" style="display: none">
    <div id="class_type" title="class type">
        <p>
        This flag determines the availability of objects in a distributed monitoring environment. The following settings can be set for each class individually:</p>
        <p>
        <strong>global</strong><br>
        Items of this class will be available globally on all Nagios collectors / monitors. If you've set an "output filename" for this class, the output file will be written to the "global" output folder.
        </p>
        <p>
        <strong>monitor</strong>*<br>
        Items of this class will only be available on the monitor server(s). If you've set an "output filename" for this class, an individual output file will be generated for each monitor server.
        </p>
        <p>
        <strong>collector</strong>*<br>
        Items of this class will only be available on the collector server(s) that they are used on. Additionally, all items will also be available on the monitor server(s). If you've set a "output filename" for this class, an individual output file will be generated for each collector / monitor server.
        </p>
        <p>
        (*) Please note that the <strong>monitor</strong> and <strong>collector</strong> settings work best with classes which are linked to hosts or services. 
        </p>
    </div>

    <div id="out_file" title="output filename">
        <p>
        The name of the Nagios configuration file that is generated by NConf (e.g. "hosts.cfg"). The output directory will be chosen automatically, depending on the configured "class type".
        </p>
        <p>
        If this field is left empty, NConf will not generate an output file, as is the case for NConf internal classes etc.
        </p>
    </div>

    <div id="nagios_object" title="Nagios object definition">
        <p>
        The object type used when specifying an object in the Nagios configuration.<br><br>
    
        Example: <pre>definine host {</pre>

        <br>This is useful when you want to define mutliple classes in NConf that end up being the same thing in Nagios (e.g. "checkcommands" and "misccommands").
        </p>
    </div>

    <div id="nav_privs" title="class permissions">
        <p>
        This flag was originally implemented to influence the positioning of the class within the navigation.<br><br>
    
        This flag is now also used to select which permissions are required to access items of a certain class.<br><br>
        INFO:<br>
        Access rights for accounts of type 'user' might not be fully implemented yet throughout the GUI.
        </p>
    </div>

</div>






<?php
mysql_close($dbh);
require_once 'include/foot.php';
?>
