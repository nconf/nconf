<?php
//!  NConf Deployment Modules class
/*!
  This class provides the basics for the deployment modules
  This main class can be expanded with other sub classes to handle different deployment capabilities
*/
abstract class NConf_Deployment_Modules
{
    public $name;
    public $destinations = array();

    abstract public function command($host_info);

    //! This method loops the configured hosts and calls the command() method
    /*!
        This method can be overwritten on a subclass, to handle the destination hosts and the command different.
        Otherwise this method will be inherited
    */
    public function deploy(){
        foreach ($this->destinations AS $destination){
            // set new swap_id
            $swap_id = NConf_HTML::set_swap_id('swap_deploy');
            // reset informations
            NConf_HTML::set_info('', 'reset');

            // add informations if status is FALSE
            // if there are host informations, add them
            if (!empty($destination["host"]) ) NConf_HTML::set_info(NConf_HTML::table_row_check('Host:', '', $destination["host"]) , 'add');

            // call the command and get its status back
            // also catch direct output (of errors etc.) which are not intercepted in the submodule
            ob_start();
                $status = $this->command($destination);
                if (ob_get_contents()){
                    NConf_DEBUG::set(ob_get_contents(), 'ERROR', $this->name);
                    NConf_HTML::set_info(
                        NConf_HTML::table_row_check('Error/Info:', 'ERROR', 'Please have a look at the NConf error message at the bottom of the page')
                        , 'add'
                    );
                }
            ob_end_clean();

            // Table output
            echo NConf_HTML::table_row_check(
                NConf_HTML::swap_opener($swap_id, $destination["title"], TRUE)
                , $status
            );

            $content = NConf_HTML::table_begin('class="table_content ui-widget-content"', array(102, 50, '') );
            $content .= NConf_HTML::get_info();
            $content .= NConf_HTML::table_end();

            // create row with detailed feedback
            echo NConf_HTML::table_row_text($content, '', 'colspan=3', TRUE);


            

            // add status to history log
            NConf_Deployment::history($destination["title"], $status);
        }
    }

    //! Run a system call
    protected function system_call($command_list, $success_output = FALSE){
        $output = array();

        # if command is an array of commands
        if ( is_array($command_list) ){
            $command = '';
            $count = 0;
            foreach ($command_list as $command_part){
                if ( $count > 0 ) $command .= " && ";
                $command .= escapeshellcmd($command_part);
                $count++;
            }
            NConf_DEBUG::set($command, 'DEBUG', "finished command");
        }else{
            // escape for security reason
            $command = escapeshellcmd($command_list);
        }

        // execute
        $status = exec($command, $output, $retval);

        if($retval == 0){
            // success
            NConf_HTML::set_info( NConf_HTML::table_row_check('system call', 'OK', $command) , 'add' );
            NConf_HTML::set_info( NConf_HTML::table_row_check('', '', $output )
                , 'add');
            if ($success_output){
                return $output;
            }else{
                return TRUE;
            }
        }else{
            // failed
            // add some informations
            NConf_HTML::set_info( NConf_HTML::table_row_check('system call', 'FAILED', $command)
                , 'add' );
            // no other way worked to get the error message:
            if ( empty($output) ){
                $out = shell_exec("$command 2> ".NCONFDIR."/temp/output");
                // file to array
                $output = $out ? $out : file(NCONFDIR."/temp/output");
            }
            NConf_HTML::set_info( NConf_HTML::table_row_check('', '', $output )
                , 'add');
            
            NConf_DEBUG::set($output, 'DEBUG', $command);
            return FALSE;
        }
    }

    //! add a host (array)
    final public function add_host($array){
        array_push($this->destinations, $array);
    }

    //! checks if this module is configured
    final public function configured(){
        if ( !empty($this->destinations) ){
            return TRUE;
        }else{
            NConf_DEBUG::set('has no configuration, skipping', 'DEBUG', 'Module '.$this->name);
            return FALSE;
        }
    }


    public function import($config){
        // Check for multiple hosts
        if ( isset($config["host"]) && is_array($config["host"]) ){
            // temporary config copy
            $config_host = $config;
            // unset host on temporary config_host array
            unset($config_host["host"]);

            // go for each host
            foreach ($config["host"] AS $host){
                // set host to temp config array
                $config_host["host"] = $host;
                // add host and its configuration to the module
                $this->add_host($config_host);
            }
        }else{
            // no host, or just one defined
            // add it to the module
            $this->add_host($config);
        }
    }


}
?>
