# -- NConf database update sctipt --
# -- from version 1.2.6 to 1.2.7 --

# -- add 'link_bidirectional' attribute to ConfigAttrs table --
ALTER TABLE ConfigAttrs ADD COLUMN `link_bidirectional` enum('yes','no') NOT NULL default 'no' AFTER link_as_child;

# -- set 'link_bidirectional' flag for certain attributes --
UPDATE ConfigAttrs SET friendly_name="assign host to hostgroup", link_bidirectional="yes" WHERE attr_name="members" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="hostgroup");
UPDATE ConfigAttrs SET friendly_name="assign service to servicegroup", link_bidirectional="yes" WHERE attr_name="members" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="servicegroup");
UPDATE ConfigAttrs SET friendly_name="assign contact to contactgroup", link_bidirectional="yes" WHERE attr_name="members" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="contactgroup");

# -- add 'class_type' attribute to ConfigClasses table --
ALTER TABLE ConfigClasses ADD COLUMN `class_type` enum('global','monitor','collector') NOT NULL default 'global' AFTER nav_privs;
UPDATE ConfigClasses SET class_type="collector" 
        WHERE config_class='host' OR config_class='hostgroup' OR config_class='service' OR config_class='servicegroup';

# -- expand history actions --
ALTER TABLE `History` CHANGE `action` `action` ENUM('created','added','assigned','unassigned','modified','edited','removed','general','module') NOT NULL;

