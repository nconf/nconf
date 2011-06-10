<?php
//!  NConf DEBUG class
/*!
*/
class NConf_PERMISSIONS{
    protected $group;
    protected $current_script;
    protected $page_check_status = FALSE;
    protected $debug = '';

    function __construct(){
        if ( isset($_SESSION["group"]) ){
            $this->group = $_SESSION["group"];
            
            # admin group has no limitations
            if ($_SESSION["group"] == GROUP_ADMIN){
                $this->page_check_status = TRUE;
            }
        }else{
            $this->group = GROUP_NOBODY;
        }

        # define current script name
        $this->current_script = preg_replace('/^\//', '', $_SERVER['SCRIPT_NAME']);
        $this->debug .= NConf_HTML::text("current script name: ".$this->current_script);
    }


    public function checkPageAccess(){
        $debug = NConf_HTML::swap_content($this->debug, "ACL feedback", FALSE, TRUE);
        NConf_DEBUG::set($debug, 'DEBUG', NConf_HTML::status_text("ACL status", $this->page_check_status) );
        return $this->page_check_status;
    }

    public function setURL($URL, $REGEX_OPEN_END = TRUE, $GROUPS = array(), $REQUEST = array() ){
        # do not check if already allowed
        if ($this->page_check_status == TRUE) return;

        # check URL for passed attributes (should only be the scriptname)
        # the navigation links will need this handler
        # search for query part in URL
        if ( strpos($URL, "?") !== FALSE ){
            $url_parsed = parse_url($URL);
            $this->debug .= NConf_HTML::swap_content($url_parsed, "ACL - Found query in URL : Parsing URL", FALSE, TRUE);

            # get request items
            parse_str($url_parsed["query"],$REQUEST);
            $this->debug .= NConf_HTML::swap_content($REQUEST, "ACL - fetched query and converted to REQUEST array", FALSE, TRUE);
            
            # override URL with correct scriptname
            $URL = $url_parsed["path"];
        }

        # check group permission
        if ( empty($GROUPS) OR in_array($this->group, $GROUPS) ){
            if ($REGEX_OPEN_END){
                if ( !preg_match('/^'.preg_quote($URL).'\w*/', $this->current_script) ){
                    return;
                }
            }else{
                if ( !preg_match('/^'.preg_quote($URL).'$/', $this->current_script) ){
                    return;
                }
            }
            $this->debug .=  NConf_HTML::text("URL matched: $URL", TRUE);

            # check for request limitations
            if ( !empty($REQUEST) ){
                # check if needed request items match
                $diff = array_diff($REQUEST, $_REQUEST);
                if ( !empty( $diff )  ){
                    # for debugging these could be grouped together
                    $this->debug .= NConf_HTML::swap_content($REQUEST, "REQUEST items do not match", FALSE, TRUE);
                    return;
                }else{
                    $this->debug .= NConf_HTML::swap_content($REQUEST, "REQUEST items matched", FALSE, TRUE);
                }
                //NConf_DEBUG::set($REQUEST, 'DEBUG', "REQUEST matched");
            }

            # all checks passed, URL is fine
            $this->page_check_status = TRUE;
            return;
        }

    }

}
?>
