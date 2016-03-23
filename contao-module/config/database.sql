-- **********************************************************
-- *                                                        *
-- * IMPORTANT NOTE                                         *
-- *                                                        *
-- * Do not import this file manually but use the TYPOlight *
-- * install tool to create and maintain database tables!   *
-- *                                                        *
-- **********************************************************

CREATE TABLE `tl_hofff_page_translation` (

  `pageFrom` int(10) unsigned NOT NULL,
  `pageTo` int(10) unsigned NOT NULL,

  PRIMARY KEY  (`pageFrom`, `pageTo`),
  KEY `pageTo_ix` (`pageTo`),

) ENGINE=MyISAM DEFAULT CHARSET=utf8;
