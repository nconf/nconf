##############################################################################
# "NConf::DB::Modify" library
# A collection of shared functions for the NConf Perl scripts.
# Functions which actually modify data in the database.
#
# Version 0.1
# Written by Angelo Gargiulo
#
# Revision history:
# 2009-02-26 v0.1   A. Gargiulo   First release
#
##############################################################################

package NConf::DB::Modify;

use strict;
use Exporter;
use DBI;
use NConf::Helpers;
use NConf::DB;
use NConf::DB::Read;
use NConf::Logger;

##############################################################################
### I N I T ##################################################################
##############################################################################

BEGIN {
    use vars qw(@ISA @EXPORT @EXPORT_OK);

    @ISA         = qw(NConf::DB);
    @EXPORT      = qw(@NConf::DB::EXPORT linkItems addItem insertValue addHistory queryExecModify);
    @EXPORT_OK   = qw(@NConf::DB::EXPORT_OK);
}

##############################################################################
### S U B S ##################################################################
##############################################################################

sub linkItems {
    &logger(5,"Entered linkItems()");

    # SUB use: Link two items in the ItemLinks table

    # SUB specs: ###################

    # Expected arguments:
    # 0: ID of item that will be linked
    # 1: ID of item to link the first one to
    # 2: name of NConf attr (of type assign_one/many/cust_order)
    # 3: optional: order number for assign_cust_order attributes

    # Return values:
    # 0: 'true' on operation success,
    #     undef on failure

    # This function automatically checks and considers the "link_as_child" flag.
    # It also links items in the proper order, if datatype is "assign_cust_order".

    ################################

    # read arguments passed
    my $id_item = shift;
    my $id_item_linked2 = shift;
    my $attr_name = shift;
    my $cust_order = shift;

    unless($id_item && $id_item_linked2 && $attr_name){&logger(1,"linkItems(): Missing argument(s). Aborting.")}

    # make sure items are not already linked
    my $link_check = &checkItemsLinked($id_item, $id_item_linked2, $attr_name);

    if($link_check eq "true"){

	# do not WARN if running in simulation mode
	my $loglevel = 2;
	if($NConf::DB::NC_db_readonly == 1){$loglevel=4;}

	&logger($loglevel,"Items '$id_item' and '$id_item_linked2' seem to be already linked. Aborting linkItems().");
        return undef;
    }
    elsif($link_check eq ""){
        &logger(2,"Failed to check if items '$id_item' and '$id_item_linked2' are already linked. Aborting linkItems().");
        return undef;
    }

    # fetch class name
    my $class_name = &getItemClass($id_item);
    unless($class_name){
        &logger(2,"Failed to resolve the class name for item ID '$id_item' using getItemClass(). Aborting linkItems().");
        return undef;
    }

    # fetch id_attr
    my $id_attr = &getAttrId($attr_name, $class_name);
    unless($id_attr){
        &logger(2,"Failed to resolve attr ID for attribute '$attr_name' using getAttrId(). Aborting linkItems().");
        return undef;
    }

    # check link_as_child
    my $las = &checkLinkAsChild($id_attr);
    unless($las){
        &logger(2,"Failed to check if 'link_as_child' flag is set using checkLinkAsChild(). Aborting linkItems().");
        return undef;
    }

    my $item1 = undef;
    my $item2 = undef;

    if($las eq "true"){
        $item1 = $id_item_linked2;
        $item2 = $id_item;
    }else{
        $item1 = $id_item;
        $item2 = $id_item_linked2;
    }

    my $sql = undef;
    my %class_attrs_hash = &getConfigAttrs();

    # maintain custom order in "assign_cust_order" attributes
    if($class_attrs_hash{$class_name}->{$attr_name}->{'datatype'} eq "assign_cust_order"){
        if($cust_order eq "" || $cust_order < 0){
    	    &logger(2,"Could not determine order of '$attr_name' items. Ordering not explicitely specified. Defaulting to 0.");
	        $cust_order = 0;
	    }
    	$sql = "INSERT INTO ItemLinks (fk_id_item, fk_item_linked2, fk_id_attr, cust_order) VALUES ($item1, $item2, $id_attr, $cust_order)";
    }
    else{
    	$sql = "INSERT INTO ItemLinks (fk_id_item, fk_item_linked2, fk_id_attr) VALUES ($item1, $item2, $id_attr)";
    }

    my $qres = &queryExecModify($sql,"Linking items '$item1' and '$item2' over attr '$attr_name'");

    if($qres){
        my $linked_item_name = &getItemName($item2);
        &addHistory('assigned',$attr_name,$linked_item_name,$item1);
        return "true";
    }
    else{return undef}
}

