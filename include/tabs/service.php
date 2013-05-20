<?php
# Advanced TAB for special editing (write attr value to multi host, etc..)
?>

<!-- jQuery part -->
<script type="text/javascript">

    $(document).ready(function(){
        $("#advanced_accordion").accordion({
            collapsible: true
        });
    });

</script>
<!-- END of jQuery part -->


<div class="tab_advanced big">
    <div style="position: absolute; width:inherit;">
        <div id="advanced_accordion">
        <h3>
            <a href="#">
                Advanced clone
            </a>
        </h3>
        <?php
        # Content
        echo '<div>';
            echo '<table>';
                    echo'<tr>
                        <td width="30" style="text-align: center">
                            <a href="clone_service.php?id='.$host_ID.'">
                                <img src="'.ADVANCED_ICON_CLONE.'" style="border-style: none; margin: 0px; padding: 0px; vertical-align: middle; width: 16px; height: 16px;" alt="clone">
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
        </div>
    </div>
</div>';

?>
