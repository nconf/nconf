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
use Data::Dumper;
use Tie::IxHash;    # preserve hash order

# read commandline arguments
use vars qw($opt_h $opt_x $opt_s);
getopts('x:sh');
if($opt_h){&usage}
if($opt_x){&setLoglevel($opt_x)}
if($opt_s){&setDbReadonly(1)}

# global vars
tie my %tp_attrs, 'Tie::IxHash';
%tp_attrs = 	 ("max_check_attempts" => "", 
			      "check_interval" 	   => "", 
				  "retry_interval"     => "", 
				  "notification_interval" => "", 
				  "notification_options"  => "", 
				  "host_notification_options" => "", 
				  "service_notification_options" => "");

tie my %mon_col_attrs, 'Tie::IxHash';
%mon_col_attrs = ("active_checks_enabled"  => "", 
 				  "passive_checks_enabled" => "", 
				  "notifications_enabled"  => "", 
				  "check_freshness"		   => "", 
				  "freshness_threshold"    => "");

tie my %data2convert, 'Tie::IxHash';
%data2convert =  ("timeperiod" 		 => \%tp_attrs, 
				  "nagios-collector" => \%mon_col_attrs, 
				  "nagios-monitor" 	 => \%mon_col_attrs);

my $no_work = undef;
tie my %uniq_htpl_cache, 'Tie::IxHash';
tie my %uniq_stpl_cache, 'Tie::IxHash';

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
		tie my %item_new_data, 'Tie::IxHash';
		foreach my $attr (@item_raw_data){

			# filter for relevant attributes only
			unless(defined($data2convert{$class}->{$attr->[0]})){next}

			# extract relevant data
			if($attr->[1] ne ""){$item_new_data{$attr->[0]} = $attr->[1]}
		}

		# proceed if one or more of the relevant attributes are in use
		if(%item_new_data){

			# evaluate host/service notification_options
			my $host_notification_options = $item_new_data{'host_notification_options'};
			my $service_notification_options = $item_new_data{'service_notification_options'};
			delete $item_new_data{'host_notification_options'};
			delete $item_new_data{'service_notification_options'};

			##### host-templates

			if($host_notification_options ne ""){$item_new_data{'notification_options'} = $host_notification_options}

			# do one last check to see if relevant attributes are still set for host-templates
			if(%item_new_data){

				# check if item is already linked to a host-template with identical content
				my $item_linked_to_identical_host_template = "false";
				my @item_links = &getItemsLinked($item->[0]);
				foreach my $link (@item_links){
					if($link->[0] eq "host_template"){

						# extract content of each linked template
						my @linked_item_raw_data = getItemData($link->[3]);
						tie my %linked_item_data, 'Tie::IxHash';
						foreach my $lattr (@linked_item_raw_data){
							# filter for relevant attributes only
							unless(defined($data2convert{$class}->{$lattr->[0]})){next}
							# extract relevant data
							if($lattr->[1] ne ""){$linked_item_data{$lattr->[0]} = $lattr->[1]}
						}
						# compare content of each linked template
						if(Dumper(%linked_item_data) eq Dumper(%item_new_data)){
							$item_linked_to_identical_host_template = "true";
						}
					}
				}

				# proceed only if item is not already linked to an identical host-template
				unless($item_linked_to_identical_host_template eq "true"){

					$no_work = "false";

					# check if the same host-template definition already exists in cache
					my $existing_htpl_name = undef;
					foreach my $tpl (keys(%uniq_htpl_cache)){
						if($uniq_htpl_cache{$tpl} eq Dumper(%item_new_data)){
							$existing_htpl_name = $tpl;
						}
					}
				
					# if the same template doesn't exist yet in cache
					unless($existing_htpl_name){

						# determine a unique name for the new template
						my $uniq_tpl_name = &getUniqueNameCounter("host-template", "converted_host-template");
	
						# write a string representation of the current template to cache
						$uniq_htpl_cache{$uniq_tpl_name} = Dumper(%item_new_data);

						# set the 'name' + 'register' attributes
						$item_new_data{'name'} = $uniq_tpl_name;
						$item_new_data{'register'} = '0';

						# add a host-template with the available data
						&logger(3,"Adding host-template     \'$item_new_data{'name'}\'    with data from $class '".&getItemName($item->[0])."'");
						unless(&addItem("host-template", %item_new_data)){&logger(1,"Error adding host-template with data from $class '".&getItemName($item->[0])."'")};

						# link new host-template with original item
						my $new_tpl_id = &getItemId($item_new_data{'name'}, "host-template");
						unless(&linkItems($item->[0], $new_tpl_id, "host_template", "0")){&logger(1,"Error linking new host-template with $class '".&getItemName($item->[0])."'")};

					}else{
						# if an identical template already exists in cache, simply link the existing template with original item
						my $existing_tpl_id = &getItemId($existing_htpl_name, "host-template");
						&logger(3,"Linking host-template    \'$existing_htpl_name\'    with $class '".&getItemName($item->[0])."'");
						unless(&linkItems($item->[0], $existing_tpl_id, "host_template", "0")){&logger(1,"Error linking existing host-template with $class '".&getItemName($item->[0])."'")};
					}

					# remove the 'name' + 'register' attributes, if set
					delete $item_new_data{'name'};
					delete $item_new_data{'register'};
					delete $item_new_data{'notification_options'};
				}
			}

			##### service-templates

			if($service_notification_options ne ""){$item_new_data{'notification_options'} = $service_notification_options}

			# do one last check to see if relevant attributes are still set for service-templates
			if(%item_new_data){

				# check if item is already linked to a service-template with identical content
				my $item_linked_to_identical_service_template = "false";
				my @item_links = &getItemsLinked($item->[0]);
				foreach my $link (@item_links){
					if($link->[0] eq "service_template"){

						# extract content of each linked template
						my @linked_item_raw_data = getItemData($link->[3]);
						tie my %linked_item_data, 'Tie::IxHash';
						foreach my $lattr (@linked_item_raw_data){
							# filter for relevant attributes only
							unless(defined($data2convert{$class}->{$lattr->[0]})){next}
							# extract relevant data
							if($lattr->[1] ne ""){$linked_item_data{$lattr->[0]} = $lattr->[1]}
						}
						# compare content of each linked template
						if(Dumper(%linked_item_data) eq Dumper(%item_new_data)){
							$item_linked_to_identical_service_template = "true";
						}
					}
				}

				# proceed only if item is not already linked to an identical service-template
				unless($item_linked_to_identical_service_template eq "true"){

					$no_work = "false";

					# check if the same service-template definition already exists in cache
					my $existing_stpl_name = undef;
					foreach my $tpl (keys(%uniq_stpl_cache)){
						if($uniq_stpl_cache{$tpl} eq Dumper(%item_new_data)){
							$existing_stpl_name = $tpl;
						}
					}

					# if the same template doesn't exist yet in cache
					unless($existing_stpl_name){

						# determine a unique name for the new template
						my $uniq_tpl_name = &getUniqueNameCounter("service-template", "converted_service-template");

						# write a string representation of the current template to cache
						$uniq_stpl_cache{$uniq_tpl_name} = Dumper(%item_new_data);

						# set the 'name' + 'register' attributes
						$item_new_data{'name'} = $uniq_tpl_name;
						$item_new_data{'register'} = '0';

						# add a service-template with the available data
						&logger(3,"Adding service-template  \'$item_new_data{'name'}\' with data from $class '".&getItemName($item->[0])."'");
						unless(&addItem("service-template", %item_new_data)){&logger(1,"Error adding service-template with data from $class '".&getItemName($item->[0])."'")};

						# link new service-template with original item
						my $new_tpl_id = &getItemId($item_new_data{'name'}, "service-template");
						unless(&linkItems($item->[0], $new_tpl_id, "service_template", "0")){&logger(1,"Error linking new service-template with $class '".&getItemName($item->[0])."'")};

					}else{
						# if an identical template already exists in cache, simply link the existing template with original item
						my $existing_tpl_id = &getItemId($existing_stpl_name, "service-template");
						&logger(3,"Linking service-template \'$existing_stpl_name\' with $class '".&getItemName($item->[0])."'");
						unless(&linkItems($item->[0], $existing_tpl_id, "service_template", "0")){&logger(1,"Error linking existing service-template with $class '".&getItemName($item->[0])."'")};
					}

					# remove the 'name' + 'register' attributes, if set
					delete $item_new_data{'name'};
					delete $item_new_data{'register'};
					delete $item_new_data{'notification_options'};
				}
			}
		}
	}
}

unless($no_work eq "false"){
	&logger(3,"None of the relevant attributes are in use or items have already been converted. There is no data to convert.");
}

&logger(3,"Finished running $0");

#########################
# SUB: display usage information
sub usage {

print <<"EOT";

Script by Angelo Gargiulo, Sunrise Communications AG

This script will convert timeperiods, nagios-collector and nagios-monitor servers in the following way:
If these items use one or more of the attributes listed below, the script will create host- and service-templates 
based on the content of the attributes and then link the new templates with the timeperiods, nagios-collectors and monitors.
This way, the original attributes will still be applied to hosts and services via e.g. timeperiods, but users will have
the ability to override inheritance by setting the same attributes on a host or service level directly.

The goal is to ensure that there is no data missing after an update to NConf 1.3.0 and that the config can be generated as usual.
If prior to the update you weren't using any of the attributes listed below or, if you don't wish for any auto-created templates, 
then you do not need to run this script.

The following attributes are deprecated and will be removed in NConf release 1.3.0:

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
