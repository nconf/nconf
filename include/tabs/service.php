<?php
# Advanced TAB for special editing (write attr value to multi host, etc..)
?>


<div id="ui-nconf-icon-bar">
    <fieldset>
        <legend>advanced clone</legend>
        <?php
        # Content
        echo '<div>';
            echo '<table>';
                    echo'<tr>
                        <td width="30" style="text-align: center">
                            <a href="clone_service.php?id='.$host_ID.'">
                                <img src="'.ADVANCED_ICON_CLONE.'" class="ui-button" alt="clone">
                            </a>
                        </td>
                        <td>
                            <a href="clone_service.php?id='.$host_ID.'">
                                clone service(s) to other host(s)
                            </a>
                        </td>
                      </tr>';
            echo '</table>';
        echo '</div>
        </fieldset>
</div>';
?>
<div style="height: 7px" class="clearer"></div>
