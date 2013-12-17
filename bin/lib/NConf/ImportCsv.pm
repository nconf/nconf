##############################################################################
# "NConf::ImportCsv" library
#
# Author:       Angelo Gargiulo, Sunrise Communications AG
# Version:      0.3
# Description:  A collection of shared functions for the NConf Perl scripts.
#               Functions needed to parse CSV files.
#
# Revision history:
# 2009-06-17  v0.1    A. Gargiulo   First release
# 2010-12-14  v0.2    Y. Charton    * Csv file parsing is now handled by 
#                                     Text::CSV_XS. Should be more robust.
#                                   * Support of csv files with/without a header 
#                                     line. The csv syntax from a header line 
#                                     takes precedence over a csv syntax defined 
#                                     in the import script.
#                                   * pre-checks on the csv syntax
# 2011-03-10  v0.3    Y. Charton    * Removed old import functionality. Syntax must 
#                                     now be defined in CSV file header
#
##############################################################################

package NConf::ImportCsv;

use strict;
use Exporter;
use NConf;
use NConf::Logger;
use NConf::DB;
use NConf::DB::Read;
use Tie::IxHash;    # preserve hash order
use Text::CSV_XS;

##############################################################################
### I N I T ##################################################################
##############################################################################

BEGIN {
    use vars qw(@ISA @EXPORT @EXPORT_OK);

    @ISA         = qw(NConf);
    @EXPORT      = qw(@NConf::EXPORT parseCsv parseHostServiceCsv);
    @EXPORT_OK   = qw(@NConf::EXPORT_OK);
}

##############################################################################
### S U B S ##################################################################
##############################################################################

