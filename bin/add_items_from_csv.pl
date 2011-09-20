#!/usr/bin/perl

########################################################################################
# Author:       Angelo Gargiulo, Sunrise Communications AG
# Version:      0.3
# Description:  This script reads a CSV file and imports the content by creating new 
#               items in NConf.
#
# Revision history:
# 2009-??-??  v0.1    A. Gargiulo   Initial release
# 2010-12-14  v0.2    Y. Charton    Added -d option for choosing a csv delimiter
#                                   CSV syntax can now be defined within CSV file header
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
use vars qw($opt_c $opt_f $opt_x $opt_s $opt_d);
getopts('c:f:x:sd:');
unless($opt_c && $opt_f){&usage}
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
%main_hash = &parseCsv($opt_f, $opt_c, $csv_delimiter);

# loop through all items
foreach my $item (keys(%main_hash)){

    my $item_class = $opt_c;

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
This script reads a CSV file and imports the content by creating new items in NConf.

The attribute names must be specified within the header line of the CSV file.
You may specify any number of attributes you'd like to import (consider mandatory attributes).

Usage:
$0 -c class -f /path/to/file [-d <delimiter>] [-x (1-5)] [-s]

Help:

  required

  -c  Specify the class of items that you wish to import. Must correspond to an NConf class
      (e.g. "host", "service, "hostgroup", "checkcommand", "contact", "timeperiod"...)

  -f  The path to the file which is to be imported. CAUTION: Make sure you have
      only items of one class in the same file (e.g. "hosts", "services"...)
      Also make sure you import host- or service-templates separately ("host" or 
      "service" items containing a "name" attribute)

  optional

  -d  CSV file delimiter (default: ";")

  -x  Set a custom loglevel (1 = lowest, 5 = most verbose)

  -s  Simulate only. Do not make any actual modifications to the database.

EOT
exit;

}
