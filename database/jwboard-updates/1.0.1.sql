CREATE TABLE IF NOT EXISTS `v2_jwboard_update_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(32) NOT NULL,
  `migration` varchar(255) NOT NULL,
  `applied_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `version_migration` (`version`, `migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
