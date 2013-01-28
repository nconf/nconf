<?php
require_once 'include/head.php';

// Form action and url handling
$request_url = set_page();

?>
<script type="text/javascript">
    $(document).ready(function() {


    });
</script>
<?php


// Page output begin



// set width of following divs
echo '<div style="width: 535px">';

echo NConf_HTML::page_title('classes', 'Administrate classes');
echo "<br>";

$content = 'This mask allows administrators to modify the data schema of the NConf application.
            There is no need to make any changes to the schema for ordinary operation.
            Users are strictly discouraged from changing any attribute names, datatypes, from modifying classes in any way, 
            and from any other changes to the schema.
            Disregarding this may result in unexpected behavour of the application, failure to generate the Nagios configuration properly 
            and may under certain circumstances <b>result in data corruption or loss!</b>';


echo NConf_HTML::show_error('WARNING', $content);

// Create link
$add_link =  '<div class="overview-add"><br>';
    $add_item = get_image( array(  "type" => "design",
                                   "name" => "add",
                                   "size" => 16,
                                   "class" => "lighten"
                                ) );
    $add_link .= '<a href="modify_class.php">'.$add_item.' Create new class</a>';
    $add_link .= '</div>';
echo $add_link;

// Attr manipulation
if ( isset($_GET["do"]) ){
    if ($_GET["do"] == "up"){
        class_order($_GET["id"], "up");
    }elseif($_GET["do"] == "down"){
        class_order($_GET["id"], "down");
    }
        
}

echo "<br>";



// for user and admin navigation
$nav_tree = array("user", "admin");
foreach ($nav_tree as $nav_priv) {
    echo '<h2 class="content_header">'.ucfirst($nav_priv).' classes:</h2>';

    $query = 'SELECT * FROM ConfigClasses WHERE nav_privs = "'.$nav_priv.'" ORDER BY grouping, ordering ASC, config_class';
    $result = db_handler($query, "result", "ConfigClasses");

    if ($result) {

        $header_content = '<div style="width: 150px;">Class Name</div>';
        $header_content .= '<div style="width: 160px;">Friendly Name</div>';
        $header_content .= '<div class="center" style="width: 70px;">Visible</div>';
        $header_content .= '<div class="center" style="width: 60px;" colspan=2>Ordering</div>';
        $header_content .= '<div class="center" style="width: 40px;">Edit</div>';
        $header_content .= '<div class="center" style="width: 40px;">Delete</div>';

        echo NConf_HTML::ui_box_header($header_content);
        

        $box_content = '<colgroup>
                    <col width=150>
                    <col width=160>
                    <col width=70>
                    <col width=30>
                    <col width=30>
                    <col width=40>
                    <col width=40>
                </colgroup>';

        // Define here , how much td's there are (for colspans needed later)
        // Attention, also check tds with colspans !
        $colspan = 7;


        $count = 1;
        $naming_attr_count = 0;
        $group_bevore = '';
        while($entry = mysql_fetch_assoc($result)){
            $row_warn = 0;
            $naming_attr_cell = "&nbsp;";

            // Show visible icons 
            switch ($entry["nav_visible"]){
                case "yes":
                    $ICON_mandatory = ICON_TRUE;
                break;
                case "no":
                    $ICON_mandatory = ICON_FALSE_SMALL;
                break;
            }

            // Make a space between groups
            if ($count == 1){
                # User or Admin group begins
                if (empty($entry["grouping"]) AND $nav_priv == "user" ){
                    $Group = TXT_MENU_BASIC;
                }elseif (empty($entry["grouping"]) AND $nav_priv == "admin" ){
                    $Group = TXT_MENU_ADDITIONAL;
                }
                
            }elseif ( $entry["grouping"] != $group_bevore){
                # New sub-group
                $Group = $entry["grouping"];
                $count = 1;

                # Close old group
                $box_content .= '<tr>
                        <td colspan='.$colspan.'>';
                            $box_content .= '&nbsp;
                        </td>
                      </tr>
                ';
            }
            if ($count == 1){
                # Make titlebox
                $box_content .= '<tr>';
                    $box_content .= '<td class="ui-widget-header" colspan='.$colspan.'>'.$Group.'</td>';
                $box_content .= '</tr>';
            }

            $group_bevore = $entry["grouping"];

			// highlight moved row
			$additional_class = "";
			if ( !empty($_GET["do"]) AND !empty($_GET["id"]) ){
				if ( $entry["id_class"] == $_GET["id"]){
					$additional_class = " ui-state-highlight";
				}
			}

            // set list color
            if ($row_warn == 1){
                $box_content .= '<tr class="color_warning highlight">';
            }elseif((1 & $count) == 1){
                $box_content .= '<tr class="odd highlight '.$additional_class.'">';
            }else{
                $box_content .= '<tr class="even highlight '.$additional_class.'">';
            }

            $detail_link = '<a href="detail_admin_items.php?type=class&id='.$entry["id_class"].'">'.$entry["config_class"].'</a>';
            $detail_link_friendly_name = '<a href="detail_admin_items.php?type=class&id='.$entry["id_class"].'">'.$entry["friendly_name"].'</a>';

            $box_content .= '<td class="">';
                $box_content .= $detail_link;
            $box_content .= '</td>';
            $box_content .= '<td>'.$detail_link_friendly_name.'</td>';
            $box_content .= '<td class="center">'.$ICON_mandatory.'</td>';
            $box_content .= '<td class="center">'.'<a href="show_class.php?id='.$entry["id_class"].'&do=up">'.ICON_UP_BOX_BLUE.'</a></td>';
            $box_content .= '<td class="center">'
                    .'<a href="show_class.php?id='.$entry["id_class"].'&do=down">'.ICON_DOWN_BOX_BLUE.'</a>'.
                 '</td>';
            $box_content .= '<td class="center"><a href="modify_class.php?id='.$entry["id_class"].'">'.ICON_EDIT.'</a></td>';
            $box_content .= '<td class="center"><a href="delete_class.php?id='.$entry["id_class"].'">'.ICON_DELETE.'</a></td>';


            $box_content .= "</tr>\n";
            
            $count++;
        }

        
        // show content box
        echo NConf_HTML::ui_box_content( NConf_HTML::ui_table($box_content) );

    }

    echo '<br>';

} // End of nav_tree

echo '</div>';

mysql_close($dbh);
require_once 'include/foot.php';

?>
