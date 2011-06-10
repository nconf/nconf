<?php
# load basic functionality
require_once('main.php');

# Imports an AJAX or MODULE file
# We want to have all ajax files in a folder, but scripts need access to includes etc. so this is because this file must be in root directory of nconf

if ( !empty($_REQUEST["module_file"]) AND !empty($_REQUEST["ajax_file"]) ){
    # Not allowed to load both
    NConf_DEBUG::set('Its not allowed to load both module and ajax file', 'ERROR');

}elseif( empty($_REQUEST["module_file"]) AND empty($_REQUEST["ajax_file"]) ){
    # No file for module and no file for ajax to load -> not allowed
    NConf_DEBUG::set('No file to load', 'ERROR');

}else{
    if ( !empty($_REQUEST["module_file"]) AND empty($_REQUEST["ajax_file"]) ){
        # MODULE file load
        $type       = 'modules';
        $file_path  = $_REQUEST["module_file"];
        $debug      = ( !empty($_REQUEST["debug"]) AND DEBUG_MODE == 1 ) ?  $_REQUEST["debug"] : 'no';
        $ajax       = ( !empty($_REQUEST["ajax"]) )  ?  $_REQUEST["ajax"]  : 'no';
        if ($ajax == "no"){
            require_once 'include/head.php';
        }
        # no JSON support here
        $json       = 'no';

    }elseif( empty($_REQUEST["module_file"]) AND !empty($_REQUEST["ajax_file"]) ){
        # AJAX file load
        $type       = 'ajax';
        $file_path  = $_REQUEST["ajax_file"];
        $debug      = ( !empty($_REQUEST["debug"]) AND DEBUG_MODE == 1 ) ?  $_REQUEST["debug"] : 'no';
        $ajax       = 'yes';
        # if file location is JSON, do not print other code (jQuery helpers won't work )
        $json       = ( preg_match( '/^json\//', $_REQUEST["ajax_file"]) ) ? 'yes' : 'no';

        # if username is set, save it for history_add etc.
        # bacause we are called with ajax here, we do not know the $_SESSION vars from the application...
        if ( !empty($_REQUEST["username"]) ){
            $_SESSION["userinfos"]["username"] = $_REQUEST["username"];
        }else{
            if ( !empty($_POST["username"]) ) $_SESSION["userinfos"]["username"] = $_POST["username"];
        }
    }

    # file validation
    $path_parts = pathinfo($file_path);
    $filename = $path_parts['basename'];
    $dirname = $path_parts['dirname'];
    NConf_DEBUG::set($file_path, 'DEBUG', 'call '.$type.'-file');

    # Do not allow to go out of directory
    if ($dirname AND stristr($dirname, '..') ){
        NConf_DEBUG::set('Its not allowed to go out of the directory or having ".." in the file path ("'.$dirname.'")', 'ERROR');
        $path = realpath($file_path);
    }else{
        if (!$file_path){
            NConf_DEBUG::set('No File given', 'ERROR');
        }elseif ( file_exists('include/'.$type.'/'.$file_path) ){
            # file found, load it
            NConf_DEBUG::set('Loading '.$type.'-File "include/'.$type.'/'.$file_path.'"', 'DEBUG', 'call_file');
            require_once('include/'.$type.'/'.$file_path);
        }else{
            NConf_DEBUG::set($type.'-File "include/'.$type.'/'.$file_path.'" not found', 'ERROR');
        }
    }
}

# get also the debug informations
if ($debug == 'yes' AND $json == 'no'){
        $title = 'Load file "'.$file_path.'" @ '.date("H:i:s");

        // set ERROR
        if ( NConf_DEBUG::status('ERROR') ){
            echo '<div id="ajax_error">';
            echo NConf_DEBUG::show_debug('ERROR', TRUE);
            echo '</div>';
        }

        // set DEBUG
        if ( NConf_DEBUG::status('DEBUG') ){
            echo '<div id="ajax_debug">';
            echo NConf_HTML::swap_content( NConf_DEBUG::show_debug('DEBUG', TRUE), $title, FALSE, FALSE, 'color_list3' );
            echo '</div>';
        }


    if ( !empty($ajax) AND $ajax == 'yes'){

        ?>
        <!-- jQuery part -->
        <script type="text/javascript">
            $(document).ready(function(){
                // fetch ERROR part and give to footer and display it
                $("#jquery_error").append( $("#ajax_error") );
                $("#ajax_error").parent("#jquery_error").show("blind");

                // fetch DEBUG part and give to footer (will be displayed if debug is enabled)
                $("#ajax_debug").prependTo( $("#jquery_console") );
                $("#jquery_console_parent").fadeIn("slow");
            });
        </script>
        <?php
    }

}



if ( empty($type) AND empty($ajax) ){
    // if both not exist disply nconf structur with error message
    require_once 'include/head.php';
    require_once 'include/foot.php';

}elseif ( (!empty($type) AND $type == 'modules') AND (!empty($ajax) AND $ajax == 'no') ){
    // modules needs footer
    require_once 'include/foot.php';
}


?>



