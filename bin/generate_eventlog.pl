#!/usr/bin/perl

########################################################################################
# Description:
# This script gives quick access to items in the NConf database. 
# It is intended for debugging purposes, to get a quick overview of an item's attributes 
# and links to other items.
# It can be also used to export all or a part of the items of a specified class. 
# Supported export formats are CSV and Nagios config file format (note that this script 
# is not a replacement for generating the actual config!)
########################################################################################
# Version 1.3.1
# Angelo Gargiulo
########################################################################################
# Revision history:
# 2010-04-22  v1.0    A. Gargiulo   Initial release
# 2010-11-12  v1.1    Y. Charton    Added attribute filter keywords: NAGIOS and VISIBLE
#                                   Added argument -s for CSV separator
#                                   Small fix to handle 'null' attribute names returned
#                                   by the getItemsLinked() function in NConf 1.2.6
# 2011-03-08  v1.2    A. Gargiulo   Renamed -s argument to -d (for "delimiter")
# 2011-03-08  v1.3    A. Gargiulo   Added special processing for services that might 
#                                   be linked to other items (add hostname to the list)
# 2011-06-16  v1.3.1  Y. Charton    Added verbosity 
#
########################################################################################

use strict;
use FindBin;
use lib "$FindBin::Bin/lib";

use NConf;
use NConf::DB;
use NConf::DB::Read;
use NConf::Logger;
use NConf::Helpers;
use Getopt::Std;
use Tie::IxHash;    # preserve hash order
use Cwd;
#use Data::Dumper;
#use feature 'say';

# read commandline arguments
use vars qw($opt_c $opt_i $opt_r $opt_a $opt_f $opt_e $opt_d $opt_h $opt_x);
getopts('c:i:r:a:d:x:feh');

if($opt_h){&usage}

if($opt_x){&setLoglevel($opt_x)}

sub del_double{
	my %all=();
	@all{@_}=1;
	return (keys %all);
}

sub getAllHostsOfHostgroup {
	&logger(5,"Entered getAllHostsOfHostgroup");
        my $hostgroupID = shift;
	my @hosts;
	my @sub_hostgroups = getItemsLinked($hostgroupID);
	foreach my $sub_hostgroup (@sub_hostgroups) {
		if ($sub_hostgroup->[0] eq "hostgroup_members"){
			@hosts = (@hosts,getAllHostsOfHostgroup($sub_hostgroup->[3]));
		}
		if ($sub_hostgroup->[0] eq "members"){
			push(@hosts,$sub_hostgroup->[1]);
		}
	}
	@hosts = del_double(@hosts); 
	return @hosts;
}
	
# Alle eventIDs aus Datnbank holen
my @events = getItems("eventID", 1);
my %events;
my $events = %events;
my @myevents;
foreach my $event (@events) {
	my @childs = getItemsLinked($event->[0]);
	my @hosts = ();
	my @direct_hosts = ();
	my $eventdata = {};
	my $eventlogname;
	foreach my $child (@childs){
		if ($child->[0] eq "hostgroup_name") { @hosts = (@hosts,getAllHostsOfHostgroup($child->[3])); }
		if ($child->[0] eq "host_name") { push(@direct_hosts,$child->[1])};
		if ($child->[0] eq "eventlog_name") { $eventlogname = $child; }
	}
		print ref(@direct_hosts);
	$eventdata->{data} = [ getItemData($event->[0]), $eventlogname ];
	$eventdata->{hosts_from_hostgroups} = [ @hosts ];
	$eventdata->{hosts} = [ @direct_hosts ];
	push(@myevents,$eventdata); 
}

