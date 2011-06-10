##############################################################################
# "NConf::DB::Read" library
# A collection of shared functions for the NConf Perl scripts.
# Functions which execute read-only queries in the database.
#
# Version 0.2
# Written by Angelo Gargiulo
#
# Revision history:
# 2009-02-25 v0.1   A. Gargiulo   First release
# 2010-06-08 v0.2   A. Gargiulo   Added additional functions
#
##############################################################################

package NConf::DB::Read;

use strict;
use Exporter;
use DBI;
use NConf;
use NConf::DB;
use NConf::Logger;
use NConf::Helpers;

##############################################################################
### I N I T ##################################################################
##############################################################################

BEGIN {
    use vars qw(@ISA @EXPORT @EXPORT_OK);

    @ISA         = qw(NConf::DB);
    @EXPORT      = qw(@NConf::DB::EXPORT getItemId getItemName getServiceId getServiceHostname getAttrId getItemClass getConfigAttrs getNamingAttr getConfigClasses getItemData getItems getItemsLinked getImportCounter checkLinkAsChild checkItemsLinked checkItemExistsOnServer queryExecRead);
    @EXPORT_OK   = qw(@NConf::DB::EXPORT_OK);
}

##############################################################################
### S U B S ##################################################################
##############################################################################

sub getItemId {
    &logger(5,"Entered getItemId()");

    # SUB use: fetch the ID of any item in the database

    # SUB specs: ###################

    # Expected arguments:
    # 0: the item name (the contents of the naming-attr)
    # 1: the class of the item

    # Return values:
    # 0: a scalar containing the ID of the item, undef on falure

    ################################

    # read arguments passed
    my $item_name = shift;
    my $item_class = shift;

    unless($item_name && $item_class){&logger(1,"getItemId(): Missing argument(s). Aborting.")}

    if($item_class eq "service"){&logger(1,"Illegal function call: getItemId() cannot be used for services. Use getServiceId() instead. Aborting.")}

    my $sql = "SELECT ConfigValues.fk_id_item FROM ConfigValues, ConfigAttrs, ConfigClasses 
                WHERE ConfigValues.fk_id_attr=ConfigAttrs.id_attr 
                    AND ConfigAttrs.fk_id_class=ConfigClasses.id_class 
                    AND ConfigAttrs.naming_attr='yes' 
                    AND ConfigClasses.config_class='$item_class' 
                    AND ConfigValues.attr_value='$item_name'
                    LIMIT 1";

    my $id_item = &queryExecRead($sql, "Fetching id_item for $item_class '$item_name'", "one");

    if($id_item){return $id_item}
    else{return undef}
}

##############################################################################

sub getItemName {
    &logger(5,"Entered getItemName()");

    # SUB use: fetch the name (the value of the naming attr) of any item in the database

    # SUB specs: ###################

    # Expected arguments:
    # 0: the item ID

    # Return values:
    # 0: a scalar containing the name of the item, undef on falure

    ################################

    # read arguments passed
    my $id_item = shift;

    unless($id_item){&logger(1,"getItemName(): Missing argument(s). Aborting.")}

    my $sql = "SELECT ConfigValues.attr_value FROM ConfigValues, ConfigAttrs
                WHERE ConfigValues.fk_id_attr=ConfigAttrs.id_attr
                    AND ConfigAttrs.naming_attr='yes'
                    AND ConfigValues.fk_id_item=$id_item
                    LIMIT 1";

    my $item_name = &queryExecRead($sql, "Fetching item name for ID '$id_item'", "one");

    if($item_name){return $item_name}
    else{return undef}
}

##############################################################################

