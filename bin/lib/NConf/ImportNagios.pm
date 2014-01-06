##############################################################################
# "NConf::ImportNagios" library
# A collection of shared functions for the NConf Perl scripts.
# Functions needed to parse existing Nagios configuration files.
#
# Version 0.1.2
# Written by Angelo Gargiulo
#
# Revision history:
# 2009-02-25 v0.1.0   A. Gargiulo   First release
# 2011-01-31 v0.1.1   Y. Charton    Handle comments in the imported config
#                                   Superseded Nagios attribute substitution
# 2011-12-03 v0.1.2	  A. Gargiulo	Changed the way the input file is read
#									(might require more memory for large files)
#
##############################################################################

package NConf::ImportNagios;

use strict;
use Exporter;
use NConf;
use NConf::Logger;
use NConf::DB;
use NConf::DB::Read;
use Tie::IxHash;    # preserve hash order

##############################################################################
### I N I T ##################################################################
##############################################################################

BEGIN {
    use vars qw(@ISA @EXPORT @EXPORT_OK);

    @ISA         = qw(NConf);
    @EXPORT      = qw(@NConf::EXPORT parseNagiosConfigFile);
    @EXPORT_OK   = qw(@NConf::EXPORT_OK);
}

##############################################################################
### S U B S ##################################################################
##############################################################################

