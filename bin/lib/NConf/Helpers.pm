##############################################################################
# "NConf::Helpers" library
# A collection of shared functions for the NConf Perl scripts.
# Miscellaneous functions used by multiple scripts.
#
# Version 0.2
# Written by Angelo Gargiulo
#
# Revision history:
# 2009-02-25 v0.1   A. Gargiulo   First release
# 2010-05-18 v0.2   A. Gargiulo   Modifications for DEBUG_GENERATE constant
#
##############################################################################

package NConf::Helpers;

use strict;
use Exporter;
use NConf;
use NConf::Logger;

##############################################################################
### I N I T ##################################################################
##############################################################################

BEGIN {
    use vars qw(@ISA @EXPORT @EXPORT_OK);

    @ISA         = qw(NConf);
    @EXPORT      = qw(@NConf::EXPORT readNConfConfig makeValuesDistinct replaceMacros);
    @EXPORT_OK   = qw(@NConf::EXPORT_OK);
}

##############################################################################
### S U B S ##################################################################
##############################################################################

sub readNConfConfig {
    &logger(5,"Entered readNConfConfig()");

    # SUB use: fetch configuration options from the main NConf configuration

    # SUB specs: ###################

    # Expected arguments:
    # 0: configuration file name and path
    # 1: configuration option (PHP constant) name
    # 2: type of value to read (scalar|array)
    # 3: optional: '1' = WARN instead of exiting with ERROR if option was not found in config

    # Return values:
    # 0: either a scalar or an array containing the values read from the config

    ################################

    # read arguments passed
    my $conf_file = shift;
    my $const     = shift;
    my $valtype   = shift;
    my $warn      = shift;

    unless($conf_file && $const && $valtype){&logger(1,"readNConfConfig(): Missing argument(s). Aborting.")}

    &logger(4,"Reading option $const from $conf_file");
    open(CONF,"$conf_file") or &logger(1,"Could not open configuration file $conf_file");
    my @conf = <CONF>;
    close(CONF);

    foreach(@conf){
        chomp $_;
        unless($_){next}

        if($valtype =~ /array/i){
            if($_ =~ /$const/ && $_ !~ /^\s*[#|\/\/]/){
                $_ =~ /$const\s*=\s*array\s*\((.+)\)/;
                my $string = $1;
                $string =~ s/['"]//g;
                my @parts = split(/\s*,\s*/, $string);
                return @parts;
            }
        }else{
            if($_ =~ /$const/ && $_ !~ /^\s*[#|\/\/]/){
                $_ =~ s/['"]//g;
                $_ =~ /$const\s*,\s*(.+)\s*\)/;
                my $string = $1;
                $string =~ s/^\s+//;
                $string =~ s/\s+$//;
                return $string;
            }
        }
    }
    if($warn eq "1"){
        &logger(2,"Could not find option $const in configuration file $conf_file");
    }else{
        &logger(1,"Could not find option $const in configuration file $conf_file");
    }
}

##############################################################################

sub makeValuesDistinct {
    &logger(5,"Entered makeValuesDistinct()");

    # SUB use: make sure that identical attribute values are combined to a single comma-separated string

    # SUB specs: ###################

    # Expected arguments:
    # 0: an array containing references to arrays that contain 'attr'->'value' pairs

    # Example:
    # arrayref -> array([0] "attr_X", [1] "value1")
    # arrayref -> array([0] "attr_X", [1] "value2")
    # arrayref -> array([0] "attr_Y", [1] "value3")
    # arrayref -> array([0] "attr_Y", [1] "value4")
    
    # Return values:
    # 0: an array containing references to arrays holding a comma separated list of values for each attr

    # Example:
    # arrayref -> array([0] "attr_X", [1] "value1,value2")
    # arrayref -> array([0] "attr_Y", [1] "value3,value4")

    ################################

    my %attrs;
    my $rowcount = 0;

    # do some very complex stuff with hashes and pointers
    # by manipulating content of the array that was passed;

    foreach my $row (@_){

        if(defined($attrs{$row->[0]})){

            # remove duplicate items
            if($attrs{$row->[0]}->[1] =~ /,\s*\Q$row->[1]\E\s*,/ 
               || $attrs{$row->[0]}->[1] =~ /^\s*\Q$row->[1]\E\s*,/ 
               || $attrs{$row->[0]}->[1] =~ /,\s*\Q$row->[1]\E\s*$/ 
               || $attrs{$row->[0]}->[1] =~ /^\s*\Q$row->[1]\E\s*$/ ){undef($row->[1])}

            # consolidate multi-line attributes into one
            if($attrs{$row->[0]}->[1] ne "" && $row->[1] ne ""){
                $attrs{$row->[0]}->[1]=$attrs{$row->[0]}->[1].",".$row->[1];
            }else{
                $attrs{$row->[0]}->[1]=$attrs{$row->[0]}->[1].$row->[1];
            }

            undef($_[$rowcount]);

        }else{
            $attrs{$row->[0]} = $row;
        }

        $rowcount++;
    }

    return(@_);
}

##############################################################################

sub replaceMacros {
    &logger(5,"Entered replaceMacros()");

    # SUB use: replace any %..% style NConf macros in a string with their respective value(s)

    # SUB specs: ###################

    # Expected arguments:
    # 0: the string to search for macros

    # Return values:
    # 0: the string with the replaced macros

    ################################

    # read arguments passed
    my $string = shift;

    # INFO:
    # This function looks for %...% style macros in any string that was passed to it. If it finds a macro, 
    # it will look for a replacement value in the globally defined hash '%NC_macro_values'. 
    # The hash is expected to have been defined in advance by the component calling the replaceMacros() function.

    # The hash structure is the following:
    # $NC_macro_values{'MACRO_NAME'} -> 'replacement value'

    if($string =~ /%\w+%/g){

        # available macro names defined here

        # %NAGIOS_SERVER_NAME%
        if($string =~ /%NAGIOS_SERVER_NAME%/ && !$NC_macro_values{'NAGIOS_SERVER_NAME'}){
            &logger(2,"Cannot replace \%NAGIOS_SERVER_NAME\%. The replacement value is undefined.");
        }
        $string =~ s/%NAGIOS_SERVER_NAME%/$NC_macro_values{'NAGIOS_SERVER_NAME'}/g;

        # add more macros here...

        # %NAGIOS...%
        # if($string =~ /%NAGIOS...%/ && !$NC_macro_values{'NAGIOS...'}){
        #     &logger(2,"Cannot replace \%NAGIOS...\%. The replacement value is undefined.");
        # }
        # $string =~ s/%NAGIOS...%/$NC_macro_values{'NAGIOS...'}/g;

    }
    return $string;
}

##############################################################################

1;

__END__

}
