#!/bin/bash
#
# Copyright © 2007-2019 - Authors:
#
# (c) 2007-2009 Remo Fritzsche    (Main application programmer)
# (c) 2009 Karl Herrick (Bugfix)
# (c) 2007-2008 Manuel Aller (Additional programming)
# (c) 2015 Dirk Groenen (Additional programming)
# (c) 2016-2019 Nico Panke (shell programming)
#
#	This program is free software: you can redistribute it and/or modify
#	it under the terms of the GNU General Public License as published by
#	the Free Software Foundation, either version 3 of the License, or
#	any later version.
#
#	This program is distributed in the hope that it will be useful,
#	but WITHOUT ANY WARRANTY; without even the implied warranty of
#	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#	GNU General Public License for more details.
#
#	You should have received a copy of the GNU General Public License
#	along with this program.  If not, see <http://www.gnu.org/licenses/>.
#    
#	Version 1.1
#

# --------------------------------------
#	mailbox configuration
# --------------------------------------

# path to mailbox storage
MAILBOX_PATH=/var/mail/vhosts

# set if mailbox as email stored
MAILBOX_AS_EMAIL=0

# --------------------------------------
#	mysql configuration
# --------------------------------------
MYSQL_HOST="localhost"
MYSQL_PORT=3306
MYSQL_USER="root"
MYSQL_PASS=""
MYSQL_DATABASE=""

USER_TABLE="users"
USER_FIELD="email"

# --------------------------------------
#	logging configuration
# --------------------------------------

LOG_ENABLE=1
LOG_DEBUG=0
LOG_FILE=/var/log/goldfish.log

# --------------------------------------
#	goldfish configuration
# --------------------------------------

# cycletime in seconds [default: 300 ~ 5 minutes]
GF_CYCLE=300

# database tablename where you will store the autoresponders
GF_AUTORESPONDER_TABLE="autoresponder"

# query to get subject or message
GF_RESPONDER_MESSAGE="SELECT \`message\` FROM \`${GF_AUTORESPONDER_TABLE}\` WHERE \`email\` = '%m';"
GF_RESPONDER_SUBJECT="SELECT \`subject\` FROM \`${GF_AUTORESPONDER_TABLE}\` WHERE \`email\` = '%m';"

# enable user check
GF_CHECK_USER=0

# query to check user
GF_CHECK_USER_QUERY="SELECT \`${USER_FIELD}\` FROM \`${USER_TABLE}\` WHERE \`${USER_FIELD}\` = '%m';"

# query to get or to manipulate the forwardings
GF_FORWARDINGS="SELECT \`email\`, \`descname\` FROM \`${GF_AUTORESPONDER_TABLE}\` WHERE \`enabled\` = 1 AND \`force_disabled\` = 0;" #FIELDS: email descname from to subject message enabled force_disabled
GF_DISABLE_FORWARDINGS="UPDATE \`${GF_AUTORESPONDER_TABLE}\` SET \`enabled\` = 0 WHERE \`to\` < CURDATE();"
GF_ENABLE_FORWARDINGS="UPDATE \`${GF_AUTORESPONDER_TABLE}\` SET \`enabled\` = 1 WHERE \`from\` <= CURDATE() AND (\`to\` >= CURDATE() OR \`to\`='0000-00-00');"

# test mode
GF_TEST=0

# --------------------------------------
# !	don't change anything below this line
# --------------------------------------

# ----------------------------------
# option definition, overwrites
# @ http://wiki.bash-hackers.org/howto/getopts_tutorial
# ----------------------------------
while getopts ':c:hl:t' opt "$@"; do
	
	case "${opt}" in
	
		c)
			GF_CYCLE=${OPTARG}
		;;
		
		l)
			LOG_ENABLE=1
			LOG_FILE=${OPTARG}
		;;
		
		t)
			GF_TEST=1
		;;
		
		u)
			GF_CHECK_USER=1
		;;
		
		h|\?)
			 # show invalid argument
			[[ "$opt" != "h" ]] && echo "Invalid option: -${OPTARG}"
			
			# show help
			echo "Usage: $0 [OPTIONS]"
			echo -e "-c\tset cycletime in seconds"
			echo -e "-h\tshow help"
			echo -e "-l\tset path to log file"
			echo -e "-t\ttest mode, no messages will send"
			echo -e "-u\tenable user check"
			exit 0
		;;
		
		:)
			echo "option -${OPTARG} required an argument"
			exit 1
		;;
		
	esac
	
