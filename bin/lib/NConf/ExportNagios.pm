##############################################################################
# "NConf::ExportNagios" library
# A collection of shared functions for the NConf Perl scripts.
# Functions needed to generate and export Nagios configuration files from NConf.
#
# Version 0.3
# Written by Angelo Gargiulo
#
# Revision history:
# 2009-10-08 v0.1   A. Gargiulo   First release
# 2010-01-21 v0.2   A. Gargiulo   Removed hostextinfo/serviceextinfo items
# 2010-04-19 v0.3   A. Gargiulo   Changed the way global/server-specific files are generated
#
# To-Do:
# This module was migrated from the former generate_config.pl script.
# Functions which access the database need to be consolidated with 
# NConf::DB::Read, if possible. 
#
##############################################################################
 
package NConf::ExportNagios;

use strict;
use Exporter;
use NConf;
use NConf::DB;
use NConf::DB::Read;
use NConf::Helpers;
use NConf::Logger;
use Tie::IxHash;    # preserve hash order

##############################################################################
### I N I T ##################################################################
##############################################################################

BEGIN {
    use vars qw(@ISA @EXPORT @EXPORT_OK);

    @ISA         = qw(NConf);
    @EXPORT      = qw(@NConf::EXPORT create_monitor_config create_collector_config create_global_config);
    @EXPORT_OK   = qw(@NConf::EXPORT_OK);
}

# set loglevel
my $loglevel = &readNConfConfig(NC_CONFDIR."/nconf.php","DEBUG_GENERATE","scalar",1);
unless($loglevel =~ /^[1245]$/){$loglevel=3}
&setLoglevel($loglevel);

# global vars
use vars qw($fattr $fval); # Superglobals
my ($root_path, $output_path, $global_path, $test_path, $monitor_path, $collector_path, $check_static_cfg);
my (@superadmins, @oncall_groups, @global_cfg_files, @server_cfg_files, @static_cfg);
my (%class_info, %checkcommand_info, %files_written);

# fetch options from main NConf config
$root_path        = &readNConfConfig(NC_CONFDIR."/nconf.php","NCONFDIR","scalar");
@superadmins      = &readNConfConfig(NC_CONFDIR."/nconf.php","SUPERADMIN_GROUPS","array");
@oncall_groups    = &readNConfConfig(NC_CONFDIR."/nconf.php","ONCALL_GROUPS","array");
@static_cfg       = &readNConfConfig(NC_CONFDIR."/nconf.php","STATIC_CONFIG","array");
$check_static_cfg = &readNConfConfig(NC_CONFDIR."/nconf.php","CHECK_STATIC_SYNTAX","scalar",1);
if($check_static_cfg ne 0){$check_static_cfg=1}

# fetch and cache all classes in ConfigClasses table
%class_info = &getConfigClasses();

# fetch and cache all checkcommands and their attributes/values
%checkcommand_info = &fetch_cmd_info();

# define output structure
$output_path = "$root_path/temp";
$test_path   = "$root_path/temp/test";
$global_path = "$output_path/global";

my $timestamp = time();
my $mon_count = 0;
my $progress_step = 0;
my $progress_status = 0;

# calculate amount of "steps" for progress bar
&define_progress_steps;

##############################################################################
### S U B S ##################################################################
##############################################################################

# SUB create_monitor_config
# Generate Nagios configuration files for monitor server(s)

sub create_monitor_config {
    &logger(5,"Entered create_monitor_config()");

    # fetch all monitor servers
    my @monitors = &getItems("nagios-monitor",1);

    foreach my $row (@monitors){

        &logger(3,"Generating config for Nagios-monitor '$row->[1]'");

        # store monitor name separately
        $NC_macro_values{'NAGIOS_SERVER_NAME'} = $row->[1];

        # create output directory
        $row->[1] =~ s/-|\s/_/g;
        $monitor_path = "$output_path/$row->[1]";
        if(-e $monitor_path){rename($monitor_path,$monitor_path."_".time) or &logger(1,"Could not rename $monitor_path")}
        &logger(4,"Creating output directory '$monitor_path'");
        mkdir($monitor_path,0755) or &logger(1,"Could not create $monitor_path");

        # create configuration files for monitor server(s)
        &create_monitor_specific_files($row->[0]);

        # create nagios.cfg file for monitor server to test generated config
        &create_test_cfg($row->[1]);
        @server_cfg_files = undef;

	    $mon_count++;

        # increase progress status for web GUI
        &set_progress();
    }
}


########################################################################################
# SUB create_collector_config
# Generate Nagios configuration files for collector servers

sub create_collector_config {
    &logger(5,"Entered create_collector_config()");

    # fetch all collector servers
    my @collectors = &getItems("nagios-collector",1);
    my $col_count = 0;

    foreach my $row (@collectors){

        &logger(3,"Generating config for Nagios-collector '$row->[1]'");

        # store collector name separately
        $NC_macro_values{'NAGIOS_SERVER_NAME'} = $row->[1];

        # create output directory
        $row->[1] =~ s/-|\s/_/g;
        $row->[1] =~ s/Nagios|Icinga/collector/i;
        $collector_path = "$output_path/$row->[1]";
        if(-e $collector_path){rename($collector_path,$collector_path."_".time) or &logger(1,"[ERROR] Could not rename $collector_path")}
        &logger(4,"Creating output directory '$collector_path'");
        mkdir($collector_path,0755) or &logger(1,"Could not create $collector_path");

        # create configuration files for collector server(s)
        &create_collector_specific_files($row->[0]);

        # create nagios.cfg file for each collector server to test generated config
        &create_test_cfg($row->[1]);
        @server_cfg_files = undef;

        $col_count++;

        # increase progress status for web GUI
        &set_progress();
    }

    unless($col_count > 0){&logger(2,"No collector servers defined. Specify at least one.")}
}


########################################################################################
# SUB create_global_config
# Process global config files. These only need to be created once.

sub create_global_config {
    &logger(5,"Entered create_global_config()");
    &logger(3,"Generating global config files");

    # create "global" folder
    if(-e $global_path){rename($global_path,$global_path."_".$timestamp)}
    &logger(4,"Creating output directory '$global_path'");
    mkdir($global_path,0755) or &logger(1,"Could not create $global_path");

    # iterate through all available ConfigClasses in database
    foreach my $class (keys(%class_info)){
        
        # skip collector/monitor specific classes or classes with no 'out_file'
        if($class_info{$class}->{'class_type'} ne "global"){next}

        # warn if certain Nagios specific items were not exported
        unless($class_info{$class}->{'out_file'}){
            if($class eq "contact" || $class eq "contactgroup" || $class eq "checkcommand" || $class eq "misccommand" || $class eq "timeperiod"){
                &logger(2,"No ".$class."s were exported. Make sure 'out_file' is set properly for the correspondig class.");
            }
            next;
        }

        # fetch all object ID's for current class
        my @class_items = &getItems("$class");

        # write an output file for each class in ConfigClasses table
        push(@global_cfg_files, "$global_path/$class_info{$class}->{'out_file'}");
        &write_file("$global_path/$class_info{$class}->{'out_file'}","$class","$class_info{$class}->{'nagios_object'}",\@class_items);

        # also generate a .htpasswd file, if there are contacts with a password attr
        if($class eq "contact"){
            &write_htpasswd_file("$global_path/nagios.htpasswd",\@class_items);
        }
    }

    # increase progress status for web GUI
    &set_progress();
}

########################################################################################
# SUB create_monitor_specific_files
# Fetch all monitor-specific data and write configuration files for monitor server(s)

