-- **********************************************************
-- *                                                        *
-- * IMPORTANT NOTE                                         *
-- *                                                        *
-- * Do not import this file manually but use the TYPOlight *
-- * install tool to create and maintain database tables!   *
-- *                                                        *
-- **********************************************************

CREATE TABLE `tl_hofff_language_relations_page` (

  `item_id` int(10) unsigned NOT NULL,
  `related_item_id` int(10) unsigned NOT NULL,

  PRIMARY KEY  (`item_id`, `related_item_id`),
  KEY `related_item_id` (`related_item_id`),

) ENGINE=MyISAM DEFAULT CHARSET=utf8;