sub getServiceId {
    &logger(5,"Entered getServiceId()");

    # SUB use: fetch the ID of a service in the database

    # SUB specs: ###################

    # Expected arguments:
    # 0: the service name (the contents of the naming-attr)
    # 1: the ID of the parent host (value in 'host_name' attr)

    # Return values:
    # 0: a scalar containing the ID of the service, undef on falure

    ################################

    # read arguments passed
    my $service_name   = shift;
    my $parent_host_id = shift;

    unless($service_name && $parent_host_id){&logger(1,"getServiceId(): Missing argument(s). Aborting.")}

    my $sql = "SELECT ConfigItems.id_item FROM ConfigItems, ConfigValues, ConfigAttrs, ConfigClasses, ItemLinks
                WHERE ConfigValues.fk_id_attr=ConfigAttrs.id_attr
                    AND ConfigValues.fk_id_item=ConfigItems.id_item
                    AND ConfigAttrs.fk_id_class=ConfigClasses.id_class
                    AND ItemLinks.fk_id_item=ConfigItems.id_item
                    AND ConfigClasses.config_class='service'
                    AND ConfigAttrs.naming_attr='yes'
                    AND ConfigValues.attr_value='$service_name'
                    AND ItemLinks.fk_item_linked2='$parent_host_id'
                    LIMIT 1"; 

    my $id_srv = &queryExecRead($sql, "Fetching id_item for service '$service_name' linked to host ID '$parent_host_id'", "one");

    if($id_srv){return $id_srv}
    else{return undef}
}

##############################################################################

sub getServiceHostname {
    &logger(5,"Entered getServiceHostname()");

    # SUB use: fetch the hostname of the host which the service belongs to

    # SUB specs: ###################

    # Expected arguments:
    # 0: the ID of the service

    # Return values:
    # 0: the hostname of the host which the service belongs to

    ################################

    # read arguments passed
    my $id_item = shift;

    unless($id_item){&logger(1,"getServiceHostname(): Missing argument(s). Aborting.")}

    my $sql = "SELECT attr_value AS hostname FROM ConfigValues, ConfigAttrs, ConfigClasses, ItemLinks 
                WHERE ItemLinks.fk_item_linked2=ConfigValues.fk_id_item 
                    AND ConfigValues.fk_id_attr=ConfigAttrs.id_attr 
                    AND ConfigAttrs.fk_id_class=ConfigClasses.id_class
                    AND config_class='host'
                    AND attr_name='host_name'
                    AND ItemLinks.fk_id_item=$id_item
                    LIMIT 1"; 

    my $hostname = &queryExecRead($sql, "Fetching hostname for service '$id_item'", "one");

    if($hostname){return $hostname}
    else{return undef}
}

##############################################################################

sub getAttrId {
    &logger(5,"Entered getAttrId()");

    # SUB use: fetch attr ID based on attr name and class name

    # SUB specs: ###################

    # Expected arguments:
    # 0: attribute name
    # 1: class name

    # Return values:
    # 0: attr ID, undef on failure

    ################################

    # read arguments passed
    my $attr_name  = shift;
    my $class_name = shift;

    $class_name = lc($class_name);

    unless($attr_name && $class_name){&logger(1,"getAttrId(): Missing argument(s). Aborting.")}

    my $id_attr = undef;
    if($NC_db_caching == 1 && exists($NC_dbcache_getAttrId{$class_name}->{$attr_name})){
        # if cached, read from cache
        &logger(4,"Fetching id_attr for '$attr_name' (cached)");
        $id_attr = $NC_dbcache_getAttrId{$class_name}->{$attr_name};
    }else{
        # if not cached, run DB query
        my $sql = "SELECT ConfigAttrs.id_attr FROM ConfigAttrs, ConfigClasses 
                    WHERE ConfigAttrs.fk_id_class=ConfigClasses.id_class 
                        AND ConfigClasses.config_class='$class_name' 
                        AND ConfigAttrs.attr_name ='$attr_name' 
                        LIMIT 1";

        $id_attr = &queryExecRead($sql, "Fetching id_attr for '$attr_name'", "one");

        # save to cache
        $NC_dbcache_getAttrId{$class_name}->{$attr_name} = $id_attr;
    }

    if($id_attr){return $id_attr}
    else{return undef}
}

##############################################################################