done

# --------------------------------------
#	logging function
# --------------------------------------
log() {
	
	# check logging config
	if ( [ $LOG_ENABLE -eq 0 ] || [ "$LOG_FILE" == "" ] ); then
		return 1
	fi
	
	# check debug mode
	if ( [ $LOG_DEBUG -eq 0 ] && [ "$2" != "" ] ); then return 0; fi

	if [ "$1" != "" ]; then
		LOG_DATE=$(echo `date +%Y-%m-%d`)
		LOG_TIME=$(echo `date +%H:%M:%S`)
		echo -e "[$LOG_DATE $LOG_TIME]\t$1" >> $LOG_FILE
	fi

}

# --------------------------------------
#	mysql functions
# --------------------------------------
mysql_test() {
	
	mcmdf="/tmp/mysql-test-output.$$"
	mysql -h $MYSQL_HOST -P $MYSQL_PORT -u $MYSQL_USER -p$MYSQL_PASS -e "USE mysql" >> $mcmdf 2>&1

	# no errors
	if [ $? -eq 0 ]; then
		rm $mcmdf
		return 0
	fi
	
	log "mysql: can't connect server '${MYSQL_HOST}:${MYSQL_PORT}' \"$(cat $mcmdf)\""
	rm $mcmdf
	return 1

}

mysql_check_database() {

	for db in `mysql -h $MYSQL_HOST -P $MYSQL_PORT -u $MYSQL_USER -p$MYSQL_PASS -e "SHOW DATABASES" -N`; do
		if [ $db == $MYSQL_DATABASE ]; then
			return 0
		fi
	done

	log "mysql: can't find database '${MYSQL_DATABASE}'"
	return 1

}

MYSQL_RESULT=""
MYSQL_RESULT_COUNT=""
mysql_execute() {

	log "mysql: execute \"$1\"" 1

	mcmdf="/tmp/mysql-test-output.$$"
	mysql -h $MYSQL_HOST -P $MYSQL_PORT -u $MYSQL_USER -p$MYSQL_PASS $MYSQL_DATABASE -e "$1" -N >> $mcmdf 2>&1

	if [ $? -eq 0 ]; then
		MYSQL_RESULT=$( cat $mcmdf )
		MYSQL_RESULT_COUNT=$( cat $mcmdf | wc -l )
		rm $mcmdf
		return 0
	else
		log "mysql: can't execute statement: \"$1\" ends with error: \"$(cat $mcmdf)\""
		rm $mcmdf
		return 1
	fi
  
}

# --------------------------------------
#	mail function
# --------------------------------------
RES_ADDRESS=
seperateAddress() {

	RES_ADDRESS=

	# with desciption name
	if [[ $1 =~ \<(.*)\> ]]; then
		RES_ADDRESS=${BASH_REMATCH[1]}
		return 0
	fi
	
	# without desciption name
	if [[ $1 =~ ^[[:space:]](.*) ]]; then
		RES_ADDRESS=${BASH_REMATCH[1]}
		return 0
	fi

}

sendmail() {
	
	SENDER=$1
	SENDER_NAME=$2
	RECEIVER=$3
	SUBJECT=$4
	MESSAGE=$5
	
	tmp_mailfile="/tmp/mail.$$"
	
	# define message body
	echo -e "From: $SENDER
To: $RECEIVER
Subject: $SUBJECT
Auto-Submitted: auto-replied
Content-Type: text/plain; charset=\"UTF-8\"
$MESSAGE
" >> $tmp_mailfile
	
	[[ $GF_TEST -eq 0 ]] \
		&& cat $mailfile | /usr/sbin/sendmail -t
	
	return $?
	
}