sub parseCsv {
    &logger(5,"Entered parseCsv()");

    # SUB use: parse a CSV file, load data into memory

    # SUB specs: ###################

    # Expected arguments:
    # 0: CSV file to parse
    # 1: the NConf class name of items to parse for
    # 2: optional: the CSV delimiter (defaults to ";")

    # Return values:
    # 0: Returns a hash containing all naming-attr values as the key,
    #    and a reference to a hash containing any attr->value pairs as the value

    ################################

    # read arguments passed
    my ($csv_file,$input_class,$csv_delim) = @_;
    &logger(5,"Parameters - File: $csv_file, Class: $input_class, CSV_delim: $csv_delim");

    # process arguments
    defined($csv_delim) ? {&logger(5,"Parameters - csv_delim: $csv_delim")} : {$csv_delim=';'};
    unless($csv_file){&logger(1,"Missing argument(s). Aborting.")};
    
    #----------------------------
    # Looking for the CSV file syntax
    #----------------------------

    my @csv_syntax;
    my $csv_syntax_found = 0;  # 0: not found
                               # 1: found in csv file
        
    # Try first to find a header line in the csv file
    &logger(5,"Looking for CSV syntax in file header");
    my $csv = Text::CSV_XS->new ({sep_char => "$csv_delim", binary => 1, eol => $/ }) or
        &logger(1,"Cannot use CSV: ".Text::CSV->error_diag()."\nAborting.");
    if (open (my $fh, "<", $csv_file)){
        my $first_row = $csv->getline($fh);
        if (defined $first_row){
            @csv_syntax = @{$first_row};
            # Check that the first row is a csv file header
            if ((grep /^name$/, @csv_syntax) ||  (grep /_name$/, @csv_syntax) 
                ||  (grep /^icon_image_alt$/, @csv_syntax) ||  (grep /^service_description$/, @csv_syntax)){
                &logger(3,"CSV syntax found in file header. Using it.");
                $csv_syntax_found = 1;
            }
            else {
                &logger(1,"CSV syntax not found in file header. Aborting."); 
            }
        }else{
            &logger(1,"Problem parsing the CSV file. Check the file format and the CSV delimiter used. Aborting.");
        }
        close $fh;
    }else{
        &logger(1,"Problem opening the CSV file $csv_file: $!");
    }
    
    #----------------------------
    # Processing the CSV file
    #----------------------------
    
    tie my %main_hash, 'Tie::IxHash';
    my $inputline = 1;

    if($csv_syntax_found){
        &logger(5,"CSV syntax is defined, proceeding with the import");
        # Csv syntax is defined, proceeding with the import
        my $csv = Text::CSV_XS->new ({sep_char => "$csv_delim", binary => 1, eol => $/ }) or
            &logger(1,"Cannot use CSV: ".Text::CSV->error_diag()."\nAborting.");
        open (my $fh, "<", $csv_file) or
            &logger(1,"Cannot open file $csv_file: $!");

        &logger(5,"Skipping header line");
        $csv->getline($fh);
        $inputline++;
            
        # Fetch naming attr for the current class
        my $naming_attr = &getNamingAttr($input_class);
        unless($naming_attr){&logger(1,"Cannot fetch naming attr for class '$input_class'. Aborting.")}
        
        while (my $row = $csv->getline($fh)) {
            
            # check the validity of the line according to the number of fields in the csv syntax
            my @row_array = @$row;
            if($#row_array != $#csv_syntax){
                &logger(2,"Cannot process CSV input line $inputline: the number of fields doesn't match the CSV syntax. Skipping the line.");
                $inputline++;next;
            }
            
            # do CSV-data -> NConf-attrs mapping
            tie my %line_hash, 'Tie::IxHash';
            my $host_name = undef;
            my $srv_name  = undef;
            my $naming_attr_value = undef;
            foreach my $attr (@csv_syntax) {
                # check if attribute is defined twice for the same item
                if($line_hash{$attr}){
                    &logger(2,"Attribute '$attr' is defined more than once (on input line $inputline). Using last instance.");
                }
    
                # add each item to the line-hash according to the specified CSV syntax
                my $value = shift @$row;
                if ($value || length $value) {
                    $line_hash{$attr} = $value;
                }
    
                # fetch and store some values separately
                if($attr eq $naming_attr){$naming_attr_value = $line_hash{$attr}}
                if($attr eq "host_name"){$host_name = $line_hash{$attr}}
                if($attr eq "service_description"){$srv_name = $line_hash{$attr}}
    
                # look for templates in host / service definitions
                if($attr =~ /^name$/i && ($input_class =~ /^service$/i || $input_class =~ /^host$/i)){
                    &logger(1,"The input file seems to contain host- or service-templates.\nMake sure you import templates separately! Do not combine them with other host / service items. Aborting.");
                }
            }
    
            # abort if the value for the naming attr could not be determined
            unless($naming_attr_value){
                &logger(1,"Could not locate '$naming_attr' for $input_class (on input line $inputline). Aborting.");
            }

            &logger(5,"Parsing attributes of $input_class '$naming_attr_value'");
    
            if($input_class eq "service"){
                # abort if hostname could not be determined for a service
                unless($host_name){&logger(1,"Could not locate 'host_name' for service (on input line $inputline). Aborting.");
                }
    
                # check if service already exists in global hash
                if($main_hash{"$host_name;;$srv_name"}){
                    &logger(2,"Service '$srv_name' is defined more than once for the same host (on input line $inputline).");
                }

                # write line-hash reference to global hash, using the service name + hostname as key
                $main_hash{"$host_name;;$srv_name"} = \%line_hash;

            }else{
                # check if item already exists in global hash
                if($main_hash{$naming_attr_value}){
                        &logger(2,"$input_class '$naming_attr_value' is defined more than once (on input line $inputline).");
                }
                # write line-hash reference to global hash, using the naming attr value as key
                $main_hash{$naming_attr_value} = \%line_hash;
            }
            $inputline++; 
        }
        close $fh;
    }else{
        &logger(1,"Unable to detect CSV syntax or correct CSV format. Aborting.");
    }
    return %main_hash;
}

##############################################################################

