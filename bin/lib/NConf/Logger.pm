##############################################################################
# "NConf::Logger" library
# A collection of shared functions for the NConf Perl scripts.
# Log and display messages based on loglevel.
#
# Version 0.1
# Written by Angelo Gargiulo
#
# Revision history:
# 2009-02-26 v0.1   A. Gargiulo   First release
#
##############################################################################

package NConf::Logger;

use strict;
use Exporter;
use NConf;

##############################################################################
### I N I T ##################################################################
##############################################################################

BEGIN {
    use vars qw(@ISA @EXPORT @EXPORT_OK);

    @ISA         = qw(NConf);
    @EXPORT      = qw(@NConf::EXPORT logger);
    @EXPORT_OK   = qw(@NConf::EXPORT_OK);

}

##############################################################################
### S U B S ##################################################################
##############################################################################

sub logger {

    # SUB use: Log and display messages based on loglevel

    # SUB specs: ###################

    # Expected arguments:
    # 0: The loglevel of the message (1-5)
    # 1: The message to be logged
    # 2: Optional: the type of message (1=sql)

    # CAUTION: loglevel 1 is percieved to be "fatal". Execution will be terminated!

    ################################

    my $loglevel = 0;
    my $msg      = undef;
    my $msg_type = undef;

    $loglevel    = shift;
    $msg         = shift;
    $msg_type    = shift;

    unless($loglevel >= 1 && $loglevel <= 5){die "You passed a message with an invalid loglevel lo logger().\n"}
    unless($NC_loglevel >= 1 && $NC_loglevel <= 5){die "The main loglevel is not set properly (must be 1-5).\n"}

    # define labels for different loglevels
    my %level_labels = ( 1 => "ERROR", 2 => "WARN", 3 => "INFO", 4 => "DEBUG", 5 => "TRACE" );

    # fetch the name of the function which called logger()
    my $caller = (caller(1))[3];
    if($caller){$caller="|".$caller."| "}

    if($loglevel <= $NC_loglevel){
        
        my $space = "";
        if(length($level_labels{$loglevel}) == 4){$space=" "}

        # show the calling function, if loglevel is higher than 4
        if($NC_loglevel > 4){
            print "[$level_labels{$loglevel}]$space $caller";
        }else{
            print "[$level_labels{$loglevel}]$space ";

        }
        
        # special formating depending on message type
        if($msg_type == 1){
            print "\n    [SQL Query] $msg\n";
        }else{
            print "$msg\n";
        }

        # abort execution on fatal error!
        if($loglevel == 1){exit}
    }

}

##############################################################################

1;

__END__

}
