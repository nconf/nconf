#!/usr/bin/perl

use strict;
use FindBin;
use lib "$FindBin::Bin/lib";

use NConf;
use NConf::DB;
use NConf::DB::Read;
use NConf::DB::Modify;
use NConf::Logger;
use Getopt::Std;

# read commandline arguments
use vars qw($opt_h $opt_x $opt_s);
getopts('x:sh');
if($opt_h){&usage}
if($opt_x){&setLoglevel($opt_x)}
if($opt_s){&setDbReadonly(1)}

# global vars
my %tp_attrs = ("max_check_attempts" => "", 
				"check_interval" => "", 
				"retry_interval" => "", 
				"notification_interval" => "", 
				"host_notification_options" => "", 
				"service_notification_options" => "");

my %mon_col_attrs = ("active_checks_enabled" => "", 
					 "passive_checks_enabled" => "", 
					 "notifications_enabled" => "", 
					 "check_freshness" => "", 
					 "freshness_threshold" => "");

my %data2convert = ("timeperiod" => \%tp_attrs, 
					"nagios-collector" => \%mon_col_attrs, 
					"nagios-monitor" => \%mon_col_attrs);

my $no_work = undef;

#########################
# MAIN

&logger(3,"Started executing $0");

# iterate through all classes that we need to convert
foreach my $class (keys(%data2convert)){

	# fetch all items in that class
	my @items = getItems($class);

	# fetch attributes for each item
	foreach my $item (@items){
		my @item_raw_data = getItemData($item->[0]);
		my %item_new_data;
		foreach my $attr (@item_raw_data){

			# filter for relevant attributes only
			unless(defined($data2convert{$class}->{$attr->[0]})){next}

			# extract relevant data
			if($attr->[1] ne ""){$item_new_data{$attr->[0]} = $attr->[1]}
		}

		# proceed if one or more of the relevant attributes are in use
		if(%item_new_data){

			$no_work = "false";

			# evaluate host/service notification_options
			my $host_notification_options = $item_new_data{'host_notification_options'};
			my $service_notification_options = $item_new_data{'service_notification_options'};
			delete $item_new_data{'host_notification_options'};
			delete $item_new_data{'service_notification_options'};

			##### host-templates

			if($host_notification_options ne ""){$item_new_data{'notification_options'} = $host_notification_options}

			# do one last check to see if relevant attributes are still set for host-templates
			if(%item_new_data){

				# determine the 'name' + 'register' attributes
				$item_new_data{'name'} = &getUniqueNameCounter("host-template", "converted_host-template");
				$item_new_data{'register'} = '0';

				# add a host-template with the available data
				&logger(3,"Adding host-template    \'$item_new_data{'name'}\'    with data from $class '".&getItemName($item->[0])."'");
				unless(&addItem("host-template", %item_new_data)){&logger(1,"Error adding host-template with data from $class '".&getItemName($item->[0])."'")};

				# link new host-template with original item
				my $new_tpl_id = &getItemId($item_new_data{'name'}, "host-template");
				unless(&linkItems($item->[0], $new_tpl_id, "host_template", "0")){&logger(1,"Error linking new host-template with $class '".&getItemName($item->[0])."'")};

				# remove the 'name' + 'register' attributes
				delete $item_new_data{'name'};
				delete $item_new_data{'register'};
				delete $item_new_data{'notification_options'};
			}

			##### service-templates

			if($service_notification_options ne ""){$item_new_data{'notification_options'} = $service_notification_options}

			# do one last check to see if relevant attributes are still set for service-templates
			if(%item_new_data){

				# determine the 'name' + 'register' attributes
				$item_new_data{'name'} = &getUniqueNameCounter("service-template", "converted_service-template");
				$item_new_data{'register'} = '0';

				# add a service-template with the available data
				&logger(3,"Adding service-template \'$item_new_data{'name'}\' with data from $class '".&getItemName($item->[0])."'");
				unless(&addItem("service-template", %item_new_data)){&logger(1,"Error adding service-template with data from $class '".&getItemName($item->[0])."'")};

				# link new service-template with original item
				my $new_tpl_id = &getItemId($item_new_data{'name'}, "service-template");
				unless(&linkItems($item->[0], $new_tpl_id, "service_template", "0")){&logger(1,"Error linking new service-template with $class '".&getItemName($item->[0])."'")};

				# remove the 'name' + 'register' attributes
				delete $item_new_data{'name'};
				delete $item_new_data{'register'};
				delete $item_new_data{'notification_options'};
			}
		}
	}
}

unless($no_work eq "false"){
	&logger(3,"None of the relevant attributes are in use. There is no data to convert.");
}

&logger(3,"Finished running $0");

#########################
# SUB: display usage information
sub usage {

print <<"EOT";

Script by Angelo Gargiulo, Sunrise Communications AG

This script will convert timeperiods, nagios-collector and nagios-monitor servers in the following way:
If these items use one or any combination of the attributes listed below, the script will create host- and service-templates 
based on the content of the attributes and link the new templates with the original item.

The goal is to ensure that there is no data missing after an update to NConf 1.3.0 and that the config can be generated as usual.
If prior to the update you weren't using any of the attributes listed below or, if you don't wish for any auto-created templates, 
then you do not need to run this script.

timeperiod attributes:
- max_check_attempts
- check_interval
- retry_interval
- notification_interval
- host_notification_options
- service_notification_options

nagios-collector & nagios-monitor attributes:
- active_checks_enabled
- passive_checks_enabled
- notifications_enabled
- check_freshness
- freshness_threshold

Usage:
$0 [-x (1-5)] [-s]

Help:

  optional

  -h  Display command usage and syntax (this text)

  -x  Set a custom loglevel (1 = lowest, 5 = most verbose)

  -s  Simulate only. Do not make any actual modifications to the database.

EOT
exit;

}
