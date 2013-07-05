DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(10) unsigned NOT NULL,
  `activated` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `username` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `username_clean` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `login_attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `last_login` datetime NOT NULL,
  `code` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `user_data` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`user_id`),
  INDEX (`activated`),
  UNIQUE (`username_clean`),
  INDEX (`last_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `session_id` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `session_expires` int(10) unsigned NOT NULL,
  `session_ip` varchar(39) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `session_browser` varchar(160) COLLATE utf8_unicode_ci NOT NULL,
  `session_data` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;