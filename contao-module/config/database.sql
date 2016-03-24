-- **********************************************************
-- *                                                        *
-- * IMPORTANT NOTE                                         *
-- *                                                        *
-- * Do not import this file manually but use the TYPOlight *
-- * install tool to create and maintain database tables!   *
-- *                                                        *
-- **********************************************************

CREATE TABLE `tl_hofff_page_translation` (

  `page_id` int(10) unsigned NOT NULL,
  `translated_page_id` int(10) unsigned NOT NULL,

  PRIMARY KEY  (`page_id`, `translated_page_id`),
  KEY `translated_page_id` (`translated_page_id`),

) ENGINE=MyISAM DEFAULT CHARSET=utf8;
