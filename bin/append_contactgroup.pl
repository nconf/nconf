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
use vars qw($opt_a $opt_b $opt_x $opt_s);
getopts('a:b:x:s');
unless($opt_a && $opt_b){&usage}
if($opt_x){&setLoglevel($opt_x)}
if($opt_s){&setDbReadonly(1)}

# global vars
my $src_group = $opt_a;
my $dst_group = $opt_b;

#########################
# MAIN

&logger(3,"Started executing $0");
&logger(4,"Current loglevel is set to $NC_loglevel");
if($NC_db_readonly == 1){
    &logger(3,"Running in simulation mode. No modifications will be made to the database!");
}

# fetch contactgroup IDs
my $src_group_id = getItemId($src_group,"contactgroup");
my $dst_group_id = getItemId($dst_group,"contactgroup");

if($src_group_id eq "" || $dst_group_id eq ""){
    &logger(1,"Failed to fetch contactgroup IDs. Make sure both contactgroup names actually exist.");
}

&logger(4,"Fetching all hosts");
my @hosts = getItems("host",1);
&logger(4,"Fetching all services");
my @servs = getItems("service",1);

# process hosts
&logger(4,"Iterating through all hosts");
foreach my $host (@hosts) {
    &logger(4,"Processing host \"$host->[1]\"");
    my $check = &checkItemsLinked($host->[0], $src_group_id, "contact_groups");
    if($check eq "true"){

	# check if host is already linked to destination group
	if(&checkItemsLinked($host->[0], $dst_group_id, "contact_groups") eq "true"){
	    &logger(3,"Skipping host \"$host->[1]\" because it is already linked with contactgroup \"$dst_group\"");
	    next;
	}

	# link new contactgroup
	my $retval = linkItems($host->[0], $dst_group_id, "contact_groups");

	if($retval eq "true"){
	    &logger(3,"Successfully linked host \"$host->[1]\" with contactgroup \"$dst_group\"");
	}else{
	    &logger(1,"Failed to link host \"$host->[1]\" with contactgroup \"$dst_group\"");
	}
    }
    elsif(!$check){
	&logger(1,"Failed to check if host \"$host->[1]\" is linked to \"$src_group\"");
    }
}

# process services
&logger(4,"Iterating through all services");
foreach my $srv (@servs) {
    &logger(4,"Processing service \"$srv->[1]\"");
    my $check = &checkItemsLinked($srv->[0], $src_group_id, "contact_groups");
    if($check eq "true"){

	# get host which service belongs to
	my $hostname = undef;
	my @srv_links  = &getItemsLinked($srv->[0], "service");
	foreach (@srv_links){
	    if($_->[0] eq "host_name"){$hostname = $_->[1]}
	}
	unless($hostname){&logger(1,"Failed to fetch hostname for service. Aborting")}

	# check if service is already linked to destination group
	if(&checkItemsLinked($srv->[0], $dst_group_id, "contact_groups") eq "true"){
	    &logger(3,"Skipping service \"$srv->[1]\" on host \"$hostname\" because it is already linked with contactgroup \"$dst_group\"");
	    next;
	}

	# link new contactgroup
	my $retval = linkItems($srv->[0], $dst_group_id, "contact_groups");

	if($retval eq "true"){
	    &logger(3,"Successfully linked service \"$srv->[1]\" on host \"$hostname\" with contactgroup \"$dst_group\"");
	}else{
	    &logger(1,"Failed to link service \"$srv->[1]\" on host \"$hostname\" with contactgroup \"$dst_group\"");
	}
    }
    elsif(!$check){
	&logger(1,"Failed to check if service \"$srv->[1]\" is linked to \"$src_group\"");
    }
}

&logger(3,"Finished running $0");

#########################
# SUB: display usage information
sub usage {

print <<"EOT";

Script by Angelo Gargiulo, Sunrise Communications AG

This script looks for hosts & services which are linked to a specific contactgroup 
and selectively links these to a new contactgroup. The original contactgroup will remain linked.

This script is useful if you are looking for a way to move multiple items to a different contactgroup 
without changing any of the other host or service parameters.

Usage:
$0 -a current_group -b new_group [-x (1-5)] [-s]

Help:

  required 

  -a  Current contactgroup: the group to look for

  -b  New contactgroup: the new group to append to the hosts & services linked to the current group

  optional

  -x  Set a custom loglevel (1 = lowest, 5 = most verbose)

  -s  Simulate only. Do not make any actual modifications to the database.

EOT
exit;

}
