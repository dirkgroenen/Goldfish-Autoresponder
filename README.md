# Goldfish autoresponder
Goldfish is a simple autoresponder script (written in PHP) for Postfix. It consists of only one PHP file which can be started through a cronjob. It works with a Postfix, Courier, MySQL and Virtual Users setup. It cannot be used with setups that don't make use of a database to store the mail accounts.

There also a plugin for Roundcube available so your users can create their own Auto reply messages.

## Installation
To install Goldfish we first have to create a table in our mail database. Open the database and run the following SQL command:

```sql
CREATE TABLE `autoresponder` (
    `email` varchar(255) NOT NULL default '',
    `descname` varchar(255) default NULL,
    `from` date NOT NULL default '0000-00-00',
    `to` date NOT NULL default '0000-00-00',
    `message` text NOT NULL,
    `enabled` tinyint(4) NOT NULL default '0',
    `force_enabled` tinyint(4) NOT NULL default '0',
    `subject` varchar(255) NOT NULL default '',
    PRIMARY KEY (`email`),
    FULLTEXT KEY `message` (`message`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
```

Afterwards you download the ``goldfish.php`` script and place it in a directory on your server. In my case I'm going for ```/usr/local/goldfish```. 

```bash
mkdir /usr/local/goldfish
wget https://github.com/dirkgroenen/Goldfish-Autoresponder/archive/master.tar.gz
tar -xvf master.tar.gz -C /usr/local/goldfish
mv /usr/local/goldfish/Goldfish-Autoresponder-master/* /usr/local/goldfish
rm -r /usr/local/goldfish/Goldfish-Autoresponder-master
```

## Configuration

After you have downloaded and extracted the script you have to open it and fill in your database credentials.

```bash
nano /usr/local/goldfish/goldfish.php
```

In this file you have to change the configuration values so they match with your setup. 

```php
$conf['mysql_host'] = "localhost"; // MySQL host
$conf['mysql_user'] = "mailuser"; // MySQL user
$conf['mysql_password'] = "password"; // MySQL password
$conf['mysql_database'] = "mailserver"; // MySQL database where we created the autoresponder table
```

In some cases you also need to change the MySQL queries to match your mail setup. 

After you have configured Goldfish you need to enable it via a cronjob. In my case I want Goldfish to be executed every 5 minutes:

```
*/5 * * * * /usr/local/goldfish/goldfish.php
```

# Creating an autoresponder

After we have installed and configured Goldfish we can create our first Autoresponse message. In this case we are going to do this through mysql so lets login

```bash
mysql -u mailuser -p 
```

```sql
USE mailserver;
```

```sql
INSERT INTO `autoresponder` (`email`, `descname`, `from`, `to`, `message`, `enabled`, `force_enabled`, `subject`) VALUES ('office@mail.com', 'office@mail.com Autoresponse', '2015-05-20', '2015-05-30', 'Dear mailer\r\n, I will be out of office till 2015-05-30. Please contact one of my colleagues.\r\nThanks!\r\Henk', 1, 1, 'Out of Office');
```

```sql
quit;
```

The above command created an autoresposne for ``office@mail.com`` which will be active from ``2015-05-20`` till ``2015-05-30``. Because we have created a cronjob which runs every 5 minutes, Goldfish won't send a message immediately, but somewhere within a range of five minutes after the mail was received. 

When opening the ``/var/log/goldfish`` it will show something like this:

```
2015-05-29 12:00:01 Connection to database established successfully
2015-05-29 12:00:01 Database selected successfully
2015-05-29 12:00:01 Successfully updated database (disabled entries)
2015-05-29 12:00:01 Successfully fetched maildir directories
2015-05-29 12:00:01 Reading new emails: new emails found: 1
2015-05-29 12:00:01 Start scanning directory /var/mail/vhosts/mail.com/example/new/
2015-05-29 12:00:01 Found entry [.] in directory /var/mail/vhosts/mail.com/example/new/
2015-05-29 12:00:01 Found entry [..] in directory /var/mail/vhosts/mail.com/example/new/
2015-05-29 12:00:01 Found entry [1432893598.M4690P8298.mail,S=29290,W=29790] in directory /var/mail/vhosts/mail.com/example/new/
2015-05-29 12:00:01 Successfully fetched subject of example@mail.com
2015-05-29 12:00:01 Successfully fetched message of example@mail.com
2015-05-29 12:00:02 Autoresponse e-mail was sent to: otheruser@othermail.com
```

# Roundcube plugin

If you want your users to be able to create their own autoresponse you can download and install the Roundcube Goldfish plugin. 

The plugin in this repository is compatible with every Roundcube version. 

## Install
Download this repository and move the ``autoreply`` directory (located in ``roundcube``) to your Roundcube plugins directory. Open the Roundcube config file (``config/main.inc.php``) and add ``autoreply`` to the plugin array.

```php
rcmail_config['plugins'] = array('pluginx', 'autoreply');
```

## Configuration
Open the ``config.inc.php`` file and change the database connection string so it matchs your setup. 

# Credits
Goldfish is originally created by [Remo Fritzsche](http://remofritzsche.com/), but now available for download anymore.