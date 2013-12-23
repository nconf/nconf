<?php
require_once 'include/head.php';

//delete cache if not resent from clone
if( !preg_match('/^clone/', $_SESSION["go_back_page"]) ){
    message ($debug, 'Cleared clone cache' );
    unset($_SESSION["cache"]["clone"]);
}
set_page();
// Check chache
if ( isset($_SESSION["cache"]["clone"]) ){
    $cache = $_SESSION["cache"]["clone"];
}elseif( !empty($_GET["id"]) ){
    $cache["template_id"] = $_GET["id"];
}


# Title
echo NConf_HTML::page_title('host', 'Clone host');
$title = "Clone a host";
echo '<div class="ui-nconf-header ui-widget-header ui-corner-tl ui-corner-tr ui-helper-clearfix">';
    echo '<h2 class="page_action_title">'.$title.' <span class="item_name">'.$item_name.'</span></h2>';
echo '</div>';
# content block
echo '<div class="ui-nconf-content ui-widget-content ui-corner-bottom">';
?>

<form name="clone_item" action="clone_host_write2db.php" method="post">
  <br>
    <table>
      <tr><td valign=top>host to clone</td>
          <td width=10></td>
          <td><select name=template_id>
            
<?php
# Fetch all hosts
$query = 'SELECT fk_id_item,attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses 
                WHERE id_attr=fk_id_attr 
                    AND naming_attr="yes" 
                    AND id_class=fk_id_class 
                    AND config_class="host" 
                ORDER BY attr_value';

$result = mysql_query($query);

while($hosts = mysql_fetch_assoc($result)){
    echo '<option value='.$hosts["fk_id_item"];
    if ( (isset($cache["template_id"])) AND ($cache["template_id"] == $hosts["fk_id_item"]) ) {
        echo ' SELECTED';
    }
    echo '>'.$hosts["attr_value"].'</option>';
}
?>
          </select></td><td valign="top" class="attention">*</td></tr>
      <tr><td valign="top">new hostname</td>
          <td width=10></td>
          <td><input name="hostname" type=text maxlength=255
                value="<?php if (isset($cache["hostname"])) echo $cache["hostname"];?>"></td>
          <td valign="top" class="attention">*</td></tr>
      <tr><td valign="top">new alias</td>
          <td width=10></td>
          <td><input name="alias" type=text maxlength=255
                value="<?php if (isset($cache["alias"])) echo $cache["alias"];?>"></td>
          <td valign="top" class="attention">&nbsp;</td></tr>
      <tr><td valign="top">new IP-address</td>
          <td width=10></td>
          <td><input name="ip" type=text maxlength=255
                value="<?php if (isset($cache["ip"])) echo $cache["ip"];?>"></td>
          <td valign="top" class="attention">*</td></tr>
      <tr><td valign="top"><br>new parent hosts</td>
          <td width=10></td>
          <td><br><select name="parents[]" style="<?php echo CSS_SELECT_MULTI ?>" multiple>
                <option value="">-> no parent hosts</option>
                <option value="CLONE-PARENTS"
                    <?php 
                    if ( isset($_SESSION["cache"]["clone"]["parents"]) ){
                        if ( in_array("CLONE-PARENTS", $_SESSION["cache"]["clone"]["parents"]) ) {
                            echo ' SELECTED';
                        }
                    }
                    echo'>-> clone original parent hosts</option>';

                $result = mysql_query($query);
                while($hosts = mysql_fetch_assoc($result)){
                    echo '<option value='.$hosts["fk_id_item"];
                    if ( isset($_SESSION["cache"]["clone"]["parents"]) ){
                        if ( in_array($hosts["fk_id_item"], $_SESSION["cache"]["clone"]["parents"]) ) {
                            echo ' SELECTED';
                        }
                    }
                    echo'>'.$hosts["attr_value"].'</option>';
                }
                ?>
              </select>
          </td>
          <td valign="top" class="attention">&nbsp;</td></tr>
    </table>

<?php
# Tell the Session, send db query is ok (we are coming from formular)
$_SESSION["submited"] = "yes";
?>
    <div id=buttons><br><br>
    <input type="Submit" value="Submit" name="submit" align="middle">
    <input type="Reset" value="Reset">
    <?php
        // Clear button
        if ( isset($_SESSION["cache"]["clone"]) ){
            if ( strstr($_SERVER['REQUEST_URI'], ".php?") ){
                $clear_url = $_SERVER['REQUEST_URI'].'&clear=1&class=clone';
            }else{
                $clear_url = $_SERVER['REQUEST_URI'].'?clear=1&class=clone';
            }
            echo '<input type="button" name="clear" value="Clear" onClick="window.location.href=\''.$clear_url.'\'">';
        }
    ?>
    </div>
</form>

<?php
mysql_close($dbh);
require_once 'include/foot.php';
?>