sub getItemClass {
    &logger(5,"Entered getItemClass()");

    # SUB use: fetch the class of an item based on the item ID

    # SUB specs: ###################

    # Expected arguments:
    # 0: item ID

    # Return values:
    # 0: class name, undef on failure

    ################################

    # read arguments passed
    my $id_item   = shift;

    unless($id_item){&logger(1,"getItemClass(): Missing argument(s). Aborting.")}

    my $item_class = undef;
    if($NC_db_caching == 1 && exists($NC_dbcache_getItemClass{$id_item})){
        # if cached, read from cache
        &logger(4,"Fetching class name for item ID '$id_item' (cached)");
        $item_class = $NC_dbcache_getItemClass{$id_item};
    }else{
        # if not cached, run DB query
        my $sql = "SELECT ConfigClasses.config_class FROM ConfigClasses, ConfigItems 
                    WHERE ConfigItems.fk_id_class=ConfigClasses.id_class 
                    AND ConfigItems.id_item=$id_item";

        $item_class = &queryExecRead($sql, "Fetching class name for item ID '$id_item'", "one");

        # save to cache
        $NC_dbcache_getItemClass{$id_item} = $item_class;
    }

    if($item_class){return $item_class}
    else{return undef}
}

##############################################################################

sub getConfigAttrs {
    &logger(5,"Entered getConfigAttrs()");

    # SUB use: get a list of all attrs plus their properties

    # SUB specs: ###################

    # Return values:
    # 0: A hash containing the following data structure:
    #    $conf_attrs{'class name'}->{'attr name'}->{'property'}

    # The following properties exist:

    # 'id_attr': the attribute ID
    # 'friendly_name': the attribute name displayed in the GUI
    # 'description': the attribute description displayed in the GUI
    # 'datatype': the data type ('text', 'select', 'assign_one' etc.)
    # 'max_length': the maximum amount of chars (for text attributes)
    # 'poss_values': a list of possible values (for select attributes)
    # 'predef_value': the predefined value(s)
    # 'mandatory': if the attribute is mandatory or not
    # 'ordering': the attribute's ordering number within its class
    # 'visible': if the attribute is visible in the GUI
    # 'write_to_conf': if the attribute should be written to the generated config
    # 'naming_attr': if the attribute is a "naming attribute"
    # 'link_as_child': if the 'link_as_child' flag is set
    # 'link_bidirectional': if the 'link_bidirectional' flag is set
    # 'fk_show_class_items': for linking attributes, which class to link to (class ID)
    # 'assign_to_class': for linking attributes, which class to link to (class name)

    ################################

    # if cached, read from cache
    if($NC_db_caching == 1 && keys(%NC_dbcache_getConfigAttrs)){
        &logger(4,"Fetching all attributes from ConfigAttrs table (cached)");
        return %NC_dbcache_getConfigAttrs;
    }

    # if not cached, run DB query
    my $q_attr = "SELECT fk_id_class AS class_id,
                    (SELECT config_class FROM ConfigClasses WHERE id_class=class_id) AS belongs_to_class, 
                    id_attr,
                    attr_name,
                    friendly_name,
                    description,
                    datatype, 
                    max_length, 
                    poss_values, 
                    predef_value, 
                    mandatory, 
                    ordering,
                    visible,
                    write_to_conf,
                    naming_attr, 
                    link_as_child, 
                    link_bidirectional, 
                    fk_show_class_items,
                    (SELECT config_class FROM ConfigClasses WHERE id_class=fk_show_class_items) AS assign_to_class
                        FROM ConfigAttrs 
                        ORDER BY fk_id_class, ordering";

    my %config_attrs = &queryExecRead($q_attr,"Fetching all attributes from ConfigAttrs table","all2","id_attr");

    # get all available classes
    my %classes;
    foreach my $attr_id (keys(%config_attrs)){
        $classes{$config_attrs{$attr_id}->{'belongs_to_class'}} = $config_attrs{$attr_id}->{'belongs_to_class'};
    }

    # feed all attrs and their properties into the following hash structure:
    # $conf_attrs{'class name'}->{'attr name'}->{'property'}

    my %conf_attrs;
    foreach my $class (keys(%classes)){
        my %attrs_hash;
        foreach my $attr_id (keys(%config_attrs)){
            if($config_attrs{$attr_id}->{'belongs_to_class'} eq $class){
                $attrs_hash{$config_attrs{$attr_id}->{'attr_name'}} = $config_attrs{$attr_id};
            }
        }
        $conf_attrs{$class} = \%attrs_hash;
    }
    
    # save to cache
    %NC_dbcache_getConfigAttrs = %conf_attrs;

    return %conf_attrs;
}

##############################################################################

