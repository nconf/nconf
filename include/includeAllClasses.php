<?php
// Define autoload for including the classes file when creating object of unknown class
function __autoload($class_name) {
    $class_path = NCONFDIR.'/include/classes/class.'.$class_name.'.php';
    if ( !empty($class_name) && file_exists($class_path) ){
        #include(NCONFDIR.'/include/classes/class.'.$class_name.'.php');
        require_once($class_path);
        NConf_DEBUG::set("class $class_name", 'DEBUG', 'Autoload');
    }
}
?>