sub create_monitor_specific_files {
    &logger(5,"Entered create_monitor_specific_files()");

    my $sql = undef;
    my @monitor_host_tpl;
    my @monitor_srv_tpl_params;

    # fetch all host ID's that have a collector assigned (hosts which are monitored)
    $sql = "SELECT id_item AS item_id
                FROM ConfigItems,ConfigClasses
                WHERE id_class=fk_id_class
                    AND config_class = 'host'
                    HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                                WHERE fk_item_linked2=id_item
                                    AND id_class=fk_id_class
                                    AND config_class = 'nagios-collector'
                                    AND fk_id_item=item_id) <> ''";

    my @hosts1 = &queryExecRead($sql, "Fetching all host ID's that have a collector assigned", "all");

    # fetch monitor-specific host-templates, these will be added to each host
    $sql = "SELECT attr_name,attr_value,write_to_conf
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'host_template' 
                        AND ItemLinks.fk_id_item=$_[0] 
                        ORDER BY cust_order,ordering";

    my @host_tpl = &queryExecRead($sql, "Fetching monitor-specific host-templates", "all");
    foreach (@host_tpl){push(@monitor_host_tpl,$_)}

    # fetch all host ID's that have a collector assigned (hosts which are monitored) and if a host is a collector itself
    $sql = "SELECT id_item AS item_id,
               (SELECT attr_value FROM ConfigValues,ConfigAttrs
                    WHERE id_attr=fk_id_attr
                    AND attr_name='host_is_collector'
                    AND fk_id_item=item_id) AS host_is_collector
               FROM ConfigItems,ConfigClasses
               WHERE id_class=fk_id_class
                   AND config_class = 'host'
                   HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                      WHERE fk_item_linked2=id_item
                      AND id_class=fk_id_class
                      AND config_class = 'nagios-collector'
                      AND fk_id_item=item_id) <> ''";

    my @hosts2 = &queryExecRead($sql, "Fetching all host ID's that have a collector assigned and if a host is a collector itself", "all");

    # fetch special monitor-specific options
    $sql = "SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr
               AND naming_attr='no'
               AND (attr_name='collector_check_freshness'
                           OR attr_name='collector_freshness_threshold')
               AND fk_id_item=$_[0]
               ORDER BY ordering";

    my @srv_attrs1 = &queryExecRead($sql, "Fetching special monitor-specific options for services", "all");
    foreach (@srv_attrs1){push(@monitor_srv_tpl_params,$_)}

    # fetch monitor-specific service-templates, these will be added to each service
    $sql = "SELECT attr_name,attr_value,write_to_conf
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'service_template' 
                        AND ItemLinks.fk_id_item=$_[0] 
                        ORDER BY cust_order,ordering";

    my @srv_tpl = &queryExecRead($sql, "Fetching monitor-specific service-templates", "all");
    foreach (@srv_tpl){push(@monitor_srv_tpl_params,$_)}

    # fetch 'stale_service_command' attr for nagios-monitor
    $sql = "SELECT attr_name,attr_value,write_to_conf
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'stale_service_command' 
                        AND ItemLinks.fk_id_item=$_[0] 
                        LIMIT 1";

    # expect exactly one row to be returned
    push(@monitor_srv_tpl_params,&queryExecRead($sql, "Fetching 'stale_service_command' attr", "all"));

    # fetch all service ID's of hosts that have a collector assigned and which are not disabled, also pass if a host is a collector itself
    my @services = undef;
    foreach my $host (@hosts2){
        $sql = "SELECT ItemLinks.fk_id_item AS item_id,'','$host->[0]','$host->[1]' FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
                    WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND fk_id_class=id_class
                        AND config_class = 'service'
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND ItemLinks.fk_item_linked2='$host->[0]'
                        HAVING ((SELECT attr_value FROM ConfigValues, ConfigAttrs 
                                WHERE id_attr=fk_id_attr AND attr_name='service_enabled' AND fk_id_item=item_id) <> 'no'
                                OR (SELECT attr_value FROM ConfigValues, ConfigAttrs
                                WHERE id_attr=fk_id_attr AND attr_name='service_enabled' AND fk_id_item=item_id) IS NULL)
                        ORDER BY attr_value";

        my @queryref = &queryExecRead($sql, "Fetching service ID's for host '$host->[0]', assigning collector to service", "all");
        foreach my $service (@queryref){push(@services, $service)}
    }

    # remove first array element if it's empty
    unless($services[0]){shift(@services)}

    # write monitor-specific classes to cfg file
    foreach my $config_class (keys(%class_info)){
        if($class_info{$config_class}->{'class_type'} eq "monitor" || $class_info{$config_class}->{'class_type'} eq "collector"){

            # skip classes with no 'out_file'
            unless($class_info{$config_class}->{'out_file'}){
                if($config_class eq 'host' || $config_class eq 'hostgroup' || $config_class eq 'service' || $config_class eq 'servicegroup'){
                    &logger(2,"No ".$config_class."s were exported. Make sure 'out_file' is set properly for the correspondig class.");
                }
                next;
            }

            push(@server_cfg_files, "$monitor_path/$class_info{$config_class}->{'out_file'}");

            if($config_class eq 'host'){
                # write hosts to cfg file
                &write_file("$monitor_path/$class_info{'host'}->{'out_file'}","host","$class_info{'host'}->{'nagios_object'}",
                    \@hosts1,\@monitor_host_tpl);
            }
            elsif($config_class eq 'service'){
                # write services to cfg file
                &write_file("$monitor_path/$class_info{'service'}->{'out_file'}","service","$class_info{'service'}->{'nagios_object'}",
                    \@services,\@monitor_srv_tpl_params);
            }
            else{
                # write remaining classes to cfg file (attach @monitor_srv_tpl_params array, which is needed for advanced-services)
                my @class_items = &getItems("$config_class");
                &write_file("$monitor_path/$class_info{$config_class}->{'out_file'}","$config_class","$class_info{$config_class}->{'nagios_object'}",
                    \@class_items,\@monitor_srv_tpl_params);
            }
        }
    }
}


########################################################################################
# SUB create_collector_specific_files
# Fetch all collector-specific data and write configuration files for collector server(s)

