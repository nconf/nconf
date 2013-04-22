<?php
# History tab view (in detail.php)
?>

<!-- jQuery part -->
<script type="text/javascript">

    $(document).ready(function(){
      // Attach the clever accordion to history tab
      $("#advanced_accordion h2").nconf_accordion_clever();
    });

</script>
<!-- END of jQuery part -->


<div class="tab_advanced big">
    <div id="advanced_accordion">
        <h2 id="advanced_history" class="ui-nconf-header ui-widget-header ui-corner-top pointer">
            History
        </h2>
    
        <?php

        # movable content
        echo '<div class="ui-widget-content box_content">';


if ( !empty($item_id) ){
    # Normal query
    $query = 'SELECT timestamp, action, attr_name FROM History
            WHERE fk_id_item='.$item_id.'
            AND action <> "edited"
            ORDER BY timestamp DESC, id_hist DESC
            LIMIT '.HISTORY_TAB_LIMIT.';';
    if ( !empty($_GET["filter"]) ) $title .= '<br>--> filtered for <i>'.$_GET["filter"].'</i>';
}


# Content
    echo '<table class="ui-nconf-table ui-nconf-max-width">';

    $result = db_handler($query, 'result', "get history entries");
    if (mysql_num_rows($result) == 0){
        echo '<tr class="box_content"><td colspan=3>no history data found</td></tr>';
    }else{
        echo '<tr class="box_content">
                <td colspan=2>Last '.HISTORY_TAB_LIMIT.' changes:</td>
                <td>
                    <div align="right">
                        <a href="history.php?id='.$item_id.'">show all changes</a>
                    </div>
                </td>
              </tr>';
        echo '<tr>';
            echo '<td class="ui-state-default">When</td>
                  <td class="ui-state-default">Action</td>
                  <td class="ui-state-default" style="border-right: 0px;">Object</td>';
        echo '</tr>';
        $count = 1;
        while($entry = mysql_fetch_assoc($result)){
            if ( !empty($timestamp_previouse_entry) AND $timestamp_previouse_entry > $entry["timestamp"]) {
                $timestamp = $entry["timestamp"];
            }elseif( !empty($timestamp_previouse_entry) ){
                $timestamp = " ";
            }else{
                $timestamp = $entry["timestamp"];
            }

            # Remove time from date
            $timestamp_arr = explode(' ', $timestamp);
            $timestamp = $timestamp_arr[0];

            if((1 & $count) == 1){
                $bgcolor = "odd";
            }else{
                $bgcolor = "even";
            }
            echo '<tr class="'.$bgcolor.' highlight">';
                echo '<td>'.$timestamp.'</td>';
                echo '<td>'.$entry["action"].'</td>';
                echo '<td>';
                    if ( !empty($item_id) ){
                        echo '&nbsp<a href="history.php?id='.$item_id.'&amp;filter='.$entry["attr_name"].'">'.$entry["attr_name"].'</a>';
                    }else{
                        echo $entry["attr_name"];
                    }
                    echo '</td>';
                //echo '<td style="vertical-align:text-top" class="color_list1">&nbsp;'.$entry["attr_value"].'</td>';
            echo '</tr>';

            # save timestampt for compare with next entry
            $timestamp_previouse_entry = $entry["timestamp"];
            $count++;
        }

    }

    echo '</table>';


    echo '</div>
    </div>
</div>';

?>
