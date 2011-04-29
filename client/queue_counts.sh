#!/bin/sh
#
# queue_counts.sh
#
# return the number of items in the specified queues
#
# Author - Matt Willman - willman.matt@threepaw.com
#

POSTCONF=/path/to/postconf
QUEUES="active deferred corrupt hold"

if [ ! -s "$POSTCONF" ]
then
	echo "ERROR: please set POSTCONF before running..."
	exit 1
fi

QDIR=`$POSTCONF -h queue_directory`

if [ -z "$QDIR" ]
then
	echo "ERROR: unable to determine Postfix queue directory..."
	exit 1
fi

for each in `echo $QUEUES`
do
	echo -e "$each:\c"
	find $QDIR/$each/ -type f | wc -l | sed 's/ *//'
done
