<?php
class scp extends NConf_Deployment_Modules
{
    protected $scp = 'scp';
    protected $ssh = 'ssh';
    protected $path;

    function __construct(){
        $this->path = dirname(__FILE__);
    }

    public function command($host_infos){
        //create command....
        $command = '';

        // File or directory (recursive mode) if one source file is a directory
        $source_files = explode(' ', $host_infos["source_file"]);
        $recursive = FALSE;
        foreach ($source_files AS $source_file){
            if ( is_dir($source_file) ){
                $recursive = TRUE;
                break;
            }
        }
        if ($recursive){
            $command .= ' -r ';
        }

        // identity_file
        if (!empty($host_infos["identity_file"]) ){
            $command .= ' -i ';
            // add path of current directory if it is relative
            if ( !preg_match( '/^\//', $host_infos["identity_file"]) ){
                $command .= $this->path.'/';
            }
            $command .= $host_infos["identity_file"];
        }

        // additional options
        // options must be complete like "-o ssh_option" or "-l limit"
        if (!empty($host_infos["ssh_options"]) ) $command .= ' '.$host_infos["ssh_options"];

        // source file
        if (!empty($host_infos["source_file"]) ) $command .= ' '.$host_infos["source_file"];

        // target
        if (!empty($host_infos["user"]) ) $command .= ' '.$host_infos["user"].'@';
        if (!empty($host_infos["host"]) ) $command .= $host_infos["host"].':';
        if (!empty($host_infos["target_file"]) ) $command .= $host_infos["target_file"];

        $status = $this->system_call($this->scp.' '.$command);

        // reload nagios/icinga ?
        if ( $status && !empty($host_infos["reload_command"]) ){
            $ssh_command = $this->ssh;
            if (!empty($host_infos["identity_file"]) ){
                $ssh_command .= ' -i ';
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