# -- allow checkcommands to have 0 parameters --
UPDATE ConfigAttrs SET poss_values='0::1::2::3::4::5::6::7::8::9::10' WHERE attr_name='command_param_count' AND fk_id_class = (SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand');

# -- add host-dependency class and attributes --
INSERT INTO ConfigClasses (config_class, friendly_name, nav_visible, ordering, grouping, nav_links, nav_privs, class_type, out_file, nagios_object) VALUES ('host-dependency','Host deps.','yes',4,'Advanced Items','Show::overview.php?class=host-dependency;;Add::handle_item.php?item=host-dependency','admin','monitor','host_dependencies.cfg','hostdependency');
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('dependency_name','dependency name','description for the dependency definition','text',1024,'','','yes',1,'yes','no','yes','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='host-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('execution_failure_criteria','execution failure criteria','possible values: o,d,u,p,n','text',20,'','','no',2,'yes','yes','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='host-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('notification_failure_criteria','notification failure criteria','possible values: o,d,u,p,n','text',20,'','','no',3,'yes','yes','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='host-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('dependency_period','dependency time period','restrict dependency to this time period','assign_one',0,'','','no',4,'yes','yes','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='timeperiod'),(SELECT id_class FROM ConfigClasses WHERE config_class='host-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('inherits_parent','inherit dependencies','inherit dependencies from host(s) depended on','select',0,'0::1','0','no',5,'yes','yes','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='host-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('host_name','master host(s)','host(s) being depended upon','assign_many',0,'','','no',6,'yes','yes','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='host'),(SELECT id_class FROM ConfigClasses WHERE config_class='host-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('hostgroup_name','master hostgroup(s)','hostgroup(s) being depended upon','assign_many',0,'','','no',7,'yes','yes','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='hostgroup'),(SELECT id_class FROM ConfigClasses WHERE config_class='host-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('dependent_host_name','dependent host(s)','the dependent host(s)','assign_many',0,'','','no',8,'yes','yes','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='host'),(SELECT id_class FROM ConfigClasses WHERE config_class='host-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('dependent_hostgroup_name','dependent hostgroup(s)','the dependent hostgroup(s)','assign_many',0,'','','no',9,'yes','yes','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='hostgroup'),(SELECT id_class FROM ConfigClasses WHERE config_class='host-dependency'));
UPDATE ConfigAttrs SET description="" WHERE attr_name="parents" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class='host');

# -- add service-dependency class and attributes --
INSERT INTO ConfigClasses (config_class, friendly_name, nav_visible, ordering, grouping, nav_links, nav_privs, class_type, out_file, nagios_object) VALUES ('service-dependency','Service deps.','yes',5,'Advanced Items','Show::overview.php?class=service-dependency;;Add::handle_item.php?item=service-dependency','admin','monitor','service_dependencies.cfg','servicedependency');
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('dependency_name','dependency name','description for the dependency definition','text',1024,'','','yes',1,'yes','no','yes','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='service-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('execution_failure_criteria','execution failure criteria','possible values: o,w,u,c,p,n','text',20,'','','no',2,'yes','yes','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='service-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('notification_failure_criteria','notification failure criteria','possible values: o,w,u,c,p,n','text',20,'','','no',3,'yes','yes','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='service-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('dependency_period','dependency time period','restrict dependency to this time period','assign_one',0,'','','no',4,'yes','yes','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='timeperiod'),(SELECT id_class FROM ConfigClasses WHERE config_class='service-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('inherits_parent','inherit dependencies','inherit dependencies from service(s) depended on','select',0,'0::1','0','no',5,'yes','yes','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='service-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('service_description','master service(s)','service(s) being depended upon','assign_many',0,'','','yes',6,'yes','yes','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='service'),(SELECT id_class FROM ConfigClasses WHERE config_class='service-dependency'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('dependent_service_description','dependent service(s)','the dependent service(s)','assign_many',0,'','','yes',7,'yes','yes','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='service'),(SELECT id_class FROM ConfigClasses WHERE config_class='service-dependency'));

# -- add "service_enabled" attribute to services class --
UPDATE ConfigAttrs SET ordering = ordering + 1 WHERE ordering > 1 AND fk_id_class = (SELECT id_class FROM ConfigClasses WHERE config_class='service');
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_id_class) VALUES ('service_enabled','service enabled','enable / disable this service','select','yes::no','yes','yes',2,'yes','no','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='service'));

# -- add attribute for recursive assignment of groups (HG to HG, SG to SG, CG to CG) --
UPDATE ConfigAttrs SET ordering = ordering + 1 WHERE ordering > 3 AND fk_id_class = (SELECT id_class FROM ConfigClasses WHERE config_class='hostgroup');
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('hostgroup_members','assign hostgroup to hostgroup','','assign_many',0,'','','no',4,'yes','yes','no','yes',(SELECT id_class FROM ConfigClasses WHERE config_class='hostgroup'),(SELECT id_class FROM ConfigClasses WHERE config_class='hostgroup'));
UPDATE ConfigAttrs SET ordering = ordering + 1 WHERE ordering > 3 AND fk_id_class = (SELECT id_class FROM ConfigClasses WHERE config_class='servicegroup');
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('servicegroup_members','assign servicegroup to servicegroup','','assign_many',0,'','','no',4,'yes','yes','no','yes',(SELECT id_class FROM ConfigClasses WHERE config_class='servicegroup'),(SELECT id_class FROM ConfigClasses WHERE config_class='servicegroup'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('contactgroup_members','assign contactgroup to contactgroup','','assign_many',0,'','','no',4,'yes','yes','no','yes',(SELECT id_class FROM ConfigClasses WHERE config_class='contactgroup'),(SELECT id_class FROM ConfigClasses WHERE config_class='contactgroup'));

# -- clean up checkcommands class --
UPDATE ConfigAttrs SET friendly_name="params description" WHERE attr_name="command_syntax" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand');
UPDATE ConfigAttrs SET description="short description of each parameter (comma separated, same order)" WHERE attr_name="command_syntax" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand');
UPDATE ConfigAttrs SET predef_value="" WHERE attr_name="command_syntax" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand');
UPDATE ConfigAttrs SET ordering=ordering+1 WHERE attr_name="command_syntax" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand');
UPDATE ConfigAttrs SET ordering=ordering-1 WHERE attr_name="default_params" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand');

# -- add "service_template" attribute to checkcommands class --
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, mandatory, predef_value, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES ('service_template','default service template(s)','add the following template(s) to all services linked with this checkcommand','assign_cust_order','no','',7,'yes','no','no','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='service-template'),(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand'));

# -- add "default_service_dependency" attributes to checkcommands class --
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, mandatory, predef_value, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES ('default_service_dependency','default command to depend upon','make all services using the current command dependent on services on the same host using this command selected here','assign_one','no','',8,'yes','no','no','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand'),(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('dependency_execution_failure_criteria','execution_failure_criteria for dependency','the \'execution_failure_criteria\' value to use for the dependency; possible values: o,w,u,c,p,[n]','text',20,'','','no',9,'yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, fk_show_class_items, fk_id_class) VALUES ('dependency_notification_failure_criteria','notification_failure_criteria for dependency','the \'notification_failure_criteria\' value to use for the dependency; possible values: o,w,u,c,p,[n]','text',20,'','','no',10,'yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand'));

# -- add "advanced-service" class and attributes --
INSERT INTO ConfigClasses (config_class, friendly_name, nav_visible, ordering, grouping, nav_links, nav_privs, class_type, out_file, nagios_object)
VALUES ('advanced-service','Advanced Services','yes',4,'','Show::overview.php?class=advanced-service;;Add::handle_item.php?item=advanced-service','user','collector','advanced_services.cfg','service');
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('service_description','advanced service name','','text',255,'','','yes',1,'yes','yes','yes','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('check_command','check command','','assign_one',0,'','','yes',2,'yes','yes','no','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='checkcommand'),(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('host_name','assign advanced-service to host','','assign_many',0,'','','no',3,'yes','yes','no','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='host'),(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('hostgroup_name','assign advanced-service to hostgroup','','assign_many',0,'','','no',4,'yes','yes','no','no','yes',(SELECT id_class FROM ConfigClasses WHERE config_class='hostgroup'),(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('check_period','check period','time period to run checks','assign_one',0,'','','no',5,'yes','yes','no','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='timeperiod'),(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('notification_period','notification period','time period to alarm','assign_one',0,'','','no',6,'yes','yes','no','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='timeperiod'),(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('use','service template(s)','parent template(s) to inherit from','assign_cust_order',0,'','','no',7,'yes','yes','no','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='service-template'),(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('contact_groups','contact groups','responsible group','assign_many',0,'','','no',8,'yes','yes','no','no','no',(SELECT id_class FROM ConfigClasses WHERE config_class='contactgroup'),(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('notes','notes','','text',1024,'','','no',9,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('notes_url','notes URL','','text',1024,'','','no',10,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('action_url','action URL','PNP URL (if installed)','select',0,'/nagios/html/pnp4nagios/index.php?host=$HOSTNAME$&srv=$SERVICEDESC$','','no',11,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('max_check_attempts','max check attempts','number of times to retry checking','text',4,'','','no',12,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('check_interval','check interval','number of [min.] between regularly scheduled checks','text',4,'','','no',13,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('retry_interval','retry interval','number of [min.] to wait before scheduling a re-check','text',4,'','','no',14,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('notification_interval','notification interval','number of [min.] to wait before re-notifying a contact','text',4,'','','no',15,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('notification_options','notification options','possible values: w,u,c,r,f,s,[n]','text',20,'','','no',16,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('active_checks_enabled','active checking','do active checking of services','select',0,'0::1','','no',17,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('passive_checks_enabled','passive checking','do passive checking of services','select',0,'0::1','','no',18,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('notifications_enabled','notification enabled','send notifications for services','select',0,'0::1','','no',19,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('check_freshness','check freshness','check age of last check results','select',0,'0::1','','no',20,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('freshness_threshold','freshness threshold','age threshold in [sec.]','text',5,'','','no',21,'yes','yes','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));
INSERT INTO ConfigAttrs (attr_name, friendly_name, description, datatype, max_length, poss_values, predef_value, mandatory, ordering, visible, write_to_conf, naming_attr, link_as_child, link_bidirectional, fk_show_class_items, fk_id_class) VALUES
('check_params','params for check command','','text',1024,'','!','no',22,'yes','no','no','no','no',NULL,(SELECT id_class FROM ConfigClasses WHERE config_class='advanced-service'));

# -- update navigation links --
UPDATE ConfigClasses SET nav_links = REPLACE(nav_links,'add_item.php','handle_item.php');

# -- change placement and grouping in the navigation --
UPDATE ConfigClasses SET grouping="Advanced Items" WHERE config_class="host-preset" OR config_class="host-template" OR config_class="service-template";
UPDATE ConfigClasses SET nav_privs="user" WHERE config_class="service";
UPDATE ConfigClasses SET ordering="1" WHERE config_class="host";
UPDATE ConfigClasses SET ordering="2" WHERE config_class="hostgroup";
UPDATE ConfigClasses SET ordering="3" WHERE config_class="service";
UPDATE ConfigClasses SET ordering="4" WHERE config_class="advanced-service";
UPDATE ConfigClasses SET ordering="5" WHERE config_class="servicegroup";
UPDATE ConfigClasses SET ordering="1" WHERE config_class="os";
UPDATE ConfigClasses SET ordering="2" WHERE config_class="contact";
UPDATE ConfigClasses SET ordering="3" WHERE config_class="contactgroup";
UPDATE ConfigClasses SET ordering="4" WHERE config_class="checkcommand";
UPDATE ConfigClasses SET ordering="5" WHERE config_class="misccommand";
UPDATE ConfigClasses SET ordering="6" WHERE config_class="timeperiod";
UPDATE ConfigClasses SET ordering="1" WHERE config_class="host-preset";
UPDATE ConfigClasses SET ordering="2" WHERE config_class="host-template";
UPDATE ConfigClasses SET ordering="3" WHERE config_class="service-template";
UPDATE ConfigClasses SET ordering="4" WHERE config_class="host-dependency";
UPDATE ConfigClasses SET ordering="5" WHERE config_class="service-dependency";
UPDATE ConfigClasses SET ordering="1" WHERE config_class="nagios-monitor";
UPDATE ConfigClasses SET ordering="2" WHERE config_class="nagios-collector";

# -- change friendy_name of certain classes --
UPDATE ConfigClasses SET friendly_name="Central monitors" WHERE config_class ="nagios-monitor";
UPDATE ConfigClasses SET friendly_name="Distrib. collectors" WHERE config_class="nagios-collector";

# -- set timeperiod alias to "mandatory" --
UPDATE ConfigAttrs SET mandatory="yes" WHERE attr_name="alias" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="timeperiod");

# -- set host-preset command link to "not mandatory" --
UPDATE ConfigAttrs SET mandatory="no" WHERE attr_name="command_name" AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="host-preset");

# -- correct some contact parameters --
UPDATE ConfigAttrs SET max_length=20 WHERE attr_name='host_notification_options' AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class='contact');
UPDATE ConfigAttrs SET max_length=20 WHERE attr_name='service_notification_options' AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class='contact');

# -- replace the friendly_name "IP-address" with "address" to call it like Nagios does --
UPDATE ConfigAttrs SET friendly_name = 'address', description = 'IP-address / DNS name' WHERE attr_name='address' AND fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class="host");

# -- add history entry for update --
INSERT INTO History (user_str, action, attr_name, attr_value) VALUES ('NConf Setup','general','updated','NConf to version 1.2.7');
