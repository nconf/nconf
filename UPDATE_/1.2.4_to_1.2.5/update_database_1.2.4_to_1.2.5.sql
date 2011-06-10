# -- NConf database update sctipt --
# -- from version 1.2.4 to 1.2.5 --

# -- add 'out_file' and 'nagios_object' attrs to ConfigClasses table --
ALTER TABLE `ConfigClasses` ADD `out_file` VARCHAR( 50 ) NULL ,
ADD `nagios_object` VARCHAR( 50 ) NULL ;

# -- set values for 'out_file' and 'nagios_object' --
UPDATE `ConfigClasses` SET `out_file` = 'hosts.cfg', `nagios_object` = 'host' WHERE `config_class` = "host";
UPDATE `ConfigClasses` SET `out_file` = 'hostgroups.cfg', `nagios_object` = 'hostgroup' WHERE `config_class` = "hostgroup";
UPDATE `ConfigClasses` SET `out_file` = 'services.cfg', `nagios_object` = 'service' WHERE `config_class` = "service";
UPDATE `ConfigClasses` SET `out_file` = 'servicegroups.cfg', `nagios_object` = 'servicegroup' WHERE `config_class` = "servicegroup";
UPDATE `ConfigClasses` SET `out_file` = 'contacts.cfg', `nagios_object` = 'contact' WHERE `config_class` = "contact";
UPDATE `ConfigClasses` SET `out_file` = 'contactgroups.cfg', `nagios_object` = 'contactgroup' WHERE `config_class` = "contactgroup";
UPDATE `ConfigClasses` SET `out_file` = 'timeperiods.cfg', `nagios_object` = 'timeperiod' WHERE `config_class` = "timeperiod";
UPDATE `ConfigClasses` SET `out_file` = 'checkcommands.cfg', `nagios_object` = 'command' WHERE `config_class` = "checkcommand";
UPDATE `ConfigClasses` SET `out_file` = 'misccommands.cfg', `nagios_object` = 'command' WHERE `config_class` = "misccommand";

# -- add 'action_url' attr to 'host' class --
UPDATE ConfigAttrs SET ordering=ordering+1 WHERE ordering > 12 AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="host");
INSERT INTO ConfigAttrs (attr_name,friendly_name,description,datatype,max_length,poss_values,predef_value,mandatory,ordering,visible,write_to_conf,naming_attr,link_as_child,fk_show_class_items,fk_id_class) VALUES ('action_url','action URL','PNP URL (if installed), NConf dependency view, or both','select','','/nagios/html/pnp4nagios/index.php?host=$HOSTNAME$,https://URL_TO_NCONF/dependency.php?xmode=nagiosview&hostname=$HOSTNAME$&service_link=https://URL_TO_NAGIOS/nagios/cgi-bin/extinfo.cgi,https://URL_TO_NCONF/dependency.php?xmode=nagiosview&hostname=$HOSTNAME$&service_link=https://URL_TO_NAGIOS/nagios/cgi-bin/extinfo.cgi&pnp_link=https://URL_TO_NAGIOS/nagios/html/pnp4nagios/index.php','','no','13','yes','yes','no','NULL',NULL,(SELECT id_class from ConfigClasses WHERE config_class = 'host') );

# -- add 'action_url' attr to 'service' class --
UPDATE ConfigAttrs SET ordering=ordering+1 WHERE ordering > 9 AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="service");
INSERT INTO ConfigAttrs (attr_name,friendly_name,description,datatype,max_length,poss_values,predef_value,mandatory,ordering,visible,write_to_conf,naming_attr,link_as_child,fk_show_class_items,fk_id_class) VALUES ('action_url','action URL','PNP URL (if installed)','select','','/nagios/html/pnp4nagios/index.php?host=$HOSTNAME$&srv=$SERVICEDESC$','','no','12','yes','yes','no','NULL',NULL,(SELECT id_class from ConfigClasses WHERE config_class = 'service') );

# -- update default values and description of several attrs --
UPDATE ConfigAttrs SET max_length="255" WHERE attr_name="address" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="host");
UPDATE ConfigAttrs SET mandatory="no" WHERE attr_name="members" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="hostgroup");
UPDATE ConfigAttrs SET mandatory="no" WHERE attr_name="members" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="servicegroup");
UPDATE ConfigAttrs SET mandatory="no" WHERE attr_name="members" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="contactgroup");
UPDATE ConfigAttrs SET ordering=ordering-1 WHERE attr_name="check_period" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="service");
UPDATE ConfigAttrs SET ordering=ordering+1 WHERE attr_name="notification_period" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="service");
UPDATE ConfigAttrs SET predef_value="d", description="possible values: d,u,r,f,n" WHERE attr_name="host_notification_options" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="contact");
UPDATE ConfigAttrs SET predef_value="c", description="possible values: w,u,c,r,f,n" WHERE attr_name="service_notification_options" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="contact");
UPDATE ConfigAttrs SET predef_value="0", mandatory="no" WHERE attr_name="event_handler_enabled" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="service");
UPDATE ConfigAttrs SET predef_value="1" WHERE attr_name="command_param_count" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="checkcommand");
UPDATE ConfigAttrs SET predef_value="" WHERE (attr_name="sunday" OR attr_name="monday" OR attr_name="tuesday" OR attr_name="wednesday" OR attr_name="thursday" OR attr_name="friday" OR attr_name="saturday") AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="timeperiod");
