<?php
class local extends NConf_Deployment_Modules
{
    protected $tar      = 'tar';
    protected $gunzip   = 'gunzip -f';


    public function recursive_copy($src,$dst){
        $status = TRUE;
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src.$file) ) {
                    $status_tmp = $this->recursive_copy($src.$file."/", $dst.$file."/");
                }else {
                    $status_tmp = copy($src.$file, $dst.$file);
                    // for debug
                    //NConf_HTML::set_info( NConf_HTML::table_row_check('copy', '', $src.$file.' -> '.$dst.$file ) , 'add' );
                }
                if ($status_tmp == FALSE){
                    $status = FALSE;
                }
            }
        }
        closedir($dir); 

        return $status;
    }

    public function command($host_infos){
        // check source
        $status = '';
        if ( !file_exists($host_infos["source_file"]) ){
            NConf_HTML::set_info( NConf_HTML::table_row_check('source_file', 'FAILED', 'Source file does not exist ('.$host_infos["source_file"].')' ) , 'add' );
            $status = FALSE;
        }

        
        if ( (substr($host_infos["target_file"], -1) == "/") OR is_dir($host_infos["source_file"]) ){
            // force target directory if source is directory
            $dirname = dirname($host_infos["target_file"].'/.');
        }else{
            // get dirname
            $dirname = dirname($host_infos["target_file"]);
        }
        // check target directory
        if ( !file_exists( $dirname ) ){
            if ( !is_dir( $dirname ) ){
                $structure = $dirname;
                // create directory
                $status = mkdir($structure, 0775, true);
                NConf_HTML::set_info( NConf_HTML::table_row_check('PHP mkdir:', $status, 'Create target directory ('.$dirname.')' ) , 'add' );
            }

            // check target again
            if ( !is_dir( $dirname ) ){
                NConf_HTML::set_info(
                    NConf_HTML::table_row_check('target_file', 'FAILED', 'Target directory does not exist, or permissions denied ('.$dirname.')' )
                    , 'add'
                );
            $status = FALSE;
            }
        }
        // break, if status already is FALSE
        if ($status === FALSE){
            return $status;
        }

        // check action
        if ( empty($host_infos["action"]) ){
            NConf_HTML::set_info( NConf_HTML::table_row_check('action', 'FAILED', 'action is not defined, read documentation for details.') , 'add' );
        }elseif ($host_infos["action"] == "extract"){
            #$target_file_tgz = $dirname.'/'.basename($host_infos["source_file"]);
            #$target_file_tar = $dirname.'/'.basename($host_infos["source_file"], ".tgz").'.tar';
            $target_file_tgz = $host_infos["target_file"].basename($host_infos["source_file"]);
            $target_file_tar = $host_infos["target_file"].basename($host_infos["source_file"], ".tgz").'.tar';

            // copy
            $status = copy($host_infos["source_file"], $target_file_tgz);
            NConf_HTML::set_info( NConf_HTML::table_row_check('PHP copy:', $status, 'temporary copy('.$host_infos["source_file"].', '.$target_file_tgz.')' ) , 'add' );
            // gunzip
            $status = $this->system_call($this->gunzip.' '.$target_file_tgz );

            // tar
            $tar_command = $this->tar;
            if (!empty($host_infos["options"]) ){
                $tar_command .= ' '.$host_infos["options"];
            }else{
                $tar_command .= ' -xf';
            }
            $tar_command .= ' '.$target_file_tar;
            $tar_command .= ' -C '.$host_infos["target_file"];

            $status = $this->system_call($tar_command);

            // remove gunzip'ed file
            // no "$status =" because it doesn't matter when this fails
            $status_unlink = unlink($target_file_tar);
            NConf_HTML::set_info( NConf_HTML::table_row_check('PHP unlink:', $status_unlink, ' remove temporary file('.$target_file_tar.')' ), 'add' );
            

        }elseif ($host_infos["action"] == "copy"){
            if (!empty($host_infos["source_file"]) && !empty($host_infos["target_file"])){
                if ( is_dir($host_infos["source_file"]) ){
                    // handle/copy directories
                    $status = $this->recursive_copy($host_infos["source_file"], $host_infos["target_file"]);
                    NConf_HTML::set_info( NConf_HTML::table_row_check('PHP copy:', $status, 'recursive copy('.$host_infos["source_file"].', '.$host_infos["target_file"].')' ) , 'add' );
                }else{
                    // copy single file
                    if ( !copy($host_infos["source_file"], $host_infos["target_file"]) ){
                        $status = FALSE;
                    }else{
                        $status = TRUE;
                    } 
                    NConf_HTML::set_info( NConf_HTML::table_row_check('PHP copy:', $status, 'copy('.$host_infos["source_file"].', '.$host_infos["target_file"].')' ) , 'add' );
                }

            }else{
                return FALSE;
            }

        }elseif ($host_infos["action"] == "move"){
            if (!empty($host_infos["source_file"]) && !empty($host_infos["target_file"])){
                // rename file or directory
                $status = rename($host_infos["source_file"], $host_infos["target_file"]);
                NConf_HTML::set_info(
                    NConf_HTML::table_row_check('PHP rename:', $status, 'rename('.$host_infos["source_file"].', '.$host_infos["target_file"].')' )
                    , 'add'
                );
            }else{
                return FALSE;
            }
        }

        // reload nagios/icinga ?
        if ( $status && !empty($host_infos["reload_command"]) ){
            if ( is_array($host_infos["reload_command"]) ){
            // predefine status to unknown
            $status = "UNKNOWN";
                foreach ($host_infos["reload_command"] as $command){
                    $command_status = $this->system_call($command);
                    if (!$command_status){
                        // command failed
                        $status = FALSE;
                    }elseif($command_status){
                        // only make true if status was not already false (previouse command failed)
                        if ($status != FALSE){
                            $status = TRUE;
                        }
                    }
                }
            }else{
                $status = $this->system_call($host_infos["reload_command"]);
            }
        }

        return $status;
    }


}

?>
