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
		
		# Helpers for debbuging
		$this->debug .= NConf_HTML::swap_content(NCONFDIR, "NCONFDIR");
		$this->debug .= NConf_HTML::swap_content($_SERVER["DOCUMENT_ROOT"], "DOCUMENT_ROOT");

        # define current script name ( includes path from webroot )
        $script_name = $_SERVER['SCRIPT_NAME'];
		
		# handle directory if not running directly on document root path
		$nconf_webroot_explode = explode($_SERVER["DOCUMENT_ROOT"], NCONFDIR);
		$this->debug .= NConf_HTML::swap_content($nconf_webroot_explode, "nconf_webroot_explode");
		$this->debug .= NConf_HTML::swap_content($_SERVER['SCRIPT_NAME'], "SERVER script_name");
		// TODO: check with different setups if now everything works as expected
		if ( !empty($nconf_webroot_explode[1]) ){
			# NConf does not run in webroot
			$nconf_webroot_path = $nconf_webroot_explode[1];
			
			# remove path from script name variable
			$script_name_explode = explode($nconf_webroot_path, $script_name);
			$this->debug .= NConf_HTML::swap_content($script_name_explode, "script_name_explode");
			
			$script_name = $script_name_explode[1];
		}
		
		# remove beginning slash and set to current_script
		$this->current_script = preg_replace('/^\//', '', $script_name);
		# This will be the script name which the permission system will check against.
        $this->debug .= NConf_HTML::text("<b>Current script name: </b>".$this->current_script);
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
        # TODO: check if ampersand can be optimized on xmode views:
        # Array(
    	#	[class] => contact
    	#	[amp;xmode] => pikett
		# )
        # perhaps using $_SERVER['QUERY_STRING'] ?
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
