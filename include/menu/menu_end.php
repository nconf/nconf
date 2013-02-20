    <?php
    # only show de logout button if authentication is enabled and the script is not INSTALL.php
    if ( defined("AUTH_ENABLED") AND AUTH_ENABLED == 1 AND ( !preg_match( '/INSTALL\.php/', $_SERVER["SCRIPT_NAME"]) )){
        echo '
          <a href="index.php?logout=1">
            <h3 class="ui-nconf-header ui-widget-header ui-corner-all logout">
                <span>
                    Logout
                </span>
            </h3>
          </a>';
    }
    ?>

   </div> <!-- END OF DIV "accordion" -->
 </div>     <!-- END OF DIV "left_navigation" -->
</div>         <!-- END OF DIV "navigation" -->


<div id="mainspace" style="float: left; width: 22px; height: 100px"> </div>
