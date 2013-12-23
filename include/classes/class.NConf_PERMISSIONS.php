<?php
//!  NConf DEBUG class
/*!
*/
class NConf_PERMISSIONS{
    protected $group;
    protected $current_script;
    protected $url_check_status = FALSE;
    protected $debug = '';
    
    protected $requested_id_authorized = 'NOT TESTED';
    public $message = '';

    function __construct(){
        if ( isset($_SESSION["group"]) ){
            $this->group = $_SESSION["group"];
            
            # admin group has no limitations
            if ($_SESSION["group"] == GROUP_ADMIN){
                $this->url_check_status = TRUE;
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
        
        # if user is not in admin group, check if requested item is accessible for him
        if ( $this->group != GROUP_ADMIN AND !empty($_REQUEST["id"]) ) {
            $this->checkIdPermission($_REQUEST["id"]);
            if ($this->requested_id_authorized === FALSE){
                $this->message = "You are not authorized to access this item";
            }
        }
        # also apply check for multiple ids
        if ( $this->group != GROUP_ADMIN AND !empty($_REQUEST["ids"]) ) {
            $array_ids = explode(",", $_REQUEST["ids"]);
            foreach ($array_ids as $item_ID){
                $this->checkIdPermission($item_ID);
            }
            if ($this->requested_id_authorized === FALSE){
                $this->message = "You are not authorized to access one ore multiple items of your selection";
            }
        }
        # Item ID authorization check
        NConf_DEBUG::set('Checks if you are allowed to access the item', 'DEBUG', NConf_HTML::status_text("Item authorization", $this->requested_id_authorized) );
    }

    protected function checkIdPermission($ID) {
        # checks the requested id, its class should be accessible for the user, otherwise access will be denied.
        $class_id = db_templates("get_classid_of_item", $ID);
        $query = 'SELECT id_class
                    FROM ConfigClasses
                    WHERE nav_privs = "'.$this->group.'"
                        AND id_class = "'.$class_id.'"';
        $user_class_permissions = db_handler($query, "getOne", "Check if user has access to the class of the requested item");
        # set authorization
        # special behaviour for multiple ids (then its not allowed to set to true if already FALSE state was set)
        if ( !empty($user_class_permissions) AND $this->requested_id_authorized !== FALSE ){
            $this->requested_id_authorized = TRUE;
        }else{
            $this->requested_id_authorized = FALSE;
        }
    }

    public function checkPageAccess(){
        # URL check
        $debug = NConf_HTML::swap_content($this->debug, "URL ACL feedback", FALSE, TRUE);
        NConf_DEBUG::set($debug, 'DEBUG', NConf_HTML::status_text("URL ACL status", $this->url_check_status) );
        
        if ($this->url_check_status === FALSE){
            $this->message = "You don't have permission to access this page!";
        }
        
        return $this->url_check_status;
    }
    
    public function checkIdAuthorization(){
        return $this->requested_id_authorized;
    }
        

    public function setURL($URL, $REGEX_OPEN_END = TRUE, $GROUPS = array(), $REQUEST = array() ){
        # do not check if already allowed
        if ($this->url_check_status == TRUE) return;

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
            $regex_open_end_message = $REGEX_OPEN_END ? "Yes" : "No";
            $this->debug .= NConf_HTML::status_text("<br>will use open end regex (REGEX_OPEN_END):", $regex_open_end_message, " @ ".$URL);
            if ($REGEX_OPEN_END){
                if ( !preg_match('/(^|\/)'.preg_quote($URL).'\w*/', $this->current_script) ){
                    return;
                }
            }else{
                if ( !preg_match('/(^|\/)'.preg_quote($URL).'$/', $this->current_script) ){
                    return;
                }
            }
            $this->debug .= NConf_HTML::text("URL matched: $URL", TRUE);
            $this->debug .= NConf_HTML::swap_content($REQUEST, "REQUEST debug", FALSE, TRUE);
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
            }else{
                $this->debug .= NConf_HTML::text("No REQUEST data", TRUE);
            }
            
            # all checks passed, URL is fine
            $this->url_check_status = TRUE;
            $this->debug .= NConf_HTML::text("status: $this->url_check_status", TRUE);
            return;
        }

    }

}
?>
