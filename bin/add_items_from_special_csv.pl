#!/usr/bin/perl

########################################################################################
# Author:       Angelo Gargiulo, Sunrise Communications AG
# Version:      0.3
# Description:  This script reads a CSV file and imports hosts / services by creating 
#               new items in NConf.
#
# Revision history:
# 2009-??-??  v0.1    A. Gargiulo   Initial release
# 2010-12-14  v0.2    Y. Charton    Added -d option for choosing a csv delimiter
#                                   Support of csv files with/without a header line
# 2011-03-08  v0.3    A. Gargiulo   Syntax must now be defined in CSV file header,
#                                   removed old @csv_syntax array.
#
########################################################################################

use strict;
use FindBin;
use lib "$FindBin::Bin/lib";

use NConf;
use NConf::DB;
use NConf::DB::Read;
use NConf::DB::Modify;
use NConf::Logger;
use NConf::ImportCsv;
use Getopt::Std;
use Tie::IxHash;    # preserve hash order

# read commandline arguments
use vars qw($opt_f $opt_x $opt_s $opt_d);
getopts('f:x:sd:');
unless($opt_f){&usage}
if($opt_x){&setLoglevel($opt_x)}
if($opt_s){&setDbReadonly(1)}

#set csv delimiter
my $csv_delimiter = ";";
if($opt_d){$csv_delimiter = "$opt_d"}

#########################
# MAIN

&logger(3,"Started executing $0");
&logger(4,"Current loglevel is set to $NC_loglevel");
if($NC_db_readonly == 1){
    &logger(3,"Running in simulation mode. No modifications will be made to the database!");
}

tie my %main_hash, 'Tie::IxHash';
%main_hash = &parseHostServiceCsv($opt_f, $csv_delimiter);

# loop through all items
foreach my $item (keys(%main_hash)){

    my $item_class = undef;
    if($main_hash{$item}->{'host_name'} && !$main_hash{$item}->{'service_description'}){$item_class = "host"}
    elsif($main_hash{$item}->{'service_description'}){$item_class = "service"}
    else{logger(1, "Failed to determine if current item is a host or a service. Aborting")}

    &logger(3,"Adding $item_class '$item'");

    tie my %item_hash, 'Tie::IxHash';
    %item_hash = %{$main_hash{$item}};

    if( &addItem($item_class, %item_hash) ){
        logger(3, "Successfully added $item_class '$item'");
    }else{
        logger(1, "Failed to add $item_class '$item'. Aborting");
    }
}

&logger(3,"Finished running $0");

#########################
# SUB: display usage information
sub usage {

print <<"EOT";

Script by Angelo Gargiulo, Sunrise Communications AG
This script reads a CSV file and imports hosts / services by creating new items in NConf.

The CSV file must have the following format:

host_name;attr 1;attr 2;service_description;attr 1;attr 2[;service_description;...;...]
<----------------------><--------------------------------><---------------------------->
     host attributes            service attributes              additional services

The attribute names must be specified within the header line of the CSV file.
You may specify any number of host and service attributes you'd like to import (consider mandatory attributes).
The import process will assume that any attributes which follow the "service_description" attribute 
belong to that service.

Usage:
$0 -f /path/to/file [-d <delimiter>] [-x (1-5)] [-s]

Help:

  required

  -f  The path to the CSV file which is to be imported.

  optional

  -d  CSV file delimiter (default: ";")

  -x  Set a custom loglevel (1 = lowest, 5 = most verbose)

  -s  Simulate only. Do not make any actual modifications to the database.

EOT
exit;

}
