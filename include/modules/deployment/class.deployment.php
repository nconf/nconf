<?php
//!  NConf Deployment class
/*!
  This class provides the deployment possibiliies for NConf
  Its easy to configure multiple deployments
  This main class can be expanded with other sub classes to handle more deployment capabilities
*/
class NConf_Deployment{
    public $path;
    public $modules = array();
    public $config_path;


    function __construct(){
        $this->path = dirname(__FILE__);
        $this->config_path = NCONFDIR.'/config/deployment.ini';
        $this->load_modules();
    }

    //! Enables the module
    final private function load_modules(){
        $module_directories = getDirectoryTree($this->path);
        foreach ($module_directories AS $module_name) {
            $module_path = $this->path. '/' .$module_name. '/class.deployment_' .$module_name. '.php';
            if (file_exists($module_path) ){
                require_once($module_path);
                // Load module
                $this->modules[$module_name] = new $module_name;
                if (is_subclass_of($this->modules[$module_name], 'NConf_Deployment_Modules') ){
                    NConf_DEBUG::set("$module_name loaded", 'DEBUG', 'Module');
                    $this->modules[$module_name]->name = $module_name;
                }else{
                    NConf_DEBUG::set("$module_name FAILED", 'ERROR', 'Module');
                    echo "<br>Failed loading modul $module_name";
                }
            }
        }
    }

    public function run_deployment(){
        if(!ALLOW_DEPLOYMENT){
            echo NConf_HTML::limit_space(
                NConf_HTML::show_error('ERROR', 'Deployment functionality is currently disabled.')
            );
        }elseif (ALLOW_DEPLOYMENT && (!ALLOW_DIRECT_DEPLOYMENT && empty($_POST["status"]) ) ){
            echo NConf_HTML::limit_space(
                NConf_HTML::show_error('ERROR', 'Please first run the "Generate Nagios config".')
            );
        }elseif ( (ALLOW_DEPLOYMENT && ALLOW_DIRECT_DEPLOYMENT)
            ||    (ALLOW_DEPLOYMENT
                    && (!ALLOW_DIRECT_DEPLOYMENT && (!empty($_POST["status"]) && $_POST["status"] == "OK") )
                  )
        ) {

            echo NConf_HTML::table_begin('class="table_checks"', array(170, 50, '') );
            
            // DEPLOY
            // First do the local module
            $local_module = $this->modules["local"];
            if ( $local_module->configured() ){
                NConf_DEBUG::set('', 'DEBUG', 'Deploying '.$local_module->name);
                echo NConf_HTML::table_row_text(NConf_HTML::title($local_module->name, '', 'class="content_header"'));
                NConf_DEBUG::set($local_module->destinations, 'DEBUG', $local_module->name);
                $local_module->deploy();
                echo NConf_HTML::table_row_text(NConf_HTML::line(), '', 'colspan=3');
            }

            // Then do all other modules if they are configured
            foreach ($this->modules AS $module){
                // Dont do the "local" module
                // Check also if module is configured
                if ($module->name == "local" OR !$module->configured() ) continue;

                NConf_DEBUG::set('', 'DEBUG', 'Deploying '.$module->name);
                echo NConf_HTML::table_row_text(NConf_HTML::title($module->name, '', 'class="content_header"'));
                NConf_DEBUG::set($module->destinations, 'DEBUG', $module->name);

                // run the deploy
                $module->deploy();
                echo NConf_HTML::table_row_text(NConf_HTML::line(), '', 'colspan=3');
            }
            echo NConf_HTML::table_end();
        }else{
            echo NConf_HTML::text('Deployment is enabled, but your configuration seems to have errors.', TRUE, 'div', 'class="attention"');
        }

    }

    final public function import_config(){
        // parse config file to array
        if ( is_readable($this->config_path) ){
            $ini_array = parse_ini_file($this->config_path, TRUE);
            if ( !empty($ini_array) ){
                NConf_DEBUG::set($ini_array, "DEBUG", "Loaded ".$this->config_path);

                // handle each config group
                foreach ($ini_array AS $config_group_name => $config_group){
                    // get type and unset type in config array
                    $type = $config_group["type"];
                    unset($config_group["type"]);

                    // If titel is unset, set the groupname for it
                    if (empty($config_group["title"]) ) $config_group["title"] = $config_group_name;

                    // import config to its module
                    if ( class_exists($type, false) ) {
                        $this->modules[$type]->import($config_group);
                    }else{
                        NConf_DEBUG::set("Configuration error, this type of deployment is not available", 'ERROR', $type);
                    }
                }
            }else{
                NConf_DEBUG::set($this->config_path, 'ERROR', 'Configuration is empty, please check your configuration file and read the documentation: ');
            }
        }else{
            NConf_DEBUG::set($this->config_path, 'ERROR', 'Could not read configuration file: ');
        }

    }

    // update history log
    final public function history($message, $status){
        if($status === TRUE){
            history_add('module', 'deploy '.$this->name, $message.' (OK)'); 
        }else{
            history_add('module', 'deploy '.$this->name, $message.' (FAILED)');
        }
    }


    
}

?>
