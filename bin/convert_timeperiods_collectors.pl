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
use vars qw($opt_x $opt_s);
getopts('x:s');
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

#########################
# MAIN

&logger(3,"Started executing $0");
&logger(4,"Current loglevel is set to $NC_loglevel");
if($NC_db_readonly == 1){
    &logger(3,"Running in simulation mode. No modifications will be made to the database!");
}

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
			$item_new_data{$attr->[0]} = $attr->[1];
		}

		if(%item_new_data){
			# juggle some data
			$item_new_data{'register'} = '0';
			my $host_notification_options = $item_new_data{'host_notification_options'};
			my $service_notification_options = $item_new_data{'service_notification_options'};
			delete $item_new_data{'host_notification_options'};
			delete $item_new_data{'service_notification_options'};
			$item_new_data{'name'} = &getUniqueNameCounter("host-template", "converted_host-template");
			$item_new_data{'notification_options'} = $host_notification_options;

			# add a host-template with available data
			&logger(3,"Adding host-template \'$item_new_data{'name'}\' with data from $class '".&getItemName($item->[0])."'");
			unless(&addItem("host-template", %item_new_data)){&logger(1,"Error adding host-template with data from $class '".&getItemName($item->[0])."'")};

			# link new host-template with original item
			my $new_tpl_id = &getItemId($item_new_data{'name'}, "host-template");
			unless(&linkItems($item->[0], $new_tpl_id, "host_template", "0")){&logger(1,"Error linking new host-template with $class '".&getItemName($item->[0])."'")};

			# juggle some more data
			$item_new_data{'name'} = &getUniqueNameCounter("service-template", "converted_service-template");
			$item_new_data{'notification_options'} = $service_notification_options;

			# add a service-template with available data
			&logger(3,"Adding service-template \'$item_new_data{'name'}\' with data from $class '".&getItemName($item->[0])."'");
			unless(&addItem("service-template", %item_new_data)){&logger(1,"Error adding service-template with data from $class '".&getItemName($item->[0])."'")};
			# link new service-template with original item
			my $new_tpl_id = &getItemId($item_new_data{'name'}, "service-template");
			unless(&linkItems($item->[0], $new_tpl_id, "service_template", "0")){&logger(1,"Error linking new service-template with $class '".&getItemName($item->[0])."'")};
		}
	}
}

&logger(3,"Finished running $0");

#########################
# SUB: display usage information
sub usage {

print <<"EOT";

Script by Angelo Gargiulo, Sunrise Communications AG
This script...

Usage:
$0 [-x (1-5)] [-s]

Help:

  optional

  -x  Set a custom loglevel (1 = lowest, 5 = most verbose)

  -s  Simulate only. Do not make any actual modifications to the database.

EOT
exit;

}