sub create_collector_specific_files {
    &logger(5,"Entered create_collector_specific_files()");

    my $sql = undef;
    my @collector_host_tpl;
    my @collector_srv_tpl;

    # fetch all host ID's that are assigned to the collector (hosts which are monitored)
    $sql = "SELECT id_item AS item_id FROM ConfigItems,ConfigClasses
                WHERE id_class=fk_id_class
                AND config_class = 'host'
                HAVING (SELECT fk_item_linked2 FROM ConfigItems,ItemLinks,ConfigClasses
                    WHERE fk_item_linked2=id_item
                    AND id_class=fk_id_class
                    AND config_class = 'nagios-collector'
                    AND fk_id_item=item_id) = $_[0]";

    my @hosts = &queryExecRead($sql, "Fetching all host ID's that are assigned to the current collector", "all");

    # fetch collector-specific host-templates, these will be added to each host
    $sql = "SELECT attr_name,attr_value,write_to_conf
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'host_template' 
                        AND ItemLinks.fk_id_item=$_[0] 
                        ORDER BY cust_order,ordering";

    my @host_tpl = &queryExecRead($sql, "Fetching collector-specific host-templates", "all");
    foreach (@host_tpl){push(@collector_host_tpl,$_)}

    # fetch collector-specific service-templates, these will be added to each service
    $sql = "SELECT attr_name,attr_value,write_to_conf
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'service_template' 
                        AND ItemLinks.fk_id_item=$_[0] 
                        ORDER BY cust_order,ordering";

    my @srv_tpl = &queryExecRead($sql, "Fetching collector-specific service-templates", "all");
    foreach (@srv_tpl){push(@collector_srv_tpl,$_)}

    # fetch all service ID's of hosts assigned to the collector and which are not disabled
    my @services = undef;
    foreach my $host (@hosts){
        $sql = "SELECT ItemLinks.fk_id_item AS item_id,'','$host->[0]' FROM ConfigValues,ItemLinks,ConfigAttrs,ConfigClasses
                    WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND fk_id_class=id_class
                        AND config_class = 'service'
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND ItemLinks.fk_item_linked2='$host->[0]'
                        HAVING ((SELECT attr_value FROM ConfigValues, ConfigAttrs 
                                WHERE id_attr=fk_id_attr AND attr_name='service_enabled' AND fk_id_item=item_id) <> 'no'
                                OR (SELECT attr_value FROM ConfigValues, ConfigAttrs
                                WHERE id_attr=fk_id_attr AND attr_name='service_enabled' AND fk_id_item=item_id) IS NULL)
                        ORDER BY attr_value";
        
        my @queryref = &queryExecRead($sql, "Fetching service ID's for host '$host->[0]'", "all");
        foreach my $service (@queryref){push(@services, $service)}
    }

    # remove first array element if it's empty
    unless($services[0]){shift(@services)}

    # write collector-specific classes to cfg file
    foreach my $config_class (keys(%class_info)){
        if(($class_info{$config_class}->{'class_type'} eq "collector" && $mon_count > 0) 
            || (($class_info{$config_class}->{'class_type'} eq "monitor" || $class_info{$config_class}->{'class_type'} eq "collector") && $mon_count eq "0")){

            # skip classes with no 'out_file'
            unless($class_info{$config_class}->{'out_file'}){
                if($config_class eq 'host' || $config_class eq 'hostgroup' || $config_class eq 'service' || $config_class eq 'servicegroup'){
                    &logger(2,"No ".$config_class."s were exported. Make sure 'out_file' is set properly for the correspondig class.");
                }
                next;
            }

            push(@server_cfg_files, "$collector_path/$class_info{$config_class}->{'out_file'}");

            if($config_class eq 'host'){

                # add the collector's ID to the list of host ID's
                foreach my $entry (@hosts){
                    $entry->[1] = $_[0];
                }

                # write hosts to cfg file
                &write_file("$collector_path/$class_info{'host'}->{'out_file'}","host","$class_info{'host'}->{'nagios_object'}",
                    \@hosts,\@collector_host_tpl);
            }
            elsif($config_class eq 'service'){

                # add the collector's ID to the list of service ID's
                foreach my $entry (@services){
                    $entry->[1] = $_[0];
                }

                # write services to cfg file
                &write_file("$collector_path/$class_info{'service'}->{'out_file'}","service","$class_info{'service'}->{'nagios_object'}",
                    \@services,\@collector_srv_tpl);
            }
            else{

                my @class_items = &getItems("$config_class");

                # add the collector's ID to the list of item ID's
                foreach my $entry (@class_items){
                    $entry->[1] = $_[0];
                }

                # write remaining classes to cfg file (attach @collector_srv_tpl array, which is needed for advanced-services)
                &write_file("$collector_path/$class_info{$config_class}->{'out_file'}","$config_class","$class_info{$config_class}->{'nagios_object'}",
                    \@class_items,\@collector_srv_tpl);
            }
        }
    }
}


########################################################################################
# SUB write_file
# Write actual config files.

