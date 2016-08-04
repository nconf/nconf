<?php
class rsync extends NConf_Deployment_Modules
{
    protected $rsync = 'rsync';
    protected $scp   = 'scp';
    protected $ssh   = 'ssh';
    protected $path;

    function __construct(){
        $this->path = dirname(__FILE__);
    }


    public function command($host_infos){
        //create command....
        $command = '--stats';

        // additional options
        // options must be complete like " -caz -e 'ssh -i /path/to/id_dsa -o StrictHostKeyChecking=no -o ConnectTimeout=15'"
        if (!empty($host_infos["rsync_options"]) ) $command .= ' '.$host_infos["rsync_options"];

        // source file
        if (!empty($host_infos["source_file"]) ) $command .= ' '.$host_infos["source_file"];

        // target
        if (!empty($host_infos["user"]) ) $command .= ' '.$host_infos["user"].'@';
        if (!empty($host_infos["host"]) ) $command .= $host_infos["host"].':';
        if (!empty($host_infos["target_file"]) ) $command .= $host_infos["target_file"];

        // execute rsync
        $status = $this->system_call($this->rsync.' '.$command, TRUE);
        
        // check if status is ok, look for transferred files (to run ext command)
        $run_command = FALSE;
        if ( is_array($status) ){
            foreach ($status AS $output_line){
                if( preg_match("/Number of (regular )?files transferred: /", $output_line) ){
                    $transferred = explode(":", $output_line);
                    if ( trim($transferred[1]) > 0 ){
                        // files transferred, run command if defined
                        $run_command = TRUE;
                    }
                }
            }
            $status = TRUE;
        }
        // reload nagios/icinga ?
        if ( $status && $run_command && !empty($host_infos["reload_command"]) ){
            $ssh_command = $this->ssh;
            if (!empty($host_infos["identity_file"]) ){
                $ssh_command .= ' -i ';
                // add path of current directory if it is relative
                if ( !preg_match( '/^\//', $host_infos["identity_file"]) ){
                    $ssh_command .= $this->path.'/';
                }
                $ssh_command .= $host_infos["identity_file"];
            }

            // options must be complete like "-o ssh_option" or "-l limit"
            if (!empty($host_infos["ssh_options"]) ) $ssh_command .= ' '.$host_infos["ssh_options"];
            
            if (!empty($host_infos["user"]) ) $ssh_command .= ' '.$host_infos["user"].'@';
            if (!empty($host_infos["host"]) ) $ssh_command .= $host_infos["host"];
            $ssh_command .= ' "'.$host_infos["reload_command"].'"';

            // execute ssh command
            $status = $this->system_call($ssh_command);
        }

        return $status;
    }


}

?>
