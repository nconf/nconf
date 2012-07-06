<?php
require_once 'include/head.php';
// Load the dataTables plugin
echo '<script src="include/js/jquery_plugins/jquery.dataTables.min.js" type="text/javascript"></script>';
echo '<script src="include/js/jquery_plugins/jquery.dataTables.fnSetFilteringDelay.js" type="text/javascript"></script>';

if ( !empty($_GET["id"]) ){
    ?>
    <script type="text/javascript">
        $(document).ready(function() {

            $('#history tbody tr').live('hover', function() {
                $(this).children().toggleClass( 'highlighted' );
            });

            oTable = $('#history').dataTable({
                "bJQueryUI": true,
                "sPaginationType": "full_numbers",
                "aaSorting": [[ 0, "desc" ], [5, "desc"] ],
                "aLengthMenu": [[30, 100, 500, -1], [30, 100, 500, "All"]],
                "iDisplayLength": 30,
                "bProcessing": true,
                "bServerSide": true,
                "sAjaxSource": "call_file.php?ajax_file=json/history.php&debug=no&id=<?php echo $_GET["id"];?>",
                "sDom": '<"H"lrf>t<"F"ip>',
                "bAutoWidth": false,
                "aoColumns": [
                    null, null, null, null, null, { "bVisible": false }
                    ],
                "fnInitComplete": function() {
                    $('#history_processing').addClass('ui-widget-header');
                    $("#loading").hide();
                    $("#hidden_history").show();
                }
            });


        });
    </script>
    <?php

    $item_class = db_templates("class_name", $_GET["id"]);
    $item_name = db_templates("naming_attr", $_GET["id"]);

    # Set time seperation (empty row after time-change)
    $time_seperation = TRUE;
    $show_item_links = FALSE;

    # Set title
    $title = NConf_HTML::page_title($item_class);
    $title .= '<h2 class="page_action_title">History of : <span class="item_name">'.$item_name .'</span></h2>';
    if ( !empty($_SESSION["go_back_page"]) ){
        $detail_navigation = '<a class="button_back jQ_tooltip" title="back" href="'.$_SESSION["go_back_page"].'"></a>';
    }
    # Expand the titel with filter
    if ( !empty($_GET["filter"]) ) $title .= '<br>--> filtered for <i>'.$_GET["filter"].'</i>';
}else{

    ?>
    <script type="text/javascript">
        $(document).ready(function() {

            $('#history tbody tr').live('hover', function() {
                $(this).children().toggleClass( 'highlighted' );
            });

            oTable = $('#history').dataTable({
                "bJQueryUI": true,
                "sPaginationType": "full_numbers",
                "aaSorting": [[ 0, "desc" ], [5, "desc"] ],
                "aLengthMenu": [[30, 100, 500, -1], [30, 100, 500, "All"]],
                "iDisplayLength": 30,
                "bProcessing": true,
                "bServerSide": true,
                "sAjaxSource": "call_file.php?ajax_file=json/history.php&debug=no",
                "sDom": '<"H"lrf>t<"F"ip>',
                "bAutoWidth": false,
                "aoColumns": [
                    null, null, null, null, null, { "bVisible": false }
                    ],
                "fnInitComplete": function() {
                    $('#history_processing').addClass('ui-widget-header');
                    $("#loading").hide();
                    $("#hidden_history").show();
                }
            });
            // set delay for lower search requests (plugin)
            oTable.fnSetFilteringDelay(500);


        });
    </script>
    <?php

    # Set title
    $title = NConf_HTML::page_title('history', "Basic history");
    $subtitle   = TXT_HISTORY_SUBTITLE;
    $time_seperation = FALSE;
    $show_item_links = TRUE;
}


### Content



# nav buttons
if (!empty($detail_navigation) ){
echo '<div id="ui-nconf-icon-bar">'
        .$detail_navigation.
        '</div>';
}

# title
if ( !empty($subtitle) ){
    $title .= $subtitle."<br><br>";
}
echo $title;

echo '<img id="loading" src="img/working_small.gif">';
echo '<div id="hidden_history" style="display: none;">';


/*
# Get result
$result = db_handler($query, 'result', "get history entries");
# amount of total entries (with a sql function)
$show_num_rows = db_handler('SELECT FOUND_ROWS();', 'getOne', "How many rows totaly");
if ( ( empty($_GET["show"]) OR $_GET["show"] != "all" ) AND $show_num_rows > "5000" ){
    $content = 'For performance reasons only the last 5000 entries are listed here. Total history entries found: '.$show_num_rows;
    echo NConf_HTML::show_highlight("Output limited to 5000 entries", $content);
    echo "<br>";
}
*/


# Show table
echo '<table id="history" style="width: 100%">';
echo '<thead>';
echo '<tr>';
    echo '<th width="120">When</td>
            <th width="120">Who</td>
            <th width="60">Action</td>
            <th width="100">Object</td>
            <th width="360">Value</td>
            <th width="20">ID</td>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';


echo '</tbody>';
echo '</table>';


echo '</div>'; // hidden div on loading

mysql_close($dbh);
require_once 'include/foot.php';

?>