# --------------------------------------
#	check required software
# --------------------------------------
if [[ `which mysql | wc -l` -eq 0 ]]; then
	log "FATAL: missing mysql"
	exit 1
fi

if [[ `which sendmail | wc -l` -eq 0 ]]; then
	log "FATAL: missing sendmail"
	exit 1
fi


# --------------------------------------
#	test mysql configuration
# --------------------------------------
mysql_test
if [ $? -eq 1 ];then
	echo "exit"
	exit 1
fi

mysql_check_database
if [ $? -eq 1 ];then
	exit 1
fi

# --------------------------------------
#	update database entries
# --------------------------------------
mysql_execute "$GF_DISABLE_FORWARDINGS"
if [ $? -eq 0 ];then
	log "successfully updated database (disabled entries)"
fi

mysql_execute "$GF_ENABLE_FORWARDINGS"
if [ $? -eq 0 ];then
	log "successfully updated database (enabled entries)"
fi

# --------------------------------------
#	execute
# --------------------------------------

# set controlling dates
current_time=$(date +%s)
response_time=$(expr $current_time - $GF_CYCLE)

# load stored forwardings
mysql_execute "$GF_FORWARDINGS"
[[ $? -gt 0 ]] && exit 1

if [[ $MYSQL_RESULT_COUNT -eq 0 ]]; then
	log "no autoresponse found, no action required" "INFO"
