<?php
echo '<h2 class="ui-widget-header header"><span>Update</span></h2>';

echo '
    <div class="ui-widget-content box_content">
        <table border=0 width=188>
                <tr>
                    <td>
                        ';
                        if ( !empty($step) AND $step == 0 ){
                            echo '<div class="link_with_tag link_with_tag_active">compatibility check</div>';
                        }else{
                            echo '<div class="link_with_tag">compatibility check</div>';
                        }

                        # steps:
                        for ($i = 1; $i< 4; $i++){
                            echo '<br><div class="';
                            if ( $i == $step ){
                                echo "link_with_tag link_with_tag_active";
                            }else{
                                echo "link_with_tag";
                            }
                            echo '">step '.($i).'</div>';
                        }
                        echo '
                    </td>
                </tr>

        </table>
    </div>
    ';

?>