sub parseHostServiceCsv {
    &logger(5,"Entered parseHostServiceCsv()");

    # SUB use: parse a CSV file containing host/service information, load data into memory

    # SUB specs: ###################

    # The CSV file must have the following format:

    # host_name;attr 1;attr 2;service_description;attr 1;attr 2[;service_description;...;...]
    # <----------------------><--------------------------------><---------------------------->
    #      host attributes            service attributes              additional services

    # The attribute names must be specified within the header line of the CSV file.
    # You may specify any number of host and service attributes you'd like to import (consider mandatory attributes).
    # The import process will assume that any attributes which follow the "service_description" attribute 
    # belong to that service.

    # Expected arguments:
    # 0: CSV file to parse
    # 1: optional: the CSV delimiter (defaults to ";")

    # Return values:
    # 0: Returns a hash containing all naming-attr values as the key,
    #    and a reference to a hash containing any attr->value pairs as the value

    ################################

    # read arguments passed
    my ($csv_file,$csv_delim) = @_;
    &logger(5,"parseHostServiceCsv(): parameters - file: $csv_file");

    # process arguments
    defined($csv_delim) ? {&logger(5,"parseHostServiceCsv(): parameters - csv_delim: $csv_delim")} : {$csv_delim=';'};
    unless($csv_file){&logger(1,"parseHostServiceCsv(): Missing argument(s). Aborting.")};

    #----------------------------
    # Looking for the CSV file syntax
    #----------------------------

    my @host_syntax;
    my @srv_syntax;
    my $csv_syntax_found = 0;  # 0: not found
                               # 1: found in csv file
        
    # Try first to find a header line in the csv file
    &logger(5,"Looking for CSV syntax in file header");
    my $csv = Text::CSV_XS->new ({sep_char => "$csv_delim", binary => 1, eol => $/ }) or
        &logger(1,"Cannot use CSV: ".Text::CSV->error_diag()."\nAborting.");
    if (open (my $fh, "<", $csv_file)){
        my $first_row = $csv->getline($fh);
        if (defined $first_row){
            my @csv_syntax = @{$first_row};
            # Check that the first row is a csv file header
            if ((grep /^host_name$/, @csv_syntax) && (grep /^service_description$/, @csv_syntax)){
                &logger(3,"CSV syntax found in file header. Using it.");
                $csv_syntax_found = 1;
                # Extracting host and service csv syntax from csv file
                my $extracting = "";
                foreach(@csv_syntax) {
                    if(($_ eq "host_name") && ($#host_syntax == -1)){
                        $extracting = "host";
                    }elsif(($_ eq "service_description") && ($#srv_syntax == -1)){
                        $extracting = "service";
                    }
                    if($extracting eq "host"){
                        push(@host_syntax, $_);
                    }elsif($extracting eq "service"){
                        push(@srv_syntax, $_);
                    }
                }
            }
            else {
                &logger(1,"CSV syntax not found in file header. Aborting."); 
            }
        }else{
            &logger(1,"Problem parsing the CSV file. Check the file format and the CSV delimiter used. Aborting.");
        }
        close $fh;
    }else{
        &logger(1,"Problem opening the CSV file $csv_file: $!");
    }
    
    #----------------------------
    # Processing the CSV file
    #----------------------------

    tie my %main_hash, 'Tie::IxHash';
    my $inputline = 1;

    if($csv_syntax_found){
        &logger(5,"CSV syntax is defined, proceeding with the import");
        # Csv syntax is defined, proceeding with the import
        my $csv = Text::CSV_XS->new ({sep_char => "$csv_delim", binary => 1, eol => $/ }) or
            &logger(1,"Cannot use CSV: ".Text::CSV->error_diag()."\nAborting.");
        open (my $fh, "<", $csv_file) or
            &logger(1,"Cannot open file $csv_file: $!");

        &logger(5,"Skipping header line");
        $csv->getline($fh);
        $inputline++;
        
        while (my $row = $csv->getline($fh)) {

            # check the validity of the line according to the number of fields in the csv syntax
            my @row_array = @$row;
            if($#row_array < ($#host_syntax + $#srv_syntax)){
                &logger(2,"Cannot process CSV input line $inputline: the number of fields doesn't match the CSV syntax. Skipping the line.");
                $inputline++;next;
            }

            ##### process host data
            
            # do CSV-data -> NConf-attrs mapping for host related data
            tie my %host_hash, 'Tie::IxHash';
            my $attr_count = 0;
            my $host_name  = undef;
            my $host_cgroup  = undef;
            my $host_cperiod  = undef;
            my $host_nperiod  = undef;
            foreach my $attr (@host_syntax){

                # check if attribute is defined twice for the same host
                if($host_hash{$attr}){&logger(2,"Attribute '$attr' is defined more than once (on input line $inputline). Using last instance.");
                }

                # add each item to the host-hash according to the specified host syntax
                my $value = shift @$row;
                if ($value || length $value) {
                    $line_hash{$attr} = $value;
                }

                # fetch and store some values separately
                if($attr eq "host_name"){$host_name = $host_hash{$attr}}
                if($attr eq "contact_groups"){$host_cgroup = $host_hash{$attr}}
                if($attr eq "check_period"){$host_cperiod = $host_hash{$attr}}
                if($attr eq "notification_period"){$host_nperiod = $host_hash{$attr}}
                $attr_count++;

            }

            # abort if hostname could not be determined
            unless($host_name){&logger(1,"Could not locate 'host_name' for host (on input line $inputline). Aborting.");
            }

            &logger(5,"Parsing attributes of host '$host_name'");

            # check if host already exists in global hash
            if($main_hash{$host_name}){&logger(2,"Host '$host_name' is defined more than once (on input line $inputline).");
            }

            # write host-hash reference to global hash, using the hostname as key
            $main_hash{$host_name} = \%host_hash;

            ##### process service data

            # do CSV-data -> NConf-attrs mapping for service related data
            tie my %srv_hash, 'Tie::IxHash';

            my $srv_name  = undef;
            my $num_elements = @srv_syntax; # count the amount of service attributes
            while (@$row){

                tie my %srv_attr_hash, 'Tie::IxHash';

                foreach my $attr (@srv_syntax){

                    # check if attribute is defined twice for the same service
                    if($srv_attr_hash{$attr}){&logger(2,"Attribute '$attr' is defined more than once for the same service (on input line $inputline). Using last instance.");
                    }

                    # add each item to the service-hash according to the specified service syntax
                    my $value = shift @$row;
                    if ($value || length $value) {
                        $srv_attr_hash{$attr} = $value;
                        # fetch and store service name separately
                        if($attr eq "service_description"){$srv_name = $value}
                    }

                }

                # add "host_name" attr to all services
                $srv_attr_hash{"host_name"} = $host_name;

                # abort if service name could not be determined
                unless($srv_name){
                    &logger(2,"Could not locate 'service_description' for service (on input line $inputline). Skipping service.");
                    next;
                }

                # make service inherit "contact_groups", "check_period" and "notification_period", if not defined explicitely
                unless($srv_attr_hash{"contact_groups"}){
                    &logger(4,"Could not locate 'contact_groups' for service (on input line $inputline). Inheriting value from host.");
                    $srv_attr_hash{"contact_groups"} = $host_cgroup;
                }
                unless($srv_attr_hash{"check_period"}){
                    &logger(4,"Could not locate 'check_period' for service (on input line $inputline). Inheriting value from host.");
                    $srv_attr_hash{"check_period"} = $host_cperiod;
                }
                unless($srv_attr_hash{"notification_period"}){
                    &logger(4,"Could not locate 'notification_period' for service (on input line $inputline). Inheriting value from host.");
                    $srv_attr_hash{"notification_period"} = $host_nperiod;
                }

                $srv_hash{$srv_name} = \%srv_attr_hash;

                &logger(5,"Parsing attributes of service '$srv_name'");

                # check if service already exists in global hash
                if($main_hash{"$host_name;;$srv_name"}){
                    &logger(2,"Service '$srv_name' is defined more than once for the same host (on input line $inputline).");
                }

                # write service-hash reference to global hash, using the service name as key
                $main_hash{"$host_name;;$srv_name"} = ${srv_hash{$srv_name}};
            }
            $inputline++; 
        }
        close $fh;
    }else{
        &logger(1,"Unable to detect CSV syntax or correct CSV format. Aborting.");
    }
    return %main_hash;
}

##############################################################################

1;

__END__

}