sub write_file {
    &logger(5,"Entered write_file()");

# define output format
format FILE =
                @<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<  @*
$fattr,$fval
.
    # read params passed
    my $path   = $_[0];
    my $class  = $_[1];
    my $item   = $_[2];
    my $list   = $_[3];
    my $params = $_[4];
    my @items  = @$list;

    # use class name if no Nagios object definition was specified
    unless($item){$item=$class};

    # read and parse additional monitor/collector params
    my @mon_col_params;
    my $stale_service_command = undef;
    if($params){
        @mon_col_params = @$params;

        # determine if a 'stale_service_command' attr is set for the services of the current nagios-monitor
        if($class eq "service" || $class eq "advanced-service"){
            foreach my $extattr (@mon_col_params){
                if($extattr->[0] eq "stale_service_command"){
                    $stale_service_command = $extattr->[1];
                }
            }
        }
    }

    # vars needed for several checks
    my $has_proc_sshd = 0;
    my @curr_host = undef;
    my @prev_host = undef;
    my %host_srv_cmd;       # used for default service dependencies within checkcommands
    my $default_srv_deps_exist = 0;

    if($files_written{$path} && $files_written{$path} ne ""){open(FILE,">>$path") or &logger(1,"Could not open $path for writing (appending)")}
    else{open(FILE,">$path") or &logger(1,"Could not open $path for writing")}
    &logger(4,"Writing file '$path'");

    foreach my $id_item (@items){

        # INFO: "$id_item" is a reference to an array with the following structure:

        # in "global" context:
        # [0] id_item
        # [1] <unused>
        # [2] <unused>
        # [3] <unused>

        # in "collector" server context:
        # [0] id_item
        # [1] the collector's id
        # [2] the host's id (only for services)
        # [3] <unused>

        # in "monitor" server context:
        # [0] id_item
        # [1] <unused>
        # [2] the service's host id (only for services)
        # [3] if a service belongs to a host that is a collector itself ("yes"/"no", only for services)

        ########################
        # CASE1: process hosts #
        ########################
        if($class eq "host"){

            my (@host_templates1, @host_templates2, @host_templates3);
            print FILE "define $item {\n";

            # fetch all ordinary attributes (text, select etc.)
            my @item_attrs = &getItemData($id_item->[0]);
            my $hostname = undef;
            foreach my $attr (@item_attrs){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
                if($attr->[0] eq "host_name"){$hostname=$attr->[1]}
            }

            # fetch OS information for hosts (icons etc.)
            my @item_attrs = &fetch_host_os_info($id_item->[0]);
            foreach my $attr (@item_attrs){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # fetch host-alive check and templates inherited from timeperiod
            my @aux_data = &fetch_inherited_host_templates($id_item->[0]);
            foreach my $attr (@aux_data){

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "host_template"){push(@host_templates2, $attr->[1]);next}

                if($attr->[0] ne "" && $attr->[1] ne ""){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # process monitor-/collector-specific templates
            foreach my $attr (@mon_col_params){

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "host_template"){push(@host_templates3, $attr->[1]);next}
            }

            # fetch all linked items (assign_one, assign_many etc.)
            my @item_links = &getItemsLinked($id_item->[0]);
            @item_links = &makeValuesDistinct(@item_links);

            my $has_contactgroup = 0;
            my $has_oncall_group = 0;
            foreach my $attr (@item_links){

                # check for all parent hosts if they exist on the current collector / if they are monitored
                if($attr->[0] eq "parents"){
                    my @parents = split(/,/, $attr->[1]);
                    my $checked_parents = undef;

                    foreach my $host (@parents){
                        unless($host){next}
                        # the id_item contained within $attr->[3] is unusable, because makeValuesDistinct() breaks it for assign_many links
                        # so, let's fetch each host ID individually based on the hostname
                        my $host_id = getItemId($host, "host");

                        # check if parent host exists on the current collector
                        if(&checkItemExistsOnServer($host_id, $id_item->[1]) eq "true"){
                            $checked_parents = $checked_parents.",".$host;
                        }
                    }

                    # only write parents to config, which exist on the current collector
                    $checked_parents =~ s/^,//;
                    if($checked_parents ne ""){$attr->[1]=$checked_parents}
                    else{next} # don't write "parents" attribute, if empty
                }

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "use"){push(@host_templates1, $attr->[1]);next}

                # check for contactgroups
                if($attr->[0] eq "contact_groups"){
                    $has_contactgroup = 1;

                    # check for oncall groups
                    my @cg_parts = split(/,/, $attr->[1]);
                    foreach my $cgroup (@cg_parts){
                        foreach (@oncall_groups){
                            if($cgroup eq $_){$has_oncall_group=1}
                        }
                    }

                    # add superadmin groups to all hosts by default
        		    if(defined($superadmins[0])){
                    	foreach(@superadmins){
                            my $user_wo_plus = $_;
                            $user_wo_plus =~ s/\+//g;
                            if($attr->[1] =~ /\b$user_wo_plus\b/){$attr->[1] =~ s/$user_wo_plus\,{0,1}//}
                    	}
                        if($attr->[1]){$attr->[1] = join(",", @superadmins).",".$attr->[1]}
                        else{$attr->[1] = join(",", @superadmins)}
		            }
                }

                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){$fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # process host-templates
            # templates will be applied to hosts in the following order: 

            # 1. host specific template(s)
            # 2. notification_period template(s)
            # 3. check_period template(s)
            # 4. collector/monitor template(s)

            my @host_templates;
            push(@host_templates, @host_templates1);
            push(@host_templates, @host_templates2);
            push(@host_templates, @host_templates3);

            # make sure templates are only applied once, keeping original order (first occurence wins)
            tie my %tpl_hash, 'Tie::IxHash';
            foreach my $tpl (@host_templates){

                if($tpl_hash{$tpl}){next}

                if($tpl =~ /,/){
                    my @temp = split(/,/,$tpl);
                    foreach(@temp){$tpl_hash{$_}=$_}
                }else{
                    $tpl_hash{$tpl} = $tpl;
                }
            }

            $fattr = "use";
            $fval = join(",",keys(%tpl_hash));
            if($fval){write FILE}

            # warn if no oncall groups were assigned to host
            if($has_oncall_group == 0 && $oncall_groups[0] ne ""){
                &logger(2,"No oncall group is assigned to host $hostname");
            }

            # add superadmin groups, even if no contactgroups were specified at all
            if($has_contactgroup == 0 && defined($superadmins[0])){
                $fattr = "contact_groups";
                $fval = join(",",@superadmins);
                write FILE;
            }
            print FILE "}\n\n";

        ###########################
        # CASE2: process services #
        ###########################
        }elsif($class eq "service"){

            my $is_proc_sshd = 0;
            my $is_trap_service = 0;
            my (@service_templates1, @service_templates2, @service_templates3);

            # fetch hostname of the host which the service belongs to
            my $hostname = &getItemName($id_item->[2]);

            # fetch service_description and all ordinary attributes (text, select etc.)
            my @item_attrs = &getItemData($id_item->[0]);
            my $srvname = undef;
            foreach my $attr (@item_attrs){
                if($attr->[0] eq "service_description"){$srvname=$attr->[1]}
                if($attr->[0] eq "service_description" && $attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){
                    if($attr->[1] =~ /TRAP$/ && $path =~ /\Q$collector_path\E/ && $mon_count > 0){
                        # don't write "TRAP" services to collector config, if a monitor server is present
                        $is_trap_service = 1;
                    }else{
                        # write all ordinary attributes to config (text, select etc.)
                        print FILE "define $item {\n";
                        $fattr=$attr->[0];
                        $fval=$attr->[1];
                        write FILE;
                    }
                }
            }

            if($is_trap_service == 1){
                &logger(4,"Removing $class '$id_item->[0]' from collector config because the $class seems to be a TRAP service");
                next;
            }

            # fetch all linked items (assign_one, assign_many etc.)
            my @item_links = &getItemsLinked($id_item->[0]);
            @item_links = &makeValuesDistinct(@item_links);

            my $has_contactgroup = 0;
            my $has_oncall_group = 0;
            foreach my $attr (@item_links){

                if($attr->[0] eq "check_command"){

                    # proccess default service dependencies
                    if($class eq "service" &&
                       (($class_info{'service-dependency'}->{'class_type'} eq "monitor" 
                          && (($monitor_path && $path =~ /\Q$monitor_path\E/) || $mon_count eq "0"))
                       ||($class_info{'service-dependency'}->{'class_type'} eq "collector" 
                          && (($collector_path && $path =~ /\Q$collector_path\E/) || ($monitor_path && $path =~ /\Q$monitor_path\E/))))){

                        # check for default_service_dependency attributes on a command level
                        my @srv_cmd_deps;
                        my %def_srv_deps_params;
                        my $def_srv_deps = $checkcommand_info{$attr->[1]}->{"default_service_dependency"};
                        if($def_srv_deps ne ""){
                            $default_srv_deps_exist = 1;    
                            my @parts = split(/,/,$def_srv_deps);
                            foreach(@parts){
                                unless($_){next};
                                push(@srv_cmd_deps,$_);
                            }
                            # fetch additional parameters for default service dependency
                            $def_srv_deps_params{"execution_failure_criteria"} = 
                                $checkcommand_info{$attr->[1]}->{"dependency_execution_failure_criteria"};
                            $def_srv_deps_params{"notification_failure_criteria"} = 
                                $checkcommand_info{$attr->[1]}->{"dependency_notification_failure_criteria"};
                        }

                        # store hosts, services, their checkcommand and the command's default service dependencies to a hash structure
                        my @srv_info;
                        push(@srv_info, $srvname);
                        push(@srv_info, \@srv_cmd_deps);
                        push(@srv_info, \%def_srv_deps_params);

                        if($host_srv_cmd{$hostname}){
                            if($host_srv_cmd{$hostname}->{$attr->[1]}){
                                push (@{$host_srv_cmd{$hostname}->{$attr->[1]}}, \@srv_info);
                            }else{
                                my @cmd_srv;
                                push(@cmd_srv, \@srv_info);
                                $host_srv_cmd{$hostname}->{$attr->[1]} = \@cmd_srv;
                            }
                        }else{
                            my %host_cmd;
                            my @cmd_srv;
                            push(@cmd_srv, \@srv_info);
                            $host_cmd{$attr->[1]} = \@cmd_srv;
                            $host_srv_cmd{$hostname} = \%host_cmd;
                        }
                    } 

                    # execute this at the very end of "check_command" processing!
                    # add a dummy check_command to all services of a monitor server, if stale_service_command attr is set 
                    if($monitor_path && $path =~ /\Q$monitor_path\E/ && $stale_service_command){
                        $attr->[1] = $stale_service_command;
                    }
                    else{
                        # add check_params to check_command attr
                        my $cmd_params = &fetch_cmd_params($id_item->[0]);
                        $cmd_params = &replaceMacros($cmd_params);
                        $attr->[1] = $attr->[1].$cmd_params;
                    }
                }

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "use"){push(@service_templates1, $attr->[1]);next}

                # check for contactgroups
                if($attr->[0] eq "contact_groups"){
                    $has_contactgroup = 1;

                    # check for oncall groups
                    my @cg_parts = split(/,/, $attr->[1]);
                    foreach my $cgroup (@cg_parts){
                        foreach (@oncall_groups){
                            if($cgroup eq $_){$has_oncall_group=1}
                        }
                    }

                    # add superadmin groups to all services by default
        		    if(defined($superadmins[0])){
                    	foreach(@superadmins){
                            my $user_wo_plus = $_;
                            $user_wo_plus =~ s/\+//g;
                            if($attr->[1] =~ /\b$user_wo_plus\b/){$attr->[1] =~ s/$user_wo_plus\,{0,1}//}
                    	}
                        if($attr->[1]){$attr->[1] = join(",", @superadmins).",".$attr->[1]}
                        else{$attr->[1] = join(",", @superadmins)}
		            }
                }

                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}

                # TO CHANGE
                # read and store the host we're currently working on
                $curr_host[0] = $hostname;
                $curr_host[1] = $id_item->[3];
                if($prev_host[0] eq ""){$prev_host[0] = $hostname;$prev_host[1] = $id_item->[3]}

            } # end of loop through a service's links


            # TO CHANGE
            # check if the host whose services we're working on has changed
            if($curr_host[0] ne $prev_host[0]){

                # if previous host was a collector, warn if no sshd service was found
                if($prev_host[1] eq "yes" && $monitor_path && $path =~ /\Q$monitor_path\E/ && $has_proc_sshd != 1){
                    &logger(2,"No SSH check was found for $prev_host[0]");
                }
                $has_proc_sshd = 0;
            }

            foreach my $attr (@item_attrs){
                if($attr->[0] eq "service_description" && $attr->[1] =~ /ssh/i){$is_proc_sshd=1;$has_proc_sshd=1}
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no" && $attr->[0] ne "service_description"){
                    $fattr=$attr->[0];
                    $fval=$attr->[1];
                    write FILE;
                }
            }

            # fetch templates inherited from checkcommand / timeperiod
            my @aux_data = &fetch_inherited_service_templates($id_item->[0]);
            foreach my $attr (@aux_data){

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "service_template"){push(@service_templates2, $attr->[1]);next}

                if($attr->[0] ne "" && $attr->[1] ne ""){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            # TO CHANGE
            # determine if this service belongs to a collector host ("host is collector" flag is set to "yes")
            my $checkfreshness = undef;
            my $freshthresh = undef;
            if($id_item->[3] eq "yes"){
                # look for collector-specific check_freshness and freshness_threshhold
                foreach my $extattr (@mon_col_params){
                    if($extattr->[0] eq "collector_check_freshness"){$checkfreshness = $extattr->[1]}
                    if($extattr->[0] eq "collector_freshness_threshold"){$freshthresh = $extattr->[1]}
                }
            }

            # process monitor-/collector-specific templates
            foreach my $attr (@mon_col_params){

                # don't write templates to config yet, store them separately to be processed later on
                if($attr->[0] eq "service_template"){push(@service_templates3, $attr->[1]);next}
            }

            # TO CHANGE
            # apply the collector_check_freshness value to .*ssh.* service(s) on "host is collector" flagged hosts (only in monitor config)
	        if($id_item->[3] eq "yes" && $is_proc_sshd == 1 && $monitor_path && $path =~ /\Q$monitor_path\E/ && $checkfreshness){
		        $fattr = "check_freshness";
    		    $fval  = $checkfreshness;
	    	    write FILE;
    	    }
            # apply the collector_freshness_threshhold value to .*ssh.* service(s) on "host is collector" flagged hosts (only in monitor config)
	        if($id_item->[3] eq "yes" && $is_proc_sshd == 1 && $monitor_path && $path =~ /\Q$monitor_path\E/ && $freshthresh){
		        $fattr = "freshness_threshold";
    		    $fval  = $freshthresh;
	    	    write FILE;
	        }

            # process service-templates
            # templates will be applied to services in the following order: 

            # 1. template(s) directly linked to advanced-service
            # 2. default service template(s) linked to checkcommand
            # 3. notification_period template(s)
            # 4. check_period template(s)
            # 5. collector/monitor template(s)

            my @service_templates;
            push(@service_templates, @service_templates1);
            push(@service_templates, @service_templates2);
            push(@service_templates, @service_templates3);

            # make sure templates are only applied once, keeping original order (first occurence wins)
            tie my %tpl_hash, 'Tie::IxHash';
            foreach my $tpl (@service_templates){

                if($tpl_hash{$tpl}){next}

                if($tpl =~ /,/){
                    my @temp = split(/,/,$tpl);
                    foreach(@temp){$tpl_hash{$_}=$_}
                }else{
                    $tpl_hash{$tpl} = $tpl;
                }
            }

            $fattr = "use";
            $fval = join(",",keys(%tpl_hash));
            if($fval){write FILE}

            # warn if no oncall groups were assigned to service
            if($has_oncall_group == 0 && $oncall_groups[0] ne ""){
                &logger(2,"No oncall group is assigned to service \"$srvname\" on host $hostname");
            }

            # add superadmin groups, even if no contactgroups were specified at all
            if($has_contactgroup == 0 && defined($superadmins[0])){
                $fattr = "contact_groups";
                $fval = join(",",@superadmins);
                write FILE;
            }

            @prev_host = @curr_host;
            print FILE "}\n\n";

        ##################################
        # CASE3: process all other items #
        ##################################
        # this section processes all items that are neither "hosts" nor "services"
        }else{

            ##### (0) special processing for advanced-services
            my $has_contactgroup = 0;
            my $has_oncall_group = 0;
            my (@service_templates1, @service_templates2, @service_templates3);

            if($class eq "advanced-service"){
                # don't write adv. services that contain the string "TRAP" in "advanced service name" to collector config, if a monitor server is present
                my $srvname = &getItemName($id_item->[0]);
                if($srvname =~ /trap/i && defined($id_item->[1]) && $mon_count > 0){
                    &logger(4,"Removing $class '$id_item->[0]' from collector config because the $class seems to be a TRAP service");
		    next;
		}
            }

            ##### (1) fetch all linked items (assign_one, assign_many etc.)
            my @item_links = &getItemsLinked($id_item->[0]);
            my $has_empty_linking_attrs = 0;

            ##### (1A) collector/monitor-specific processing for linked items (assign_* attrs)
            if(defined($id_item->[1]) || ($monitor_path && $path =~ /\Q$monitor_path\E/)){

                # look for linked items, that are server-specific (hosts, services, hostgroups, servicegroups),
                # check if they exist on the current Nagios server, remove them if not;

                # this routine makes sense both for collectors and monitors, since hosts/services can also be disabled (not monitored) on a monitor,
                # and must therefore be removed from any items they might be linked to;

                foreach my $attr (@item_links){

                    # in "collector" context, $id_item->[1] contains the collector's ID
                    # in "monitor"   context, $id_item->[1] is undefined
                    unless(&checkItemExistsOnServer($attr->[3],$id_item->[1]) eq "true"){

                        # if the linked item doesn't exist on current server, empty the linking attribute 
                        # (careful, there may be multiple instances of the same linking attribute per item)
                        # the empty linking attributes will be processed later (after class_dependent_processing() & makeValuesDistinct() )
                        if($id_item->[1]){
                            &logger(4,"Removing item '$attr->[3]' from $class '$id_item->[0]' because the item doesn't exist on collector '$id_item->[1]'");
                        }else{
                            &logger(4,"Removing item '$attr->[3]' from $class '$id_item->[0]' because the item is not monitored");
                        }
                        undef $attr->[1];
                    }
                }

                # apply certain class specific exceptions
                # caution: this function must always be called before makeValuesDistinct() AND after checking for collector specific items
                @item_links = &class_dependent_processing($class, @item_links);

                # consolidate multi-line attributes into one line
                @item_links = &makeValuesDistinct(@item_links);

                # after a first cleanup round, look for linking attributes that are now empty; hostgroups and servicegroups are handled separately
                # IMPORTANT: this logic only concerns items with linking attrs that were cleared above, not items that weren't linked to start with!
                my $group_has_members = 0;
                my $has_members = 0;
                my $has_hg_members = 0;
                my $has_sg_members = 0;
                my $assigned_to_host = 0;
                my $assigned_to_hostgroup = 0;

                foreach my $attr (@item_links){

                    # if we find any empty linking attributes (there may be multiple empty attributes per item)
                    if(defined($attr->[0]) && $attr->[1] eq "" && $class ne "hostgroup" && $class ne "servicegroup"){

                        # remove the whole item from the configuration, unless it's an advanced-service (special rule applies there)
                        unless($class eq "advanced-service" && ($attr->[0] eq "host_name" || $attr->[0] eq "hostgroup_name" || $attr->[0] eq "servicegroups")){
                            &logger(4,"Removing $class '$id_item->[0]' from config because the attribute '$attr->[0]' was empty");
                            $has_empty_linking_attrs = 1;
                        }

                        # additional rules might be necessary for dependencies & escalations
                    }

                    # handle hostgroups and servicegroups separately
                    if($class eq "hostgroup" || $class eq "servicegroup"){
                        if($attr->[0] eq "members" && $attr->[1] ne ""){$has_members=1}
                        if($attr->[0] eq "hostgroup_members" && $attr->[1] ne ""){$has_hg_members=1}
                        if($attr->[0] eq "servicegroup_members" && $attr->[1] ne ""){$has_sg_members=1}
                    }

                    # special processing for advanced-services
                    if($class eq "advanced-service"){

                        if($attr->[0] eq "check_command"){
                            # add a dummy check_command to all services of a monitor server, if stale_service_command attr is set 
                            if($monitor_path && $path =~ /\Q$monitor_path\E/ && $stale_service_command){
                                $attr->[1] = $stale_service_command;
                            }
                            else{
                                # add check_params to check_command attr
                                my $cmd_params = &fetch_cmd_params($id_item->[0]);
                                $cmd_params = &replaceMacros($cmd_params);
                                $attr->[1] = $attr->[1].$cmd_params;
                            }
                        }

                        # check for contactgroups
                        if($attr->[0] eq "contact_groups"){
                            $has_contactgroup = 1;

                            # check for oncall groups
                            my @cg_parts = split(/,/, $attr->[1]);
                            foreach my $cgroup (@cg_parts){
                                foreach (@oncall_groups){
                                    if($cgroup eq $_){$has_oncall_group=1}
                                }
                            }

                            # add superadmin groups to all services by default
                		    if(defined($superadmins[0])){
                            	foreach(@superadmins){
                                    my $user_wo_plus = $_;
                                    $user_wo_plus =~ s/\+//g;
                                    if($attr->[1] =~ /\b$user_wo_plus\b/){$attr->[1] =~ s/$user_wo_plus\,{0,1}//}
                            	}
                                if($attr->[1]){$attr->[1] = join(",", @superadmins).",".$attr->[1]}
                                else{$attr->[1] = join(",", @superadmins)}
		                    }
                        }
                        
                        # verify that the advanced-service is well linked to at least one host / hostgroup
                        if($attr->[0] eq "host_name" && $attr->[1] ne ""){$assigned_to_host=1}
                        if($attr->[0] eq "hostgroup_name" && $attr->[1] ne ""){$assigned_to_hostgroup=1}
                        
                    }
                }

                # special processing for hostgroups and servicegroups: remove groups if both linking attributes are empty, 
                # regarless if the attributes were emptied above or if the groups were empty to start with
                if($class eq "hostgroup"){
                    if($has_members != 1 && $has_hg_members != 1){
                        &logger(4,"Removing $class '$id_item->[0]' from config because the attributes 'members' and 'hostgroup_members' were empty");
                        $has_empty_linking_attrs = 1;
                    }
                }
                if($class eq "servicegroup"){

                    # check if any advanced-services are assigned to the servicegroup
                    my $is_used_by_as = 0;
                    my @items_using = &getChildItemsLinked($id_item->[0]);
                    foreach my $child (@items_using){
                        unless($child->[0]){next}
                        if($child->[2] eq "advanced-service"){$is_used_by_as=1}
                    }

                    if($has_members != 1 && $has_sg_members != 1 && $is_used_by_as != 1){
                        &logger(4,"Removing $class '$id_item->[0]' from config because the attributes 'members' and 'servicegroup_members' were empty and no advanced-service was linked");
                        $has_empty_linking_attrs = 1;
                    }
                }
                
                # special processing for advanced-service: remove it if not assigned to any host / hostgroup
                if($class eq "advanced-service"){
                    if($assigned_to_host != 1 && $assigned_to_hostgroup != 1){
                        &logger(4,"Removing $class '$id_item->[0]' from config because the attributes 'host_name' and 'hostgroup_name' were empty");
                        $has_empty_linking_attrs = 1;
                    }
                }

            }else{
            ##### (1B) non-collector/monitor-specific processing (i.e. "global" processing of assign_* attrs)

                # apply certain class specific exceptions
                # caution: this function must always be called before makeValuesDistinct() 
                @item_links = &class_dependent_processing($class, @item_links);

                # consolidate multi-line attributes into one line
                @item_links = &makeValuesDistinct(@item_links);
            }

            ##### (1C) skip writing items to config, which are linked to objects that don't exist on the current collector (as evaluated above)
            if($has_empty_linking_attrs == 1){next}

            print FILE "define $item {\n";

            ##### (2) fetch all ordinary attributes and write them to config (text, select etc.)
            my @item_attrs = &getItemData($id_item->[0]);
            foreach my $attr (@item_attrs){
                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            ##### (1D) write linked items (processed above) to config
            foreach my $attr (@item_links){

                if($class eq "advanced-service"){
                    # don't write templates to config yet, store them separately to be processed later on
                    if($attr->[0] eq "use"){push(@service_templates1, $attr->[1]);next}
                }

                if($attr->[0] ne "" && $attr->[1] ne "" && $attr->[2] ne "no"){ $fattr=$attr->[0];$fval=$attr->[1];write FILE}
            }

            ##### (3) special processing for advanced-services
            if($class eq "advanced-service"){

                # fetch templates inherited from checkcommand / timeperiod
                my @aux_data = &fetch_inherited_service_templates($id_item->[0]);
                foreach my $attr (@aux_data){
                    # don't write templates to config yet, store them separately to be processed later on
                    if($attr->[0] eq "service_template"){push(@service_templates2, $attr->[1]);next}
                }

                # process monitor-/collector-specific templates
                foreach my $attr (@mon_col_params){

                    # don't write templates to config yet, store them separately to be processed later on
                    if($attr->[0] eq "service_template"){push(@service_templates3, $attr->[1]);next}
                }

                # process service-templates
                # templates will be applied to advanced-services in the following order: 

                # 1. template(s) directly linked to advanced-service
                # 2. default service template(s) linked to checkcommand
                # 3. notification_period template(s)
                # 4. check_period template(s)
                # 5. collector/monitor template(s)

                my @service_templates;
                push(@service_templates, @service_templates1);
                push(@service_templates, @service_templates2);
                push(@service_templates, @service_templates3);

                # make sure templates are only applied once, keeping original order (first occurence wins)
                tie my %tpl_hash, 'Tie::IxHash';
                foreach my $tpl (@service_templates){
    
                    if($tpl_hash{$tpl}){next}
    
                    if($tpl =~ /,/){
                        my @temp = split(/,/,$tpl);
                        foreach(@temp){$tpl_hash{$_}=$_}
                    }else{
                        $tpl_hash{$tpl} = $tpl;
                    }
                }

                $fattr = "use";
                $fval = join(",",keys(%tpl_hash));
                if($fval){write FILE}

                # warn if no oncall groups were assigned to service
                if($has_oncall_group == 0 && $oncall_groups[0] ne ""){
                    &logger(2,"No oncall group is assigned to advanced-service \"".&getItemName($id_item->[0])."\"");
                }

                # add superadmin groups, even if no contactgroups were specified at all
                if($has_contactgroup == 0 && defined($superadmins[0])){
                    $fattr = "contact_groups";
                    $fval = join(",",@superadmins);
                    write FILE;
                }
            }

            print FILE "}\n\n";
        }

    }
    close(FILE);
    $files_written{$path} = $path; # remember which files were already written (needed for appending logic)


    #########################################
    # proccess default service dependencies #
    #########################################

    # INFO: default service dependencies don't apply to advanced-services

    # hash structure for hosts, checkcommands, services and possible dependencies:
    # %host_srv_cmd{'hostname'} 
    #               -> %host_cmd{'command'} 
    #                            -> @cmd_srv
    #                               [0] = @srv_info service 1
    #                               [1] = @srv_info service 2
    #                               ... = @srv_info service n
    #                                     [0] = service name
    #                                     [1] = @srv_cmd_deps
    #                                           [0] = dependent upon command 1
    #                                           [1] = dependent upon command 2
    #                                           ... = dependent upon command n
    #                                     [2] = %def_srv_deps_params{'attr name'} -> 'attr value'

    # default service dependencies will only be processed if class_type is set to "monitor" or "collector"
    if($class eq "service" && $default_srv_deps_exist == 1 &&
       (($class_info{'service-dependency'}->{'class_type'} eq "monitor" 
          && (($monitor_path && $path =~ /\Q$monitor_path\E/) || $mon_count eq "0"))
       ||($class_info{'service-dependency'}->{'class_type'} eq "collector" 
          && (($collector_path && $path =~ /\Q$collector_path\E/) || ($monitor_path && $path =~ /\Q$monitor_path\E/))))){

        # create 'auto_service_dependencies.cfg' file
        $path =~ /(.*)\/.+$/;
        my $default_deps_path = $1."/auto_service_dependencies.cfg";

        open(FILE,">$default_deps_path") or &logger(1,"Could not open $default_deps_path for writing");
        &logger(4,"Writing file '$default_deps_path'");

        # check all services on all hosts for checkcommands that have a "default_service_dependency"
        foreach my $host (keys(%host_srv_cmd)){
            foreach my $cmd (keys(%{$host_srv_cmd{$host}})){
                foreach my $srv (@{$host_srv_cmd{$host}->{$cmd}}){

                    if($srv->[1]->[0]){

                        # if a possible dependency was detected, parse all services on the same host 
                        # to determine if there are any services to depend upon
                        foreach my $cmd_dep (@{$srv->[1]}){
                            if($host_srv_cmd{$host}->{$cmd_dep}->[0]){

                                # if services to depend upon were found, write a servicedependency entry to the cfg
                                foreach my $service_depended_upon (@{$host_srv_cmd{$host}->{$cmd_dep}}){

                                    #print "[DEVEL] service '$srv->[0]' on host '$host' is dependent upon "
                                    #    ."service '$service_depended_upon->[0]' on the same host\n";

                                    # write "service_description", "dependent_service_description" and "host_name" attrs
                                    print FILE "define servicedependency {\n";

                                    $fattr = "service_description"; $fval = $service_depended_upon->[0]; 
                                    write FILE;

                                    $fattr = "dependent_service_description"; $fval = $srv->[0]; 
                                    write FILE;

                                    $fattr = "host_name"; $fval = $host; 
                                    write FILE;

                                    # write any other default service dependency params (e.g "notification_failure_criteria" etc)
                                    foreach my $def_srv_deps_param (keys(%{$srv->[2]})){
                                        unless($def_srv_deps_param && %{$srv->[2]}->{$def_srv_deps_param}){next}
                                        $fattr = $def_srv_deps_param;
                                        $fval  = %{$srv->[2]}->{$def_srv_deps_param};
                                        write FILE;
                                    }
                                    print FILE "}\n\n";
                                }
                            }
                        }
                    }
                }
            }
        }

        close(FILE);
        $files_written{$default_deps_path} = $default_deps_path;
        push(@server_cfg_files, "$default_deps_path");
    }
}

########################################################################################
# SUB class_dependent_processing
# Do some processing that is unique to specific classes, like attaching hostnames to service definitions etc.

sub class_dependent_processing {

    my $class = shift;
    my @item_links = @_;

    ####### special processing for servicegroups
    if($class eq "servicegroup"){

        # attach the hostname before each service definition within the "members" attribute 
        foreach my $attr (@item_links){
            if($attr->[0] eq "members" && $attr->[1]){
                my $hostname = &getServiceHostname($attr->[3]);
                unless($hostname){&logger(1,"Failed to get the hostname for service ID '$attr->[3]'")}
                $attr->[1] = $hostname.",".$attr->[1];
            }
        }                
    }

    ###### special processing for service-dependencies
    if($class eq "service-dependency"){

        # add the attributes "host_name" and "dependent_host_name" to the configuration based on 
        # the host that owns the service (these attributes don't physically exist in NConf)
        my (@hosts1, @hosts2);
        foreach my $attr (@item_links){
            if($attr->[0] eq "service_description" && $attr->[1]){
                my $hostname = &getServiceHostname($attr->[3]);
                unless($hostname){&logger(1,"Failed to get the hostname for service ID '$attr->[3]'")}
                push(@hosts1, $hostname);
            }
            if($attr->[0] eq "dependent_service_description" && $attr->[1]){
                my $hostname = &getServiceHostname($attr->[3]);
                unless($hostname){&logger(1,"Failed to get the hostname for service ID '$attr->[3]'")}
                push(@hosts2, $hostname);
            }
        }

        # add additional attributes to @item_links array
        foreach (@hosts1){
            my @temp;
            $temp[0] = "host_name";
            $temp[1] = $_;
            push(@item_links, \@temp);
        }
        foreach (@hosts2){
            my @temp;
            $temp[0] = "dependent_host_name";
            $temp[1] = $_;
            push(@item_links, \@temp);
        }
    }

    return @item_links;
}

########################################################################################
# SUB write_htpasswd_file
# Create a .htpasswd file for Apache webservers based on contact entries.
# Apache requires password encryption in NConf to be set to either "crypt" or "sha_raw".
# This allows users to manage access to the Nagios website based on contact entries in NConf.

sub write_htpasswd_file {
    &logger(5,"Entered write_htpasswd_file()");

    # read params passed
    my $path = $_[0];
    my $list = $_[1];
    my @items = @$list;
    my $usercount = 0;

    open(FILE,">$path") or &logger(1,"Could not open $path for writing");
    &logger(4,"Writing file '$path'");

    foreach my $id_item (@items){

        # fetch all ordinary attributes
        my @item_attrs = &getItemData($id_item->[0]);

        my $username = undef;
        my $userpass = undef;
        my $userperm = undef;

        foreach my $attr (@item_attrs){
            if($attr->[0] eq "contact_name" && $attr->[1] ne ""){ $username=$attr->[1] }
            if($attr->[0] eq "user_password" && $attr->[1] ne ""){ $userpass=$attr->[1] }
            if($attr->[0] eq "nagios_access"){ $userperm=$attr->[1] }
        }

        # if password contains "{SHA_RAW}" string, rename to "{SHA}" and write to htpasswd file
        if($userpass =~ /\{SHA_RAW\}/i){
            $userpass =~s/\{.+\}/{SHA}/;
        }else{
        # assume all other passwords are CRYPT; remove the "{CRYPT}" part because Apache doesn't need it
            $userpass =~s/\{.+\}//;
        }
        
        if($username && $userpass && $userperm !~ /disabled/i){
      	    print FILE "$username:$userpass\n";
      	    $usercount++;
      	}

    }
    close(FILE);

    # make sure .htpasswd file is only created if users with a password attr exist
    if($usercount==0){unlink $path}
}

########################################################################################
# SUB create_test_cfg
# Create a nagios.cfg file for each collector/monitor to run tests on the generated config

sub create_test_cfg {
    &logger(5,"Entered create_test_cfg()");

    # read params passed
    my $server = $_[0];
    my $testfile = "$test_path/$server.cfg";

    unless(-e $test_path){
        &logger(4,"Creating output directory '$test_path'");
        mkdir($test_path,0775) or &logger(1,"Could not create $test_path");
    }

	open(FILE,">$testfile") or &logger(1,"Could not open $testfile for writing");
    &logger(4,"Writing file '$testfile'");

    # write header
    print FILE "### nagios.cfg file - FOR SYNTAX CHECKING ONLY ###\n\n";
    print FILE "# OBJECT CONFIGURATION FILE(S)\n";

    # write global cfg files
    my %unique_global_cfg;
    foreach my $global_cfg (@global_cfg_files){
        unless($global_cfg){next}
        if($unique_global_cfg{$global_cfg} && $unique_global_cfg{$global_cfg} ne ""){next}
        print FILE "cfg_file=$global_cfg\n";
        $unique_global_cfg{$global_cfg} = $global_cfg;
    }

    # write server-specific cfg files
    my %unique_server_cfg;
    foreach my $server_cfg (@server_cfg_files){
        unless($server_cfg){next}
        if($unique_server_cfg{$server_cfg} && $unique_server_cfg{$server_cfg} ne ""){next}
        print FILE "cfg_file=$server_cfg\n";
        $unique_server_cfg{$server_cfg} = $server_cfg;
    }

    # write static_cfg folders, if CHECK_STATIC_SYNTAX is enabled
    if($check_static_cfg eq 1){
        foreach my $static_cfg (@static_cfg){
            unless($static_cfg){next}
            if($static_cfg =~ /\/+[^\/]+.*/){
                print FILE "cfg_dir=$static_cfg\n";
            }else{
                print FILE "cfg_dir=$root_path/$static_cfg\n";
            }
        }
    }

    # write footer + extra options
    print FILE "illegal_object_name_chars=`~!\$%^&*|'\"<>?,()=\n";
    print FILE "illegal_macro_output_chars=`~\$&|'\"<>\n";
    print FILE "check_result_path=$root_path/temp/";

    close(FILE);
}


########################################################################################
# SUB fetch_host_os_info
# Fetch OS information for hosts (icons etc.)

sub fetch_host_os_info {
    &logger(5,"Entered fetch_host_os_info()");

    my $sql = "SELECT attr_name,attr_value,write_to_conf FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr
                   AND fk_id_item = (SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                     WHERE id_attr=fk_id_attr
                                     AND attr_name='os'
                                     AND fk_id_item=$_[0])";

    my @attrs = &queryExecRead($sql, "Fetching certain OS attributes for host '$_[0]'", "all");

    # replace NConf macros with the respective value
    foreach my $attr (@attrs){
        $attr->[1] = &replaceMacros($attr->[1]);
    }

    return(@attrs);
}


########################################################################################
# SUB fetch_inherited_host_templates
# Fetch host-alive check and templates inherited from timeperiod

sub fetch_inherited_host_templates {
    &logger(5,"Entered fetch_inherited_host_templates()");

    my $sql = undef;

    # fetch host-alive check from the misccommand that is linked to the host-preset of the host
    $sql = "SELECT 'check_command', attr_value
              FROM ConfigValues, ConfigAttrs
              WHERE fk_id_attr=id_attr
                  AND attr_name='command_name'
                  AND fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                  WHERE id_attr=fk_id_attr
                                    AND attr_name='hostalive_check'
                                    AND fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                                                  WHERE id_attr=fk_id_attr
                                                    AND attr_name='host-preset'
                                                    AND fk_id_item=$_[0]))";

    my @hostalive = &queryExecRead($sql, "Fetching host-alive check for host '$_[0]'", "row");

    unless($hostalive[1]){
        &logger(1,"Failed to get host-alive check for host '".&getItemName($_[0])."'. Make sure the host is linked with a host-preset. Aborting.")
    }

    my @attrs;

    # fetch host-templates linked to timeperiod (notification_period)
    $sql = "SELECT attr_name,attr_value
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'host_template' 
                        AND ItemLinks.fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs 
                            WHERE id_attr=fk_id_attr
                                AND attr_name='notification_period'
                                AND fk_id_item = $_[0]) 
                                ORDER BY cust_order,ordering";

    my @host_tpl1 = &queryExecRead($sql, "Fetching host-templates linked to timeperiod (notification_period) for host '$_[0]'", "all");
    foreach (@host_tpl1){push(@attrs,$_)}

    # fetch host-templates linked to timeperiod (check_period)
    $sql = "SELECT attr_name,attr_value
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'host_template' 
                        AND ItemLinks.fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs 
                            WHERE id_attr=fk_id_attr
                                AND attr_name='check_period'
                                AND fk_id_item = $_[0]) 
                                ORDER BY cust_order,ordering";

    my @host_tpl2 = &queryExecRead($sql, "Fetching host-templates linked to timeperiod (check_period) for host '$_[0]'", "all");
    foreach (@host_tpl2){push(@attrs,$_)}

    unshift(@attrs,\@hostalive);
    return(@attrs);
}


