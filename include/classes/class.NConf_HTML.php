<?php
//!  NConf HTML class
/*!
*/
class NConf_HTML{

    private static $counter = 0;
    private static $cache   = array();
    private static $swap_name;

    private static function counter(){
        self::$counter++;
        return self::$counter;
    }

    public static function set_swap_id($name = ''){
        if ( !empty($name) ){
            self::$swap_name = $name;
        }elseif ( empty($name) AND empty(self::$swap_name) ){
            self::$swap_name = 'swap_fragment';
        }
        // create new id
        $counter = self::counter();
        $name = self::$swap_name;
        return $name."_".$counter;
    }
    public static function get_swap_id($name = ''){
        // read last id
        $counter = self::$counter;
        if ( empty($name) ) $name = self::$swap_name;
        return $name."_".$counter;
    }

    public static function printArray($array){
        // PHP: Turn on output buffering
        ob_start();
            echo "<pre>";
            print_r($array);
            echo "</pre>";
            // PHP: Return the contents of the output buffer
        $buffer = ob_get_contents();
        // Clean (erase) the output buffer and turn off output buffering
        ob_end_clean();

        return $buffer;
    }

    public static function line(){
        return "<hr>";
    }

    public static function title($title, $h = "2", $options = ''){
        if (empty($h)) $h = 2;
        return '<h'.$h.' '.$options.'>'. $title .'</h'.$h.'>';
    }

    public static function text($TEXT, $LINEBREAK = TRUE, $tag = '', $tag_options = ''){
        $output = '';
        if ($LINEBREAK) $output .= "<br>";
        if ( !empty($tag) ){
            $output .= '<'.$tag.' '.$tag_options.'>'. $TEXT.'</'.$tag.'>';
        }else{
            $output .= $TEXT;
        }

        return $output;
    }

    public static function text_converter( $MODE, $TEXT ){
        switch ($MODE){
            case "sql_words":
                // detects sql statements and gives a nicer output
                #$converted_text = preg_replace('/(SELECT|FROM|WHERE|AND|OR|ORDER BY|INSERT INTO)/', '<BR><font color="red">${1}</font>', $text[1]);
            break;
            case "sql_uppercase":
                // matches any UPPERCASE words
                // (sql commands must be UPPERCASE in queries for this)
                $converted_text = preg_replace('/\b([A-Z]{2,})\b/', '<BR><font color="red">${1}</font>', $TEXT);
                $converted_text = preg_replace('/</', '&lt;', $TEXT);
                $converted_text = preg_replace('/>/', '&gt;', $TEXT);
            break;
        }
        return $converted_text;
    }

    public static function swap_content ($CONTENT, $TITLE = '', $OPEN = FALSE, $LINEBREAK = TRUE, $CLASS = ''){
        # set swap name for different scripts at same time (needed when calling per ajax)
        $swap_id = self::set_swap_id(basename($_SERVER["SCRIPT_NAME"].'_'.time().'_'.rand(0, 1000)));
        $output = '';
        if ($LINEBREAK) $output .= "<br>";
        $output .= '
            <a href="javascript:swap_visible(\''.$swap_id.'\')">
            <img id="swap_icon_'.$swap_id.'" alt="expand" src="';
            if (!$OPEN){
                $output .= 'img/icon_expand.gif';
            }else{
                $output .= 'img/icon_collapse.gif';
            }
            $output .= '">&nbsp;';

            if ( is_array($CONTENT) ){
                    $output .= '<b>Array</b> '.$TITLE;
            }else{
                    $output .= $TITLE;
            }
        $output .= '</a>';

        $output .= '<div ';
        if (!empty($CLASS)) $output .= 'class="'.$CLASS.'" ';
        $output .= 'id="'.$swap_id.'" style="display: ';
        if (!$OPEN) $output .= "none";
        $output .= '">';
        if ( is_array($CONTENT) ){
            $output .= self::printArray($CONTENT);
            $output .= '</div>';
        }else{
            $output .= $CONTENT;
            $output .= '</div>';
        }
        
        return $output;

    }

    /* INFO */

    public static function set_info($value = '', $mode = 'overwrite'){
        
        // handle cache mode
        switch ($mode){
            case "add":
                array_push(self::$cache, $value);
            break;
            default:
            case "overwrite":
                self::$cache = array();
                array_push(self::$cache, $value);
            break;
            case "reset":
                self::$cache = array();
            break;
        }

    }


