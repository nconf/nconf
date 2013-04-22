<?php

require_once 'include/head.php';

// Form action and url handling
if ( !isset($_GET["goto"]) ){
    $request_url = set_page();
}else{
    $request_url = $_SESSION["go_back_page"];
}

if ( !empty($_GET["order"]) ){
    $regex = '/&order=[^&]*/';
    $request_url4ordering = preg_replace($regex, '', $request_url);
}else{
    $request_url4ordering = $request_url;
}
if ( !empty($_GET["start"]) ){
    $regex = '/&start=[^&]*/';
    $request_url4limit = preg_replace($regex, '', $request_url);
    $request_url4ordering = preg_replace($regex, '', $request_url4ordering);
    $request_url4form = $request_url4ordering;
}else{
    $request_url4limit= $request_url;
    $request_url4form = $request_url4ordering;
}
# Quantity
if ( !empty($_GET["quantity"]) ){
    $regex = '/&quantity=[^&]*/';
    $request_url4quantity = preg_replace($regex, '', $request_url);
}else{
    $request_url4quantity = $request_url;
}



if ( isset($_GET["spec"]) ) {
    $spec = $_GET["spec"];
    $show_class_select = "yes";
}else{
    $spec = "";
}


// show select class field when no class is given in URL
// (admin check would be not relevant, because access rules are set very smart :)
if (  ( !isset($_GET["class"]) AND empty($_GET["class"]) ) AND ($_SESSION["group"] == GROUP_ADMIN) AND (!isset($_GET["xmode"])) ) {
    $show_class_select = "yes";
}



# set Filters

// Class Filter
if ( isset($_GET["filter1"]) AND !empty($_GET["filter1"])) {
    $class = $_GET["filter1"];
    $_SESSION["cache"]["searchfilter"]["filter1"] = $class;
}elseif ( isset($_GET["class"]) ) {
    $class = $_GET["class"];
}elseif ( isset($_SESSION["cache"]["searchfilter"]["filter1"]) ) {
    $class = $_SESSION["cache"]["searchfilter"]["filter1"];
}else{
    $class = "";
}

# special mode to allow ordinary users to change on-call settings
if (isset($_GET["xmode"]) && $_GET["xmode"] == "pikett"){
    $class = "contact";
}



# OS Filter
if ( isset($_GET["os"]) ) {
    $filter_os = $_GET["os"];
    $_SESSION["cache"][$class]["searchfilter"]["os"] = $filter_os;
}elseif ( isset($_SESSION["cache"][$class]["searchfilter"]["os"]) ) {
    $filter_os = $_SESSION["cache"][$class]["searchfilter"]["os"];
}else{
    $filter_os = "";
}

# saved/cached ordering
if ( isset($_GET["order"]) ) {
    $order = $_GET["order"];
    $_SESSION["cache"][$class]["searchfilter"]["order"] = $order;
}elseif ( isset($_SESSION["cache"][$class]["searchfilter"]["order"]) ) {
    $order = $_SESSION["cache"][$class]["searchfilter"]["order"];
}else{
    $order = "";
}

if ( ($class == "host") AND ($spec == "") ){
    $show_os_select = 1;
}

# Searchfilter
if ( isset($_GET["filter2"]) AND !empty($_GET["filter2"]) ) {
    $filter2 = $_GET["filter2"];
    $filter2 = str_replace("%", "*", $filter2);
    $_SESSION["cache"][$class]["searchfilter"]["filter2"] = $filter2;
}elseif ( isset($_SESSION["cache"][$class]["searchfilter"]["filter2"]) ) {
    $filter2 = $_SESSION["cache"][$class]["searchfilter"]["filter2"];
}else{
    $filter2 = "";
}

# quantity
# how many entries to show
if ( isset($_GET["quantity"]) ) {
    $show_quantity = $_GET["quantity"];
    $_SESSION["cache"][$class]["searchfilter"]["quantity"] = $show_quantity;
}elseif ( isset($_SESSION["cache"][$class]["searchfilter"]["quantity"]) ) {
    $show_quantity = $_SESSION["cache"][$class]["searchfilter"]["quantity"];
}else{
    if ( defined('OVERVIEW_QUANTITY_STANDARD') ){
        $show_quantity = OVERVIEW_QUANTITY_STANDARD;
    }else{
        $show_quantity = '';
    }
}
# handle "all" (empty variable will show all entries)
if ($show_quantity == "all") $show_quantity = '';