########################################################################################
# SUB fetch_inherited_service_templates
# Fetch templates inherited from checkcommand / timeperiod

sub fetch_inherited_service_templates {
    &logger(5,"Entered fetch_inherited_service_templates()");

    my $sql = undef;
    my @attrs;

    # fetch service-templates linked to checkcommand
    $sql = "SELECT attr_name,attr_value
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND attr_name = 'service_template'
                        AND ItemLinks.fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs
                            WHERE id_attr=fk_id_attr
                                AND attr_name='check_command'
                                AND fk_id_item = $_[0])
                                ORDER BY cust_order,ordering";

    my @srv_tpl1 = &queryExecRead($sql, "Fetching service-templates linked to checkcommand for service '$_[0]'", "all");
    foreach (@srv_tpl1){push(@attrs,$_)}

    # fetch service-templates linked to timeperiod (notification_period)
    $sql = "SELECT attr_name,attr_value
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'service_template' 
                        AND ItemLinks.fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs 
                            WHERE id_attr=fk_id_attr
                                AND attr_name='notification_period'
                                AND fk_id_item = $_[0]) 
                                ORDER BY cust_order,ordering";

    my @srv_tpl2 = &queryExecRead($sql, "Fetching service-templates linked to timeperiod (notification_period) for service '$_[0]'", "all");
    foreach (@srv_tpl2){push(@attrs,$_)}

    # fetch service-templates linked to timeperiod (check_period)
    $sql = "SELECT attr_name,attr_value
                FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr 
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes' 
                        AND attr_name = 'service_template' 
                        AND ItemLinks.fk_id_item=(SELECT fk_item_linked2 FROM ItemLinks,ConfigAttrs 
                            WHERE id_attr=fk_id_attr
                                AND attr_name='check_period'
                                AND fk_id_item = $_[0]) 
                                ORDER BY cust_order,ordering";

    my @srv_tpl3 = &queryExecRead($sql, "Fetching service-templates linked to timeperiod (check_period) for service '$_[0]'", "all");
    foreach (@srv_tpl3){push(@attrs,$_)}

    return(@attrs);
}