    public static function status_text($title = '', $status = '', $message = ''){
        $output = '';

        if ($title){
            $output .= '<b>'.$title.'</b>';
        }
        if ( isset($status) ){
            $output .= " ".self::status($status, TRUE);
        }
        if ($message){
            $output .= " ".$message;
        }
        return $output;
    }

    public static function status($status, $bracket = FALSE){
        $output = '<b>';
        if ($bracket){
            $output .= "[";
        }


        if ($status === FALSE) $status = "FALSE";
        if ( !empty($status) ){

        if ($status === TRUE)  $status = "TRUE";
            
            switch ($status){
                case "OK":
                case "TRUE":
                    $output .= '<span class="status_ok">OK</span>';
                break;
                
                case "Yes":
                    $output .= '<span class="status_ok">'.$status.'</span>';
                break;

                case "FAILED":
                case "ERROR":
                case "FALSE":
                    $output .= '<span class="status_failed">FAILED</span>';
                break;
                
                case "No":
                    $output .= '<span class="status_failed">'.$status.'</span>';
                break;

                default:
                case "UNKNOWN":
                    $output .= '<span class="status_unknown">UNKNOWN</span>';
                    $output .= '(<i>'.$status.'</i>)';
                break;
            }
        }

        if ($bracket){
            $output .= "]";
        }
        $output .= '</b>';

        return $output;
    }


    public static function get_info($array = '', $start = TRUE){
        if ( $start ) $array = self::$cache;
        $output = '';
        if ( !empty($array) ){
            foreach ($array AS $child_entry){
                if ( is_array($child_entry) AND !empty($child_entry)  ){
                        $output .= self::get_info($child_entry, FALSE);
                }else{
                    if ( !empty($child_entry) ){
                        $output .= $child_entry;
                    }
                }
            }
        }
        return $output;
    }


    /* TABLE */

    public static function table_begin($table_options = '', $colgroup = ''){
        $output = '<table ';
        if ( !empty($table_options) ){
            $output .= $table_options;
        }
        $output .= '>';
        
        if (!empty($colgroup) ){
            $output .= "<colgroup>";
            foreach ($colgroup AS $col){
                $output .= '<col width="'.$col.'">';
            }
            $output .= "</colgroup>";
        }

        return $output;
    }

    public static function table_end(){
        return "</table>";
    }

    public static function table_row_text($content, $tr_options = '', $td_options = '', $SWAP = FALSE, $CACHE = FALSE){
        $row = '<tr';
        if ($SWAP){
            $swap_id = self::get_swap_id();
            $row .= ' id="'.$swap_id.'"';
            $row .= ' style="display: none"';
        }
        if ( !empty($tr_options) ) $row .= ' '.$tr_options;
        $row .= '>';

            $row .= '<td';
            if ( !empty($td_options) ) $row .= ' '.$td_options;
            $row .= '>'.$content.'</td>';

        $row .= '</tr>';

        if ($CACHE){
            self::cache($row, $CACHE);
        }else{
            return $row;
        }
    }

    
    public static function table_row_check($title, $status, $details = ''){
        if ( is_array($details) ) $details = implode("<br>", $details);

        // generate table row
        $row = '<tr>
                    <td>
                        <b>'.$title.'</b>
                    </td>
               ';
        $row .=    '<td>';
        $row .= self::status($status);
        $row .=    '</td>';
        $row .=    '<td><pre>'.$details.'</pre></td>';
        $row .= '</tr>';
       
        return $row; 
    }

    public static function swap_opener($swap_id, $link_text = '', $swap_icon = FALSE){
        if ( !empty($link_text) ){
            $output = '<a href="javascript:swap_visible(\''.$swap_id.'\')">';
            if ($swap_icon){
                $output .= '<img src="img/icon_expand.gif" id="swap_icon_'.$swap_id.'" alt="expand"> ';
            }
            
            $output .= $link_text;
            $output .= '</a>';
        }else{
            $output = 'href="javascript:swap_visible(\''.$swap_id.'\')"';
        }

        return $output;

    }


    public static function limit_space($content, $width = ''){
        // makes tables around some content, to limit the size to its content
        // (otherwise divs inherit the size of the parent
        $box_width = '';
        if ( !empty($width) ) $box_width = $width;
        $output =
            '<table '.$box_width.'>
                <tr>
                    <td>'.$content.'
                    </td>
                </tr>
             </table>';

        return $output;
    }

