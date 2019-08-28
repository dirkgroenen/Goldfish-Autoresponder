CREATE TABLE `autoresponder` (
    `email` varchar(255) NOT NULL default '',
    `descname` varchar(255) default NULL,
    `from` date NOT NULL default '0000-00-00',
    `to` date NOT NULL default '0000-00-00',
    `message` text NOT NULL,
    `enabled` tinyint(4) NOT NULL default '0',
    `subject` varchar(255) NOT NULL default '',
    PRIMARY KEY (`email`),
    FULLTEXT KEY `message` (`message`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
