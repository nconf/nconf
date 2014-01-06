<?php
require_once 'include/head.php';

// Form action and url handling
$request_url = set_page();

// Delete Cache of modify (if still exist)
if ( isset($_SESSION["cache"]["modify_attr"]) ) unset($_SESSION["cache"]["modify_attr"]);

// set info in the footer when naming attribute changes have done (from modify attribute)
if ( isset($_GET["naming_attr"]) ){
    if ($_GET["naming_attr"] == "changed"){
        message($info, TXT_NAMING_ATTR_CHANGED);
    }elseif ($_GET["naming_attr"] == "last"){
        message($info, TXT_NAMING_ATTR_LAST);
    }

    // Remove it from url, so that formular dont takes this get variable all around
    $request_url = preg_replace("/&naming_attr=last/", "", $request_url);
    $request_url = preg_replace("/&naming_attr=changed/", "", $request_url);
}




# Filters


if ( isset($_POST["os"]) ) {
    $filter_os = $_POST["os"];
}else{
    $filter_os = "";
}



# Show class selection
$show_class_select = "yes";


if ( isset($_GET["class"]) ) {
    $class = $_GET["class"];
}else{
    $class = "host";
}



// Page output begin

# Title
echo NConf_HTML::page_title('attributes', 'Administrate attributes');



$content = 'This mask allows administrators to modify the data schema of the NConf application.
            There is no need to make any changes to the schema for ordinary operation.
            Users are strictly discouraged from changing any attribute names, datatypes, from modifying classes in any way, 
            and from any other changes to the schema.
            Disregarding this may result in unexpected behavour of the application, failure to generate the Nagios configuration properly 
            and may under certain circumstances <b>result in data corruption or loss!</b>';

echo NConf_HTML::limit_space(
    NConf_HTML::show_error('WARNING', $content)
    , 'style="float: right; width: 555px;"'
);
// Create link
$add_link =  '<div class="overview-add"><br>';
    $add_item = get_image( array(  "type" => "design",
                                   "name" => "add",
                                   "size" => 16,
                                   "class" => "lighten"
                                ) );
    $add_link .= '<a href="modify_attr.php">'.$add_item.' Create new attribute</a>';
    $add_link .= '</div>';
echo $add_link;

echo '<form name="filter" action="'.$request_url.'" method="get">
<fieldset class="inline">
<legend>Select class</legend>
      <table>';


// Class Filter
if ( isset($show_class_select) ){
    echo '<tr>';
        $result = db_handler('SELECT config_class FROM ConfigClasses ORDER BY config_class', "result", "Get Config Classes");

    echo '</tr>';
    echo '<tr>';
        echo '<td><select name="class" style="width:192px" onchange="document.filter.submit()">';
        //echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';

        while($row = mysql_fetch_row($result)){
            echo "<option value=$row[0]";
            if ( (isset($class) ) AND ($row[0] == $class) ) echo " SELECTED";
            echo ">$row[0]</option>";
        }

        echo '</select>
            </td>';
    echo '</tr>';
}


    // submit button
    echo '<tr>';
    echo '<td align=right id=buttons>';

/*
    // Clear button
    if ( isset($_SESSION["cache"]["searchfilter"]) ){
        if ( strstr($_SERVER['REQUEST_URI'], ".php?") ){
            $clear_url = $_SERVER['REQUEST_URI'].'&clear=1';
        }else{
            $clear_url = $_SERVER['REQUEST_URI'].'?clear=1';
        }
        echo '&nbsp;&nbsp;<input type="button" name="clear" value="Clear" onClick="window.location.href=\''.$clear_url.'\'">';
    }
*/

    echo "</td>";
echo "</tr>";


echo '
</table>
</fieldset>';

/*
<div id="buttons">
    <br>
    <input type="submit" value="Show" name="submiter" align="middle">
</div>

*/

echo '</form>';


    
echo '<div class="clearer"></div>';
echo '<h2>Overview of '.$class.'</h2>';