########################################################################################
# SUB fetch_cmd_params
# Fetch checkcommand params from service

sub fetch_cmd_params {
    &logger(5,"Entered fetch_cmd_params()");

    my $sql = "SELECT attr_value FROM ConfigValues,ConfigAttrs
               WHERE id_attr=fk_id_attr
                 AND attr_name='check_params'
                 AND fk_id_item=$_[0]";

    my @params = &queryExecRead($sql, "Fetching checkcommand params for service '$_[0]'", "all");

    return($params[0]->[0]);
}


########################################################################################
# SUB fetch_cmd_info
# fetch and cache all checkcommands and their attributes/values (performance improvement)
# INFO: multiple assigned items will be returned as a single, comma separated line

sub fetch_cmd_info {
    &logger(5,"Entered fetch_cmd_info()");

    my %checkcommand_info;
    my @cmds = getItems("checkcommand", 1);

    foreach my $cmd (@cmds){
        my %cmd_hash;
        my @cmd_data  = getItemData($cmd->[0]);
        my @cmd_links = getItemsLinked($cmd->[0]);

        # consolidate multi-line attributes / links into one line
        @cmd_links = &makeValuesDistinct(@cmd_links); 

        # de-reference and neatly store into a hash structure
        foreach my $attr (@cmd_data){
            $cmd_hash{$attr->[0]} = $attr->[1];
        }

        foreach my $attr (@cmd_links){
            $cmd_hash{$attr->[0]} = $attr->[1];
        }

        $checkcommand_info{$cmd->[1]} = \%cmd_hash;
    }

    return(%checkcommand_info);
}