    public static function back_button($BUTTON_LINK, $BUTTON_TEXT = 'Back'){
        $output = '';
        $output .= '<div id=buttons>';
            $output .= '<input type=button onClick="window.location.href=\''.$BUTTON_LINK.'\'" value="'.$BUTTON_TEXT.'">';
        $output .= '</div>';

        return $output;
    }

    /* NConf UI ( jQuery ) stuff */

    public static function show_highlight($title, $content){
        $output = '';
        $output .= '<div class="ui-state-highlight ui-corner-all fg-error">';
            $output .= '<span style="float: left; margin-right: 0.3em;" class="ui-icon ui-icon-info">&nbsp;</span>';
            $output .= NConf_HTML::title($title, 2);
            $output .= $content;
        $output .= '</div>';

        return $output;
    }

    public static function show_error($title = "Error:", $content = ''){
        $output = '';
        $output .= '<div class="ui-state-error ui-corner-all fg-error">';
            $output .= '<span style="float: left; margin-right: 0.3em;" class="ui-icon ui-icon-alert">&nbsp;</span>';
            $output .= NConf_HTML::title($title, 2);
            if ( !empty($content) ){
                $output .= $content;
            }else{
                $output .= NConf_DEBUG::show_debug('ERROR', TRUE);
            }
        $output .= '</div>';

        return $output;
    }

    // End the page with error message
    public static function exit_error(){
        if ( self::status('ERROR') ) {
            echo self::limit_space( self::show_error() );
            if ( !empty($_SESSION["after_delete_page"]) ){
                echo "<br><br>";
                echo self::back_button($_SESSION["after_delete_page"]);
            }
        }

        include('include/foot.php');

        if ( isset($dbh) ){
            mysql_close($dbh);
        }

        exit;

    }




    // UI Box Header
    public static function ui_box_header($content = '', $id = ''){
        if (!empty($id)){
            $id = 'id="'.$id.'" ';
        }
        $output = '<div '.$id.'class="ui-nconf-header ui-widget-header ui-corner-tl ui-corner-tr ui-helper-clearfix">';
        if ( !empty($content) ){
            $output .= $content;
        }
        $output .= '</div>';

        return $output;
    }

    // UI Box Content
    public static function ui_box_content($content = '', $id = ''){
        if (!empty($id)){
            $id = 'id="'.$id.'" ';
        }
        $output = '<div '.$id.'class="ui-nconf-content ui-widget-content ui-corner-bottom">';
        if ( !empty($content) ){
            $output .= $content;
        }
        $output .= '</div>';

        return $output;
    }


    // UI Table Header
    public static function ui_table($content, $additional_classes = ''){
        $output = '<table class="ui-nconf-table ui-widget ui-widget-content '.$additional_classes.'">';
            $output .= $content;
        $output .= '</table>';

        return $output;
    }

    // page title including icon of the class and optional actions
    public static function page_title($class, $title = '', $toolbar = array()){
        $output = '<div class="title">';
        // Get Icon
        $icon = get_image(array( "type" => "design",
                                 "name" => $class,
                                 "size" => 24,
                                 "class" => "float_left"
                                 ) );
        // get friendly name of class, if it exists
        $class_friendly_name   = db_templates('class_friendly_name', $class);
        if ($class_friendly_name){
            /* TODO: perhaps move the item title also into the main header
            if ($title) {
                $title = $class_friendly_name. TITLE_SEPARATOR . $title;
            }else {
                $title = $class_friendly_name;
            }*/
            // use friendly name only when title is not set
            if (empty($title)){
                $title = $class_friendly_name;
            }
        }
        $output .= $icon.'<h1 class="content_header">'.$title.'</h1>';
        
        /* TODO: this toolbar will perhaps not be used */
        if ( !empty($toolbar) ){
            $output .=  '<div id="ui-nconf-icon-bar">';
            $add_item = get_image( array(  "type" => "design",
                                           "name" => "add",
                                           "size" => 16,
                                           "tooltip" => 'Add '.$nav_class["friendly_name"],
                                           "class" => "lighten"
                                        ) );
            $output .= '<a href="handle_item.php?item='.$class.'">'.$add_item.'</a>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        return $output;
    }

}
?>
