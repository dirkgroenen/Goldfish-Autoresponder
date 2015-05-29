<?php

/**
 * Auto - Reply Plugin (for Goldfish)
 * Configuration file
 *
 * @version 2.1
 *
 * Author(s): 
 *     Yevgen Sklyar, 
 *     David Müller (Incloud), 
 *     Dirk Groenen (Bitlabs)
 *     
 * Date: March 16, 2011
 * License: GPL
 * www.eugenesklyar.com, www.incloud.de, www.bitlabs.nl
 */

/**
 * Connection string for this plugin (alternatively it will use the default RoundCube connection)
 */
$rcmail_config['autoreply_db_dsn'] = 'mysql://mailuser:password@localhost/mailserver';

/**
 * Query for fetching the current autoreply message for the particular email address
 */
$rcmail_config['get_query'] = 'SELECT `from`, `to`, `message`, `force_disabled`, `subject` FROM autoresponder WHERE `email` = %u LIMIT 1';

/**
 * Query for adding a new autoreply message for a particular address
 */
$rcmail_config['insert_query'] = 'INSERT INTO autoresponder(`email`, `from`, `to`, `message`, `enabled` , `force_disabled`, `subject`) VALUES (%u, %f, %t, %m, 0, %d, %s)';

/**
 * Query for updating an autoreply message that is already in the database
 */
$rcmail_config['update_query'] = 'UPDATE autoresponder SET `from` = %f, `to` = %t, `message` = %m, `force_disabled` = %d, `subject` = %s WHERE `email` = %u LIMIT 1';
