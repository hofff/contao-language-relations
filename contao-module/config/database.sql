-- **********************************************************
-- *                                                        *
-- * IMPORTANT NOTE                                         *
-- *                                                        *
-- * Do not import this file manually but use the TYPOlight *
-- * install tool to create and maintain database tables!   *
-- *                                                        *
-- **********************************************************

CREATE TABLE `tl_page` (

  `cca_lr_group` int(10) unsigned NULL,

  KEY `cca_lr_group` (`cca_lr_group`),

) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE `tl_cca_lr_relation` (

  `pageFrom` int(10) unsigned NOT NULL,
  `pageTo` int(10) unsigned NOT NULL,

  PRIMARY KEY  (`pageFrom`, `pageTo`),
  KEY `pageTo_ix` (`pageTo`),

) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE `tl_cca_lr_group` (

  `id` int(10) unsigned NOT NULL auto_increment,
  `tstamp` int(10) unsigned NOT NULL default '0',
  `title` varchar(255) NOT NULL default '',
  
  PRIMARY KEY  (`id`),

) ENGINE=MyISAM DEFAULT CHARSET=utf8;
