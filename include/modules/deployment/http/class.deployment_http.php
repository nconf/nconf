<?php
class http extends NConf_Deployment_Modules
{
    public function command($host_info){
        $CONF_DEPLOY_URL  = $host_info["host"];
        $CONF_DEPLOY_USER = ( !empty($host_info["user"]) )          ? $host_info["user"] : '';
        $CONF_DEPLOY_PWD  = ( !empty($host_info["password"]) )      ? $host_info["password"] : '';
        $REMOTE_EXECUTE   = ( !empty($host_info["remote_execute"]) )   ? $host_info["remote_execute"] : '';
        $REMOTE_ACTION    = ( !empty($host_info["remote_action"]) )   ? $host_info["remote_action"] : '';
        $post_data = array();
        $post_data['file'] = '@'.$host_info["source_file"];
        $post_data['remote_execute'] = $REMOTE_EXECUTE;
        $post_data['remote_action']  = $REMOTE_ACTION;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $CONF_DEPLOY_URL );
        curl_setopt($ch, CURLOPT_POST, 1 );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        if( !empty($CONF_DEPLOY_USER) and !empty($CONF_DEPLOY_PWD) ){
            curl_setopt($ch, CURLOPT_USERPWD, $CONF_DEPLOY_USER.":".$CONF_DEPLOY_PWD);
        }

        $postResult = curl_exec($ch);
        if( $postResult === false){
            $curl_error = 'Curl-Error: ' . curl_error($ch);
            if( !curl_errno($ch) ){
                $info = curl_getinfo($ch);
                $curl_error .= "<br>".$info["http_code"];
            }
        }
        curl_close($ch);
        if ($postResult == "OK"){
            return TRUE;
        }else{
            if ( !empty($postResult) ){
                NConf_HTML::set_info( NConf_HTML::table_row_check('', 'FAILED', $postResult) , 'add' );
            }
            
            if ( !empty($curl_error) ){
                NConf_HTML::set_info( NConf_HTML::table_row_check('', 'FAILED', $curl_error) , 'add');
            }
            return FALSE;
        }

    }

}

?>