my $output = {};
my @types = ( "hosts_from_hostgroups", "hosts" );
# Einmal für Hostgruppen, dann für Hosts die Daten zusammenbauen. Dadurch ergibt sich die Möglichkeit auf Hostebene Änderungen zu überschreiben
foreach my $type (@types){
	foreach my $event (@myevents) {
		my $eventid = "";
		my $eventsource = "";
		my $eventmessage = "";
		my $result = "";
		my $eventlogname = "";
		foreach my $data (@{$event->{data}}){
			if ($data->[0] eq "result"){ $result = $data->[1];}
			if ($data->[0] eq "event_id"){ $eventid = $data->[1];}
			if ($data->[0] eq "event_source"){ $eventsource = $data->[1];}
			if ($data->[0] eq "event_msg"){ $eventmessage = $data->[1];}
			if ($data->[0] eq "eventlog_name"){ $eventlogname = $data->[1]};
		}
		foreach my $data ($event->{$type}){
			foreach my $host (@$data){
				$output->{$host}->{$eventlogname}->{$eventid}->{$eventsource}->{$eventmessage} = $result;
			}
		}
	}
}

my $root_path =  &readNConfConfig(NC_CONFDIR."/nconf.php","NCONFDIR","scalar");
my $output_path = "$root_path/eventlog_output";

# Ausgabeverzeichnis leeren
unlink (glob("$output_path/*.conf"));

# pro Host eine Konfigurationsdatei schreiben
foreach my $host (keys %$output){
	my $ohost;
	if ($host eq "Gebäudeleittechnik Server"){
		$ohost = "npg-ulm-ser-190";
	} else {
		$ohost = $host;
	}
	open (FILE, ">>$output_path/$ohost.conf");
	print FILE "\@searches = (\n";
	print FILE "# Autogeneriert für $host ($ohost)\n";
	# Jeden Eventlogtyp einzeln abgrasen
	foreach my $eventlog (keys %{$output->{$host}}){
		my @ok, my @critical, my @warning;
		# Ein letztes Mal Daten umsortieren
		foreach my $id (keys %{$output->{$host}->{$eventlog}}) {
			foreach my $source (keys %{$output->{$host}->{$eventlog}->{$id}}) {
				foreach my $message (keys %{$output->{$host}->{$eventlog}->{$id}->{$source}}) {
					my $result = $output->{$host}->{$eventlog}->{$id}->{$source}->{$message};
					my $out = "";
					my $space = "";
					unless($id eq ".*") { $out = "id:$id"; $space = " "; }
					unless($source eq ".*") { $out = "$out${space}so:$source"; $space = " ";}
					unless($message eq ".*") {
						if ($space eq " ") { $out = "$out.*"; }
						$out = "$out$message";
					}
					if ($out eq ""){ $out = ".*"; }
					if ($result eq "OK"){ push(@ok, $out)};
					if ($result eq "Warning"){ push(@warning, $out)};
					if ($result eq "Critical"){ push(@critical, $out)};
				}
			}
		}
		print FILE "{\n\ttype => 'eventlog',\n\ttag => '$eventlog',\n\teventlog => {\n\t\teventlog => '$eventlog',\n\t\tinclude => { eventtype => 'error,warning', },\n\t},\n\t";
		print FILE "options => 'lookback=3d,preferredlevel=critical,eventlogformat=\"id:%i so:%s ca:%c msg:%m\"',\n";
		# Wenn für einen Host gar nichts definiert ist, Standard Warning. Tritt aber glaube nie ein.
		if (@ok+@warning+@critical eq 0) { 
			print FILE "\twarningpatterns => [\n";
			print FILE "\t'.*'\n";
			print FILE "\t],\n";
		}
		if (@ok > 0) {
			print FILE "\twarningexceptions => [\n";
			foreach my $str (@ok){
				print FILE "\t\t'$str',\n";
			}
			print FILE "\t],\n";
		}
		if (@warning > 0) {
			print FILE "\twarningpatterns => [\n";
			foreach my $str (@warning){
				print FILE "\t\t'$str',\n";
			}
			print FILE "\t],\n";
		}
		if (@critical > 0) {
			print FILE "\tcriticalpatterns => [\n";
			foreach my $str (@critical){
				print FILE "\t\t'$str',\n";
			}
			print FILE "\t],\n";
		}
		print FILE "},\n";
	}
	print FILE ")\n";
}
exit 0;
