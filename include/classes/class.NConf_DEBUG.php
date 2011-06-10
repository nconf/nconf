<?php
//!  NConf DEBUG class
/*!
*/
class NConf_DEBUG{

    public static $debug = array();
    protected static $INFO    = FALSE;
    protected static $DEBUG    = FALSE;
    protected static $ERROR    = FALSE;
    protected static $CRITICAL = FALSE;
    protected static $GROUP_LEVEL = 1;

    /*
    protected function counter(){
        return self::$counter++;
    }
    */
    public static function display(){
        // Show debug 
        echo NConf_HTML::swap_content(self::show_debug("DEBUG"), "<b>Debugging</b>", TRUE, TRUE);
        
        echo NConf_HTML::line();
        echo NConf_HTML::title("Predefined variables:", 3);
        echo NConf_HTML::swap_content($_COOKIE, "COOKIE", FALSE, FALSE);
        echo NConf_HTML::swap_content($_GET, "GET");
        echo NConf_HTML::swap_content($_POST, "POST");

        // SESSION output
        $session_footer_output = $_SESSION;
        // remove serverlist (is obsolet for debuging)
        if ( isset($session_footer_output["cmdb_serverlist"]) ) unset($session_footer_output["cmdb_serverlist"]);
        // display session info
        echo NConf_HTML::swap_content($session_footer_output, "SESSION");
        
    }

    public static function show_debug($LEVEL = '', $IN_CONTENT = FALSE, $BACK_BUTTON = FALSE){
        if (self::status($LEVEL) ){
            $output = '';
            $after_titel = TRUE;
            $group_open = FALSE;
            $group_level = 0;
            foreach (self::$debug AS $entry){
                if ( empty($LEVEL) || $LEVEL == $entry["LEVEL"] ){
                    //if ( !empty($entry["GROUP"]) ){
                    if ( is_int($entry["GROUP"]) AND $entry["GROUP"] > 0 ){
                        # Handle groups
                        if ($entry["GROUP"] == $group_level AND $group_open){
                            $output .= '</fieldset><fieldset';
                        }elseif ($entry["GROUP"] == $group_level AND !$group_open){
                            $output .= '<fieldset';
                        }elseif( $entry["GROUP"] > $group_level ){
                            $output .= '<fieldset';
                            $group_level++;
                            $group_open = TRUE;
                        }elseif( $entry["GROUP"] < $group_level ){
                            for($level = $group_level; $level >= $entry["GROUP"]; $level--){
                                $output .= '</fieldset>';
                            }
                            $output .= '<fieldset';
                            $group_level = $entry["GROUP"];
                        }
                        # define the background
                        if((1 & $entry["GROUP"]) == 1){
                            $bgcolor = "even";
                        }else{
                            $bgcolor = "odd";
                        }
                        $output .= ' class="'.$bgcolor.'">';


                        // put title in FIELDSET - LEGEND
                        if (!empty($entry["TITLE"]) ) $output .= '<legend><b>'.$entry["TITLE"].'</b></legend>';

                    }elseif ( $entry["GROUP"] === FALSE ){
                        for($level = $group_level; $level > 0; $level--){
                                $output .= '</fieldset>';
                        }
                        $group_open = FALSE;
                    }elseif ( is_int($entry["GROUP"]) AND $entry["GROUP"] < 0){
                        for($level = (int)(-$group_level); $level <= $entry["GROUP"]; $level++){
                            $output .= '</fieldset>';
                        }
                        $group_open = FALSE;
                    }elseif ( is_array($entry["MESSAGE"]) ){
                        if ($after_titel){
                            $output .= NConf_HTML::swap_content($entry["MESSAGE"], $entry["TITLE"]);
                        }else{
                            $output .= NConf_HTML::swap_content($entry["MESSAGE"], $entry["TITLE"], FALSE, FALSE);
                        }
                    }else{
                        if ($IN_CONTENT){
                            if ( !empty($entry["TITLE"]) ){
                                if ( !empty($entry["MESSAGE"]) ){
                                    if ($after_titel){
                                        $output .= NConf_HTML::text('<b>'.$entry["TITLE"].'</b>: '.$entry["MESSAGE"], FALSE);
                                    }else{
                                        $output .= NConf_HTML::text('<b>'.$entry["TITLE"].'</b>: '.$entry["MESSAGE"]);
                                    }
                                    // info about new line for next message
                                    $after_titel = FALSE;
                                }else{
                                    $output .= NConf_HTML::title($entry["TITLE"]);
                                    // info about new line for next message
                                    $after_titel = TRUE;
                                }
                            }else{
                                if ($after_titel){
                                    $output .= NConf_HTML::text($entry["MESSAGE"], FALSE);
                                    $after_titel = FALSE;
                                }else{
                                    $output .= NConf_HTML::text($entry["MESSAGE"]);
                                }
                            }
                        }else{
                            if ( !empty($entry["TITLE"]) ){
                                $output .= NConf_HTML::text('<b>'.$entry["TITLE"].'</b>: '.$entry["MESSAGE"]);
                            }else{
                                // mostly for fieldset not making a first line
                                //if ($after_titel){
                                //    $output .= NConf_HTML::text($entry["MESSAGE"], FALSE);
                                //    $after_titel = FALSE;
                                //}else{
                                    $output .= NConf_HTML::text($entry["MESSAGE"]);
                                //}
                            }
                        }
                    }
                }
            }

            # print back button if wanted
            if ( $BACK_BUTTON ){
                $output .= '<br><br>';
                $output .= '<div id=buttons>';
                    $output .= '<input type=button onClick="window.location.href=\''.$BACK_BUTTON.'\'" value="Back">';
                $output .= '</div>';
            }

            return $output;

        }else{
            return '';
        }

    }

    //public static function close_group($LEVEL = "DEBUG"){
    public static function close_group($GROUPLEVEL = FALSE, $LEVEL = "DEBUG"){
        if ( is_int($GROUPLEVEL) AND $GROUPLEVEL > 0 ){
            $GROUPLEVEL = (int)(-$GROUPLEVEL);
        }
        self::set('', $LEVEL, '', $GROUPLEVEL);
    }

    public static function open_group($TITLE, $GROUPLEVEL = 'current', $LEVEL = "DEBUG"){
        if ($GROUPLEVEL == 'current'){
            $GROUPLEVEL = self::$GROUP_LEVEL;
        }elseif ($GROUPLEVEL == 'next'){
            $GROUPLEVEL = (self::$GROUP_LEVEL + 1);
            self::$GROUP_LEVEL = $GROUPLEVEL;
        }elseif( is_int($GROUPLEVEL) ){
            self::$GROUP_LEVEL = $GROUPLEVEL;
        }
        self::set('', $LEVEL, $TITLE, $GROUPLEVEL);
    }

    public static function set($MESSAGE, $LEVEL = "DEBUG", $TITLE = '', $GROUP = ''){
        if (empty($LEVEL)) $LEVEL = 'DEBUG';
        $LEVEL = strToUpper($LEVEL);
        switch ($LEVEL){
            case "INFO":
            case "ERROR":
            case "CRITICAL":
            case "DEBUG":
                # register level, for fast check if a level has content
                self::$$LEVEL = TRUE;
            default:
                array_push(self::$debug, array("LEVEL"      => $LEVEL
                                              ,"MESSAGE"    => $MESSAGE
                                              ,"TITLE"      => $TITLE
                                              ,"GROUP"      => $GROUP
                                         )
                );
                break;
        }
    }
    
    # return status of debug level
    # this is used to check, if there are some entries of LEVEL error or critical
    public static function status($LEVEL){
        return self::$$LEVEL;
    }
}
?>
