DROP TABLE IF EXISTS `item`;

CREATE TABLE `item` (
  `modified` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `created` timestamp NOT NULL default '0000-00-00 00:00:00',
  `feed` varchar(255) default NULL,
  `link` varchar(255) default NULL,
  `short_link` varchar(255) default NULL,
  `title` varchar(255) default NULL,
  `description` varchar(65535) default NULL,
  `hash` varchar(255) default NULL,
  `username` varchar(255) default NULL,
  `password` varchar(255) default NULL,
  `endpoint` varchar(255) default NULL,
  `published` timestamp NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`hash`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