// Attr manipulation
if ( !empty($_GET["do"]) AND !empty($_GET["id"]) ){
    if ($_GET["do"] == "up"){
        attr_order($_GET["id"], "up");
    }elseif($_GET["do"] == "down"){
        attr_order($_GET["id"], "down");
    }
        
}


    $query = 'SELECT ConfigAttrs.friendly_name, ConfigAttrs.ordering, id_attr, attr_name, datatype, mandatory, naming_attr
            FROM ConfigAttrs,ConfigClasses
                WHERE id_class=fk_id_class
                AND config_class="'.$class.'"
                ORDER BY ConfigAttrs.ordering
    ';

    $result = db_handler($query, "result", "get attributes from class");
    
    // Table beginning will be added in the output function
    $table  = '';

    if ($result != "") {

        $table .= '<thead class="ui-widget-header">';
        $table .= '<tr>';
            $table .= '<th width=30>&nbsp;</th>';
            $table .= '<th width=170>Attribute Name</th>';
            $table .= '<th width=170>Friendly Name</th>';
            $table .= '<th width=100>Datatype</th>';
            $table .= '<th width=70 class="center">Mandatory</th>';
            $table .= '<th width=60 class="center">Ordering</th>';
            $table .= '<th width=50 class="center">PK</th>';
            $table .= '<th width=40 class="center">Edit</th>';
            $table .= '<th width=40 class="center">Delete</th>';

        $table .= "</tr>";
        $table .= '</thead>';

        $table .= '<tbody class="ui-widget-content">';


        $count = 1;
        $naming_attr_count = 0;
        while($entry = mysql_fetch_assoc($result)){
            $row_warn = 0;
            if ($entry["naming_attr"] == "yes"){
                $naming_attr_count++;
                $pre = "<b>";
                $fin = "</b>";
                $naming_attr_cell = SHOW_ATTR_NAMING_ATTR;
                if ($naming_attr_count > 1){
                    $row_warn = 1;
                    message($info, TXT_NAMING_ATTR_CONFLICT);
                    $naming_attr_cell .= SHOW_ATTR_NAMING_ATTR_CONFLICT;
                }
				$additional_class = " color_nomon";
            }else{
                $pre = "";
                $fin = "";
                $naming_attr_cell = "";
				$additional_class = "";
            }

            // Show datatype icons 
            switch ($entry["datatype"]){
                case "text":
                    $ICON_datatype = SHOW_ATTR_TEXT;
                break;
                case "password":
                    $ICON_datatype = SHOW_ATTR_PASSWORD;
                break;
                case "select":
                    $ICON_datatype = SHOW_ATTR_SELECT;
                break;
                case "assign_one":
                    $ICON_datatype = SHOW_ATTR_ASSIGN_ONE;
                break;
                case "assign_many":
                    $ICON_datatype = SHOW_ATTR_ASSIGN_MANY;
                break;
				case "assign_cust_order":
                    $ICON_datatype = SHOW_ATTR_ASSIGN_CUST_ORDER;
				break;
				default:
					$ICON_datatype = '';
				break;
            }

            // Show mandatory icons 
            switch ($entry["mandatory"]){
                case "yes":
                    $ICON_mandatory = ICON_TRUE_RED;
                break;
                case "no":
                default:
                    $ICON_mandatory = ICON_FALSE_SMALL;
                break;
            }
			
			// highlight moved row
			if ( !empty($_GET["do"]) AND !empty($_GET["id"]) ){
				if ( $entry["id_attr"] == $_GET["id"]){
					$additional_class .= " ui-state-highlight";
				}
			}


            // set list color
            if ($row_warn == 1){
                $table .= '<tr class="ui-state-error highlight">';
            }elseif((1 & $count) == 1){
                $table .= '<tr class="color_list1 highlight '.$additional_class.'">';
            }else{
                $table .= '<tr class="color_list2 highlight '.$additional_class.'">';
            }


            
            $table .= '<td>'.$ICON_datatype.'</td>';

            $table .= '<td>'.$pre.'<a href="detail_admin_items.php?type=attr&class='.$class.'&id='.$entry["id_attr"].'">'.$entry["attr_name"].'</a>'.$fin.'</td>';
            $table .= '<td>'.$pre.$entry["friendly_name"].$fin.'</td>';
            $table .= '<td>'.$pre.$entry["datatype"].$fin.'</td>';
            $table .= '<td align="center"><div align=center>'.$ICON_mandatory.'</div></td>';
            // Ordering is good for debbuging
            //$table .= '<td>'.$pre.$entry["ordering"].$fin.'</td>';
            $table .= '<td class="center">'.$pre;
                $table .= '<a href="show_attr.php?class='.$class.'&id='.$entry["id_attr"].'&do=up">'.ICON_UP_BOX_BLUE.'</a>';
                $table .= '&nbsp;';
                $table .= '<a href="show_attr.php?class='.$class.'&id='.$entry["id_attr"].'&do=down">'.ICON_DOWN_BOX_BLUE.'</a>';
            $table .= $fin.'</td>';
            $table .= '</td>';
            $table .= '<td align="center"><div align=center>'.$naming_attr_cell.'</div></td>';
            $table .= '<td style="text-align:center"><a href="modify_attr.php?id='.$entry["id_attr"].'">'.ICON_EDIT.'</a></td>';
            $table .= '<td style="text-align:center"><a href="delete_attr.php?id='.$entry["id_attr"].'">'.ICON_DELETE.'</a></td>';
            $table .= "</tr>\n";

            $count++;
        }
        
        // Warn if there is no naming attribute
        if ($naming_attr_count == 0){
            message($info, TXT_NAMING_ATTR_MISSED);
        }

    }

$table .= '</tbody>';


echo NConf_HTML::ui_table($table, 'ui-nconf-max-width');



mysql_close($dbh);
require_once 'include/foot.php';

?>
