<?php
#
# load and configure modules
#
$modules_dir = getDirectoryTree('include/modules');
foreach($modules_dir as $module){
    $module_config = 'include/modules/'.$module.'/init.php';
    if (file_exists($module_config) ){
        require_once($module_config);
        message($debug, "Initialized module: $module_config");
    }else{
        message($error, "FAILED initialize module: $module_config");
    }
}
?>