sub getNamingAttr {
    &logger(5,"Entered getNamingAttr()");

    # SUB use: get the name of the "naming attribute" for a specific class

    # SUB specs: ###################

    # Expected arguments:
    # 0: class name

    # Return values:
    # 0: attribute name of the "naming attribute" for the specified class (e.g. 'host_name' for class 'host')

    ################################

    # read arguments passed
    my $class_name = shift;
    $class_name = lc($class_name);

    unless($class_name){&logger(1,"getNamingAttr(): Missing argument(s). Aborting.")}

    # if cached, read from cache
    if($NC_db_caching == 1 && $NC_dbcache_getNamingAttr{$class_name}){
        &logger(4,"Fetching the naming attr for class '$class_name' (cached)");
        return $NC_dbcache_getNamingAttr{$class_name};
    }

    # if not cached, run DB query
    my $q_attr = "SELECT attr_name FROM ConfigAttrs, ConfigClasses WHERE naming_attr='yes' AND fk_id_class=id_class AND config_class='$class_name'";

    my $naming_attr = &queryExecRead($q_attr,"Fetching the naming attr for class '$class_name'","one");
    
    # save to cache
    $NC_dbcache_getNamingAttr{$class_name} = $naming_attr;

    return $naming_attr;
}

##############################################################################

sub getConfigClasses {
    &logger(5,"Entered getConfigClasses()");

    # SUB use: get a list of all classes plus their properties

    # SUB specs: ###################

    # Return values:
    # 0: A hash containing the following data structure:
    #    $class_hash{'class name'}->{'property'}

    # The following properties exist:

    # 'class_type':    whether the class is "global", "monitor" or "collector" specific
    # 'out_file':      the name of the .cfg file to write items of this class to
    # 'nagios_object': the object definition for Nagios ('define host {')

    ################################

    # if cached, read from cache
    if($NC_db_caching == 1 && keys(%NC_dbcache_getConfigClasses)){
        &logger(4,"Fetching all classes from ConfigClasses table (cached)");
        return %NC_dbcache_getConfigClasses;
    }

    # if not cached, run DB query
    my $q_class = "SELECT config_class, class_type, nagios_object, out_file FROM ConfigClasses ORDER BY ordering";

    my %class_hash = &queryExecRead($q_class,"Fetching all classes from ConfigClasses table","all2","config_class");

    # save to cache
    %NC_dbcache_getConfigClasses = %class_hash;

    return %class_hash;
}

##############################################################################

sub getItemData {
    &logger(5,"Entered getItemData()");

    # SUB use: fetch all attributes and values assigned to an item over the ConfigValues table
    #          (i.e. the data of 'text', 'select' and 'password' attributes)

    # SUB specs: ###################

    # Expected arguments:
    # 0: the item ID

    # Return values:
    # 0: an array containing references to arrays that contain 'attr' -> 'value' pairs

    ################################

    # read arguments passed
    my $id_item = shift;

    unless($id_item){&logger(1,"getItemData(): Missing argument(s). Aborting.")}

    my $sql = "SELECT attr_name,attr_value,write_to_conf FROM ConfigAttrs,ConfigValues
                  WHERE id_attr=fk_id_attr
                  AND fk_id_item=$id_item
                  ORDER BY naming_attr,ordering";

    my @attrs = &queryExecRead($sql, "Fetching all normal attributes and values for item '$id_item'", "all");

    # replace NConf macros with the respective value
    foreach my $attr (@attrs){
        $attr->[1] = &replaceMacros($attr->[1]);
    }

    return(@attrs);
}

##############################################################################

sub getItems {
    &logger(5,"Entered getItems()");

    # SUB use: fetch all items of a certain class (e.g. all contacts)

    # SUB specs: ###################

    # Expected arguments:
    # 0: class name
    # 1: optional: '1' = also return item names

    # Return values:
    # 0: an array containing references to arrays with two values: 
    #    [0] the item ID
    #    [1] optional: the name of the item (value of the naming attr)

    ################################

    # read arguments passed
    my $class = shift;
    my $item_names = shift;

    unless($class){&logger(1,"getItems(): Missing argument(s). Aborting.")}

    my $sql = undef;

    if($item_names == 1){
        $sql = "SELECT fk_id_item,attr_value
                    FROM ConfigValues,ConfigAttrs,ConfigClasses
                    WHERE id_attr=fk_id_attr
                       AND naming_attr='yes'
                       AND id_class=fk_id_class
                       AND config_class = '$class'
                       ORDER BY attr_value";
    }else{
        $sql = "SELECT id_item FROM ConfigItems,ConfigClasses
                    WHERE id_class=fk_id_class
                       AND config_class = '$class'
                       ORDER BY id_item";
    }

    my @items = &queryExecRead($sql, "Fetching all items of type '$class'", "all");

    return @items;
}