sub parseNagiosConfigFile {
    &logger(5,"Entered parseNagiosConfigFile()");

    # SUB use: parse a Nagios config file, load data into memory

    # SUB specs: ###################

    # Expected arguments:
    # 0: NConf class name of items to parse for
    # 1: File to parse

    # Return values:
    # 0: Returns a hash containing all naming-attr values as the key, 
    #    and a reference to a hash containing any attr->value pairs as the value

    ################################

    # read arguments passed
    my $input_class = shift;
    my $input_file  = shift;

    unless($input_class && $input_file){&logger(1,"parseNagiosConfigFile(): Missing argument(s). Aborting.")}

    # clean up arguments passed
    $input_class =~ s/^\s*//;
    $input_class =~ s/\s*$//;

    # Fetch naming attr for the current class
    my $naming_attr = &getNamingAttr($input_class);
    unless($naming_attr){&logger(1,"Cannot fetch naming attr for class '$input_class'. Aborting.")}

    tie my %main_hash, 'Tie::IxHash';
    my %conf_attrs = &getConfigAttrs();
    my $file_class = undef;
    my $filepos = 1;

    &logger(4,"Opening and parsing input file $input_file");
    open(LIST, $input_file) or &logger(1,"Could not read from $input_file. Aborting.");

    # unset input record separator (read whole file at once!)
	$/ = undef;
	#my @blocks = split(/[^#\s]\s*\n\s*define\s+/, <LIST>); # 20120131 A. Gargiulo changed this regex
	my @blocks = split(/\n[ \r\t\f]*define[ \r\t\f]*/, <LIST>); # CAUTION: changing this regex might mess up the linecount mechanism! 

	foreach (@blocks){

        # count amount of lines in current block
        my $linecount = 1;
        while($_ =~ /\n/g){$linecount++}
        my $filepos_new = $filepos + $linecount;

        # skip empty blocks and commented blocks
        chomp $_;
        unless($_){$filepos=$filepos_new;next}
        if($_ =~ /^\s*$/){$filepos=$filepos_new;next}
        if($_ =~ /^\s*#/){$filepos=$filepos_new;next}
        my $block = $_;

        # check if more than one class type is defined within the input file
        $block =~ /\s*([A-Za-z0-9_-]+)\s*{/;
        if($file_class && $file_class ne $1){
            &logger(1,"The input file contains more than one class of items (starting at line $filepos).\nMake sure only '$input_class' items are defined. Aborting."); 
        }
        $file_class = $1;

        # clean up current block (remove empty lines and trailing spaces/comments)
        $block =~ s/\n\s*#.*//g;
        $block =~ s/\n*.*\{//;
        $block =~ s/\s*\}[^"']*\n*//;
        $block =~ s/^\s*\n//;
        $block =~ s/\n\s*\n/\n/g;
        $block =~ s/#.*\n/\n/;

        my @lines = split(/\n/, $block);
        tie my %block_hash, 'Tie::IxHash';
        my $block_naming_attr = undef;
        my $service_parent_attr = undef;

        # process each line of the current block
        foreach my $line (@lines){

            # clean up current line
            $line =~ s/;.*//g;
            $line =~ s/^\s*//g;
            $line =~ s/\s*$//g;
            if($line =~ /^\s*#/ || $line eq ""){next}

            $line =~ /^([^\s]+)\s+(.+)$/;
            my $attr  = $1;
            my $value = $2;

            # skip importing empty attributes
            if($attr eq "" or $value eq ""){
                #&logger(1,"Problem reading some attrs/values for $input_class (starting at line $filepos). Aborting.");
                next;
            }

            # look for Nagios 2.x properties which have changed in Nagios 3.x
            my $attr_rep = "";
            if($attr eq "normal_check_interval"){$attr_rep = "check_interval"}
		    elsif($attr eq "retry_check_interval"){$attr_rep = "retry_interval"}

            if($attr_rep ne "") {
                &logger(3,"Superseded Nagios property found. Replacing '$attr' with '$attr_rep'.");
                $attr = $attr_rep;
            }
            
            # look for multiple identical attribute definitions
            if($block_hash{$attr}){
                &logger(2,"'$attr' is defined more than once for $input_class (starting at line $filepos). Using last instance.");
            }

            # push all attributes and their values into a hash (make distinct)
            $block_hash{$attr} = $value;

            # determine the naming attr in the current block
            if($attr eq $naming_attr){$block_naming_attr=$value}
            if($input_class =~ /^service$/i){
                if($attr =~ /^host_name$/i){$service_parent_attr=$value}
            }
            # look for templates in host / service definitions
            if($attr =~ /^name$/i && ($input_class =~ /^service$/i || $input_class =~ /^host$/i)){
                &logger(1,"The input file seems to contain host- or service-templates (starting at line $filepos).\nMake sure you import templates separately! Do not combine them with other host / service items. Aborting.");
            }
        }

        # if no naming attr was found in the current block
        unless($block_naming_attr){

            # check if the naming attr for the current class is an NConf internal attribute (true for dependencies, escalations etc.)
            if($conf_attrs{$input_class}->{$naming_attr}->{'write_to_conf'} eq "no" && $input_class ne "advanced-service"){
                # if so, generate a unique name for the item to be imported
                $block_naming_attr = &getUniqueNameCounter($input_class, "imported_$input_class");
                $block_hash{$naming_attr} = $block_naming_attr;

            }elsif($input_class eq "advanced-service"){
                # for advanced-services, set the naming attr to match the service_description (if available), 
                # run getUniqueNameCounter() to add a numeric counter, if necessary
		if($block_hash{'service_description'} ne ""){
                	$block_naming_attr = &getUniqueNameCounter($input_class, $block_hash{'service_description'});
	                $block_hash{$naming_attr} = $block_naming_attr;
		}else{
			# auto-generate a service_description in case none was defined 
			# (might be necessary in cases where the service_description is inherited from a service-template)
			# however, since the service_description attribute is marked as mandatory, the import will fail unless the falg is changed
                	$block_naming_attr = &getUniqueNameCounter($input_class, "imported_$input_class");
	                $block_hash{$naming_attr} = $block_naming_attr;
		}

            }else{
                # in any other case, exit with an error
                &logger(1,"Could not locate '$naming_attr' for $input_class (starting at line $filepos). Aborting.");
            }
        }

        if(!$service_parent_attr && $input_class =~ /^service$/i){
            &logger(1,"Could not locate 'host_name' attr for service (starting at line $filepos). Aborting.");
        }


        # write block hash reference to global hash, using the naming attr as key
        if($input_class =~ /^service$/i){
            &logger(5,"Parsing attributes of $input_class '$service_parent_attr;;$block_naming_attr'");
            if($main_hash{"$service_parent_attr;;$block_naming_attr"}){
                &logger(2,"$input_class '$service_parent_attr: $block_naming_attr' (starting at line $filepos) is defined more than once.");
            }
            $main_hash{"$service_parent_attr;;$block_naming_attr"} = \%block_hash;
        }else{
            &logger(5,"Parsing attributes of $input_class '$block_naming_attr'");
            if($main_hash{$block_naming_attr}){
                &logger(2,"$input_class '$block_naming_attr' (starting at line $filepos) is defined more than once.");
            }
            $main_hash{$block_naming_attr} = \%block_hash;
        }

        $filepos = $filepos_new;
    }

    close(LIST);
    return %main_hash;
}

##############################################################################

1;

__END__

}
