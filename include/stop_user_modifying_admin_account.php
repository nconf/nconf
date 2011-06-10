<?php
        message($info, TXT_SUBMIT_DISABLED4USER, "red");
        message($info, '<a href="'.$_SESSION["go_back_page"].'">Go back to previous page</a>');
        echo NConf_DEBUG::show_debug('INFO', TRUE);

        // Finish page
        mysql_close($dbh);
        require_once 'include/foot.php';
        exit;
?>