##############################################################################

sub addItem {
    &logger(5,"Entered addItem()");

    # SUB use: Add a new item including all its attributes / values

    # SUB specs: ###################

    # Expected arguments:
    # 0: The class name of the item you want to add (e.g. 'host, 'contact' etc.)
    # 1: A hash containing all attr/value pairs of the item to be added

    # Return values:
    # 0: 'true' on operation success,
    #     undef on failure

    ################################

    # read arguments passed
    my $class_name = shift;
    my %main_hash  = @_;
    $class_name = lc($class_name);

    unless($class_name && %main_hash){&logger(1,"addItem(): Missing argument(s). Aborting.")}

    ####### check for services which belong to multiple hosts,

    # invoke addItem() recursively to add the service for each host
    if($class_name eq "service"){

        if($main_hash{'host_name'} =~ /[^,],[^,]/){
            my @hosts = split(/,/, $main_hash{'host_name'});

            foreach my $host_name (@hosts){
                $host_name =~ s/^\s*//;
                $host_name =~ s/\s*$//;

                $main_hash{'host_name'} = $host_name;
                &logger(3,"Adding service '$main_hash{'service_description'}' to host '$host_name'");
                &logger(4,"Recursively invoking addItem() to add a service assigned to multiple hosts: "
                            ."adding service '$main_hash{'service_description'}' to host '$host_name'");
                &addItem('service', %main_hash);
            }
            return "true";
        }
    }

    ####### run several checks

    # get a list of all class attrs plus their properties (datatype, maxlength, mandatory etc.)
    # list datastructure: $class_attrs_hash{'class name'}->{'attr name'}->{'property'}
    my %class_attrs_hash = &getConfigAttrs();

    # TESTCASES:
    # Test for following behavour (test for each datatype separately):
    # CASE 1:  Attribute exists in the DB (for the current class), 
    #          but is not in the import file (e.g. host-preset, monitored-by)
    # CASE 1A: Attribute is NConf-specific*, mandatory      --> try to use default value, show WARN msg
    # CASE 1B: Attribute is NConf-specific*, not mandatory  --> try to use default value, show DEBUG msg
    # CASE 1C: Attribute is Nagios-specific, mandatory      --> return undef, throw ERROR (abort)
    # CASE 1D: Attribute is Nagios-specific, not mandatory  --> try to use default value, show DEBUG msg
    # *NConf-specific meaning 'write-to-conf'=no
    #
    # CASE 2: Attribute exists in the import file, but is not in the DB (for the current class, 
    #         e.g. check_freshness, active_checks_enabled)
    # CASE 2: --> show WARN msg

    ####### CHECK1:

    # check if all attrs for the item to be added exist in the database
    foreach my $attr (keys(%main_hash)){
        unless($class_attrs_hash{$class_name}->{$attr}->{'attr_name'}){

            # skip attributes that don't exist in the DB
            # special exceptions apply for specific class + attr combinations
            unless(($class_name eq "service-dependency" && ($attr eq "host_name" || $attr eq "dependent_host_name"))
                || ($class_name eq "host" && $attr eq "hostgroups")
                || ($class_name eq "service" && $attr eq "servicegroups")
                || ($class_name eq "contact" && $attr eq "contactgroups")){

                &logger(2, "Could not find attribute '$attr' belonging to class '$class_name'. Skipping import of this attribute.");
                $main_hash{$attr} = undef;
            }
        }

        # special, class-specific attribute manipulation:
        # process "check_command" attrs of services & advanced-services
        if(($class_name eq "service" || $class_name eq "advanced-service") && $attr eq "check_command" && $main_hash{$attr} =~ /\!/){
            # separate checkcommand from params
            $main_hash{$attr} =~ /^([^!]+)(!.*)$/;
            $main_hash{$attr} = $1;
            $main_hash{'check_params'} = $2;
        }
    }

    ####### Special processing for certain item types (dependencies, escalations etc.)

    if($class_name eq "service-dependency"){

        # concatenate host_names with service_descriptions into one comma separated string,
        # then remove the host_name attributes, since NConf does not store these attributes

        my $concat1 = undef;
        my $concat2 = undef;
        my @hosts;

        if(defined($main_hash{'host_name'}) && defined($main_hash{'service_description'})){

            @hosts = split(/,/, $main_hash{'host_name'});
            my @servs = split(/,/, $main_hash{'service_description'});
        
            foreach my $host (@hosts){
                unless($host){next}
                foreach my $serv (@servs){
                    unless($serv){next}
                    $concat1 = $concat1.$host.",".$serv.",";                    
                }
            }
            $concat1 =~ s/^,//;
            $concat1 =~ s/,$//;
            $main_hash{'service_description'} = $concat1;
        }

        if(defined($main_hash{'host_name'}) && defined($main_hash{'dependent_service_description'})){

            if(defined($main_hash{'dependent_host_name'})){
                @hosts = split(/,/, $main_hash{'dependent_host_name'});
            }
            my @servs = split(/,/, $main_hash{'dependent_service_description'});
        
            foreach my $host (@hosts){
                unless($host){next}
                foreach my $serv (@servs){
                    unless($serv){next}
                    $concat2 = $concat2.$host.",".$serv.",";                    
                }
            }
            $concat2 =~ s/^,//;
            $concat2 =~ s/,$//;
            $main_hash{'dependent_service_description'} = $concat2;
        }

        delete $main_hash{'host_name'};
        delete $main_hash{'dependent_host_name'};
    }

    ####### CHECK2:

    # determine naming attr and all mandatory attrs for current class
    my $class_naming_attr = undef;
    my @class_mandatory_attrs;
    foreach my $attr (keys(%{$class_attrs_hash{$class_name}})){
        if($class_attrs_hash{$class_name}->{$attr}->{'naming_attr'} eq "yes"){
            if($class_naming_attr eq undef){$class_naming_attr = $attr}
            else{&logger(1,"More than one naming attr defined for class '$class_name' in DB. Aborting.")}
        }

        if($class_attrs_hash{$class_name}->{$attr}->{'mandatory'} eq "yes"){
	    # write all mandatory attrs to an array (for later use)
            push(@class_mandatory_attrs, $attr);
        }else{
            # try to lookup and use default values for empty attrs (only non-mandatory attrs)
            if(!$main_hash{$attr} && $main_hash{$attr} ne "0" && ($class_attrs_hash{$class_name}->{$attr}->{'predef_value'} 
            || $class_attrs_hash{$class_name}->{$attr}->{'predef_value'} eq "0")){
                $main_hash{$attr} = $class_attrs_hash{$class_name}->{$attr}->{'predef_value'};
                &logger(4, "Attribute '$attr' missing for current $class_name. Using default value: '$class_attrs_hash{$class_name}->{$attr}->{'predef_value'}'.");
            }
        }
    }
    unless($class_naming_attr){
        &logger(1, "Failed to determine naming attr for class '$class_name'. Aborting.");
    }

    ####### CHECK3:

    # check if an item with the same naming attr already exists (unless it's a service)
    if($class_name ne "service"){
    	if(&getItemId($main_hash{$class_naming_attr},$class_name)){
	        &logger(2, "$class_name with $class_naming_attr '$main_hash{$class_naming_attr}' already exists!");
            return undef;
    	}

		# make sure there are no nagios-collectors + nagios-monitors with the same name
		if($class_name eq "nagios-collector"){
	    	if(&getItemId($main_hash{$class_naming_attr},"nagios-monitor")){
		        &logger(2, "nagios-monitor with $class_naming_attr '$main_hash{$class_naming_attr}' already exists! collector and monitor names cannot be identical.");
        	    return undef;
	    	}
		}
		if($class_name eq "nagios-monitor"){
	    	if(&getItemId($main_hash{$class_naming_attr},"nagios-collector")){
		        &logger(2, "nagios-collector with $class_naming_attr '$main_hash{$class_naming_attr}' already exists! collector and monitor names cannot be identical.");
        	    return undef;
	    	}
		}

		# make sure there are no checkcommands + misccommands with the same name
		if($class_name eq "checkcommand"){
	    	if(&getItemId($main_hash{$class_naming_attr},"misccommand")){
		        &logger(2, "misccommand with $class_naming_attr '$main_hash{$class_naming_attr}' already exists! checkcommand and misccommand names cannot be identical.");
        	    return undef;
	    	}
		}
		if($class_name eq "misccommand"){
	    	if(&getItemId($main_hash{$class_naming_attr},"checkcommand")){
		        &logger(2, "checkcommand with $class_naming_attr '$main_hash{$class_naming_attr}' already exists! checkcommand and misccommand names cannot be identical.");
        	    return undef;
	    	}
		}

    }else{
    # make sure a host doesn't have more than one service with the same name
        unless(&getItemId($main_hash{'host_name'},'host')){
            &logger(1, "Could not find host '".$main_hash{'host_name'}."'. Aborting.");
        }
    	if(&getServiceId($main_hash{'service_description'}, &getItemId($main_hash{'host_name'},"host")) && $class_name eq "service"){
            &logger(2, "$class_name '$main_hash{$class_naming_attr}' already exists for host \'$main_hash{'host_name'}\'!");
            return undef;
    	}
    }

    ####### CHECK4:

    # check if all mandatory attributes have been supplied
    foreach my $man_attr (@class_mandatory_attrs){
        if((!$main_hash{$man_attr} && $main_hash{$man_attr} != 0) || $main_hash{$man_attr} eq ""){

            # ignore NConf-specific mandatory attributes (set to default value)
            if($class_attrs_hash{$class_name}->{$man_attr}->{'write_to_conf'} ne "yes"){
                &logger(2, "Mandatory attribute '$man_attr' missing for $class_name '$main_hash{$class_naming_attr}'. Using default value: '$class_attrs_hash{$class_name}->{$man_attr}->{'predef_value'}'.");

                # add default value of mandatory attr to the list values to add (only mandatory attrs)
                $main_hash{$man_attr} = $class_attrs_hash{$class_name}->{$man_attr}->{'predef_value'};

            }else{
                &logger(2, "Mandatory attribute '$man_attr' missing for $class_name '$main_hash{$class_naming_attr}'.");
                return undef;
            }
        }
    }

    ####### Add new item

    # add new entry to ConfigItems table (generate new id_item)
    my $sql  = "INSERT INTO ConfigItems (fk_id_class)
                 VALUES ((SELECT id_class FROM ConfigClasses WHERE config_class='$class_name'))";

    my ($qres, $id_item) = &queryExecModify($sql,"Adding entry for new $class_name to ConfigItems table",1,"$class_name");

    if($id_item){
        &logger(4,"Successfully created new $class_name item with ID '$id_item'");

        # write history entries for new item added
        if($class_name eq "service"){
            my $host_id = getItemId($main_hash{'host_name'},'host');
    	    &addHistory('created',$class_name,$main_hash{'host_name'}.": ".$main_hash{$class_naming_attr},$id_item);
	        &addHistory('added',$class_name,$main_hash{$class_naming_attr},$host_id);
        }else{
	        &addHistory('created',$class_name,$main_hash{$class_naming_attr},$id_item);
        }
    }
    else{&logger(1,"Failed to create new $class_name item. Aborting.")}

    ####### Add all attributes to new item

    # first of all, add the naming_attr; error handling is done by insertValue()
    &insertValue($class_name, $id_item, $class_naming_attr, $main_hash{$class_naming_attr});

    # second, if the item is a service, then link it to its host right away
    if($class_name eq "service"){
        &insertValue($class_name, $id_item, "host_name", $main_hash{"host_name"});
    }

    # next, add all other attributes
    foreach my $attr (keys(%main_hash)){

        # skip adding attrs with no value (def. values should have been added to %main_hash by now, if available)
        unless($main_hash{$attr}){
            if($main_hash{$attr} ne "0"){next}
        }

    	# skip the naming_attr, because it was already added
	    if($attr eq $class_naming_attr){next}

        # for services, skip the "host_name" attr, because it was already added
        if($attr eq "host_name" && $class_name eq "service"){next}

    	# error handling is done by insertValue()
	    my $retval = &insertValue($class_name, $id_item, $attr, $main_hash{$attr});
	    unless($retval eq "true"){next}
    }

    ####### determine return value and finish
    if($qres){return "true"}
    else{return undef}
}

