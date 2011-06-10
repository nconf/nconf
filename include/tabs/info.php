<?php
# Advanced TAB for special editing (write attr value to multi host, etc..)
?>
<div class="tab_info accordion">
    <div class="absolute" style="width:inherit;">
        <div class="move_tab_right">
            <div class="dhtmlgoodies_question">
            </div>
            <div class="dhtmlgoodies_answer">
                <div style="height:400px">
                    <h2 class="header" onmouseover="this.style.cursor='pointer'" onclick="showHideContent('', 'dhtmlgoodies_q1', 'hide');">
                        <span>
                            Info
                        </span>
                    </h2>
                    <div id="info_ajax" style="width: 100%">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<?php
# activate movable content
echo js_prepare("initShowHideDivs();");
?>

