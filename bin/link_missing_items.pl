#!/usr/bin/perl

use strict;
use FindBin;
use lib "$FindBin::Bin/lib";

use NConf;
use NConf::DB;
use NConf::DB::Read;
use NConf::DB::Modify;
use NConf::Logger;
use NConf::ImportNagios;
use Getopt::Std;

# read commandline arguments
use vars qw($opt_c $opt_f $opt_l $opt_a $opt_g $opt_x $opt_s);
getopts('c:n:f:l:a:g:x:s');
unless($opt_c && $opt_f && $opt_l && $opt_a){&usage}
if($opt_x){&setLoglevel($opt_x)}
if($opt_s){&setDbReadonly(1)}

$opt_c =~ s/^\s*//;
$opt_c =~ s/\s*$//;

#########################
# MAIN

&logger(3,"Started executing $0");
&logger(4,"Current loglevel is set to $NC_loglevel");
if($NC_db_readonly == 1){
    &logger(3,"Running in simulation mode. No modifications will be made to the database!");
}

my %main_hash = &parseNagiosConfigFile($opt_c, $opt_f);

# loop through all items
foreach my $item (keys(%main_hash)){

    my @item_link_items = split(/\s*,\s*/, $main_hash{$item}->{$opt_a});

    # if arg "-g" was specified, remove any non-specified items from the list
    if($opt_g){
        my @temp_items;
        my @limit_items = split(/\s*,\s*/, $opt_g);
        foreach my $l_item (@item_link_items){
            foreach (@limit_items){
                if($l_item eq $_){push(@temp_items, $_)}
            }
        }
        @item_link_items = @temp_items;
    }

    unless($item_link_items[0]){
        &logger(1,"Could not find any of the items to be linked. Make sure the args -l, -a and -g are correct and the items exist.")
    }

    # fetch the ID of the item from the database
    my $item_id = undef;
    if($opt_c =~ /^service$/i){
        my @srv_parts = split(/;;/, $item);
        my $parent_host_id = &getItemId($srv_parts[0],'host');
        $item_id = &getServiceId($srv_parts[1],$parent_host_id);
    }
    else{$item_id = &getItemId($item,$opt_c)}

    unless($item_id){&logger(1,"Could not get item id for $opt_c '$item'. Aborting.")}

    # fetch the ID of each item to be linked
    foreach my $l_item (@item_link_items){
        my $l_item_id = &getItemId($l_item,$opt_l);
        unless($l_item_id){&logger(1,"Could not get item id for $opt_l '$l_item'. Aborting.")}
            
        # check if item and contactgroup are linked
        my $items_linked = &checkItemsLinked($item_id, $l_item_id, $opt_a);

        if($items_linked eq "false"){

            # link items, if they weren't already linked
            &logger(4,"$opt_c '$item' and $opt_l '$l_item' not linked yet. Proceeding.");
            my $res = &linkItems($item_id, $l_item_id, $opt_a);
            if($res eq 'true'){
                &logger(3,"Successfully linked $opt_c '$item' with $opt_l '$l_item'");
            }else{
                &logger(1,"Unable to link $opt_c '$item' with $opt_l '$l_item'. Aborting.");
            }

        }elsif($items_linked eq "true"){
            &logger(3,"$opt_c '$item' and $opt_l '$l_item' seem to be already linked. Skipping.");
        }else{
            &logger(1,"Failed to check if $opt_c '$item' and $opt_l '$l_item' are linked. Aborting.");
        }
    }
}

&logger(3,"Finished running $0");

#########################
# SUB: display usage information
sub usage {

print <<"EOT";

Script by Angelo Gargiulo, Sunrise Communications AG
This script reads an existing Nagios configuration file and checks if an item, which should
be linked to another one, is actually linked in the database. If not, it links them.

E.g: A host is linked to a timepieriod via the "notification_period" attr. This script checks
if there really is a link between these two items in the database. If not, it will create the link.

Usage:
$0 -c class -f /path/to/file -l class -a attr_name [-g item1,item2,...] [-x (1-5)] [-s]

Help:

  required

  -c  Specify the class of items that you wish to check. Must correspond to an NConf class
      (e.g. "host", "service, "hostgroup", "checkcommand", "contact", "timeperiod"...)

  -f  The path to the Nagios config file which is to be parsed. CAUTION: Make sure you have
      only items of one class in the same file (e.g. "hosts.cfg", "services.cfg"...)

  -l  Specify the class that the checked items should be linked to. Must correspond to an NConf class
      (e.g. "host", "service, "hostgroup", "checkcommand", "contact", "timeperiod"...)

  -a  The name of the 'linking attribute' (the attribute used to link the checked item with another one)

  optional

  -g  Restrict the items that should be checked/linked. By default, all items contained in the
      linking attr will be used.

  -x  Set a custom loglevel (1 = lowest, 5 = most verbose)

  -s  Simulate only. Do not make any actual modifications to the database.

EOT
exit;

}
