<?php
# Advanced TAB for special editing (write attr value to multi host, etc..)
?>

<div class="tab_advanced">
    <div class="independent">
        <div id="advanced_accordion">
            <h3>
                <a href="#">
                    Advanced
                </a>
            </h3>

    <?php

    # movable content
    echo '<div>';

        # Content
        echo '<table class="collapse">';
            echo '<colgroup>
                    <col width="30">
                    <col>
                  </colgroup>';
            if ($class == "host"){
                echo'<tr id="submit_clone">
                    <td style="text-align: center">
                            <input type="image" src="'.ADVANCED_ICON_CLONE.'" value="clone" name="clone" style="width: 16px; height: 16px; border-style:none" onclick="document.advanced.submit();" class="lighten">
                    </td>
                    <td>
                        <a href="#clone">clone</a>
                    </td>
                  </tr>';
            }
            echo '<tr id="submit_multimodify">
                    <td style="text-align: center">
                        <input type="image" src="'.ADVANCED_ICON_MULTIMODIFY.'" value="multimodify" name="multimodify" style="width: 16px; height: 16px; border-style:none" class="lighten">
                    </td>
                    <td>
                        <a href="#multi modify">multi modify</a>
                    </td>
                  </tr>';
        echo '<tr id="submit_multidelete">
                <td style="text-align: center">
                    <input type="image" src="'.ADVANCED_ICON_DELETE.'" value="multidelete" name="multidelete" style="width: 16px; height: 16px; border-style:none" onclick="document.advanced.submit();" class="lighten">
                </td>
                <td>
                    <a href="#mutidelete">delete</a>
                </td>
              </tr>';
        echo '<tr>
                <td style="text-align: center; height: 16px;">
                    <input type="checkbox" id="checkbox_selectall" style="border-style: none; width: 12px; height: 12px;" value="5393" class="pointer">
                </td>
                <td>
                    <a id="text_selectall" href="#selectall">select all</a>
                </td>
              </tr>';
      echo '</table>';

    # Close answer (movable content)
    echo '  </div>';


    # Close tab:
    ?>

        </div>
    </div>
</div>


<!-- jQuery part -->
<script type="text/javascript">

// not wait for document ready, because overview can be slow on loading...
//    $(document).ready(function(){
        var cookie_status = readCookie('advanced_box');
        if (cookie_status && cookie_status == "open") {
            cookie_status = 0;
        }else{
            cookie_status = false;
        }
        $("#advanced_accordion").accordion({
            collapsible: true,
            active: cookie_status,
            changestart: function(event, ui) {
                    var active = $( this ).accordion( "option", "active" );
                    if (active === false){
                        // Remove advanced boxes
                        $( 'td[name="advanced_box"]' ).hide();
                        createCookie('advanced_box', "close", 365);
                    }else{
                        // Show advanced boxes
                        $( 'td[name="advanced_box"]' ).show();
                        createCookie('advanced_box', "open", 365);
                    }

                }
        });


//    });
</script>
<!-- END of jQuery part -->