##############################################################################

sub insertValue {
    &logger(5,"Entered insertValue()");

    # SUB use: Add the value of a single attribute (any datatype)

    # SUB specs: ###################

    # Expected arguments:
    # 0: class name
    # 1: ID of item
    # 2: attribute name
    # 3: attribute value

    # Return values:
    # 0: 'true' on operation success,
    #     undef on failure

    ################################

    # read arguments passed
    my $class_name = shift;
    my $id_item    = shift;
    my $attr 	   = shift;
    my $attr_value = shift; # value can be "0"
    $class_name = lc($class_name);

    unless($class_name && $attr){&logger(1,"insertValue(): Missing argument(s). Aborting.")}
    if($attr_value eq ""){&logger(1,"insertValue(): Missing argument(s). Aborting.")}

    # get a list of all class attrs plus their properties (datatype, maxlength, mandatory etc.)
    # list datastructure: $class_attrs_hash{'class name'}->{'attr name'}->{'property'}
    my %class_attrs_hash = &getConfigAttrs();

    ####### special processing for item to group(s) assignment where the 'members' attr isn't used

    # change around the direction in which these objects are linked to eachother (concerns HG, SG and CG)
    # invoke insertValue() recursively to link items to multiple groups
    if(($class_name eq "host" && $attr eq "hostgroups") 
    || ($class_name eq "service" && $attr eq "servicegroups") 
    || ($class_name eq "contact" && $attr eq "contactgroups")){

        # change the class we're working with
        if($class_name eq "host"){$class_name="hostgroup"}
        elsif($class_name eq "service"){$class_name="servicegroup"}
        elsif($class_name eq "contact"){$class_name="contactgroup"}

        # change the attr name we're working with
        $attr = "members";

        # fetch the name of the item to which we're linking our group(s)
        my $item_name = &getItemName($id_item);

        # if we're linking services to a servicegroup, fetch and append the hostname too
        if($class_name eq "servicegroup"){
            my $host_name = &getServiceHostname($id_item);
            $item_name = "$host_name,$item_name";
        }

        # split group(s) by ','
        my @groups = split(/,/, $attr_value);

        # process each group individually
        foreach my $group (@groups){
            $group =~ s/^\s*//;
            $group =~ s/\s*$//;

            # remove any prepending '+' characters
            if($group =~ /^\+.+/){
                &logger(2, "The $class_name '$group' is preceeded by a '+' character. The '+' will be removed to allow proper import.");
                $group =~ s/^\+//;
            }
            unless($group){next}

            # quote any characters that must be escaped in SQL
            $group = &NConf::DB::dbQuote($group);
            $group =~ s/^'//;
            $group =~ s/'$//;

            # check if the group to be linked actually exists
            my $id_group = &getItemId($group,$class_name);

            # notify if the group to be linked doesn't exist
            unless($id_group){
                &logger(2, "Could not link $class_attrs_hash{$class_name}->{$attr}->{'assign_to_class'} '$item_name' with $class_name '$group'. No such $class_name.");
                next;
            }

            # link group(s)
            &logger(4,"Recursively invoking insertValue() to link $class_attrs_hash{$class_name}->{$attr}->{'assign_to_class'} to one or more $class_name(s): "
                          ."linking $class_name '$group' to $class_attrs_hash{$class_name}->{$attr}->{'assign_to_class'} '$item_name'.");

            # recursive call to insertValue()
            &insertValue($class_name,$id_group,$attr,$item_name);
        }
        return "true";
    }

    ####### normal processing: process data to be added depending on its datatype

    # CASE1: add all simple attributes ('text' & 'password')
    if($class_attrs_hash{$class_name}->{$attr}->{'datatype'} eq "text" 
    || $class_attrs_hash{$class_name}->{$attr}->{'datatype'} eq "password"){

        my $maxlength =  $class_attrs_hash{$class_name}->{$attr}->{'max_length'};

        # check max_length of attr value to be inserted
        if(length($attr_value) > $maxlength){
            &logger(2,"Value of '$attr' is longer than the specified max-length for the attribute. Value will be cropped.");
            &logger(4,"Value '$attr_value' cropped after $maxlength chars.");

            # crop line at max_length
            $attr_value = substr($attr_value,0,$maxlength);
        }

        # add entries
        my $id_attr = &getAttrId($attr, $class_name);
        $attr_value = &NConf::DB::dbQuote($attr_value);

        my $sql  = "INSERT INTO ConfigValues (fk_id_attr, fk_id_item, attr_value) 
                    VALUES ($id_attr,$id_item,$attr_value)";

        my $qres = &queryExecModify($sql,"Adding attr '$attr' to $class_name");

        if($qres){&logger(4,"Successfully added $attr to $class_name")}
        else{&logger(1,"Failed to add attr '$attr' to $class_name. Aborting.")}
        &addHistory('added',$attr,$attr_value,$id_item);
    	return "true";
    }

    # CASE2: add all 'select' attributes
    elsif($class_attrs_hash{$class_name}->{$attr}->{'datatype'} eq "select"){

        # remove any prepending '+' characters in the value to be added
        if($attr_value =~ /^\+.+/){
            &logger(2, "The $attr value '$attr_value' is preceeded by a '+' character. The '+' will be removed to allow proper import.");
            $attr_value =~ s/^\+//;
        }

        # check if value to be added is in list of possible values for select attr
        my $value_in_list = undef;

        # get separator from config, split possible values
        my $separator = &readNConfConfig(NConf::NC_CONFDIR."/nconf.php","SELECT_VALUE_SEPARATOR","scalar",1);

		# SELECT_VALUE_SEPARATOR has been a mandatory configuration option since NConf 1.2.6
		# if it is not defined, then assume we are running NConf <= 1.2.5 where the separator value was statically set to ","
		unless(defined($separator)){
			&logger(2,"Could not fetch SELECT_VALUE_SEPARATOR from config. Assuming it is ',' (true for NConf <= 1.2.5)");
			$separator = ",";
		}

        my @poss_values = split(/$separator/, $class_attrs_hash{$class_name}->{$attr}->{'poss_values'});

        foreach my $option (@poss_values){
            $option =~ s/^\s*//;
            $option =~ s/\s*$//;
            if($option eq $attr_value){$value_in_list=1}
        }

        unless($value_in_list==1){
            &logger(2, "'$attr_value' is not a possible value for attr '$attr' (not in list of poss. values). Skipping import of this attribute.");
            return undef;
        }

        # add entries
        my $id_attr = &getAttrId($attr, $class_name);
        $attr_value = &NConf::DB::dbQuote($attr_value);

        my $sql  = "INSERT INTO ConfigValues (fk_id_attr, fk_id_item, attr_value) 
                    VALUES ($id_attr,$id_item,$attr_value)";

        my $qres = &queryExecModify($sql,"Adding attr '$attr' to $class_name");

        if($qres){&logger(4,"Successfully added $attr to $class_name")}
        else{&logger(1,"Failed to add attr '$attr' to $class_name. Aborting.")}
        &addHistory('added',$attr,$attr_value,$id_item);
    	return "true";
    }

    # CASE3: add all 'assign_one', 'assign_many' & 'assign_cust_order' attributes
    elsif($class_attrs_hash{$class_name}->{$attr}->{'datatype'} eq "assign_one" 
    || $class_attrs_hash{$class_name}->{$attr}->{'datatype'} eq "assign_many"
    || $class_attrs_hash{$class_name}->{$attr}->{'datatype'} eq "assign_cust_order"){

        # split values by ','
        my @values = split(/,/, $attr_value);

        # parse each value individually
    	my $i = 0;
	    my $val_count = 1;
        foreach my $value (@values){
            $value =~ s/^\s*//;
            $value =~ s/\s*$//;

            # remove any prepending '+' characters in attributes to be linked
            if($value =~ /^\+.+/){
                &logger(2, "The $class_attrs_hash{$class_name}->{$attr}->{'assign_to_class'} '$value' is preceeded by a '+' character. The '+' will be removed to allow proper import.");
                $value =~ s/^\+//;
            }

            unless($value){next}
	        my $id_item_linked2 = undef;

	        # if items to be assigned are "services", parse the values differenty 
            # (this is used in servicegroups and service dependencies)
	        if($class_attrs_hash{$class_name}->{$attr}->{'assign_to_class'} eq "service"){

      	        # assume that every second value is the hostname, and the following value is always a service name
		        # syntax: <host1>,<service1>,<host2>,<service2>,...,<hostn>,<servicen>

		        my $hostname = $value;
		        $value = $values[$i+1];
		        splice(@values,$i+1,1);
		        $i++;
                $value =~ s/^\s*//;
                $value =~ s/\s*$//;

		        # get ID of host which the service belongs to
		        my $host_id = &getItemId($hostname, 'host');

                # quote any characters that must be escaped in SQL
                $value = &NConf::DB::dbQuote($value);
                $value =~ s/^'//;
                $value =~ s/'$//;

            	# check if services to be linked actually exist
		        $id_item_linked2 = &getServiceId($value,$host_id);

	        }else{
                # quote any characters that must be escaped in SQL
                $value = &NConf::DB::dbQuote($value);
                $value =~ s/^'//;
                $value =~ s/'$//;

            	# check if items to be linked actually exist
            	$id_item_linked2 = &getItemId("$value","$class_attrs_hash{$class_name}->{$attr}->{'assign_to_class'}");
	        }

            # notify if items/services to be linked don't exist
            unless($id_item_linked2){
	            my $loglevel = 2;
                if($class_name eq "service" && $attr eq "check_command"){$loglevel=1}
                &logger($loglevel, "Could not link $class_name with $class_attrs_hash{$class_name}->{$attr}->{'assign_to_class'} '$value' (attr '$attr'). No such item.");
                next;
            }

            # add entries
            my $qres = undef;
            if($class_attrs_hash{$class_name}->{$attr}->{'datatype'} eq "assign_cust_order"){
                $qres = &linkItems($id_item,$id_item_linked2,"$attr",$val_count);
            }else{
                $qres = &linkItems($id_item,$id_item_linked2,"$attr");
            }

            if($qres){
                &logger(4,"Successfully linked $class_name with $class_attrs_hash{$class_name}->{$attr}->{'assign_to_class'} '$value' (attr '$attr').")
            }else{
                # do not WARN if running in simulation mode
	            my $loglevel = 2;
	            if($NConf::DB::NC_db_readonly == 1){$loglevel=4;}
                &logger($loglevel,"Could not link $class_name with $class_attrs_hash{$class_name}->{$attr}->{'assign_to_class'} '$value' (attr '$attr').");
                next;
            }

            $val_count++;
            # history entries are added by linkItems()
        }

    	return "true";
    }

    # CASE4: unknown datatype
    else{
        &logger(2, "Unable to import attr '$attr'. Datatype unknown. Skipping import of this attribute.");
        return undef;
    }
}