if ( (defined('CMDB_SERVERLIST_COMPARE') AND CMDB_SERVERLIST_COMPARE == 1) AND ( !isset($_SESSION["cmdb_serverlist"]) )  ){ 
    # load server list, if activated and shoudl be loaded
    # the new login system can directly go to overview without loading the server list after login, so do it here when not done yet
    $load_serverlist = 'include/modules/sunrise/load_serverlist.php';
    if (file_exists($load_serverlist) ){
        require_once ($load_serverlist);
    }
}




?>

<!-- jQuery part -->
<script type="text/javascript">
    $(document).ready(function(){

        // Clone
        $('#submit_clone').click(function() {
            var first_id = $('input:checked:first').val();
            // Check if there was a checkbox clicked
            if (first_id){
                $('#advanced').attr('action', 'clone_host.php?id=' + first_id);
            }else{
                $('#advanced').attr('action', 'clone_host.php');
            }
            $('#advanced').submit();
        });

        // Multimodify
        $('#submit_multimodify').click(function() {
            $('#advanced').attr('action', 'handle_item.php?item=<?php echo $class;?>&type=multimodify');
            $('#advanced').submit();
        });

        // Delete
        $('#submit_multidelete').click(function() {
            $('#advanced').attr('action', 'delete_item.php?type=multidelete');
            $('#advanced').submit();
        });

        // Checkbox selection with textlink
        $('#text_selectall').click(function() {
            var all_checked = $('#checkbox_selectall').attr('checked');
            if (all_checked){
                $("#checkbox_selectall").attr('checked', false);
            }else{
                $("#checkbox_selectall").attr('checked', true);
            }

            update_checkboxes();

        });

        // Checkbox selection with checkbox
        $('#checkbox_selectall').click(function() {
            update_checkboxes();
        });

        function update_checkboxes(){
            $("input:checkbox").attr('checked', $('#checkbox_selectall').is(':checked') );
        }




        // BUTTONS

        // Disable buttons if not needed
        $( "#overview_navigation .disabled > img" ).removeClass("pointer");
        $( "#overview_navigation .disabled" ).removeAttr("href");

    });
</script>

<!-- END of jQuery part -->

<?php



# save overview page for a delete operation
$_SESSION["after_delete_page"] = $request_url;


echo '<form name="search" action="'.$request_url.'" method="get">';
# set some var for search form
if (!empty($_GET["class"]) ){
    echo '<input type="hidden" id="class" name="class" value="'.$class.'">';
}
//if (!empty($_GET["order"]) ){
if ( !empty($order) ){
    echo '<input type="hidden" name="order" value="'.$order.'">';
}
if (!empty($_GET["quantity"]) ){
    echo '<input type="hidden" name="quantity" value="'.$_GET["quantity"].'">';
}

// Page output begin
echo NConf_HTML::page_title($class, '');

// Create link
    $add_link =  '<div class="overview-add">';
            $add_item = get_image( array(  "type" => "design",
                                           "name" => "add",
                                           "size" => 16,
                                           "tooltip" => 'Add '.$nav_class["friendly_name"],
                                           "class" => "lighten ui-button"
                                        ) );
            $add_link .= '<a href="handle_item.php?item='.$class.'">'.$add_item.' Add new '.$class.'</a>';
            $add_link .= '</div>';
    echo $add_link;

echo '<div class="search">';  
echo '<table border=0 frame=box rules=none style="border-width: 0px">';


// Class Filter
if ( isset($show_class_select) ){
    echo '<tr>';
        echo '<td colspan=2>Class</td>';

        $query = 'SELECT config_class FROM ConfigClasses ORDER BY config_class';
        $result = db_handler($query, 'result', "Get Config Classes");

    echo '</tr>';
    echo '<tr>';
        //echo '<td><select name="filter1" style="width:190px">';
        echo '<td colspan=2><select name="filter1">';
        echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';

        while($row = mysql_fetch_row($result)){
            echo "<option value=$row[0]";
            if ( (isset($class) ) AND ($row[0] == $class) ) echo " SELECTED";
            echo ">$row[0]</option>";
        }

        echo '</select>&nbsp;&nbsp;</td>';
    echo '</tr>';
}


// Searchfilter
echo '<tr>';
    echo '<td>Searchfilter</td>';

    if ( isset($show_os_select) ){
        echo '<td>OS</td>';
    }
    echo '<td></td>';
echo '</tr>';

