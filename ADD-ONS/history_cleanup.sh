#!/bin/bash

# configure
# NCONF database settings:
USER="nconf"
DBNAME="NConf"
# enter password here, if your security allows it
#PASSWORD="xxxyyy"

# delete history entries older than
# UNIT should be one of these : SECOND, MINUTE, HOUR, DAY, WEEK, MONTH, QUARTER, or YEAR
# INTERVAL should be an integer
# details see mysql documentation

# for example, delete all history entires which are older than 1 month
UNIT="MONTH"
INTERVAL="1"

# OR if arguments are set, use them as values
# execute ./history_clean_up.sh UNIT INTERVAL
# for example : ./history_clean_up.sh DAY 5
if [ $# -eq 2 ] ; then
    UNIT="$1"
    INTERVAL="$2"
fi

DELETE_QUERY="DELETE FROM History WHERE timestamp < TIMESTAMPADD("${UNIT}",-"${INTERVAL}",CURRENT_TIMESTAMP)"

# prints query:  (for debugging)
#echo 'mysql -u '${USER}' -p '${DBNAME}' -e "'${DELETE_QUERY}'"'

# executes query:
mysql -u ${USER} -p${PASSWORD} ${DBNAME} -e "${DELETE_QUERY}"

exit