##############################################################################

sub getItemsLinked {
    &logger(5,"Entered getItemsLinked()");

    # SUB use: fetch all attributes and values linked to an item over the ItemLinks table
    #          (i.e. the data of 'assign_one', 'assign_many' and 'assign_cust_order' attributes)

    # SUB specs: ###################

    # Expected arguments:
    # 0: the item ID

    # Return values:
    # 0: an array containing references to arrays that contain the following data structure:
    #    [0] the attribute name
    #    [1] the attribute value (the name of the linked item)
    #    [2] the 'write_to_conf' flag for the current attribute
    #    [3] the item ID of the linked item
    #    [4] the order number of the linked item within an 'assign_cust_order' attribute
    #    [5] the ordering number of the current attribute within its class

    ################################

    # read arguments passed
    my $id_item = shift;

    unless($id_item){&logger(1,"getItemsLinked(): Missing argument(s). Aborting.")}

    my $sql = "SELECT attr_name,attr_value,write_to_conf,fk_item_linked2 AS item_id,cust_order,ordering
                    FROM ConfigValues,ItemLinks,ConfigAttrs
                    WHERE fk_item_linked2=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND (link_as_child <> 'yes' OR link_as_child IS NULL)
                        AND ItemLinks.fk_id_item=$id_item
                UNION
                SELECT attr_name,attr_value,write_to_conf,ItemLinks.fk_id_item AS item_id,cust_order,ordering
                     FROM ConfigValues,ItemLinks,ConfigAttrs
                     WHERE ItemLinks.fk_id_item=ConfigValues.fk_id_item
                        AND id_attr=ItemLinks.fk_id_attr
                        AND (SELECT naming_attr FROM ConfigAttrs WHERE id_attr=ConfigValues.fk_id_attr)='yes'
                        AND link_as_child = 'yes'
                        AND fk_item_linked2=$id_item
                        ORDER BY cust_order,ordering";

    my @attrs = &queryExecRead($sql, "Fetching all linked attributes and values for item '$id_item'", "all");

    return(@attrs);
}

##############################################################################

sub getImportCounter {
    &logger(5,"Entered getImportCounter()");

    # SUB use: determine a unique name for imported items

    # SUB specs: ###################

    # Expected arguments:
    # 0: the class name of the items being imported

    # Return values:
    # 0: a unique name for that class in the following format:
    #    "imported_class-name_n" ("n" being the next available number)

    # Note: the name returned by this function is unique at the time this function is called.
    # If you wish to add multiple items, make sure you increment the number part for each item!

    ################################
    
    my $class = $_[0];

    unless($class){&logger(1,"getImportCounter(): Missing argument(s). Aborting.")}

    my @class_items = getItems($class,1);

    my $max_count = undef;
    foreach my $item (@class_items){
        $item->[1] =~ /^imported_$class\_([0-9]+)/;
        if($1 && (($1 > $max_count) || !defined($max_count))){$max_count = $1}
    }

    if(defined($max_count)){return $max_count+1}
    else{return "1"}
}

##############################################################################