echo '<tr>';

    echo '<td><input style="width:150px" type="text" name="filter2" value="'.$filter2.'"></td>';

    if ( isset($show_os_select) ){
        // OS filter
        echo '<td>';
        echo '<select name="os" style="width:200px">';
        echo '<option value="">'.SELECT_EMPTY_FIELD.'</option>';

        $query = 'SELECT fk_id_item,attr_value
                    FROM ConfigValues,ConfigAttrs,ConfigClasses
                    WHERE id_attr=fk_id_attr
                    AND id_class=fk_id_class
                    AND naming_attr="yes"
                    AND config_class="os"
                    ORDER BY attr_value
                 ';
        $result = db_handler($query, 'result', "select all os");
        while ($entry = mysql_fetch_assoc($result) ){
            echo '<option value='.$entry["fk_id_item"];
            if ( (isset($filter_os) ) AND ($entry["fk_id_item"] == $filter_os) ) echo " SELECTED";
            echo '>'.$entry["attr_value"].'</option>';
        }
        echo '</select></td>';
    }


    // submit button
    echo '<td align="left" id="buttons">&nbsp;&nbsp;<input type="submit" value="Search" name="search" align="middle">';

    // Clear button
    if ( isset($_SESSION["cache"][$class]["searchfilter"]) ){

        # get the script name
        $clear_url = $_SERVER['SCRIPT_NAME'].'?';

        # remember the class only if given ( should not be set on "general overview" )
        if (isset($_GET["class"]) )   $clear_url .= 'class='.$class.'&';
        # clear filter 1 if given
        if (isset($_GET["filter1"]) ) $clear_url .= 'filter1=&';

        # add the clear
        $clear_url .= 'clear=1';

        echo '&nbsp;&nbsp;<input type="button" name="clear" value="Clear" onClick="window.location.href=\''.$clear_url.'\'">';
    }

    echo "</td>";
echo "</tr>";

echo '</table>';

echo '</div>';


echo '</form>';

# open new form
echo '<form id="advanced" name="advanced" action="'.$request_url4form.'" method="post">';

# set class for submit form
if (!empty($class) ){
    echo '<input type="hidden" id="class" name="class" value="'.$class.'">';
}

# Advanced Tab-View
if (!isset($_GET["xmode"])){
    require_once 'include/tabs/advanced.php';
}