else

	FORWARDINGS=$MYSQL_RESULT
	
	while read email name; do
	
		log "run autoresponse for email '$email'" 1
	
		# check email account
		if [[ $GF_CHECK_USER -eq 1 ]]; then
		
			GF_CHECK_USER_QUERY_SQL=$(echo $GF_CHECK_USER_QUERY | sed "s/\%m/${email}/g")
			mysql_execute "$GF_CHECK_USER_QUERY_SQL"
			if ( [ $? -eq 1 ] || [ "$MYSQL_RESULT" == "" ] ); then
				log "can't find email '$email' in user table"
				continue
			fi
		
		fi
	
		# get email parts
		account=${email%%@*}
		domain=${email##*@}
		
		# get response subject
		GF_RESPONDER_SUBJECT_SQL=$(echo $GF_RESPONDER_SUBJECT | sed "s/\%m/$email/g")
		mysql_execute "$GF_RESPONDER_SUBJECT_SQL"
		RESPONSE_SUBJECT=$MYSQL_RESULT
		
		# get response message
		GF_RESPONDER_MESSAGE_SQL=$(echo $GF_RESPONDER_MESSAGE | sed "s/\%m/$email/g")
		mysql_execute "$GF_RESPONDER_MESSAGE_SQL"
		RESPONSE_MESSAGE=$MYSQL_RESULT
		
		# define mailbox path
		[[ $MAILBOX_AS_EMAIL -eq 1 ]] \
			&& USER_MAILBOX_PATH=$MAILBOX_PATH/$domain/$account/ \
			|| USER_MAILBOX_PATH=$MAILBOX_PATH/$email/
		
		# check mailbox folder new
		if [ ! -d $USER_MAILBOX_PATH ]; then
			log "can't find account mailbox '$USER_MAILBOX_PATH'"
			continue
		fi
		
		# check for new mails
		log "try to find new mails in '$USER_MAILBOX_PATH'" 1
		while read file; do
		
			# check if file an file
			[[ ! -f $file ]] && continue
		
			# ignore folders (Spam, Trash, Drafts), if exists
			[[ $file =~ ^$USER_MAILBOX_PATH.(Spam|Trash|Drafts)/ ]] && continue
		
			# get creation time of file
			create_time=$(stat -c %Y $file)
		
			# calc time difference
			response_time_diff=$(expr $response_time - $create_time)
			log "creation time difference '$response_time_diff' for file '$file'" 1
			
			# check response time cycle limit
			if [ $response_time_diff -lt 0 ]; then
				
				log "new mail found $file ($response_time_diff)"
				
				# preset variables
				RESPONSE=1
				RETURN_PATH=
				REPLY_TO=
				FROM=
				
				# data
				SUBJECT=
				
				# spam protection variables
				SPAM_FLAG=0
				
				# read file to find addresses to response
				while read line; do
				
					# auto submitted detection, an reason why auto respnonsed mails take much traffic
					# @see RFC3834
					if [[ $line =~ ^Auto-Submitted: [[:space:]](auto-generated|auto-replied) ]]; then
						log "mail auto submitted, no autoresponse" "DEBUG"
						continue
					fi
					
					# cron service detection
					if [[ $line =~ ^X-Cron-Env\: ]]; then
						log "mail auto submitted by cron service, no autoresponse" "DEBUG"
						continue
					fi
					
					# return path
					if [[ $line =~ ^Return-Path\: ]]; then
						seperateAddress "${line##*\:}"
						RETURN_PATH=$RES_ADDRESS
						log "found return-path '$RETURN_PATH'" 1
					fi
					
					# from with desciption name
					if [[ $line =~ ^From\: ]]; then
						seperateAddress "${line##*\:}"
						FROM=$RES_ADDRESS
						log "found from '$FROM'" 1
					fi
					
					# reply-to
					if [[ $line =~ ^Reply-To\: ]]; then
						seperateAddress "${line##*\:}"
						REPLY_TO=$RES_ADDRESS
						log "found reply-to '$REPLY_TO'" 1
					fi
					
					# find to address for only response by correct address
					if [[ $line =~ ^To\: ]]; then
						seperateAddress "${line##*\:}"
						if [[ "$RES_ADDRESS" == "$email" ]]; then
							log "correct target for '$file'" 1
							[[ $RESPONSE -eq 1 ]] && RESPONSE=1
						fi
					fi
					
					# spam-protection
					if ( [[ $line =~ ^X-Spam-Flag\:[[:space:]]YES ]] ); then
						RESPONSE=0
						log "spam detected no response"
						break;
					fi
					
					# subject
					if [[ $line =~ ^Subject\: ]]; then
						SUBJECT=${line##*\:}
						log "subject found '$SUBJECT'" 1
					fi
					
					# check for header close line
					if [[ $line =~ ^Content-Type\: ]]; then
						log "r:$RETURN_PATH rt:$REPLY_TO f:$FROM ar:$RESPONSE" 1
						break
					fi
				
				done < <(cat $file)
				
				# response
				if [ $RESPONSE -eq 1 ]; then
				
					# from
					[[ "$FROM" != "" ]] && RESPONSE_ADDRESS=$FROM;
				
					# return-path
					( [[ "$RETURN_PATH" != "" ]] ) && RESPONSE_ADDRESS=$RETURN_PATH
					
					log "set response address to '$RESPONSE_ADDRESS'" 1
					
					# reply-to restrict response
					if [[ "$REPLY_TO" != "" ]]; then
						RESPONSE_ADDRESS=$REPLY_TO
						log "replace response address with reply-to '$RESPONSE_ADDRESS'"
					fi

					# check for no-reply will no resonse
					if [[ $RESPONSE_ADDRESS =~ ^(no-?reply|bounce) ]]; then
						log "no-reply detected no autoresponse"
						continue
					fi
					
					# check response
					# fix by Karl Herrick, thank's a lot
					if [[ $RESPONSE_ADDRESS == $email ]]; then
						log "email address from autoresponder table is the same as the intended recipient, no autoresponse"
						continue
					fi
					
					# update subject, if %s defined in response subject
					RESPONSE_SUBJECT=´echo $RESPONSE_SUBJECT | sed 's/%s/$SUBJECT/g'´
					
					# send response mail
					sendmail $email "$name" $RESPONSE_ADDRESS "$RESPONSE_SUBJECT" "$RESPONSE_MESSAGE"
					if [ $? -eq 0 ]; then
						log "autoresponse sended to '$RESPONSE_ADDRESS' for '$email'"
					fi
					
				fi
				
			fi
			
		done < <(find $USER_MAILBOX_PATH -type f)
		
	done < <(echo "$FORWARDINGS")
	
fi

exit 0