sub checkLinkAsChild {
    &logger(5,"Entered checkLinkAsChild()");

    # SUB use: check if "link_as_child" flag is set for a specific attribute

    # SUB specs: ###################

    # Expected arguments:
    # 0: attr ID

    # Return values:
    # 0: 'true' if link_as_child = "yes", 
    #    'false' if link_as_child = "no", 
    #     undef on failure

    ################################

    # read arguments passed
    my $id_attr = shift;

    unless($id_attr){&logger(1,"checkLinkAsChild(): Missing argument(s). Aborting.")}

    my $qres;
    if($NC_db_caching == 1 && exists($NC_dbcache_checkLinkAsChild{$id_attr})){
        # if cached, read from cache
        &logger(4,"Fetching 'link_as_child' flag for attr ID '$id_attr' (cached)");
        $qres = $NC_dbcache_checkLinkAsChild{$id_attr};
    }else{
        # if not cached, run DB query
        my $sql = "SELECT ConfigAttrs.link_as_child FROM ConfigAttrs WHERE id_attr = $id_attr";

        $qres = &queryExecRead($sql, "Fetching 'link_as_child' flag for attr ID '$id_attr'", "one");

        # save to cache
        $NC_dbcache_checkLinkAsChild{$id_attr} = $qres;
    }

    if($qres eq "yes"){return "true"}
    elsif($qres eq "no"){return "false"}
    else{
        &logger(2,"Failed to determine if 'link_as_child' flag is set for attr ID '$id_attr'. Aborting checkLinkAsChild().");
        return undef;
    }
}

##############################################################################

sub checkItemsLinked {
    &logger(5,"Entered checkItemsLinked()");

    # SUB use: check if two items are linked via the ItemLinks table

    # SUB specs: ###################

    # Expected arguments:
    # 0: ID of item that will be linked
    # 1: ID of item to link the first one to
    # 2: name of NConf attr (of type assign_one/many/cust_order)

    # Return values:
    # 0: 'true' if items already linked, 'false' if not, undef on failure

    # This function automatically checks and considers the "link_as_child" flag

    ################################

    # read arguments passed
    my $id_item = shift;
    my $id_item_linked2 = shift;
    my $attr_name = shift;

    unless($id_item && $id_item_linked2 && $attr_name){&logger(1,"checkItemsLinked(): Missing argument(s). Aborting.")}

    # fetch class name
    my $class_name = &getItemClass($id_item);
    unless($class_name){
        &logger(2,"Failed to resolve the class name for item ID '$id_item' using getItemClass(). Aborting checkItemsLinked().");
        return undef;
    }

    # fetch id_attr
    my $id_attr = &getAttrId($attr_name, $class_name);
    unless($id_attr){
        &logger(2,"Failed to resolve attr ID for '$attr_name' using getAttrId(). Aborting checkItemsLinked().");
        return undef;
    }

    # check link_as_child
    my $las = &checkLinkAsChild($id_attr);
    unless($las){
        &logger(2,"Failed to check if 'link_as_child' flag is set using checkLinkAsChild(). Aborting checkItemsLinked().");
        return undef;
    }

    # check if items are linked
    my $sql = undef;

    if($las eq "true"){
        $sql = "SELECT 'true' AS item_linked FROM ItemLinks 
                  WHERE fk_id_item=$id_item_linked2 
                    AND fk_item_linked2=$id_item 
                    AND fk_id_attr=$id_attr 
                    LIMIT 1";
    }else{
        $sql = "SELECT 'true' AS item_linked FROM ItemLinks 
                  WHERE fk_id_item=$id_item 
                    AND fk_item_linked2=$id_item_linked2 
                    AND fk_id_attr=$id_attr 
                    LIMIT 1";
    }

    my $qres = &queryExecRead($sql, "Checking if items '$id_item' and '$id_item_linked2' are linked", "one");

    if($qres eq "true"){return 'true'}
    else{return 'false'}
}

##############################################################################

