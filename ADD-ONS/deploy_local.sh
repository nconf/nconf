#!/bin/bash

OUTPUT_DIR="/var/www/html/nconf/output/"
NAGIOS_DIR="/usr/local/nagios/etc/"
TEMP_DIR=${NAGIOS_DIR}"import/"
CONF_ARCHIVE="NagiosConfig.tgz"

if [ ! -e ${TEMP_DIR} ] ; then
mkdir -p ${TEMP_DIR}
fi

if [ ${OUTPUT_DIR}${CONF_ARCHIVE} -nt ${TEMP_DIR}${CONF_ARCHIVE} ] ; then
cp -p ${OUTPUT_DIR}${CONF_ARCHIVE} ${TEMP_DIR}${CONF_ARCHIVE}
tar -xf ${TEMP_DIR}${CONF_ARCHIVE} -C ${NAGIOS_DIR}
/etc/init.d/nagios reload
fi

exit
