<?php

if( !empty($_GET["item"]) OR !empty($config_class)  ){
    if ( empty($config_class) AND !empty($_GET["item"]) ){
        $config_class = $_GET["item"]; 
    }

    $query = "SELECT id_attr,predef_value,datatype,fk_show_class_items 
                            FROM ConfigAttrs,ConfigClasses 
                            WHERE visible='no' 
                            AND id_class=fk_id_class 
                            AND config_class='$config_class'
    ";
    $result = db_handler($query, "result", "Load not visible attrs");
    
    while($entry = mysql_fetch_assoc($result)){

        if( ($entry["datatype"] == "text") OR ($entry["datatype"] == "select") ){
            $output = '<input type="hidden" name="'.$entry["id_attr"].'" value="'.$entry["predef_value"].'">';
            echo $output;

            message($debug, "Hidden Field:".str_replace("<", "", $output) );

        }elseif(
               ($entry["datatype"] == "assign_one")
            OR ($entry["datatype"] == "assign_many")
            OR ($entry["datatype"] == "assign_cust_order")
        ){

            if ( $entry["datatype"] != "assign_one" ){
                // split predefined values
                $predef_values = preg_split("/".SELECT_VALUE_SEPARATOR."/", $entry["predef_value"]);
            }else{
                // set predefined value as array, to use same loop as splited values
                $predef_values = array($entry["predef_value"]);
            }
            
            foreach ($predef_values AS $predef_value){
                if ( empty($predef_value) ){
                    // empty values must not be looked up
                    $entry2 = '';
                }else{
                    // lookup for id
                    $query2 = 'SELECT fk_id_item 
                                FROM ConfigValues,ConfigAttrs 
                                WHERE id_attr=fk_id_attr 
                                AND naming_attr="yes" 
                                AND fk_id_class="'.$entry["fk_show_class_items"].'"
                                AND attr_value="'.$predef_value.'";
                    ';
                    $entry2 = db_handler($query2, "getOne", "Load linked item: ( not visible assign_MANY/CUST_ORDER )");
                }
                
                // output
                $output2 = '<input type="hidden" name="'.$entry["id_attr"].'[]" value="'.$entry2.'">';
                echo $output2;

                message('DEBUG', "Hidden Field:".str_replace("<", "", $output2));
            }

        }
        
    } // end of while

}


?>
