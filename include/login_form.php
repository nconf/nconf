<!-- jQuery part -->
<script type="text/javascript">

    $(document).ready(function(){
        $("#login_username").focus();
    });

</script>


<table>
    <tr>
        <td>
            <?php

                if ( !isset($_SESSION["group"]) ) {

                    if ( !empty($_GET["goto"]) ){
                        $url = $_GET["goto"];
                    }else{
                        $url = "index.php";
                    }

                    ?>
                    <table>
                        <tr>
                            <td>
                                <br>
                                    <?php
                                        echo VERSION_STRING;
                                        echo "<br><br>".COPYRIGHT_STRING;
                                    ?>
                            </td>
                        </tr>
                    </table>
                    <br><br>
                    <?php
                    if ( !defined("AUTH_METHOD") OR (AUTH_METHOD == "login") ){
                    ?>
                        <form action="<?php echo $url; ?>" method="POST">
                        <table frame=box rules=none cellspacing=2 cellpadding=2 style="border-width: 0px">
                            <?php
                            if ( constant("VERSION_NOT_FINAL_WARNING") !== ''){
                                echo "<tr>
                                        <td colspan=2>";
                                echo NConf_HTML::show_error('Attention', VERSION_NOT_FINAL_WARNING);
                                echo"   </td>
                                      </tr>";
                                echo "<tr><td>&nbsp;</td></tr>";
                            }
                            ?>
                            <tr>
                                <td width=75>
                                    &nbsp;<b>Login as:</b>
                                </td>
                                <td>
                                    <input style="width:200px" type="text" name="username" id="login_username" tabindex="1">
                                </td>
                            </tr>
                            <tr>
                                <td width=75>
                                    &nbsp;<b>Password:</b>
                                </td>
                                <td>
                                    <input style="width:200px" type="password" name="password" tabindex="2">
                                </td>
                            </tr>
                            <tr>
                                <td colspan=2><br>
                                    <input type="hidden" name="authenticate" value="yes">
                                    <input style="width:75px" type="submit" value="login" tabindex="3">
                                </td>
                            </tr>
                        </table>
                        </form>
                        
                    <?php
                    }elseif( !empty($auth_logout) ){
                        message($info, "Logout successfull");
                        ?>
                        <form action="<?php echo $url; ?>" method="POST">
                          Logout sucessfull<br><br>
                          <input style="width:75px" type="submit" value="login" tabindex="1">
                        </form>
                        <?php
                    }
                }

            ?>
        </td>
    </tr>
    <tr>
        <td>
            <table>
                <tr>
                    <td width="600" colspan=2>
                        <?php
                            if ( isset($_SESSION["group"]) ) { 
                                # This will only be displayed on the main "home" page (when authenticated)
                                echo VERSION_STRING."<br><br>";
                                echo COPYRIGHT_STRING."<br>";

                                if ( constant("VERSION_NOT_FINAL_WARNING") !== ''){
                                    echo "<br>";
                                    echo NConf_HTML::limit_space(
                                        NConf_HTML::show_error('Attention', VERSION_NOT_FINAL_WARNING)
                                    );
                                }

                                # new nconf version info
                                if ( defined('CHECK_UPDATE') AND CHECK_UPDATE == 1 AND $_SESSION["group"] == "admin" ){
                                    echo '<script src="include/js/jquery_plugins/jquery.nconf_check_update.js" type="text/javascript"></script>';
                                    echo js_prepare('
                                              $(document).ready(function(){
                                                $.nconf_check_update("'.VERSION_NUMBER.'");
                                              });
                                         ');
                                    # html container
                                    echo "<br>";
                                    echo '<div id="check_update" style="display:none">';
                                        echo NConf_HTML::limit_space(
                                            NConf_HTML::show_highlight('<span id="check_update_title"></span>', '<div id="check_update_content"></div>')
                                        );
                                    echo '</div>';

                                }


                                echo "<br>".DISCLAIMER_STRING."<br><br>";
                                echo POWERED_BY_LOGOS;
                            }else{
                                # This will be displayed on the login screen
                                echo "<br><br>".POWERED_BY_LOGOS;
                            }
                        ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
