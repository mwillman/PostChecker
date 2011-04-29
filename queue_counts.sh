#!/bin/sh
#
# queue_counts.sh
#
# return the number of items in the specified queues
#
#
# Copyright (c) 2005 Matt Willman
# ALL RIGHTS RESERVED
#
# Contact: willman.matt@threepaw.com
#
#
# This program is free software; you can redistribute it and/or modify it under
# the terms of the GNU General Public License as published by the Free Software
# Foundation; either version 2 of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
# FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along with
# this program; if not, write to the Free Software Foundation, Inc., 59 Temple
# Place, Suite 330, Boston, MA 02111-1307 USA
#
# Initial release
# 06/2005	MBWillman
# 
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
