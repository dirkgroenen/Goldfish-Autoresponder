<?php
/*
	goldfish - the PHP auto responder for postfix

    Copyright © 2007-2015 - Authors:
    
    (c) 2007-2009 Remo Fritzsche    (Main application programmer)
    (c) 2009 Karl Herrick (Bugfix)
    (c) 2007-2008 Manuel Aller (Additional programming)
    (c) 2015 Dirk Groenen (Additional programming)

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
    
    Version 1.1
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
    /* General */
    $conf['cycle'] = 5 * 60;
    
    /* Logging */
    $conf['log_file_path'] = "/var/log/goldfish";
    $conf['write_log'] = true;
    
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
		var $logfile;
		var $str;
		function addLine($str)
		{
		    $str = date("Y-m-d h:i:s")." ".$str;
		    $this->str .= "\n$str";
		    echo $str."\n";
		}
		
		function writeLog(&$conf)
		{
		    if (! $conf['write_log'] ) return;
		    
		    if (is_writable($conf['log_file_path']))
		    {
		    	$this->addLine("--------- End execution ------------");
	   	    	if (!$handle = fopen($conf['log_file_path'], 'a'))
	   	    	{
	                echo "Cannot open file ({$conf['log_file_path']})";
	                exit;
	            }
	
	            if (fwrite($handle, $this->str) === FALSE)
	            {
	                echo "Cannot write to file)";
	                exit;
	            }
	            else
	            {
					echo "Wrote log successfully.";
		    	}
	
	            fclose($handle);
	
		  }
		  else
		  {
			echo "Error: The log file is not writeable.\n";
			echo "The log has not been written.\n";
		  }
		}
    }
    
    ######################################
    # Create log object #
    ######################################
    $log = new Logger();
    
    ######################################
    # function endup() #
    ######################################
    function endup(&$log, &$conf)
    {
		$log->writeLog($conf);
		exit;
    }
    
    ######################################
    # Database connection #
    ######################################
    $link = @mysql_connect($conf['mysql_host'], $conf['mysql_user'], $conf['mysql_password']);
    if (!$link)
    {
		$log->addLine("Could not connect to database. Abborting.");
		endup($log, $conf);
    }
    else
    {
		$log->addLine("Connection to database established successfully");
		
		if (!mysql_select_db($conf['mysql_database']))
		{
	    	$log->addLine("Could not select database ".$conf['mysql_database']);
	    	endup($log, $conf);
		}
		else
		{
	    	$log->addLine("Database selected successfully");
		}
    }
    
    ######################################
    # Update database entries #
    ######################################
    $result = mysql_query($conf['q_disable_forwarding']);
    
    if (!$result)
    {
		$log->addLine("Error in query ".$conf['q_disable_forwarding']."\n".mysql_error());
    }
    else
    {
		$log->addLine("Successfully updated database (disabled entries)");
    }
    
    mysql_query($conf['q_enable_forwarding']);
    
    if (!$result)
    {
		$log->addLine("Error in query ".$conf['q_enable_forwarding']."\n".mysql_error());
    }
    else
    {
		$log->addLine("Successfully updated database (enabled entries)");
    }
    
    ######################################
    # Catching dirs of autoresponders mailboxes #
    ######################################
    
    // Corresponding email addresses
    $result = mysql_query($conf['q_forwardings']);
    
    if (!$result)
    {
    	$log->addLine("Error in query ".$conf['q_forwardings']."\n".mysql_error());
    	exit;
    }
   
    $num = mysql_num_rows($result);
    
    for ($i = 0; $i < $num; $i++)
    {
		$emails[] = mysql_result($result, $i, "email");
		$name[] = mysql_result($result, $i, "descname");
    }
    
    // Fetching directories
    for ($i = 0; $i < $num; $i++)
    {
		$result = mysql_query(str_replace("%m", $emails[$i], $conf['q_mailbox_path']));
		
		if (!$result)
		{
	    	$log->addLine("Error in query ".$conf['q_mailbox_path']."\n".mysql_error()); exit;
		}
		else
		{
	    	$log->addLine("Successfully fetched maildir directories");
		}
	
		$paths[] = mysql_result($result, 0, 'path') . 'new/';
    }
    
    ######################################
    # Reading new mails #
    ######################################
    if ($num > 0)
    {
        $log->addLine("Reading new emails: new emails found: " . $num);
	    $i = 0;
	    
	    foreach ($paths as $path)
	    {
            $log->addLine("Start scanning directory " . $path);

	    	foreach(scandir($path) as $entry)
	    	{
		    	if ($entry != '.' && $entry != '..')
		    	{
                    $log->addLine("Found entry [" . $entry . "] in directory " . $path);
                    
					if (time() - filemtime($path . $entry) - $conf['cycle'] <= 0)
					{
			    		$mails[] = $path . $entry;
			    		
					    ###################################
					    # Send response #
					    ###################################
			    
			    		// Reading mail address
			   			$mail = file($path.$entry);
			    		
    					foreach ($mail as $line)
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
		    
			    		// Check: Is this mail allready answered
			    
			    		if (empty($address))
			    		{
							$log->addLine("Error, could not parse mail $path");
			    		}
			    		else
			    		{
							// Get data of current mail
				   			$email = $emails[$i];
				    
				    		// Get subject
				    		$result = mysql_query(str_replace("%m", $emails[$i], $conf['q_subject']));
				    
				    		if (!$result)
				    		{
								$log->addLine("Error in query ".$conf['q_subject']."\n".mysql_error()); exit;
				    		}
				    		else
				    		{
								$log->addLine("Successfully fetched subject of {$emails[$i]}");
				    		}
		
				    		$subject = mysql_result($result, 0, 'subject');
	
				    		// Get Message
				    		$result = mysql_query(str_replace("%m", $emails[$i], $conf['q_messages']));
				    		
				    		if (!$result)
				    		{
								$log->addLine("Error in query ".$conf['q_messages']."\n".mysql_error()); exit;
				    		}
				    		else
				    		{
								$log->addLine("Successfully fetched message of {$emails[$i]}");
				    		}
				    
				    		$message = mysql_result($result, 0, 'message');
	
				    		$headers = "From: ".$name[$i]."<".$emails[$i].">";
	
				    		// Check if mail is allready an answer:
				    		if (strstr($mail, $message))
				    		{
								$log->addLine("Mail from {$emails[$i]} allready answered");
								break;
				    		}
				
							// strip the line break from $address for checks
							// fix by Karl Herrick, thank's a lot
							if ( substr($address,0,strlen($address)-1) == $email )
							{
							        $log->addLine("Email address from autoresponder table is the same as the intended recipient! Not sending the mail!");
							        break;
							}

							$sent = mail($address, $subject, $message, $headers);

                            if($sent){
                                $log->addLine("Autoresponse e-mail was sent to: " . $address);
                            }
                            else{
                                $log->addLine("Autoresponse was not sent. Something went wrong");   
                            }
			   			}
					}
		    	}
			}
			
			$i++;
		}
	}
    else
    {
        $log->addLine("No new email found. Doing nothing...");
    }
	$log->writeLog($conf);
	echo "End execution."; 
?>
