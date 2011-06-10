<!-- BEGIN "foot.php" -->

    </div> <!-- END OF DIV "maincontent" -->
</div>     <!-- END OF DIV "mainwindow" -->
<!-- empty clear div for space to footer (without that, it will not work correctly when footer has clear:both! -->
<div class="clearer"></div>
<div id="footer">
    <div>
        <?php

        if ( NConf_DEBUG::status("INFO") ){
            echo NConf_HTML::title("Info:", 2);
                echo NConf_DEBUG::show_debug("INFO");
            echo NConf_HTML::line();
        }

        // Display jQuery/ajax errors
        echo '<div id="jquery_error" class="ui-state-error" style="display: none">';
                echo '<span style="float: left; margin-right: 0.3em;" class="ui-icon ui-icon-alert">&nbsp;</span>';
                echo NConf_HTML::title("Error:", 2);
        echo '</div>';

        if ( NConf_DEBUG::status("ERROR") ){
                echo NConf_HTML::title("Error:", 2);
                echo '<font color="red">';
                echo NConf_DEBUG::show_debug("ERROR");
                echo '</font>';
            echo NConf_HTML::line();
        }


        if (DEBUG_MODE == 1){
            echo '<div id="jquery_console_parent" style="padding: 0; display: none">';
            echo NConf_HTML::swap_content('<div id="jquery_console"></div>', "<b>jQuery debugging</b>", FALSE, FALSE);
            echo '</div>';
            NConf_DEBUG::display();
        }
        ?>

        &nbsp;
    </div>
</div> <!-- END OF DIV "footer" -->

</body>
</html>