sub checkItemExistsOnServer {
    &logger(5,"Entered checkItemExistsOnServer()");

    # SUB use: Check if a server-specific item (host, service, hostgroup or servicegroup) exists on a collector server, or if it's monitored at all.
    #          This function is intended for distributed Nagios environments with multiple collectors.

    # SUB specs: ###################

    # Expected arguments:
    # 0: ID of item to check for
    # 1: optional: ID of a specific collector server 

    # If the second parameter is specified, the function will determine if the item being checked exists on the specified collector server.
    # If the second parameter is omitted, the function will determine if the item being checked is monitored at all 
    # by any collector (the "monitored by" flag is set for hosts / "service_enabled" is not set to "no" for services).

    # Return values:
    # 0: 'true'  if item exists on specified collector / if item is monitored by any collector, 
    #    'false' if item does not exist on collector / if item is not monitored by any other collector

    # Note: this function will always return 'true' if the item being checked is NOT a host, service, hostgroup or servicegroup.

    ################################
    
    my $item2check4 = $_[0];
    my $collector_id = $_[1];
    my $sql  = undef;
    my $qres = undef;

    unless($item2check4){&logger(1,"checkItemExistsOnServer(): Missing argument(s). Aborting.")}

    if(&getItemClass($item2check4) eq "host"){

        if($collector_id){
            # check if a certain host exists on the current collector
            $sql = "SELECT ItemLinks.fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses 
                        WHERE ItemLinks.fk_id_attr=id_attr 
                            AND ConfigAttrs.fk_id_class=id_class 
                            AND attr_name='monitored_by' 
                            AND config_class='host' 
                            AND fk_id_item=$item2check4 
                            AND fk_item_linked2=$collector_id";

            $qres = &queryExecRead($sql, "Checking if host '$item2check4' exists on collector '$collector_id'", "one");

        }else{
            # check if a certain host is monitored by any collector
            $sql = "SELECT ItemLinks.fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses
                    WHERE ItemLinks.fk_id_attr=id_attr
                        AND ConfigAttrs.fk_id_class=id_class
                        AND attr_name='monitored_by'
                        AND config_class='host'
                        AND fk_id_item=$item2check4
                        AND fk_item_linked2 <> ''";

            $qres = &queryExecRead($sql, "Checking if host '$item2check4' is monitored by any collector", "one");
        }

    }
    elsif(&getItemClass($item2check4) eq "service"){

        if($collector_id){
            # check if a certain service belongs to a host that exists on the current collector and the service is not disabled itself
            $sql = "SELECT fk_item_linked2 AS host_id FROM ItemLinks, ConfigAttrs 
                        WHERE fk_id_attr = id_attr 
                            AND attr_name = 'host_name' 
                            AND fk_id_item = $item2check4 
                            AND ((SELECT attr_value FROM ConfigValues,ConfigAttrs 
                                    WHERE id_attr=fk_id_attr 
                                        AND attr_name = 'service_enabled' 
                                        AND fk_id_item = $item2check4) <> 'no' 
                                OR (SELECT attr_value FROM ConfigValues,ConfigAttrs
                                    WHERE id_attr=fk_id_attr
                                        AND attr_name = 'service_enabled'
                                        AND fk_id_item = $item2check4) IS NULL)
                            HAVING (SELECT ItemLinks.fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses 
                                    WHERE ItemLinks.fk_id_attr=id_attr 
                                        AND ConfigAttrs.fk_id_class=id_class 
                                        AND attr_name='monitored_by' 
                                        AND config_class='host' 
                                        AND fk_id_item=host_id 
                                        AND fk_item_linked2=$collector_id) = host_id";

            $qres = &queryExecRead($sql, "Checking if service '$item2check4' exists on collector '$collector_id'", "one");

        }else{
            # check if a certain service belongs to a host that is monitored by any collector and the service is not disabled itself
            $sql = "SELECT fk_item_linked2 AS host_id FROM ItemLinks, ConfigAttrs
                    WHERE fk_id_attr = id_attr
                        AND attr_name = 'host_name'
                        AND fk_id_item = $item2check4
                        AND ((SELECT attr_value FROM ConfigValues,ConfigAttrs 
                                WHERE id_attr=fk_id_attr 
                                    AND attr_name = 'service_enabled' 
                                    AND fk_id_item = $item2check4) <> 'no' 
                            OR (SELECT attr_value FROM ConfigValues,ConfigAttrs
                                WHERE id_attr=fk_id_attr
                                    AND attr_name = 'service_enabled'
                                    AND fk_id_item = $item2check4) IS NULL)
                        HAVING (SELECT ItemLinks.fk_id_item FROM ItemLinks,ConfigAttrs,ConfigClasses
                                WHERE ItemLinks.fk_id_attr=id_attr
                                    AND ConfigAttrs.fk_id_class=id_class
                                    AND attr_name='monitored_by'
                                    AND config_class='host'
                                    AND fk_id_item=host_id) <> ''"; 

            $qres = &queryExecRead($sql, "Checking if service '$item2check4' belongs to a host that is monitored by any collector", "one");
        }

    }
    elsif(&getItemClass($item2check4) eq "hostgroup" || &getItemClass($item2check4) eq "servicegroup"){
    # the logic here is used by items that refer to host- or servicegroups, such as host- & service dependencies,
    # recursive hostgroup to hostgroup assignments etc., as well as services linked to hostgroups (planned);

        # fetch all items linked
        my @item_links = &getItemsLinked($item2check4);

        my $check_item_on_collector = 1;
        foreach my $attr (@item_links){

            # check if all hosts / services within the group exist on the current collector / are monitored at all by any collector
            if($attr->[0] eq "members"){ # this clause prevents endless loops!
                unless(&checkItemExistsOnServer($attr->[3],$collector_id) eq "true"){undef $attr->[1]}
            }
        }

        @item_links = &makeValuesDistinct(@item_links);

        my $has_members = 0;
        foreach my $attr (@item_links){

            # remove hosts / services that don't exist on the current collector / are monitored by any collector
            if(defined($attr->[0]) && $attr->[1] eq ""){
                $check_item_on_collector = 0;
            }

            if($attr->[0] eq "members"){$has_members = 1}
        }

        # if no host / services are left within the group, consider the group as inexistent on the current server
        unless($check_item_on_collector == 1 && $has_members == 1){$qres = undef}
        else{$qres = "true"}

    }else{$qres = "true"}

    if(defined($qres)){return 'true'}
    else{return 'false'}
}

