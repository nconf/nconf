#!/usr/bin/perl
#
# generate_config.pl
#
########################################################################################
# Description:
# This script generates the actual config files for the Nagios deamon, based on the
# information stored in the NConf DB.
########################################################################################
# Version 1.2.8
# Angelo Gargiulo
########################################################################################
# Revision history:
# 2006-07-31  v1.0    A. Gargiulo   Initial release
# 2006-08-25  v1.1    A. Gargiulo   Changed hostgroup/servicegroup.cfg to not be
#                                   globally defined anymore.
# 2006-12-06  v1.1.1  B. Waldvogel  Removed check_interval in host config
# 2008-10-23  v1.1.2  A. Gargiulo   Create .htpasswd file based on contact items
# 2008-10-23  v1.1.3  A. Gargiulo   Dynamically generate nagios.cfg for each 
#                                   collector/monitor to test new config.
# 2008-11-05  v1.1.4  A. Gargiulo   Handle setups with no Monitor server(s) present.
# 2009-02-06  v1.1.5  A. Gargiulo   Read basic configuration from /config folder
# 2009-02-12  v1.1.6  A. Gargiulo   Removed collector based on-call-location dependency,
#                                   added default contactgroups defined in config.
# 2009-02-17  v1.1.7  A. Gargiulo   Improved "fetch_config", added "parents" attr to
#                                   hosts.cfg, if no monitor server is present. 
# 2009-02-18  v1.1.8  A. Gargiulo   Changed the query that fetches the host-alive check.
#                                   host -> host-template -> misccommand -> name
# 2009-02-18  v1.1.9  A. Gargiulo   generate misccommands.cfg
# 2009-02-24  v1.2.0  A. Gargiulo   Added check for oncall groups, overall bugfixing
# 2009-03-12  v1.2.1  A. Gargiulo   Write "trap" services to collector config, 
#                                   if no monitor server is present.
# 2009-04-07  v1.2.2  A. Gargiulo   Small improvements and bugfixes
# 2009-04-30  v1.2.3  A. Gargiulo   Services check_command on monitor servers is now 
#                                   dependent on "active_checks_enabled" flag (service_is_stale not forced)
# 2009-05-11  v1.2.4  A. Gargiulo   Filenames to generate are now read from the DB ('out_file' attr)
# 2009-07-27  v1.2.5  A. Gargiulo   Added "check_result_path" = nconf/temp/ to nagios.cfg for syntax checking
# 2009-08-05  v1.2.6  A. Gargiulo   Added possibility to use %...% style NConf macros in any text attribute
# 2009-09-11  v1.2.7  A. Gargiulo   Fixed bug in host/service attrs copied from linked check_/notification_period
# 2009-10-08  v1.2.8  A. Gargiulo   Merged generate_config with NConf perl-API, moved functions to ext. perl module
#
########################################################################################
# INIT

use strict;

use FindBin;
use lib "$FindBin::Bin/lib";

use NConf;
use NConf::ExportNagios;
use NConf::Logger;

########################################################################################
# MAIN

&logger(3,"Starting generate_config script");

# Generate all necessary config files (don't change this order!)
&create_global_config;
&create_monitor_config;
&create_collector_config;

&logger(3,"Ended generate_config script");