########################################################################################
# SUB define_progress_steps
# Count all collector and monitor servers in order to calculate amount of "steps" for progress bar
# (this is a feature for the jQuery progressbar). This function is needed by set_progress().

sub define_progress_steps {
    &logger(5,"Entered define_progress_steps()");

    # count amount of monitors and collectors
    # each server is considered a "step"
    my @monitors = &getItems("nagios-monitor");
    my @collectors = &getItems("nagios-collector");
    my @monitors_and_collectors = (@monitors,@collectors);
    my $progress_steps_count = scalar(grep {defined $_} @monitors_and_collectors);
    
    # add additional steps
    # 1 for global config
    # 1 for generation of tgz (?)
    $progress_steps_count += 1;

    &logger(4,"Calculated total amount of monitors and collectors: '$progress_steps_count'");

    # calculate one step in percent
    $progress_step = (100/$progress_steps_count);
    &logger(4,"Setting progress step to: '$progress_step'");
}


########################################################################################
# SUB set_progress
# Write progress status into a .lock file for the jQuery progressbar to read

sub set_progress {
    &logger(5,"Entered set_progress()");

    # set new progress_status
    $progress_status = $progress_status + $progress_step;
    
    if ( open(FILE,">$output_path/generate.lock") ){
        print FILE "$progress_status\n";
        close(FILE);
        &logger(4,"Updated current progress status to: '$progress_status'");
    }else{
        &logger(2,"Could not open $output_path/generate.lock for writing. Could not update progress status.");
    }
}


########################################################################################

1;

__END__

}
