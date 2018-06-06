<?php
/*
    goldfish - the PHP auto responder for postfix

    Copyright © 2007-2018 - Authors:

    (c) 2007-2009 Remo Fritzsche    (Main application programmer)
    (c) 2009 Karl Herrick (Bugfix)
    (c) 2007-2008 Manuel Aller (Additional programming)
    (c) 2015 Dirk Groenen (Additional programming)
    (c) 2018 Roy Arisse <support@perfacilis.com> (Additional programming)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

    Version 1.2
*/

    ini_set('display_errors', true);
    error_reporting( E_ALL );

    ######################################
    # Check PHP version #
    ######################################

    if ( version_compare( PHP_VERSION, "5.0.0" ) == - 1 )
    {
        echo "Error, you are currently not running PHP 5 or later. Exiting.\n";
        exit;
    }

    ######################################
    # Configuration #
    ######################################
    /* Check every x seconds for new e-mails default 5mins. Set cronjob to same frequency or less. */
    $conf['cycle'] = 5 * 60;
    /* Ensure sender doesn't get a response more than once per n seconds, default 24h */
    $conf['resend_after'] = 24 * 60 * 60;

    /* Logging */
    $conf['log_file_path'] = "/var/log/goldfish";

    /* Database information */
    $conf['mysql_host'] = "localhost";
    $conf['mysql_user'] = "mailuser";
    $conf['mysql_password'] = "password";
    $conf['mysql_database'] = "mailserver";

    /* Database Queries */

    # This query has to return the path (`path`) of the corresponding maildir-Mailbox with email-address %m
    $conf['q_mailbox_path'] = "SELECT CONCAT('/var/mail/vhosts/', SUBSTRING_INDEX(email,'@',-1), '/', SUBSTRING_INDEX(email,'@',1), '/') AS `path` FROM virtual_users WHERE `email` = '%m'";

    # This query has to return the following fields from the autoresponder table: `from`, `to`, `email`, `message` where `enabled` = 2
    $conf['q_forwardings'] = "SELECT * FROM `autoresponder` WHERE `enabled` = 1 AND `force_disabled` = 0"; //modified for the autoresponder plugin

    # This query has to disable every autoresponder entry which ended in the past
    $conf['q_disable_forwarding'] = "UPDATE `autoresponder` SET `enabled` = 0 WHERE `to` < CURDATE();";

    # This query has to activate every autoresponder entry which starts today
    $conf['q_enable_forwarding'] = "UPDATE `autoresponder` SET `enabled` = 1 WHERE `from` <= CURDATE() AND (`to` >= CURDATE() OR `to`='0000-00-00');"; //modified for the autoresponder plugin

    # This query has to return the message of an autoresponder entry identified by email %m
    $conf['q_messages'] = "SELECT `message` FROM `autoresponder` WHERE `email` = '%m'";

    # This query has to return the subject of the autoresponder entry identified by email %m
    $conf['q_subject'] = "SELECT `subject` FROM `autoresponder` WHERE `email` = '%m'";


    ############################################################################
    #
    # Don't edit anything below here unless you know what you're doing!
    #
    ############################################################################

    ######################################
    # Logger class #
    ######################################

    class Logger
    {
        private $logfile = '';
        private $str = '';

        public function __construct($logfile)
        {
            // Ensure logfile exists
            if (!file_exists($logfile) && !touch($logfile)) {
                echo 'Unable to create logfile \'' . $logfile . '\'. Exiting.';
                exit(1);
            }

            // And ensure it's writable
            if (!is_writable($logfile)) {
                echo 'No permission to write to logfile \'' . $logfile . '\'. Exiting.';
                exit(1);
            }

            $this->logfile = $logfile;

            // Read n last lines to avoid logfile becomes too large
            $lines = file($this->logfile);
            $count = count($lines);
            $limit = 10000;
            for ($i = max(0, $count - $limit); $i < $count; $i += 1) {
                $this->str .= $lines[$i];
            }

            $this->addLine('------------ Start execution ------------');
        }

        public function addLine($str)
        {
            $str = date("Y-m-d H:i:s") . " " . $str;
            $this->str .= PHP_EOL . $str;

            // Immediately write to log, to ensure log exists even when script fails
            $this->writeLog($this->str);

            echo $str . PHP_EOL;
        }

        public function getLastSent($address) {
            $address = trim($address);
            $last_sent = 0;
            $matches = [];

            if (!preg_match_all('~^([\d\-\: ]+) SENDING to ' . preg_quote($address) . '$~m', $this->str, $matches)) {
                return $last_sent;
            }

            foreach($matches[1] as $date_time) {
                $stamp = strtotime($date_time);
                if ($stamp > $last_sent) {
                    $last_sent = $stamp;
                }
            }

            return $last_sent;
        }

        public function addSent($address) {
            $this->addLine('SENDING to ' . $address);
        }

        public function __destruct() {
            $this->addLine('------------ End execution ------------');
        }

        private function writeLog()
        {
            if (!file_put_contents($this->logfile, $this->str)) {
                echo 'Unable to write log. Exiting.';
                exit(1);
            }
        }
    }

    ######################################
    # Create log object #
    ######################################
    $log = new Logger($conf['log_file_path']);

    ######################################
    # function endup() #
    ######################################
    function endup()
    {
        echo 'End execution.';
        exit;
    }

    ######################################
    # Replacement function for mysqli_result #
    ######################################
    function mysqli_result($result, $row = 0, $field = 0) {
        $numrows = mysqli_num_rows($result);
        if (!$numrows || $row > ($numrows -1) || $row < 0) {
            return false;
        }

        mysqli_data_seek($result, $row);
        $data = is_numeric($field) ? mysqli_fetch_row($result) : mysqli_fetch_assoc($result);

        if (!isset($data[$field])) {
            return false;
        }

        return $data[$field];
    }

    ######################################
    # Database connection #
    ######################################
    $link = @mysqli_connect($conf['mysql_host'], $conf['mysql_user'], $conf['mysql_password']);
    if (!$link)
    {
        $log->addLine("Could not connect to database. Aborting.");
        endup();
    }
    else
    {
        $log->addLine("Connection to database established successfully");

        if (!mysqli_select_db($link, $conf['mysql_database']))
        {
            $log->addLine("Could not select database ".$conf['mysql_database']);
            endup();
        }
        else
        {
            $log->addLine("Database selected successfully");
        }
    }

    ######################################
    # Update database entries #
    ######################################
    $result = mysqli_query($link, $conf['q_disable_forwarding']);

    if (!$result)
    {
        $log->addLine("Error in query ".$conf['q_disable_forwarding']."\n".mysql_error());
        endup();
    }
    else
    {
        $log->addLine("Successfully updated database (disabled entries)");
    }

    mysqli_query($link, $conf['q_enable_forwarding']);

    if (!$result)
    {
        $log->addLine("Error in query ".$conf['q_enable_forwarding']."\n".mysql_error());
        endup();
    }
    else
    {
        $log->addLine("Successfully updated database (enabled entries)");
    }

    ######################################
    # Catching dirs of autoresponders mailboxes #
    ######################################

    // Corresponding email addresses
    $result = mysqli_query($link, $conf['q_forwardings']);

    if (!$result)
    {
        $log->addLine("Error in query ".$conf['q_forwardings']."\n".mysql_error());
        endup();
    }

    $num = mysqli_num_rows($result);

    $emails = $name = [];
    for ($i = 0; $i < $num; $i++)
    {
        $emails[] = mysqli_result($result, $i, "email");
        $name[] = mysqli_result($result, $i, "descname");
    }

    // Fetching directories
    $paths = [];
    for ($i = 0; $i < $num; $i++)
    {
        $result = mysqli_query($link, str_replace("%m", $emails[$i], $conf['q_mailbox_path']));

        if (!$result)
        {
            $log->addLine("Error in query ".$conf['q_mailbox_path']."\n".mysql_error());
            endup();
        }
        else
        {
            $log->addLine("Successfully fetched maildir directories");
        }

        // Check both cur and new, grouped per email
        $paths[$i][] = mysqli_result($result, 0, 'path') . 'new/';
        $paths[$i][] = mysqli_result($result, 0, 'path') . 'cur/';
    }

    ######################################
    # Reading new mails #
    ######################################
    if (!$num) {
        $log->addLine("No new email found. Doing nothing...");
        endup();
    }

    $log->addLine("Reading new emails: new emails found: " . $num);

    foreach ($paths as $i => $set)
    {
        foreach($set as $path) {
            // Ensure dir exists
            if (!is_dir($path)) {
                $log->addLine('Skipping directory ' . $path . ', because it doesn\'t exist.');
                continue;
            }

            $log->addLine("Start scanning directory " . $path);

            foreach(scandir($path) as $entry)
            {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }

                // Skip files older than cycle duration
                $delta = time() - filectime($path . $entry);
                if ($delta > $conf['cycle']) {
                    continue;
                }

                $log->addLine("Found entry [" . $entry . "] in directory " . $path);

                // Current recipient email
                $email = $emails[$i];

                // Reading sender mail address
                $lines = file($path . $entry);
                foreach ($lines as $line)
                {
                    $line = trim($line);

                    if (substr($line, 0, 12) == 'Return-Path:')
                    {
                        $returnpath = substr($line, strpos($line, '<') + 1, strpos($line, '>') - strpos($line, '<')-1)."\n";
                    }

                    if (substr($line, 0, 5) == 'From:' && strstr($line,"@"))
                    {
                        $address = substr($line, strpos($line, '<') + 1, strpos($line, '>') - strpos($line, '<')-1)."\n";
                        break;
                    }
                    elseif(substr($line,0,5) == 'From:' && !strstr($line,"@") && !empty ($returnpath))
                    {
                        $address = $returnpath;
                        break;
                    }
                }

                if (empty($address))
                {
                    $log->addLine("Error, could not parse mail $path");
                    continue;
                }

                // Only send mail once every x seconds
                $delta = time() - $log->getLastSent($address);
                if ($delta < $conf['resend_after']) {
                    $log->addLine('Receiver ' . trim($address) . ' notified ' . $delta . ' seconds ago, skipping.');
                    continue;
                }

                // Get subject
                $result = mysqli_query($link, str_replace("%m", $email, $conf['q_subject']));
                if (!$result)
                {
                    $log->addLine("Error in query ".$conf['q_subject']."\n".mysql_error());
                    endup();
                }
                else
                {
                    $log->addLine("Successfully fetched subject of {$email}");
                }

                $subject = mysqli_result($result, 0, 'subject');

                // Get Message
                $result = mysqli_query($link, str_replace("%m", $email, $conf['q_messages']));

                if (!$result)
                {
                    $log->addLine("Error in query ".$conf['q_messages']."\n".mysql_error());
                    endup();
                }
                else
                {
                    $log->addLine("Successfully fetched message of {$email}");
                }

                $message = mysqli_result($result, 0, 'message');

                $headers = "From: ".$name[$i]."<".$email.">";

                // Check if mail is allready an answer:
                // if (strstr($mail, $message))
                // {
                //     $log->addLine("Mail from {$email} allready answered");
                //     break;
                // }

                // strip the line break from $address for checks
                // fix by Karl Herrick, thank's a lot
                if ( substr($address,0,strlen($address)-1) == $email )
                {
                    $log->addLine("Email address from autoresponder table is the same as the intended recipient! Not sending the mail!");
                    break;
                }

                $sent = mail($address, $subject, $message, $headers);

                if ($sent) {
                    $log->addSent($address);
                }
                else{
                    $log->addLine("Autoresponse was not sent. Something went wrong");
                }
            }
        }
    }

    endup();
