<?php

$id = $host_id = $_POST["host_id"];


# set attribute id of 
$attribute_id = db_templates("get_attr_id", "advanced-service", "host_name");

$advanced_services[$attribute_id] = $_POST["advanced_services"];

$config_class = "advanced-service";

    $query = 'SELECT
                ConfigValues.fk_id_item AS id,
                attr_value AS entryname,
                (SELECT attr_value
                    FROM ConfigValues,ConfigAttrs
                    WHERE id_attr=fk_id_attr
                        AND attr_name="service_enabled"
                        AND fk_id_item=id) AS service_enabled
                FROM ConfigValues, ConfigAttrs, ConfigClasses, ItemLinks
                WHERE id_attr = ConfigValues.fk_id_attr
                AND naming_attr = "yes"
                AND id_class = fk_id_class
                AND config_class = "advanced-service"
                AND ConfigValues.fk_id_item = ItemLinks.fk_id_item
                AND fk_item_linked2 = '.$host_id.'
                ORDER BY entryname
             ';

    $old_linked_data[$attribute_id] = db_handler($query, "array_direct", "Get advanced-service of host with its service_enabled status");

    NConf_DEBUG::set( $advanced_services, 'DEBUG', "send items");
    $class_id = db_templates("get_id_of_class", "advanced-service");
    $name = db_templates("naming_attr", $id);

    # history entry status for "edited"
    $edited = FALSE;

    $handle_action = 'modify';
    $items2write = $advanced_services;

    # special case for advanced_services !
    # items_write2db will look for $advanced_services and use the "bidirection/child" feature, to swap the data
    require_once('include/items_write2db.php'); // needs $items2write

if (!NConf_DEBUG::status('ERROR') AND $edited ){
    echo '<div id="clone_success">ok</div>';
}



?>
