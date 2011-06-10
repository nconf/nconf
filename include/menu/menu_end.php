    <?php
    # only show de logout button if authentication is enabled and the script is not INSTALL.php
    if ( defined("AUTH_ENABLED") AND AUTH_ENABLED == 1 AND ( !preg_match( '/INSTALL\.php/', $_SERVER["SCRIPT_NAME"]) )){
        echo '
            <h2 class="ui-widget-header header">
                <span>
                    <a href="index.php?logout=1" >Logout</a>
                </span>
            </h2>';
    }
    ?>

   </div> <!-- END OF DIV "accordion" -->
 </div>     <!-- END OF DIV "left_navigation" -->
</div>         <!-- END OF DIV "navigation" -->


<div id="mainspace" style="float: left; width: 22px; height: 100px"> </div>