if( ( isset($class) ) AND ($class != "") ){

    # handle start
    if (empty($_GET["start"]) OR $_GET["start"] < 0 ){
        $start = 0;
    }else{
        $start = $_GET["start"];
    }

    # Querys
    if ($class == "host") {

        $query = '
    SELECT SQL_CALC_FOUND_ROWS
       fk_id_item AS host_id,
       attr_value AS hostname,
       (SELECT attr_value 
             FROM ConfigValues,ConfigAttrs 
             WHERE id_attr=fk_id_attr 
                 AND attr_name="address" 
                 AND fk_id_item=host_id) AS IP,
       IF( (SELECT INET_ATON(IP)),(SELECT INET_ATON(IP)),(SELECT IP)) AS BIN_IP,
       (SELECT attr_value 
             FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks 
             WHERE id_attr=ConfigValues.fk_id_attr 
                 AND naming_attr="yes" 
                 AND ConfigValues.fk_id_item=fk_item_linked2 
                 AND id_class=fk_id_class 
                 AND config_class="nagios-collector" 
                 AND ItemLinks.fk_id_item=host_id) AS collector,
       (SELECT attr_value 
             FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses 
             WHERE ConfigValues.fk_id_item=ItemLinks.fk_item_linked2 
                 AND id_attr=ConfigValues.fk_id_attr 
                 AND naming_attr="yes" 
                 AND id_class=fk_id_class 
                 AND config_class="os" 
                 AND ItemLinks.fk_id_item=host_id) AS os,
       (SELECT ConfigValues.fk_id_item 
             FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses 
             WHERE ConfigValues.fk_id_item=ItemLinks.fk_item_linked2 
                 AND id_attr=ConfigValues.fk_id_attr 
                 AND naming_attr="yes" 
                 AND id_class=fk_id_class 
                 AND config_class="os" 
                 AND ItemLinks.fk_id_item=host_id) AS os_id,
       (SELECT attr_value
             FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses 
             WHERE ConfigValues.fk_id_item=ItemLinks.fk_item_linked2 
                 AND id_attr=ConfigValues.fk_id_attr 
                 AND attr_name="icon_image"
                 AND id_class=fk_id_class 
                 AND config_class="os" 
                 AND ItemLinks.fk_id_item=host_id) AS os_icon,
       (SELECT "true" 
            FROM ConfigValues, ConfigAttrs, ConfigClasses, ItemLinks 
            WHERE id_attr=ConfigValues.fk_id_attr 
                AND ConfigAttrs.fk_id_class=id_class 
                AND config_class="service" 
                AND attr_name="service_enabled" 
                AND attr_value="no"
                AND ConfigValues.fk_id_item=ItemLinks.fk_id_item 
                AND ItemLinks.fk_item_linked2=host_id LIMIT 1) AS service_disabled 
       FROM ConfigValues,ConfigAttrs,ConfigClasses 
       WHERE id_attr=fk_id_attr AND naming_attr="yes" 
           AND id_class=fk_id_class 
           AND config_class="'.$class.'"';

        # use filters
        if ($filter2 != ""){
            $filter2 = str_replace("*", "%", $filter2);
            $filter2 = escape_string($filter2);
            $query .= ' AND attr_value LIKE "'.$filter2.'"';
        }

        # use os filter
        if ( !empty($filter_os) ) $query .= ' HAVING os_id = "'.$filter_os.'"';

        # Handle order
        if (!empty($order) ){
            $query .= ' ORDER BY '.$order;
        }else{
            $order = 'hostname ASC';
            $query .= ' ORDER BY '.$order;
        }

        # LIMIT
        if ( !empty($show_quantity) ){
            # lookup how many entries are totaly
            if ($start < 0) $start = 0;
            
            
            # make limited query
            $query .= ' LIMIT '.$start.' , '.$show_quantity;
        }

        # result for overview list
        $result = db_handler($query, 'result', "Overview list");

        # amount of total entries (with a sql function)
        $show_num_rows = $show_num_rows2 = db_handler('SELECT FOUND_ROWS();', 'getOne', "How many rows totaly");


    }else{
        # class != "host"
        if ($class == "checkcommand"){
            # special query for checkcommand and its default service name
            $query = 'SELECT SQL_CALC_FOUND_ROWS
                id_item,
                attr_value AS entryname,
                (SELECT attr_value
                    FROM ConfigValues, ConfigAttrs
                    WHERE ConfigValues.fk_id_item = id_item
                    AND ConfigAttrs.id_attr = ConfigValues.fk_id_attr
                    AND ConfigAttrs.attr_name = "default_service_name") AS default_service_name,
                IFNULL(
                    NULLIF(
                        (SELECT attr_value
                        FROM ConfigValues, ConfigAttrs
                        WHERE ConfigValues.fk_id_item = id_item
                            AND ConfigAttrs.id_attr = ConfigValues.fk_id_attr
                            AND ConfigAttrs.attr_name = "default_service_name"
                        )
                    , "")
                , attr_value) AS sorting
                FROM ConfigItems,ConfigValues,ConfigAttrs,ConfigClasses
                WHERE id_item=fk_id_item
                    AND id_attr=fk_id_attr
                    AND naming_attr="yes"
                    AND ConfigItems.fk_id_class=id_class
                    AND config_class="'.$class.'"';
            # define special ordering
            if( empty($order) ){
                $order = "entryname ASC";
            }
        }elseif($class == "service"){
            # special query for service and its hostnames
            $query = 'SELECT SQL_CALC_FOUND_ROWS
                    id_item,
                    attr_value AS entryname,
                    (SELECT attr_value FROM ConfigValues,ConfigAttrs,ConfigClasses,ItemLinks
                        WHERE fk_item_linked2=ConfigValues.fk_id_item
                            AND id_attr=ConfigValues.fk_id_attr
                            AND naming_attr="yes"
                            AND fk_id_class = id_class
                            AND config_class="host"
                            AND ItemLinks.fk_id_item=id_item) AS hostname,
                    (SELECT attr_value
                        FROM ConfigValues,ConfigAttrs
                        WHERE id_attr=fk_id_attr
                            AND attr_name="service_enabled"
                            AND fk_id_item=id_item) AS service_enabled
                    FROM ConfigItems,ConfigValues,ConfigAttrs,ConfigClasses
                    WHERE id_item=fk_id_item
                        AND id_attr=fk_id_attr
                        AND naming_attr="yes"
                        AND ConfigItems.fk_id_class=id_class
                        AND config_class="'.$class.'"';
        }else{
            $query = 'SELECT SQL_CALC_FOUND_ROWS
                    id_item,
                    attr_value AS entryname
                    FROM ConfigItems,ConfigValues,ConfigAttrs,ConfigClasses
                    WHERE id_item=fk_id_item
                        AND id_attr=fk_id_attr
                        AND naming_attr="yes"
                        AND ConfigItems.fk_id_class=id_class
                        AND config_class="'.$class.'"';
        }
        if($filter2 != ""){
            # replace * with % for sql search
            $filter2 = str_replace("*", "%", $filter2);
            $filter2 = escape_string($filter2);
            if($class == "service"){
                # search for servername AND servicename on "service"
                $query .= ' HAVING CONCAT(hostname,entryname) LIKE "'.$filter2.'"';
            }elseif($class == "checkcommand"){
                # search for default service name and checkcommand name
                $query .= 'HAVING default_service_name LIKE "'.$filter2.'"
                            OR entryname LIKE "'.$filter2.'"';
            }else{
                $query .= ' AND attr_value LIKE "'.$filter2.'"';
            }
        }
        
        # XMODE
        if(isset($_GET["xmode"]) && $_GET["xmode"] == "pikett"){
            if ( !empty($ONCALL_GROUPS) ){
                # first entry must be AND, all other are part of it with OR
                $oncall_i = 1;
                foreach ($ONCALL_GROUPS as $oncall_group){
                    if ($oncall_i == 1){
                        $query .= ' AND ( attr_value = "'.$oncall_group.'"';
                    }else{
                        $query .= ' OR attr_value = "'.$oncall_group.'"';
                    }
                    $oncall_i++;
                }
                #close query correct
                $query .= ' ) ';
            }
        }

        # Handle order
        if ($class == "service"){
            # define special ordering
            if( empty($order) ){
                $order = "hostname ASC, entryname ASC";
            }
        }

        if (!empty($order) ){
            $query .= ' ORDER BY '.$order;
        }else{
            $order = 'entryname ASC';
            $query .= ' ORDER BY '.$order;
        }


        # LIMIT
        if ( !empty($show_quantity) ){
            # lookup how many entries are totaly
            if ($start < 0) $start = 0;

            # make limited query
            $query .= ' LIMIT '.$start.' , '.$show_quantity;
        }

        # result for overview list
        $result = db_handler($query, 'result', "Overview list");

        # amount of total entries (with a sql function)
        $show_num_rows = $show_num_rows2 = db_handler('SELECT FOUND_ROWS();', 'getOne', "How many rows totaly");
        
    }


    # overview table in IE 8 will only do correct margin-top when previouse element has clear:both
    # IE 8 also needs more space for advanced box: make the empty diff 7px height
    echo '<div class="clearer" style="height: 7px"></div>';

    ##
    # show quantity
    ##
    if ($class == "host"){
        echo '<table class="overview_head" style="width: 100%;">';
    }else{
        $table_width = 400;
        if ($class == "service") $table_width = 500;

        echo '<table class="overview_head" style="width: '.$table_width.'px;">';
    }

        echo '<tr>';

        echo '<td width="20%">
                <h2 class="content_header">Overview</h2>
              </td>';

        if ( !empty($show_quantity) ){

            ###
            # show limited entries, make it swap'able
            ###
            $show_first = 0;
            $show_next = $start + $show_quantity;
            $show_prev = $start - $show_quantity; 
            $show_last = $show_num_rows - $show_quantity;

            if ($show_prev < 0) $show_prev = 0;
            if ($show_next > $show_num_rows) $show_next = $show_num_rows;
            $show_start = $start + 1;
            # no results mean no start at 0 not 1
            if ($show_num_rows == 0) $show_start = 0;

            echo '<td id="overview_navigation" style="text-align: center; vertical-align: middle">';
                if ($show_start === 1 OR $show_start === 0){
                    $if_disabled = 'class="disabled"';
                }else{
                    $if_disabled = '';
                }
                echo '<a id="2first"     '.$if_disabled.' href="'.$request_url4limit.'&start='.$show_first.'">'.ICON_LEFT_FIRST_ANIMATED.'</a>';
                echo '<a id="2previouse" '.$if_disabled.' href="'.$request_url4limit.'&start='.$show_prev.'">'.ICON_LEFT_ANIMATED.'</a>';

                echo '<span> Entries '.$show_start.' - '.$show_next.' of '.$show_num_rows.' </span>';

                if ($show_next == $show_num_rows){
                    $if_disabled = 'class="disabled"';
                }else{
                    $if_disabled = '';
                }
                echo '<a id="2next" '.$if_disabled.' href="'.$request_url4limit.'&start='.$show_next.'">'.ICON_RIGHT_ANIMATED.'</a>';
                echo '<a id="2last" '.$if_disabled.' href="'.$request_url4limit.'&start='.$show_last.'">'.ICON_RIGHT_LAST_ANIMATED.'</a>';
            echo '</td>';
        }else{
            echo '<td>&nbsp;</td>';
        }




        # selectable quantity
        echo '<td class="overview_quantity"  width="20%">';
            echo ($show_quantity != QUANTITY_SMALL) ?  '<a href="'.$request_url4quantity.'&quantity='.QUANTITY_SMALL.'">'.QUANTITY_SMALL.'</a>' : QUANTITY_SMALL;
            echo '&nbsp;&nbsp;';
            echo ($show_quantity != QUANTITY_MEDIUM) ? '<a href="'.$request_url4quantity.'&quantity='.QUANTITY_MEDIUM.'">'.QUANTITY_MEDIUM.'</a>' : QUANTITY_MEDIUM;
            echo '&nbsp;&nbsp;';
            echo ($show_quantity != QUANTITY_LARGE) ?  '<a href="'.$request_url4quantity.'&quantity='.QUANTITY_LARGE.'">'.QUANTITY_LARGE.'</a>' : QUANTITY_LARGE;
            echo '&nbsp;&nbsp;';
            echo ($show_quantity != "") ?  '<a href="'.$request_url4quantity.'&quantity=all">all</a>' : 'all';
        echo '</td>';


    # close table header div
    echo '</tr>';
    echo '</table>';



    if ($class == "host"){
        echo '<table class="ui-nconf-table ui-nconf-max-width ui-widget ui-widget-content">';
        echo '<colgroup>
                <col width="30">
                <col>
                <col width="100">
                <col width="100">
                <col width="160">
                <col width="30">
                <col width="30">
                <col width="30">
             </colgroup>';

    }else{
        $table_width = 400;
        if ($class == "service") $table_width = 500;
        echo '<table class="ui-nconf-table ui-widget ui-widget-content" style="min-width:400px" width="'.$table_width.'">';

        echo '<colgroup>';
            echo '<col>';
            if ( !isset($_GET["xmode"]) ){
                echo '<col width="30">';
                echo '<col width="30">';
            }else{
                # xmode view 
                echo '<col width="60">';
            }
        echo '</colgroup>';
    }

    

    # Fetch column titles
    $query = 'SELECT ConfigAttrs.friendly_name
                            FROM ConfigAttrs,ConfigClasses
                            WHERE id_class=fk_id_class
                            AND naming_attr="yes"
                            AND config_class="'.$class.'"
                            ';
    $title_result = db_handler($query, 'result', "Friendly name");
    echo '<thead class="ui-widget-header">';
    echo '<tr>';
        if ($class == "host") {
            echo '<td width="30">'.FRIENDLY_NAME_OS_LOGO.'</td>';
        }
        while($entry = mysql_fetch_assoc($title_result)){
            if ($class == "host"){
                $order_value = (!empty($order) AND $order ==  "hostname ASC") ? 'hostname DESC' : 'hostname ASC';
            }elseif ($class == "checkcommand"){
                # checkcommands could be sorted in 4 special ways:
                $ordering_default_service_name = FALSE;
                switch($order){
                    case "entryname ASC":
                        NConf_DEBUG::set(CHECKCOMMAND_ORDER_COMMAND_NAME_ASC, 'INFO', CHECKCOMMAND_ORDER_PRETEXT);
                        $order_value = 'entryname DESC';
                    break;
                    case "entryname DESC":
                        NConf_DEBUG::set(CHECKCOMMAND_ORDER_COMMAND_NAME_DESC, 'INFO', CHECKCOMMAND_ORDER_PRETEXT);
                        $order_value = 'sorting ASC, entryname ASC';
                        $ordering_default_service_name = TRUE;
                    break;
                    case "sorting ASC, entryname ASC":
                        NConf_DEBUG::set(CHECKCOMMAND_ORDER_DEFAULT_SERVICE_NAME_ASC, 'INFO', CHECKCOMMAND_ORDER_PRETEXT);
                        $order_value = 'sorting DESC, entryname DESC';
                        $ordering_default_service_name = TRUE;
                    break;
                    case "sorting DESC, entryname DESC":
                        NConf_DEBUG::set(CHECKCOMMAND_ORDER_DEFAULT_SERVICE_NAME_DESC, 'INFO', CHECKCOMMAND_ORDER_PRETEXT);
                        $order_value = 'entryname ASC';
                    break;
                }
            }elseif ($class == "service"){
                $order_value = (!empty($order) AND $order ==  "hostname ASC, entryname ASC") ? 'hostname DESC, entryname DESC' : 'hostname ASC, entryname ASC';
            }else{
                $order_value = (!empty($order) AND $order ==  "entryname ASC") ? 'entryname DESC' : 'entryname ASC';
            }
            echo '<td>
                <a href="'.$request_url4ordering.'&order='.$order_value.'">'
                .$entry["friendly_name"].
                '</a></td>';
        }
        if ($class == "host") {
            $order_value = ($order ==  "BIN_IP ASC") ? 'BIN_IP DESC' : 'BIN_IP ASC';
            echo '<td width=100>
                <a href="'.$request_url4ordering.'&order='.$order_value.'">'
                .FRIENDLY_NAME_IPADDRESS.
                '</a></td>';
            $order_value = ($order ==  "collector ASC") ? 'collector DESC' : 'collector ASC';
            echo '<td width=100>
                <a href="'.$request_url4ordering.'&order='.$order_value.'">'
                .FRIENDLY_NAME_NAGIOSSERVER.
                '</a></td>';
            $order_value = ($order ==  "os ASC") ? 'os DESC' : 'os ASC';
            echo '<td width=100>
                <a href="'.$request_url4ordering.'&order='.$order_value.'">'
                .FRIENDLY_NAME_OS.
                '</a></td>';
        }

        if ($class == "host") {
            echo '<td colspan="4" class="center">'.FRIENDLY_NAME_ACTIONS.'</td>';
        }elseif(!isset($_GET["xmode"])){
            echo '<td colspan="2" class="center">'.FRIENDLY_NAME_ACTIONS.'</td>';
        }else{
            echo '<td colspan="1" class="center">'.FRIENDLY_NAME_ACTIONS.'</td>';
        }

        if ( !isset($_GET["xmode"]) ){
            echo '<td id="advanced_box" name="advanced_box" class="center" style="width: 70px;">';
            echo '<input type="checkbox" id="checkbox_selectall" class="pointer"><a id="text_selectall" href="#selectall">select</a></td>';
        }

    echo "</tr>";

    echo '</thead>';
    echo '<tbody class="ui-widget-content">';

    # check for nothing found
    if ( mysql_num_rows($result) == 0 ){
        echo '<tr class="color_list1 highlight"><td colspan=9>'.TXT_NOTHING_FOUND.'</td></tr>';
    }elseif ($class == "host") {
        # Show host overview    

        # result was generated near row 384 / 453....
        if ( $result != "" ){
            $count = 1;
            while($entry = mysql_fetch_assoc($result)){

                # set list color
                if ((1 & $count) == 1){
                    $bgcolor = "color_list1";
                }else{
                    $bgcolor = "color_list2";
                }

                $nocol_style = "";

                // Compare hostname feature
                $compare_status = 0;  # default set to 0 (deactivated)
                if ( (defined('CMDB_SERVERLIST_COMPARE') AND CMDB_SERVERLIST_COMPARE == 1) AND ( isset($_SESSION["cmdb_serverlist"]) AND is_array($_SESSION["cmdb_serverlist"]) )  ){ 
                    $compare_status = compare_hostname($entry["hostname"], $_SESSION["cmdb_serverlist"]);
                }
                if ($compare_status == 2){
                    # status 2 = not in array
                    echo '<tr class="color_warning">';
                }else{
                    if($entry["collector"]){
                        echo '<tr class="'.$bgcolor.' highlight">';
                    }else{
                        echo '<tr class="ui-state-error highlight">';
                        $entry["collector"] = "not monitored";
                        $nocol_style = 'class="color_nomon_text"';
                    }
                }
                echo '<td style="text-align:center">';
                $os_icon_path = OS_LOGO_PATH.'/'.$entry["os_icon"];
                if ( file_exists($os_icon_path) ){
                    echo '<img src="'.$os_icon_path.'" alt="'.$entry["os"].'" '.OS_LOGO_SIZE.'>';
                }
                echo '</td>';
                echo '<td><a href="detail.php?class='.$class.'&id='.$entry["host_id"].'">'.$entry["hostname"].'</a></td>';
                echo '<td>'.$entry["IP"].'</td>';
                echo '<td '.$nocol_style.'>'.$entry["collector"].'</td>';
                echo '<td>'.$entry["os"].'</td>';
                echo '<td style="text-align:center"><a href="handle_item.php?item='.$class.'&amp;id='.$entry["host_id"].'">'.ICON_EDIT.'</a></td>';
                echo '<td style="text-align:center"><a href="delete_item.php?item='.$class.'&amp;ids='.$entry["host_id"].'">'.ICON_DELETE.'</a></td>';
                echo '<td style="text-align:center"><a href="modify_item_service.php?item='.$class.'&amp;id='.$entry["host_id"].'">';
                    # if all services are enabled gear-icon will be yellow
                    # red will show that there is a disabled service
                    if ( isset($entry["service_disabled"]) AND $entry["service_disabled"] == "true"){
                        echo ICON_SERVICES_DISABLED;
                    }else{
                        echo ICON_SERVICES;
                    }
                    echo '</a></td>';
                // clone button
                echo ( $class == "host" ) ? '<td style="text-align:center"><a href="clone_host.php?class='.$class.'&amp;id='.$entry["host_id"].'">'.ICON_CLONE.'</a></td>' : '';

                if ( !isset($_GET["xmode"]) ){
                    # Checkbox will now be shown all the times and it is not possible to disable them. Disabling code for now
                    # TODO: Remove swapable advanced checkboxes if really not needed anymore
                    echo '<td id="advanced_box" name="advanced_box" style="text-align:center;">';
                    echo '<input type="checkbox" name="advanced_items[]" value="'.$entry["host_id"].'" class="pointer"></td>';
                }
                echo "</tr>\n";
                $count++;
            }
        }

    }else{
    # all other classes

        if ($result != "") {
            $count = 1;
            while($entry = mysql_fetch_assoc($result)){
                # class for not active services
                if( !empty($entry["service_enabled"]) AND $entry["service_enabled"] === "no"){
                    echo '<tr class="ui-state-error highlight">';
                }else{
                    # set list color
                    if((1 & $count) == 1){
                        echo '<tr class="color_list1 highlight">';
                    }else{
                        echo '<tr class="color_list2 highlight">';
                    }
                }


                # checkcommand name and default service name special handling
                if( ( isset($class) ) AND ($class == "checkcommand") ){
                    if ( strpos($order, "entryname") === FALSE ){
                        if ( !empty($entry["default_service_name"]) ){
                            $entry["entryname"] = $entry["default_service_name"].' ('.$entry["entryname"].')';
                        }
                    }else{
                        if ( !empty($entry["default_service_name"]) ){
                            $entry["entryname"] = $entry["entryname"].' ('.$entry["default_service_name"].')';
                        }
                    }
                }

                if( ( isset($class) ) AND ($class == "service") ){

                    echo '<td><a href="detail.php?class='.$class.'&id='.$entry["id_item"].'">'.$entry["hostname"].': '.$entry["entryname"].'</a></td>';

                }else{
                    if(isset($_GET["xmode"])){
                        echo '<td><a href="detail.php?class='.$class.'&id='.$entry["id_item"].'&xmode='.$entry["entryname"].'">'.$entry["entryname"].'</a></td>';
                    }else{
                        echo '<td><a href="detail.php?class='.$class.'&id='.$entry["id_item"].'">'.$entry["entryname"].'</a></td>';
                    }
                }

                if(isset($_GET["xmode"])){
                    echo '<td style="text-align:center"><a href="handle_item.php?item='.$class.'&xmode='.$entry["entryname"].'">'.ICON_EDIT.'</a></td>';
                }else{
                    echo '<td style="text-align:center"><a href="handle_item.php?item='.$class.'&amp;id='.$entry["id_item"].'">'.ICON_EDIT.'</a></td>';
                    echo '<td style="text-align:center"><a href="delete_item.php?item='.$class.'&amp;ids='.$entry["id_item"].'">'.ICON_DELETE.'</a></td>';
                }

                if ( !isset($_GET["xmode"]) ){
                    // Advanced checkbox
                    echo '<td id="advanced_box" name="advanced_box" style="text-align:center;">';
                        echo '<input type="checkbox" name="advanced_items[]" value="'.$entry["id_item"].'" class="pointer checkbox-small">';
                    echo '</td>';
                }

                echo "</tr>\n";

                $count++;
            }
        }
    }
    echo '</tbody>';
    echo '</table>';

}

echo '</form>';



mysql_close($dbh);
require_once 'include/foot.php';

?>
