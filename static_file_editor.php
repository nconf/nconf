<?php
require_once 'include/head.php';
?>

<!-- jQuery part -->
<script type="text/javascript">

    $(document).ready(function(){

        // OPEN BUTTON
        $('#open').button({
            icons: {
                primary: "ui-icon ui-icon-folder-open"
            },
            text: false,
        }).click(function() {
            $('#action').attr('value', 'open');
            $('form').submit();
        });

        // SAVE BUTTON
        $('#save').button({
            icons: {
                primary: "ui-icon ui-icon-disk"
            },
            text: false,
        }).click(function() {
            $('#action').attr('value', 'Save');
            $('form').submit();
        });

    });
</script>

<?php


// Form action and url handling
$request_url = set_page();

# array should be set in config/nconf.php
if (!isset($STATIC_CONFIG)){
    $STATIC_CONFIG = array();
    message($error, "The STATIC_CONFIG array must be set in your configuration.");
}else{
    # Directory
    if ( !empty($_POST["directory"]) ) {
      $directory = $_POST["directory"];
      // Check if the directory is really in the STATIC_CONFIG array
      // this prevents hacking some other directories
      if (!in_array($directory, $STATIC_CONFIG)){
        NConf_DEBUG::set($directory, "CRITICAL", 'Directory is not in STATIC_CONFIG variable.');
      }
        
    }else{
        $directory = $STATIC_CONFIG[0];
    }
}

# Filename
if ( isset($_POST["filename"]) ){
    if (!empty($_POST["filename"])) {
        $filename = $_POST["filename"];
        // Verify that only the filename is taken, no going out of directory should be possible
        $filename = basename($filename);
        $full_path = $directory.'/'.$filename;
        // Verify file exists and it is not set to "." or ".."
        if (!file_exists($full_path) OR (( $filename == '.' ) OR ( $filename == '..' )) ) {
            NConf_DEBUG::set($filename, "CRITICAL", 'File does not exist');
        }
    }
}
# new fileContent
if ( isset($_POST["content"]) ) {
    $content = $_POST["content"];
}

# set basic action
if ( isset($_POST["action"]) ) {
    $action = $_POST["action"];
}else{
    $action = "Open";
}


# Check for critical error, continue or abort
if ( NConf_DEBUG::status('CRITICAL') ){
    $msg_critical = NConf_DEBUG::show_debug('CRITICAL');
    echo NConf_HTML::show_error('Error', $msg_critical);
    require_once(NCONFDIR.'/include/foot.php');
    exit;
}



###
# Save file
###
if ( ($action == "Save") AND (isset($content) AND isset($full_path) ) ){
    # try to open config file writable
    $fh = @fopen($full_path, "w");
    if ($fh === FALSE){
        message($error, "The config file ($full_path) could not be saved.");
        $saved = FALSE;
    }else{
        #write to file
        $content = str_replace("\r\n", "\n", $content); #remove carriage returns
        if ( fwrite($fh, $content) == FALSE){
            # could not write to file
            message($info, "The config directory and all its content must be writable for your webserver user.", "overwrite");
            message($error, "Could not write config file ($full_path). Make sure the directory and all its content is writable for your webserver user.");
            $saved = FALSE;
        }else{
            # write file success
            message($info, "Changes saved successfully.", "overwrite");
            $saved = TRUE;
        }
        fclose($fh);

    }
}

###
# Open file
###



if( isset($full_path) AND !empty($filename) ){
    # read the config file
    if (isset($saved) AND $saved == FALSE){
        $file_content = $content;
    }else{
        $file_content = @file_get_contents($full_path);
        if ($file_content === FALSE){
            message($error, "The config file ($full_path) could not be read.");
        }
    }
}




###
# Info/Warning in the top right corner
###
# Title
echo NConf_HTML::page_title('editor-static-files', 'Edit static config files');

echo '<div class="editor_info">';
        if ( NConf_DEBUG::status('ERROR') ){
            $title = 'WARNING';
            $content = NConf_DEBUG::show_debug('ERROR', TRUE);
            $content .= '<br>The webserver user must have write permissions for your config directory, <br>otherwise NConf cannot save your changes.';
            echo NConf_HTML::show_error($title, $content);
        }elseif( NConf_DEBUG::status('INFO') AND !empty($saved) ){
            $title = 'Successfully saved file';
            $content = ICON_TRUE.NConf_DEBUG::show_debug('INFO', TRUE);
            echo NConf_HTML::show_highlight($title, $content);
        }else{
            $title = 'Info';
            $content = 'This mask allows administrators to modify static Nagios configuration files.';
            echo NConf_HTML::show_highlight($title, $content);
        }

echo '</div>';


echo '<form name="editor" action="'.$request_url.'" method="post">

<fieldset class="inline">
<legend>choose a file</legend>
<table>
    <colgroup>
        <col width="70">
        <col>
    </colgroup>
    ';
###
# List directories and files for editing
###
    echo '<tr>';
        echo '<td><b>directory</b></td>';
    echo '
            <td>
                <select name="directory" style="width:192px" onchange="document.editor.filename.value=\'\'; document.editor.submit()">';
        foreach($STATIC_CONFIG as $config_dir){
            echo "<option value=$config_dir";
            if ( (!empty($directory) ) AND ($directory == $config_dir) ) echo " SELECTED";
            echo ">$config_dir</option>";
        }

        echo '  </select>&nbsp;&nbsp;
            </td>';
    echo '</tr>';
    if ( !empty($directory) ){
        echo '<tr>';
            echo '<td><b>file</b></td>';

            $config_files = getFiles($directory);

            echo '<td><select name="filename" style="width:192px" onchange="document.editor.submit()">';
            echo '<option value="">choose a file...</option>';

            foreach($config_files as $config_filename){
                echo "<option value=$config_filename";
                if ( (isset($filename) ) AND ($filename == $config_filename) ) echo " SELECTED";
                echo ">$config_filename</option>";
            }

            echo '</select>&nbsp;&nbsp;</td>';
        echo '</tr>';
    }

echo '</table>
</fieldset>';



###
# Display editor
###
if ( (!empty($directory) AND !empty($filename) ) AND ($file_content !== FALSE) ){

    echo '<br><br>';
    echo '<div class="fg-toolbar ui-toolbar ui-widget-header ui-corner-tl ui-corner-tr ui-helper-clearfix">';
        /* buttons */
        echo '<input type="hidden" id="action" name="action" value="">';
        echo '<button id="open" class="jQ_tooltip" title="open file"></button>';
        echo '<button id="save" class="jQ_tooltip" title="save file"></button>';
    echo '</div>';
    # Edit field
    echo '<div class="ui-widget-content ui-toolbar ui-corner-bottom">';
        echo '<textarea class="editor_field" name="content" rows="35" wrap="soft">';
            echo $file_content;
        echo '</textarea>';
    echo '</div>';

}



echo '</form>';


mysql_close($dbh);
require_once 'include/foot.php';

?>