##############################################################################

sub addHistory {
    &logger(5,"Entered addHistory()");

    # SUB use: Add a history entry

    # SUB specs: ###################

    # Expected arguments:
    # 0: action
    # 1: attr name or class name
    # 2: attr value or item name
    # 3: item ID

    # Return values:
    # 0: 'true' on operation success,
    #     undef on failure

    ################################

    # read arguments passed
    my $action   = shift;
    my $name     = shift;
    my $value    = shift;
    my $id_item  = shift;

    unless($action && $name && $value && $id_item){&logger(1,"addHistory(): Missing argument(s). Aborting.")}
    $value =~ s/^'//;
    $value =~ s/'$//;

    my $sql = "INSERT INTO History (user_str, action, attr_name, attr_value, fk_id_item) 
                VALUES ('Database API', '$action', '$name', '$value', $id_item)";

    my $qres = &queryExecModify($sql,"Adding history entry: '$action $name $value'");

    if($qres){return "true"}
    else{
        &logger(2,"Could not create History entry: '$action $name $value'");
        return undef;
    }
}

##############################################################################

sub queryExecModify {
    &logger(5,"Entered queryExecModify()");

    # SUB use: Execute a query which modifies data in the database

    # SUB specs: ###################

    # Expected arguments:
    # 0: The SQL query
    # 1: The message to log for the query
    # 2: Optional: if the insert_id should be returned
    # 3: if param 2 was passed, also pass the class name of the item being inserted (not optional)

    # Return values:
    # 0: 'true' on operation success,
    #     undef on failure
    # 1: the ID of the newly inserted record (if requested)

    ################################

    # read arguments passed
    my $sql = shift;
    my $msg = shift;
    my $ret = shift;
    my $class = shift;

    unless($sql && $msg){&logger(1,"queryExecModify(): Missing argument(s). Aborting.")}
    if($ret && !$class){&logger(1,"queryExecModify(): Missing argument(s). Aborting.")}

    my $dbh  = &NConf::DB::dbConnect;
    my $sth  = $dbh->prepare($sql);
    my $qres = undef;
    my $id_item = undef;

    unless($NConf::DB::NC_db_readonly == 1){
	# execute query, if not running in simulation-mode
        &logger(4,$msg);
        &logger(5,$sql,1);
        $qres = $sth->execute();

        unless($qres){
            &logger(1, "Failed to execute query. Aborting.");
        }

        if($ret == 1){
            $id_item = $dbh->{'mysql_insertid'};
            unless($id_item){
                &logger(1, "Failed to get the ID of the record that was just inserted. Aborting.");
            }
        }

    }else{
	# simulate query, try to fetch an existing item ID to be returned as insert_ID, if requested
        &logger(4,"DB is read-only! Simulating ".$msg);
        &logger(5,$sql,1);
        if($sth){
            
	    $qres = 1;

	    if($ret == 1){
	        # try to get the lowest available item ID for the current class
	        my $t_query = "SELECT min(id_item) FROM ConfigItems 
        	               WHERE fk_id_class=(SELECT id_class FROM ConfigClasses WHERE config_class='$class')";
	        $id_item = &queryExecRead($t_query,"Fetching an existing item ID for simulation purposes","one");

		unless($id_item){
		    # if first try was unsuccessful, try again, but this time get any item ID regardless of the class
	            my $t_query = "SELECT min(id_item) FROM ConfigItems";
  	            $id_item = &queryExecRead($t_query,"Retrying to fetch an existing item ID for simulation purposes","one");
		}
	    }
        }
    }
    &logger(5,"Query OK, $qres row(s) affected");

    if($ret == 1){return $qres, $id_item}
    else{return $qres}
}

##############################################################################

#sub add... {
#    &logger(5,"Entered add...()");

    # SUB use: Add...

    # SUB specs: ###################

    # Expected arguments:
    # 0: ...

    # Return values:
    # 0: 'true' on operation success,
    #     undef on failure

    ################################

    # read arguments passed
#    my $id_item = shift;

#    unless($id_item){&logger(1,"add...(): Missing argument(s). Aborting.")}

#    my $sql  = "INSERT INTO...";

#    my $qres = &queryExecModify($sql,"Adding...");

#    if($qres){
#        my $item_name = &getItemName($id_item);
#        &addHistory('created',$class_name,$item_name,$id_item);
#        return "true";
#    }
#    else{return undef}
#}

##############################################################################

1;

__END__

}