##############################################################################

sub queryExecRead {
    &logger(5,"Entered queryExecRead()");

    # SUB use: Execute a query which reads data from the database

    # SUB specs: ###################

    # Expected arguments:
    # 0: The SQL query
    # 1: The message to log for the query
    # 2: The format of the return value:
    #    "one"  return a single scalar value (first value returned by the query)
    #    "row"  return an array containing all values of one row
    #    "row2" return a hash containing all values of one row (with attr names as keys)
    #    "all"  return an array that contains one array reference per row of data
    #    "all2" return a hash containing one hash reference per row of data (with a specified attr as key)
    #
    # 3: Only if "all2": The attr name to use as key for the hash that is returned

    # Return values:
    # 0: The output of the query in the specified format, 
    #    undef if query returns no rows / on failure

    ################################

    # read arguments passed
    my $sql = shift;
    my $msg = shift;
    my $ret = shift;
    my $key = shift;

    unless($sql && $msg && $ret){&logger(1,"queryExecRead(): Missing argument(s). Aborting.")}
    if ($ret eq "all2" && !$key){&logger(1,"queryExecRead(): Missing argument(s). Aborting.")}

    my $dbh  = &dbConnect;

    &logger(4,$msg);
    &logger(5,$sql,1);
    my $sth = $dbh->prepare($sql);
    $sth->execute();

    if($ret eq "one"){
        my @qres = $sth->fetchrow_array;
        if($qres[0]){
            &logger(5,"Query result: '$qres[0]'");
            return $qres[0];
        }else{return undef}

    }elsif($ret eq "row"){
        my $array_ref = $sth->fetchrow_arrayref;
        if($array_ref){return @$array_ref}
        else{return undef}

    }elsif($ret eq "row2"){
        my $hash_ref = $sth->fetchrow_hashref;
        if($hash_ref){return %{$hash_ref}}
        else{return undef}

    }elsif($ret eq "all"){
        my $array_ref = $sth->fetchall_arrayref;
        if($array_ref){return @$array_ref}
        else{return undef}

    }elsif($ret eq "all2"){
        my $hash_ref = $sth->fetchall_hashref($key);
        if($hash_ref){return %{$hash_ref}}
        else{return undef}
    }
}

##############################################################################

#sub get... {
#    &logger(5,"Entered get...()");

    # SUB use: fetch ...

    # SUB specs: ###################

    # Expected arguments:
    # 0: ...
    # 1: ...

    # Return values:
    # 0: ..., undef on failure

    ################################

    # read arguments passed
#    my $item = shift;

#    unless($item){&logger(1,"get...(): Missing argument(s). Aborting.")}

#    my $sql = "SELECT...";

#    my @qres = &queryExecRead($sql, "Fetching ... for '$item'", "all");

#    if(@qres){return @qres}
#    else{return undef}
#}

##############################################################################

1;

__END__

}
