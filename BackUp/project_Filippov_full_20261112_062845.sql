-- АИС «Паспортный стол»: полный экспорт базы `project_Filippov`
-- Сформирован: 2026-11-12 06:28:45 (UTF-8)
-- Состав: таблицы + данные + функции, процедуры, триггеры; DEFINER удалён.
SET FOREIGN_KEY_CHECKS=0;

-- ---- таблица `action_log` ----
DROP TABLE IF EXISTS `action_log`;
CREATE TABLE `action_log` (
  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `action_time` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `entity_name` enum('citizens','passports','applications','users','action_log') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `action_type` enum('create','read','update','delete','login','logout','export','import','restore') NOT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_json`)),
  PRIMARY KEY (`log_id`),
  KEY `fk_action_log_user` (`user_id`),
  CONSTRAINT `fk_action_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=218 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `action_log` VALUES ('67', '2026-10-14 11:25:07', '3', 'users', '3', 'login', NULL, '{\"result\":\"ok\"}');
INSERT INTO `action_log` VALUES ('68', '2026-10-14 11:25:16', '3', 'users', '1', 'update', '{\"user_id\":1,\"login\":\"admin\",\"password_hash\":\"***\",\"role\":\"оператор\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-09-29\",\"failed_attempts\":0,\"is_blocked\":0,\"locked_until\":null}', '{\"login\":\"admin\",\"role\":\"администратор\"}');
INSERT INTO `action_log` VALUES ('69', '2026-10-14 11:25:21', '3', 'users', '3', 'update', '{\"user_id\":3,\"login\":\"user\",\"password_hash\":\"***\",\"role\":\"администратор\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-09-29\",\"failed_attempts\":0,\"is_blocked\":0,\"locked_until\":null}', '{\"login\":\"user\",\"role\":\"пользователь\"}');
INSERT INTO `action_log` VALUES ('70', '2026-10-14 11:25:28', '3', 'users', '3', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('71', '2026-10-14 11:25:41', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\"}');
INSERT INTO `action_log` VALUES ('72', '2026-10-14 11:26:11', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('73', '2026-10-14 11:26:50', '3', 'users', '3', 'login', NULL, '{\"result\":\"ok\"}');
INSERT INTO `action_log` VALUES ('74', '2026-10-14 11:26:55', '3', 'users', '3', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('75', '2026-10-14 11:27:46', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\"}');
INSERT INTO `action_log` VALUES ('76', '2026-10-14 11:34:00', '1', 'users', '3', 'update', '{\"user_id\":3,\"login\":\"user\",\"password_hash\":\"***\",\"role\":\"пользователь\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-09-29\",\"failed_attempts\":0,\"is_blocked\":1,\"locked_until\":null}', '{\"is_blocked\":0,\"failed_attempts\":0,\"locked_until\":null}');
INSERT INTO `action_log` VALUES ('77', '2026-10-14 11:34:08', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('78', '2026-10-14 11:34:24', '3', 'users', '3', 'login', NULL, '{\"result\":\"ok\"}');
INSERT INTO `action_log` VALUES ('79', '2026-10-14 11:34:27', '3', 'users', '3', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('80', '2026-10-14 11:34:57', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\"}');
INSERT INTO `action_log` VALUES ('81', '2026-10-14 11:37:23', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('82', '2026-10-14 11:45:04', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\"}');
INSERT INTO `action_log` VALUES ('83', '2026-10-16 11:15:00', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\"}');
INSERT INTO `action_log` VALUES ('84', '2026-10-16 11:15:07', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('85', '2026-10-16 11:16:34', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('86', '2026-10-16 11:48:26', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('87', '2026-10-16 11:48:26', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('88', '2026-10-16 11:49:33', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('89', '2026-10-16 11:49:33', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('90', '2026-10-16 11:51:05', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('91', '2026-10-16 11:51:05', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('92', '2026-10-16 11:51:22', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('93', '2026-10-16 11:51:22', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('94', '2026-10-16 11:51:48', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('95', '2026-10-16 11:51:48', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('96', '2026-10-16 11:51:53', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('97', '2026-10-16 11:51:53', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('98', '2026-10-16 11:52:03', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('99', '2026-10-16 11:52:03', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('100', '2026-10-16 11:56:19', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('101', '2026-10-16 11:56:19', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('102', '2026-10-16 13:22:10', '1', 'users', '1', 'login', NULL, '{\"result\":\"failed_password\",\"attempts_in_row\":1}');
INSERT INTO `action_log` VALUES ('103', '2026-10-16 13:23:54', '1', 'users', '1', 'login', NULL, '{\"result\":\"failed_password\",\"attempts_in_row\":1}');
INSERT INTO `action_log` VALUES ('104', '2026-10-16 13:23:54', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('105', '2026-10-16 13:23:54', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('106', '2026-10-16 13:23:54', '1', 'citizens', '1', 'export', NULL, '{\"format\":\"pdf\"}');
INSERT INTO `action_log` VALUES ('107', '2026-10-16 13:23:55', '1', 'passports', '1', 'export', NULL, '{\"format\":\"pdf\"}');
INSERT INTO `action_log` VALUES ('108', '2026-10-16 13:23:55', '1', 'citizens', '1', 'export', NULL, '{\"format\":\"pdf\"}');
INSERT INTO `action_log` VALUES ('109', '2026-10-16 13:28:36', '1', 'users', '1', 'login', NULL, '{\"result\":\"failed_password\",\"attempts_in_row\":1}');
INSERT INTO `action_log` VALUES ('110', '2026-10-16 13:28:37', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('111', '2026-10-16 13:28:37', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('112', '2026-10-16 13:28:37', '1', 'citizens', '1', 'export', NULL, '{\"format\":\"pdf\"}');
INSERT INTO `action_log` VALUES ('113', '2026-10-16 13:28:37', '1', 'passports', '1', 'export', NULL, '{\"format\":\"pdf\"}');
INSERT INTO `action_log` VALUES ('114', '2026-10-16 13:28:37', '1', 'citizens', '1', 'export', NULL, '{\"format\":\"pdf\"}');
INSERT INTO `action_log` VALUES ('115', '2026-10-16 13:40:41', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('116', '2026-10-16 13:41:18', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('117', '2026-10-16 13:41:38', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('118', '2026-10-16 13:52:04', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('119', '2026-10-16 13:52:26', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('120', '2026-10-16 13:53:05', '1', 'applications', '2', 'update', '{\"application_id\":2,\"citizen_id\":1,\"operator_id\":2,\"service_type\":\"Выдача паспорта\",\"status\":\"подано\",\"created_at\":\"2026-10-06 10:27:31\",\"updated_at\":null}', '{\"status\":\"в работе\"}');
INSERT INTO `action_log` VALUES ('121', '2026-10-16 13:53:09', '1', 'applications', '2', 'update', '{\"application_id\":2,\"citizen_id\":1,\"operator_id\":2,\"service_type\":\"Выдача паспорта\",\"status\":\"в работе\",\"created_at\":\"2026-10-06 10:27:31\",\"updated_at\":\"2026-10-16 13:53:05\"}', '{\"status\":\"подано\"}');
INSERT INTO `action_log` VALUES ('122', '2026-10-16 13:58:25', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('123', '2026-10-16 13:58:25', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('124', '2026-10-16 14:14:33', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('125', '2026-10-16 14:14:34', '1', 'applications', '0', 'export', NULL, '{\"format\":\"pdf\",\"kind\":\"report\"}');
INSERT INTO `action_log` VALUES ('126', '2026-10-16 14:16:26', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('127', '2026-10-16 14:16:46', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('128', '2026-10-16 14:17:04', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('129', '2026-10-20 09:06:10', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('130', '2026-10-20 09:06:22', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('131', '2026-11-06 14:33:19', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('132', '2026-11-06 14:43:03', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('133', '2026-11-06 14:43:34', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('134', '2026-11-06 14:43:53', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('135', '2026-11-07 09:15:50', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('136', '2026-11-07 09:17:51', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('137', '2026-11-07 09:18:23', '11', 'users', '11', 'create', NULL, '{\"login\":\"Ivn\",\"role\":\"пользователь\"}');
INSERT INTO `action_log` VALUES ('138', '2026-11-07 09:18:42', '11', 'users', '11', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('139', '2026-11-07 09:19:55', '11', 'users', '11', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('140', '2026-11-07 09:20:28', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('141', '2026-11-07 09:23:22', '1', 'users', '4', 'update', '{\"user_id\":4,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"пользователь\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-10-09\",\"failed_attempts\":0,\"is_blocked\":0,\"locked_until\":null}', '{\"login\":\"ivan\",\"role\":\"оператор\",\"is_blocked\":0,\"locked_until\":null}');
INSERT INTO `action_log` VALUES ('142', '2026-11-07 09:41:10', '1', 'users', '4', 'delete', '{\"user_id\":4,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"оператор\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"locked_until\":null}', NULL);
INSERT INTO `action_log` VALUES ('143', '2026-11-07 09:41:29', '1', 'users', '4', 'restore', NULL, '{\"user_id\":4,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"оператор\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"locked_until\":null}');
INSERT INTO `action_log` VALUES ('144', '2026-11-07 09:41:36', '1', 'users', '4', 'delete', '{\"user_id\":4,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"оператор\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"locked_until\":null}', NULL);
INSERT INTO `action_log` VALUES ('145', '2026-11-07 09:47:33', '1', 'users', '4', 'restore', NULL, '{\"user_id\":4,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"оператор\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"locked_until\":null}');
INSERT INTO `action_log` VALUES ('146', '2026-11-07 09:48:38', '1', 'users', '4', 'delete', '{\"user_id\":4,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"оператор\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"locked_until\":null}', NULL);
INSERT INTO `action_log` VALUES ('147', '2026-11-07 09:48:42', '1', 'users', '4', 'restore', NULL, '{\"user_id\":4,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"оператор\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"locked_until\":null}');
INSERT INTO `action_log` VALUES ('148', '2026-11-07 10:21:11', '1', 'users', '4', 'delete', '{\"user_id\":4,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"оператор\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"locked_until\":null}', NULL);
INSERT INTO `action_log` VALUES ('149', '2026-11-07 10:27:50', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('150', '2026-11-07 10:28:30', '12', 'users', '12', 'create', NULL, '{\"login\":\"ivan\",\"role\":\"пользователь\"}');
INSERT INTO `action_log` VALUES ('151', '2026-11-07 10:28:49', '12', 'users', '12', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('152', '2026-11-07 10:28:56', '12', 'users', '12', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('153', '2026-11-07 10:29:15', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('154', '2026-11-07 10:29:24', '1', 'users', '12', 'delete', '{\"user_id\":12,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"пользователь\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"is_deleted\":0,\"locked_until\":null}', '{\"is_deleted\":1}');
INSERT INTO `action_log` VALUES ('155', '2026-11-07 10:29:27', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('156', '2026-11-07 10:35:57', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('157', '2026-11-07 10:37:08', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('158', '2026-11-07 10:37:49', '1', 'users', '1', 'login', NULL, '{\"result\":\"ok\",\"factor\":\"2fa\"}');
INSERT INTO `action_log` VALUES ('159', '2026-11-07 10:38:10', '1', 'users', '11', 'delete', '{\"user_id\":11,\"login\":\"Ivn\",\"password_hash\":\"***\",\"role\":\"пользователь\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"is_deleted\":0,\"locked_until\":null}', '{\"is_deleted\":1}');
INSERT INTO `action_log` VALUES ('160', '2026-11-07 10:38:29', '1', 'users', '11', 'restore', NULL, '{\"user_id\":11,\"login\":\"Ivn\",\"password_hash\":\"***\",\"role\":\"пользователь\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"is_deleted\":0,\"locked_until\":null}');
INSERT INTO `action_log` VALUES ('161', '2026-11-07 10:51:27', '1', 'users', '1', 'update', NULL, '{\"tool\":\"admin_sql_console\",\"statements\":1,\"sql\":\"UPDATE users SET mfa_secret = TO_BASE64(RANDOM_BYTES(16)) WHERE password_hash = \'***\';\"}');
INSERT INTO `action_log` VALUES ('162', '2026-11-07 10:59:51', '1', 'users', '11', 'restore', NULL, '{\"user_id\":11,\"login\":\"Ivn\",\"password_hash\":\"***\",\"role\":\"пользователь\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"is_deleted\":0,\"locked_until\":null}');
INSERT INTO `action_log` VALUES ('163', '2026-11-07 11:00:25', '1', 'users', '12', 'restore', NULL, '{\"user_id\":12,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"пользователь\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"is_deleted\":0,\"locked_until\":null}');
INSERT INTO `action_log` VALUES ('164', '2026-11-07 11:00:38', '1', 'users', '12', 'delete', '{\"user_id\":12,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"пользователь\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"is_deleted\":0,\"locked_until\":null}', '{\"is_deleted\":1,\"is_blocked\":1,\"login\":\"deleted_12_20261107\"}');
INSERT INTO `action_log` VALUES ('165', '2026-11-07 11:01:02', '1', 'users', '12', 'restore', NULL, '{\"user_id\":12,\"login\":\"ivan\",\"password_hash\":\"***\",\"role\":\"пользователь\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"is_deleted\":0,\"locked_until\":null}');
INSERT INTO `action_log` VALUES ('166', '2026-11-07 11:01:15', '1', 'users', '11', 'delete', '{\"user_id\":11,\"login\":\"Ivn\",\"password_hash\":\"***\",\"role\":\"пользователь\",\"mfa_secret\":\"***\",\"last_password_change\":\"2026-11-07\",\"failed_attempts\":0,\"is_blocked\":0,\"is_deleted\":0,\"locked_until\":null}', '{\"is_deleted\":1,\"is_blocked\":1,\"login\":\"deleted_11_20261107\"}');
INSERT INTO `action_log` VALUES ('167', '2026-11-07 12:10:18', '1', 'action_log', '0', 'export', NULL, '\"enc1:9w7HAZoyfFldN5JGN0+\\/EBPdmmPySzAfi00Me+fQ2zc\\/GPZ4PiNjtmcWzDrVDhNBPhwsIg5c5YuRqzc1UbcTAXzBDxe9\"');
INSERT INTO `action_log` VALUES ('168', '2026-11-10 09:51:42', '1', 'users', '1', 'login', NULL, '\"enc1:QTWKz5JvgzWL8bGzbW58uSu3XPW90mX7Paqa40ozS6b1yethATeTxHcrBuRZDBMdKtOtdkH2MOR3xg==\"');
INSERT INTO `action_log` VALUES ('169', '2026-11-10 09:51:51', '1', 'users', '12', 'delete', '\"enc1:tlwP2O8pSX8H6H8uRRIoAmCdVXUOJwN06YzuP2v566GHdBrzWAeBAuI+b+HanNbPzuLYnXyLbm27UE9J+FCSB9r4kvdss++gYl6URGBs88hu+QO2AYqtemw0dskYpJ8\\/jqJOwvutQMA+OgYB5Xm7janFR40MTsYeC8IB1DrVTulLojuW+w7StdyYRk5y8CGiuYj4epLnCmmzkQb4l3NRcOOP+SmM4g3jYLvkIQFppvgbGx1As48STzIa38U\\/LfmqOnL7+Ksv6Myz8gOfAzab9kvyzNbnUET8oL53Afax4\\/7OYKZX\\/1YDRqCVY+899w==\"', '\"enc1:8xZpE570Gd\\/C4nGot7YyaPzNdZk9J9QxhJ48DyJe\\/9phtc8IuYExqqshUuHsvq1ghifuXzIkJslUsVf+BbcZWqkhTkvyIST65Vez03PIzBc5xTqPfzDXtPI=\"');
INSERT INTO `action_log` VALUES ('170', '2026-11-10 09:52:04', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('171', '2026-11-10 09:53:26', '3', 'users', '3', 'login', NULL, '\"enc1:viK8nC+41oxwIw5uUShupg\\/5JreS27miu0iELJhdaEe3mF9mEaOVWkLaEgXLbx9VcdsybK3TknkO2Q==\"');
INSERT INTO `action_log` VALUES ('172', '2026-11-10 09:56:40', '3', 'users', '3', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('173', '2026-11-10 09:57:02', '2', 'users', '2', 'login', NULL, '\"enc1:8KvHCKCX+lRHTS\\/iNop9rTTZQfsBunk0oWtsz+MOzh\\/WmtQ18N1o8vKqqmsfAN8vh3KaEbyehPQU+w==\"');
INSERT INTO `action_log` VALUES ('174', '2026-11-10 10:00:21', '2', 'applications', '0', 'export', NULL, '\"enc1:qthvHqxTYdSjqgz9NVo+EKFDaj63B1JkNRwv5dWFgnI\\/nvbbpi44UXdebVBFdCdkzWB9cr8rWMzZ5vAp\"');
INSERT INTO `action_log` VALUES ('175', '2026-11-10 10:06:45', '2', 'action_log', '0', 'export', NULL, '\"enc1:NmYsAYjH90GypMbSbNl4DVIo23UBhml2FnOzz8EuM8lZU4LF4pbUgpYXRy34FEitgZiVPymLymQ77O148zf+FQ2NYrLf\"');
INSERT INTO `action_log` VALUES ('176', '2026-11-10 10:09:30', '2', 'citizens', '4', 'create', NULL, '{\"last_name\": \"Ггг\", \"first_name\": \"Гг\", \"reg_status\": \"активна\"}');
INSERT INTO `action_log` VALUES ('177', '2026-11-10 10:09:30', '2', 'applications', '3', 'create', NULL, '{\"service_type\": \"Регистрация гражданина\", \"status\": \"завершено\"}');
INSERT INTO `action_log` VALUES ('178', '2026-11-10 10:22:18', '2', 'passports', '2', 'create', NULL, '\"enc1:ACEi3Pgo0PAHnm6gB3A1D1ubMpZokf1kV5wLTeZEPH+1J3V7jesS5VzKYAhbNqBl8B+ji16WSxcHRsRTlTbQK27tABY2jEaZs\\/hHYJzNkHKlJHJAIqDs4aaWS+8XrccmArwBwhvnxx3E0DM6JOD34nyhwYvhm5T5KEPyB5ofR4wl3jNxli64g+ujTJbOeDh5Dd+Tb\\/FpikRYcnSZ58Ht14yL1jJSC6nKaSS4rCibCgNsFoWy+zZM3J3LnBlC7aXKHcXyhnZPqBHFiO8=\"');
INSERT INTO `action_log` VALUES ('179', '2026-11-10 10:24:34', '2', 'citizens', '5', 'create', NULL, '{\"last_name\": \"ГГГГ\", \"first_name\": \"ДД\", \"reg_status\": \"активна\"}');
INSERT INTO `action_log` VALUES ('180', '2026-11-10 10:24:34', '2', 'applications', '4', 'create', NULL, '{\"service_type\": \"Регистрация гражданина\", \"status\": \"завершено\"}');
INSERT INTO `action_log` VALUES ('181', '2026-11-10 10:44:52', '2', 'citizens', '5', 'delete', '\"enc1:BhCrHwon9aSVu7hZPRLt1eARmanma5EGLTZY3CPqVEjO46u+BQllCYF3AQaSvzxvudavxDu5bBfyiokfDTxO0ZhS9kp23Kys0b0MuolLqYIB\\/ggWBcogAITUnM5oIIPoJ\\/ANT8scc56IjHVnYxjVeQZoAySElnwhNEtFkMjFIwiXUozs674TuOHqJOXzANLTLp+rddVgV\\/kbWrzDQ2KvoR76hy6A0NHEQa6kCvK2OtSLyv2+8IIzubnKGDEAfBlwAVPDxral5JX2iqptcWApQ79uvgKwU2Jrsg2sykXDU\\/Lu7t7ZL1LtPAmTvteLheyQUrUVVQ3AkDLgAqIghhe0iOAxke9fo0ZVY+s+tdzzwU+Vry9B\"', NULL);
INSERT INTO `action_log` VALUES ('182', '2026-11-10 10:44:56', '2', 'citizens', '4', 'delete', '\"enc1:5SPBFNATY4rwnrdSeGUW4XL2NOAZ9HQrD4TDLTflTwOpzE+n8z9OY+aIt4SE+uS7yJ4dXvE0Y4vtgnY4hS3gGCFw2hGTGThc\\/ElrZY3Uczpzwd6NYwXI6ZJXx+jKfSe56f6MRSjZZVL5uEdMkw00UP3E6b2YOxcdY8ZcCib+uocCa6rzd\\/a2JGxGUlZcSPPWNummkI3OXAw5llogY0Vo59rjgijJE24ZpAwazcpmP0dL8C5UthhOi745EY8cAUVoiPr5YnD2Af3M9V4sZ\\/DYgTMGR50F+8mx0aCAEEhmlwkYqjTl\\/aXbsPVHQxsRY+jlE+kc\\/rUJrcnr8NgWetVTLm6bEQvqa3rkkQM6r5nxnf8=\"', NULL);
INSERT INTO `action_log` VALUES ('183', '2026-11-10 10:47:02', '2', 'citizens', '6', 'create', NULL, '\"enc1:BpRssqtRYYbRp5ZjHtt104axsHv+isP8T1RvZeXtUT9wFWJZpTL8L7wnKipNF3WvtUgaAFnX6YM3zlXbzhVyv49muZR5kObNFsv8\\/f5MkwZ9\\/F16o+MmDBA3UNvJNu+8+jONQ249LncbRdWX8\\/G6APiGZowTdFCO8OZVA7VrUNbUSwhYlxuiuyFM\\/NFbNNUaioqJjtaFLOd3mCIvnAUKj6yvjaAVWCudAVWKBjLCVwXx9riGZ7ntpjNNHDg0ynnbzwqHok8d+O2tA70sHc1LTN\\/hnSrGKNBvoN6ifR+VnmADjtKHC8mHejUPrAChJHL68XEub2CI02lGBepvPRApFbw=\"');
INSERT INTO `action_log` VALUES ('184', '2026-11-10 10:47:57', '2', 'passports', '3', 'create', NULL, '\"enc1:xqLoKfmFLCOSF3z0DbU2227KmCWXWQYsrZPuHJupiYUxEr69n5JEZhWzqoXI2YQ9RH18XFjPvAWuw1RlvdC30hiYQ4ue666SDc0mDY3PSixvaYBbRCkpwvqOZoYXmLtoLSxP90xVpbP1eb1f6RqU6cSTHsaXnBw+K22nRbNdyrDXI8lhQjnVydysk+9JOw792X4TCKkhrZh7UmWOjl9e16+K78RCyM3aCtvCZcZ97ODfta0=\"');
INSERT INTO `action_log` VALUES ('185', '2026-11-10 10:51:47', '2', 'citizens', '7', 'create', NULL, '\"enc1:8mQkNVr3pBRoX4GwwLKiNzUxMGub682YL9Rq4ELx6qyjS7ZhAi7EOUu9X6PPDLQyO2NMP18KIYS+maYWu8HoNHTKbaLf+mFh3Ff1qDZdVu+3Vy+krER1VTiAYgOYinNaxgNYnoyCzOE=\"');
INSERT INTO `action_log` VALUES ('186', '2026-11-10 10:51:47', '2', 'applications', '185', 'create', NULL, '\"enc1:JFosT43X7VtjTPrp58gQsHryhgfTG5eJlaXM6XZ34\\/cChrAGiyx3MzDbiY7spw1SO1fUwN1Bg9uFln8A5vx5Pf\\/KbpUP48LQCHYgoGMPLGaAo5qwxoa66rZ7aEaMICZ+ZRrge0yQN5nxNHGaSQ6zG9eL3Onx+2Up\"');
INSERT INTO `action_log` VALUES ('187', '2026-11-10 10:53:14', '2', 'applications', '6', 'create', NULL, '\"enc1:h0O9VsC3SwcGV8etypXBKmKAm6fI5ZJuoL9YS5QO0kg\\/NCnWZcgkpmvs72KHlpowwQKy2xSraZ4puciH9SM83YL3umYL2PIMN2ZFpfKEWTCo4E7oAV\\/cuql+Z0N38AgHrLXkh9r\\/FMjr5g==\"');
INSERT INTO `action_log` VALUES ('188', '2026-11-10 10:53:14', '2', 'passports', '4', 'create', NULL, '\"enc1:SjjKCmhcQhiFvbim2uD0GRNER5S2mA23il0+UMWL6+aPbIK7Ty7ZJMa014ZqLtfr3vu\\/OQaMnmV+4IIiPMuX7HoKdQV3HYorYktqiSJuiQqaOIOzL86\\/jCXD5vgB\"');
INSERT INTO `action_log` VALUES ('189', '2026-11-10 10:53:17', '2', 'passports', '4', 'read', NULL, NULL);
INSERT INTO `action_log` VALUES ('190', '2026-11-10 10:53:39', '2', 'citizens', '7', 'delete', '\"enc1:QbQP8fXcxq4Xjst69P7ketPpa2TpN2gLmys5UoLUyPXXBCiS+34Ezy6Xb2vuaiZMU\\/1p8VxOCEPcEE2zfv50UcBYzuiTeIAf\\/\\/ieYeFwN+72XwHJc5uQg+OA+XjbwZ+ftqWCwEpBF6GjWY1VPyH5GQ5H0rzUsmBNMAG2d+arTpuJeBDMfEDnEH6YNRBpsxnh3O86\\/sJ4p7bzx62wEjcOJFyFvY3zxyD53whaOgTeNtddNh74WxwxVmsnTuVdXCwa+2NllQjCIwUA2wcfCYZqVYv7fY29+eundWdZFifsPXAA1UvCFqv7+KeAIu2gAzYxZZqYz0e4URfiIUAiJV3OOi+gy6KN8s6XWUF3dVwJyCEQdRKU\"', NULL);
INSERT INTO `action_log` VALUES ('191', '2026-11-10 10:53:42', '2', 'citizens', '6', 'delete', '\"enc1:dye7QtYmzd\\/5SHKDdBqKxv7B+DTM12qwxl3ARhu5+AjgiiyBYq\\/1oHQoh0vBT3\\/jz33nOTwbXH4WEatInMV5AQAg27+RtO0qbpdIzrBsqWfygE9tNaEukgJOegqR5t3qBSq7KeD2BUyzya2Kg+4kVjLfQXPNBs6igE8b+vUILmJ6QjWnpkZ+trubXQrGS7TL++iJ0a1aFDTFpdtYQiha6HBE7ETLpULgnABx6nG\\/oUlvXPcjYe\\/s+0xNeJ6zv4uoFyaGNlwl8uwEZTPhV3GmXTwg+90QKaHqusl9\\/ywaqBmBdxEXSBemx9XvHCVo8ZjjjvKNOqcUa+BWF5joY2dYtrJrEjuvlQqULYbL\\/b5i5ug=\"', NULL);
INSERT INTO `action_log` VALUES ('192', '2026-11-10 10:55:03', '2', 'users', '2', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('193', '2026-11-10 10:55:21', '1', 'users', '1', 'login', NULL, '\"enc1:tstBo+TW811Rei\\/zScre5Vz7y3FILHA14GW0ylC9RjVyvUNEC7YanSObOAXC5ID7vbCBPjBftyTSfg==\"');
INSERT INTO `action_log` VALUES ('194', '2026-11-10 10:56:05', '1', 'action_log', '0', 'export', NULL, '\"enc1:bnRZ1yOiCP\\/tCiNp02IE\\/iVcGt+qn\\/W+QV2H2ugSXrTp9Ta79mov1Df8MuvQqAm5tDxnh\\/eIjTeHDjnJuav0s1WTw10DTp758\\/i4ZqfEsw==\"');
INSERT INTO `action_log` VALUES ('195', '2026-11-10 11:04:02', '1', 'citizens', '8', 'create', NULL, '\"enc1:Ok3j8Y8stS1LSFII\\/IidaRSBpK1Zpbog5i+x8o6XqX7jLOWWqvglc8qB+7NMoZHMT\\/EmEJTDGpRyeEYtBxlmbqRP638GEl2HZwkv7MkJO3D53L+PGfcohoD8A4Z3futvbxtU9m2aw89vV3cJONpeQ0Q1NrfDAKGdxgK4Q4MwQbXpfUHuutl8g5tEuh0uUZnLsENB2NzPcpkZUHhXT4gjMVNJ7JXGu5jo8kNSR9sp5ceoCpd9KwKJFs7Kh68OD60QhrL666HeqWVABLKNm3tfFHNHznvZHA8IcIneVOgJoPTdXCJPGIQ8GJ5rFAUeARv8ZxFc5xZOgiz3QHXumg==\"');
INSERT INTO `action_log` VALUES ('196', '2026-11-10 11:04:58', '1', 'citizens', '9', 'create', NULL, '\"enc1:E3RsfpA6709VcA3Jjlh8aXS87bnoCdkn4PJ3dRhKGR+Jw+3UKcBZn4Z97i3p9NkJgcLYGeWV4XbZMt88TvxbkaqIjNI9RCark\\/UIzoCaqhhCYV+mKeIEhdueoLAQLu2AplGFuzfTl2mU\"');
INSERT INTO `action_log` VALUES ('197', '2026-11-10 11:04:58', '1', 'applications', '196', 'create', NULL, '\"enc1:QNdcW0ni2vzU4Mae5KZ3ZA5jGSlWXEz34kAEbcfXDW6gR8z\\/obIGYtTM68WAoP67y6v8GIgKRHmA\\/dFBsu2L9BC2iURObGvQpvkuElrRE7BfSl0DyF5IhSfE3oSDSUYqfZKfR6GH08JAszGgdqeWa5XeGBiIVKaT\"');
INSERT INTO `action_log` VALUES ('198', '2026-11-10 11:08:24', '1', 'citizens', '9', 'delete', '\"enc1:IrkFeEexMC4UWUtR4R6dQs+iqkU\\/W2AfJ0gkh3CbqXqpSH\\/2lrfemq7fMAqD1sdmDAZD0Ga9YBOEyN2AEJ5DMxw\\/Oeficquv+2qXeb8VovIsFI03TYE5tTXr0UVGnuvoaxWjhFaXmKSWLjNYTRGF8IXmrBhA6qkIl6VN21WWILBtBPkvrvBFdn4ETc\\/f6tF4wNnsall00\\/JGE76o5VWM\\/PypoTwLDiJR3Rs6mq40kDlQITGoLIFMJbHhzz\\/YPhSIT\\/TlfJH7xFarhbjRI4tpXgDIlp8FysTgtyGvvF60u+5Kl8ZyG8KcAdiX8++0RUgLkt9T+aBBPNkUyZ0pYRU7sw8RtjxR9PaNmKinVZW2vQ==\"', NULL);
INSERT INTO `action_log` VALUES ('199', '2026-11-10 11:08:26', '1', 'citizens', '8', 'delete', '\"enc1:NAPCBE9Z5cr7bJshZcduai5AeI\\/zSzpKy8v6\\/+MHiT2OfkBuvUaUwwG\\/0TLCoDps1BMyARDiaRTBzK+b2mpzPhaeveJWlpc3KCmAHqqvtxFiv3xmCJP7xT7bs+eo+FZ3V1zKV14t3LvM0zbmV4wNZiVU9YrqhgvpYgaRKr6FAa1VqGYA4I6jGOkfX5r1TBfpqRAewNLONmrJYcVii3xkqcaOUxdF0zONXEsq+Lfgia6ftfkVjPK49cOgdpyWYvcxlhTOAxE03ioyl98MZQ9z4t8X5oyuzrNog7ZDqEzLFVZjB9TtvU+os1vubZX2xhTxIUya5jOt6cqAxRMtiFIKFwGXtPoY3IiCwriqNQ==\"', NULL);
INSERT INTO `action_log` VALUES ('200', '2026-11-10 11:11:27', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('201', '2026-11-10 11:13:24', '1', 'users', '1', 'login', NULL, '\"enc1:tsQKKjWj+EDeuF50Z\\/7Xj9YlsO4a\\/lbi\\/uY\\/U8KwgwuEYZbs47xmIVBSL\\/oDUUssA1D009rpksrB4A==\"');
INSERT INTO `action_log` VALUES ('202', '2026-11-10 12:11:02', '1', 'action_log', '0', 'export', NULL, '\"enc1:lFky98\\/cME4W+khT0S9j3SCsVUfRRacHlhogfbPd6UQ+7Cxo+ip2LawcD+jHkiKoY6jt8avdUgFa\\/kLU6XpJFx4ddkaYyRe4iPfGsNQhgw==\"');
INSERT INTO `action_log` VALUES ('203', '2026-11-11 11:09:08', '1', 'users', '1', 'login', NULL, '\"enc1:\\/9k6ZO4JTScWLEt9S2BuiSF0Z6ZcTbnUvb\\/dWUnWl9LasDs+JZyWznuW2ZRBi3jDrGSLpGlHRecuGQ==\"');
INSERT INTO `action_log` VALUES ('204', '2026-11-11 11:09:12', '1', 'action_log', '0', 'export', NULL, '\"enc1:2MHtEb0+HvS65G572zuBPXYgRFH4jR7PFEa1HBXBUcoaUVAwMBtPBvAK2B27Ymxa\\/Xv4kw8o+X2XDPdieyk\\/1PfdJkieROOnB9rCZgnBZQ==\"');
INSERT INTO `action_log` VALUES ('205', '2026-11-11 11:24:24', '1', 'action_log', '0', 'export', NULL, '\"enc1:t+VhRwvw8HuGO4run77FkrNzuw8FTPiPtjNL+BZp8y3XaBpKS0F6sMRWdSdKUH250VYHv6q8R\\/\\/M0PUjRN4Bw3zamtFfGxq0ODpwb+CvYA==\"');
INSERT INTO `action_log` VALUES ('206', '2026-11-11 11:28:50', '1', 'action_log', '0', 'export', NULL, '\"enc1:EB1ErO08NF5IiIdusYiWjEGHRR2EewatpmbfL2+hyIv9ooE+RymSE++bY1G2EZcHwHAGv\\/L2HCwDH63J\\/ZbRnwSnfy176aAquCv2VE2XhA==\"');
INSERT INTO `action_log` VALUES ('207', '2026-11-11 11:37:46', '1', 'action_log', '0', 'export', NULL, '\"enc1:\\/fGoCso+7oXmWUGF75ppWQA8GpQYgEEdlAe5wfH8RcwjV1tgocc4UIWZt5AKNJ97YyCZzDycjFxwkuGzZHxP7mQOtu3Npx1cpX8kRQ5iwmjUeOxM6T9IJU96\\/0BOCBp4GA\\/0DADPcwC8\"');
INSERT INTO `action_log` VALUES ('208', '2026-11-11 11:38:24', '1', 'action_log', '0', 'export', NULL, '\"enc1:G1xsmAeZGXplLglAfb5lNCDBQ74ognXl9iiMictw15wB8H2t2pdaP0XWJcx+xd7tPMBanUs9mQDtou+9tCSEbPPId2\\/BuP0WSQtWiLBm5pfGckqITvgsvhiD+V\\/ePveLsRwwMBi9xhb+\"');
INSERT INTO `action_log` VALUES ('209', '2026-11-11 11:49:34', '1', 'action_log', '0', 'export', NULL, '\"enc1:PVQdpubT+W6OicZOUTOp0oNnzzYTMZF+dJhiRbGlZwJZFUU5t459bw2MPqjFf3UUkkVBDHaY5qUYz7LhmXcaKAG8udDs\\/6UkN8I+wlui6W\\/6iNs1w6TH7wVSe9Cu\\/688uldN\\/bUpvn2oMdDM\\/jVrpbnxK2cFfJHzv1xjBVLAJoH5zTxyphcqV6Yiv\\/SlbYE=\"');
INSERT INTO `action_log` VALUES ('210', '2026-11-11 11:53:42', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('211', '2026-11-11 11:54:02', '2', 'users', '2', 'login', NULL, '\"enc1:MJbP14gkgFDzhN0pOPzd3urDCIg6AVFKVMTbB5dms\\/GtFDj1b\\/KqP4ubrZtxDzWt0wsyIds0d\\/p9bw==\"');
INSERT INTO `action_log` VALUES ('212', '2026-11-12 13:02:35', '1', 'users', '1', 'login', NULL, '\"enc1:qNuAYn+Sqdt0LMYZEoBGoiN3Ej++uH17oAznlRJUfapoAqdRpWkUYScOdVoI3llDu55cLF08SdO6Fg==\"');
INSERT INTO `action_log` VALUES ('213', '2026-11-12 13:02:39', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('214', '2026-11-12 13:15:22', '1', 'users', '1', 'login', NULL, '\"enc1:jc6xS8zIgNFe7zYPSVwvNlAEF6HV0vY6QOW1vP2FV\\/ZOganMbiRGRjDaRiO7mCOVqztuntvre2rjFA==\"');
INSERT INTO `action_log` VALUES ('215', '2026-11-12 13:30:23', '1', 'users', '1', 'logout', NULL, NULL);
INSERT INTO `action_log` VALUES ('216', '2026-11-12 13:31:04', '2', 'users', '2', 'login', NULL, '\"enc1:xMAhn8ZG0jqzOgD96pAZeNzGxlHooo9UG5l2gwJ1tsUvqpq8Ml0TKPpdir7FiKgKuNT273XFGeVUyw==\"');
INSERT INTO `action_log` VALUES ('217', '2026-11-12 13:32:15', '2', 'action_log', '0', 'export', NULL, '\"enc1:ib0EgrlPVU5Fn8AqV7hUSFomD3rySH96v3RqdL01jhcQJH8AHw6i2z9nEPuDWWOvD8CYLAayD8ksid2qHcyH6Y\\/7ITjZKaavEk+x8x2JJUT8\\/dh4mauoTzCMzI\\/Ttt42eLo2bADO8+0xHQQGooln+rfMUyhWd7rUdSayzcP9bnhI2\\/elseT3ViBrYmXIq8J6UA==\"');

-- ---- таблица `applications` ----
DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `application_id` int(11) NOT NULL AUTO_INCREMENT,
  `citizen_id` int(11) NOT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `service_type` enum('Регистрация гражданина','Выдача паспорта','Замена паспорта','Снятие с регистрационного учёта','Справка о регистрации','Отчёт') NOT NULL,
  `status` enum('подано','в работе','завершено','отклонено') NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`application_id`),
  KEY `fk_applications_citizen` (`citizen_id`),
  KEY `fk_applications_operator` (`operator_id`),
  CONSTRAINT `fk_applications_citizen` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`citizen_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_applications_operator` FOREIGN KEY (`operator_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `applications` VALUES ('1', '1', '2', 'Регистрация гражданина', 'завершено', '2018-03-01 10:00:00', '2026-10-06 09:28:07');
INSERT INTO `applications` VALUES ('2', '1', '2', 'Выдача паспорта', 'подано', '2026-10-06 10:27:31', '2026-10-16 13:53:09');

-- ---- таблица `citizens` ----
DROP TABLE IF EXISTS `citizens`;
CREATE TABLE `citizens` (
  `citizen_id` int(11) NOT NULL AUTO_INCREMENT,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `sex` enum('М','Ж') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `reg_address` varchar(255) NOT NULL,
  `reg_status` enum('активна','прекращена') NOT NULL,
  `reg_start_date` date NOT NULL,
  `reg_end_date` date DEFAULT NULL,
  PRIMARY KEY (`citizen_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `citizens` VALUES ('1', 'Иванов', 'Иван', 'Иванович', '1990-05-12', 'М', '+79170000001', 'ivanov@mail.ru', 'г. Казань, ул. Баумана, д. 12, кв. 34', 'активна', '2018-03-01', NULL);
INSERT INTO `citizens` VALUES ('2', 'Петрова', 'Анна', 'Сергеевна', '1985-11-03', 'Ж', '+79170000002', 'petrova@mail.ru', 'г. Казань, ул. Гагарина, д. 8, кв. 12', 'активна', '2015-07-20', NULL);
INSERT INTO `citizens` VALUES ('3', 'Сидоров', 'Пётр', 'Алексеевич', '2000-02-29', 'М', '+79170000003', NULL, 'г. Казань, ул. Ленина, д. 45, кв. 7', 'активна', '2020-01-15', NULL);

-- ---- таблица `notes` ----
DROP TABLE IF EXISTS `notes`;
CREATE TABLE `notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `id_user` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_notes_user` (`id_user`),
  CONSTRAINT `fk_notes_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `notes` VALUES ('1', 'Конференция ИТ', 'Доклад об интеграции программных модулей и тестировании API.', '2', '2027-03-15 10:30:00');
INSERT INTO `notes` VALUES ('2', 'Совещание по проекту', 'Обсуждение ER-диаграммы и схемы базы данных производственного учёта.', '1', '2027-02-01 09:00:00');
INSERT INTO `notes` VALUES ('3', 'Отчёт за квартал', 'Сводка по заказам покупателей и полной стоимости производства продукции.', '2', '2026-12-28 17:45:00');
INSERT INTO `notes` VALUES ('4', 'План внедрения', 'Этапы внедрения информационной системы и обучение персонала.', '1', '2027-01-20 12:00:00');
INSERT INTO `notes` VALUES ('5', 'Заметка о тестировании API', 'Перечень тестов коллекции: статус 200, статус 500, валидность JSON.', '3', '2027-04-10 08:15:00');

-- ---- таблица `passports` ----
DROP TABLE IF EXISTS `passports`;
CREATE TABLE `passports` (
  `passport_id` int(11) NOT NULL AUTO_INCREMENT,
  `citizen_id` int(11) NOT NULL,
  `series` char(4) NOT NULL,
  `number` char(6) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `issuer` varchar(255) NOT NULL,
  `status` enum('черновик','действует','заменён','аннулирован') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`passport_id`),
  UNIQUE KEY `uq_series_number` (`series`,`number`),
  KEY `fk_passports_citizen` (`citizen_id`),
  CONSTRAINT `fk_passports_citizen` FOREIGN KEY (`citizen_id`) REFERENCES `citizens` (`citizen_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `passports` VALUES ('1', '1', '1234', '456789', '2018-04-10', NULL, 'ГУ МВД по Республике Татарстан', 'действует', 'Достижение 14 лет');

-- ---- таблица `users` ----
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('администратор','оператор','пользователь') NOT NULL,
  `mfa_secret` varchar(255) NOT NULL,
  `last_password_change` date NOT NULL,
  `failed_attempts` tinyint(4) NOT NULL DEFAULT 0,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `users` VALUES ('1', 'admin', '$argon2id$v=19$m=65536,t=4,p=1$DxzZ9FV/WPaX5RZjswA/Yw$3tVC5Sp7xUZWzqZ2UBKe0Onu9MsXV74vqaDbM4pvYaM', 'администратор', 'ifXPFzVtRgd3olgroWVVfQ==', '2026-09-29', '0', '0', '0', NULL);
INSERT INTO `users` VALUES ('2', 'operator', '$argon2id$v=19$m=65536,t=4,p=1$DjXSj0PeExRGlbxW1qC/sQ$GiAkogYUV+HPO4mQsLq0m1qAVPBhOz+qyBXFKBkK/0A', 'оператор', 'wu3EitoUuaPVS7bnZZxXog==', '2026-09-29', '0', '0', '0', NULL);
INSERT INTO `users` VALUES ('3', 'user', '$argon2id$v=19$m=65536,t=4,p=1$RZ3Br9SvodxAowibAysOyA$6zsJILc/s6GJLqY8LcToM0lDpIBBU1nViWLzpl0w+qE', 'пользователь', 'MW/LL93BsEetO4ElP/SO3Q==', '2026-09-29', '0', '0', '0', NULL);
INSERT INTO `users` VALUES ('11', 'deleted_11_20261107', '***', 'пользователь', 'uPZzwT3mNRC4oo7zYos6ZQ==', '2026-11-07', '0', '1', '1', NULL);
INSERT INTO `users` VALUES ('12', 'deleted_12_20261110', '$argon2id$v=19$m=65536,t=4,p=1$7IsLCGsJOcCZ9OGRq6GWpQ$wJv7HxxGhWplmmGEImJRXKEIWCFzg/gCr9F2Xusitq8', 'пользователь', 'BoJcWjNfgdyeD4riIkFR2A==', '2026-11-07', '0', '1', '1', NULL);

SET FOREIGN_KEY_CHECKS=1;

-- ---- функции ----
DROP FUNCTION IF EXISTS `fn_active_passport_id`;
DELIMITER $$
CREATE FUNCTION `fn_active_passport_id`(p_citizen_id INT) RETURNS int(11)
    READS SQL DATA
BEGIN
    DECLARE v_id INT;
    SELECT passport_id INTO v_id FROM passports
     WHERE citizen_id = p_citizen_id AND status = 'действует'
     ORDER BY passport_id DESC LIMIT 1;
    RETURN v_id;
END$$
DELIMITER ;
DROP FUNCTION IF EXISTS `fn_age`;
DELIMITER $$
CREATE FUNCTION `fn_age`(p_birth_date DATE) RETURNS int(11)
    DETERMINISTIC
BEGIN
    RETURN TIMESTAMPDIFF(YEAR, p_birth_date, CURDATE());
END$$
DELIMITER ;
DROP FUNCTION IF EXISTS `fn_citizen_full_name`;
DELIMITER $$
CREATE FUNCTION `fn_citizen_full_name`(p_citizen_id INT) RETURNS varchar(320) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    READS SQL DATA
BEGIN
    DECLARE v_fio VARCHAR(320);
    SELECT TRIM(CONCAT(last_name, ' ', first_name, ' ', IFNULL(middle_name, ''))) INTO v_fio
      FROM citizens WHERE citizen_id = p_citizen_id;
    RETURN IFNULL(v_fio, '');
END$$
DELIMITER ;
DROP FUNCTION IF EXISTS `fn_open_applications`;
DELIMITER $$
CREATE FUNCTION `fn_open_applications`(p_citizen_id INT) RETURNS int(11)
    READS SQL DATA
BEGIN
    DECLARE v_cnt INT;
    SELECT COUNT(*) INTO v_cnt FROM applications
     WHERE citizen_id = p_citizen_id AND status IN ('подано', 'в работе');
    RETURN v_cnt;
END$$
DELIMITER ;

-- ---- процедуры ----
DROP PROCEDURE IF EXISTS `sp_deregister_citizen`;
DELIMITER $$
CREATE PROCEDURE `sp_deregister_citizen`(
    IN p_citizen_id INT, IN p_dereg_date DATE, IN p_reason VARCHAR(255),
    IN p_new_address VARCHAR(255), IN p_operator_id INT)
BEGIN
    DECLARE v_reg_start DATE;
    DECLARE v_reg_status VARCHAR(20);
    DECLARE v_app_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    SELECT reg_start_date, reg_status INTO v_reg_start, v_reg_status
      FROM citizens WHERE citizen_id = p_citizen_id;
    IF v_reg_start IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Гражданин не найден';
    END IF;
    IF p_dereg_date IS NULL OR TRIM(IFNULL(p_reason,'')) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Заполните дату снятия и причину';
    END IF;
    IF fn_open_applications(p_citizen_id) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'У гражданина есть открытые заявления';
    END IF;
    IF p_dereg_date < v_reg_start THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Дата снятия не может быть ранее даты начала регистрации';
    END IF;

    START TRANSACTION;
        UPDATE citizens SET reg_status = 'прекращена', reg_end_date = p_dereg_date
         WHERE citizen_id = p_citizen_id;

        INSERT INTO applications (citizen_id, operator_id, service_type, status, created_at)
        VALUES (p_citizen_id, p_operator_id, 'Снятие с регистрационного учёта', 'завершено', NOW());
        SET v_app_id = LAST_INSERT_ID();

        INSERT INTO action_log (action_time, user_id, entity_name, entity_id, action_type, before_json, after_json)
        VALUES (NOW(), p_operator_id, 'citizens', p_citizen_id, 'update',
                JSON_OBJECT('reg_status', v_reg_status, 'reg_end_date', NULL),
                JSON_OBJECT('reg_status', 'прекращена', 'reg_end_date', p_dereg_date,
                            'reason', p_reason, 'new_address', IFNULL(p_new_address, '')));
        INSERT INTO action_log (action_time, user_id, entity_name, entity_id, action_type, before_json, after_json)
        VALUES (NOW(), p_operator_id, 'applications', v_app_id, 'create', NULL,
                JSON_OBJECT('service_type', 'Снятие с регистрационного учёта', 'status', 'завершено'));
    COMMIT;
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS `sp_issue_passport`;
DELIMITER $$
CREATE PROCEDURE `sp_issue_passport`(
    IN p_citizen_id INT, IN p_series CHAR(4), IN p_number CHAR(6),
    IN p_issue_date DATE, IN p_issuer VARCHAR(255), IN p_reason VARCHAR(255),
    IN p_operator_id INT, OUT p_passport_id INT)
BEGIN
    DECLARE v_app_id INT;
    DECLARE v_app_date DATE;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    IF (SELECT COUNT(*) FROM citizens WHERE citizen_id = p_citizen_id) = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Гражданин не найден';
    END IF;
    IF p_series NOT REGEXP '^[0-9]{4}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Серия должна содержать ровно 4 цифры';
    END IF;
    IF p_number NOT REGEXP '^[0-9]{6}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Номер должен содержать ровно 6 цифр';
    END IF;
    IF (SELECT COUNT(*) FROM passports WHERE series = p_series AND number = p_number) > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Комбинация серия + номер уже используется';
    END IF;
    IF fn_active_passport_id(p_citizen_id) IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'У гражданина уже есть паспорт со статусом «действует»';
    END IF;
    SELECT MAX(DATE(created_at)) INTO v_app_date FROM applications
     WHERE citizen_id = p_citizen_id AND service_type = 'Выдача паспорта';
    IF v_app_date IS NOT NULL AND p_issue_date < v_app_date THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Дата выдачи не может быть ранее даты подачи заявления';
    END IF;

    START TRANSACTION;
        INSERT INTO applications (citizen_id, operator_id, service_type, status, created_at)
        VALUES (p_citizen_id, p_operator_id, 'Выдача паспорта', 'завершено', NOW());
        SET v_app_id = LAST_INSERT_ID();

        INSERT INTO passports (citizen_id, series, number, issue_date, issuer, status, reason)
        VALUES (p_citizen_id, p_series, p_number, p_issue_date, TRIM(p_issuer), 'действует',
                NULLIF(TRIM(IFNULL(p_reason,'')),''));
        SET p_passport_id = LAST_INSERT_ID();

        INSERT INTO action_log (action_time, user_id, entity_name, entity_id, action_type, before_json, after_json)
        VALUES (NOW(), p_operator_id, 'applications', v_app_id, 'create', NULL,
                JSON_OBJECT('service_type', 'Выдача паспорта', 'status', 'завершено'));
        INSERT INTO action_log (action_time, user_id, entity_name, entity_id, action_type, before_json, after_json)
        VALUES (NOW(), p_operator_id, 'passports', p_passport_id, 'create', NULL,
                JSON_OBJECT('series', p_series, 'number', p_number, 'status', 'действует'));
    COMMIT;
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS `sp_register_citizen`;
DELIMITER $$
CREATE PROCEDURE `sp_register_citizen`(
    IN p_last_name VARCHAR(100), IN p_first_name VARCHAR(100), IN p_middle_name VARCHAR(100),
    IN p_birth_date DATE, IN p_sex ENUM('М','Ж'), IN p_reg_address VARCHAR(255),
    IN p_reg_start_date DATE, IN p_operator_id INT, OUT p_citizen_id INT)
BEGIN
    DECLARE v_app_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    IF TRIM(IFNULL(p_last_name,'')) = '' OR TRIM(IFNULL(p_first_name,'')) = ''
       OR TRIM(IFNULL(p_reg_address,'')) = '' OR p_birth_date IS NULL OR p_reg_start_date IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Не заполнены обязательные сведения о гражданине';
    END IF;

    START TRANSACTION;
        INSERT INTO citizens (last_name, first_name, middle_name, birth_date, sex, reg_address, reg_status, reg_start_date)
        VALUES (TRIM(p_last_name), TRIM(p_first_name), NULLIF(TRIM(IFNULL(p_middle_name,'')),''),
                p_birth_date, p_sex, TRIM(p_reg_address), 'активна', p_reg_start_date);
        SET p_citizen_id = LAST_INSERT_ID();

        INSERT INTO applications (citizen_id, operator_id, service_type, status, created_at)
        VALUES (p_citizen_id, p_operator_id, 'Регистрация гражданина', 'завершено', NOW());
        SET v_app_id = LAST_INSERT_ID();

        INSERT INTO action_log (action_time, user_id, entity_name, entity_id, action_type, before_json, after_json)
        VALUES (NOW(), p_operator_id, 'citizens', p_citizen_id, 'create', NULL,
                JSON_OBJECT('last_name', p_last_name, 'first_name', p_first_name, 'reg_status', 'активна'));
        INSERT INTO action_log (action_time, user_id, entity_name, entity_id, action_type, before_json, after_json)
        VALUES (NOW(), p_operator_id, 'applications', v_app_id, 'create', NULL,
                JSON_OBJECT('service_type', 'Регистрация гражданина', 'status', 'завершено'));
    COMMIT;
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS `sp_unlock_user`;
DELIMITER $$
CREATE PROCEDURE `sp_unlock_user`(IN p_user_id INT, IN p_actor_id INT)
BEGIN
    DECLARE v_login VARCHAR(100);
    DECLARE v_blocked TINYINT;
    SELECT login, is_blocked INTO v_login, v_blocked FROM users WHERE user_id = p_user_id;
    IF v_login IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Пользователь не найден';
    END IF;
    IF v_blocked = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Пользователь не заблокирован';
    END IF;
    UPDATE users SET is_blocked = 0, failed_attempts = 0, locked_until = NULL WHERE user_id = p_user_id;
    INSERT INTO action_log (action_time, user_id, entity_name, entity_id, action_type, before_json, after_json)
    VALUES (NOW(), p_actor_id, 'users', p_user_id, 'update',
            JSON_OBJECT('is_blocked', 1, 'login', v_login),
            JSON_OBJECT('is_blocked', 0, 'login', v_login));
END$$
DELIMITER ;

-- ---- триггеры ----
DROP TRIGGER IF EXISTS `trg_action_log_before_update`;
DELIMITER $$
CREATE TRIGGER trg_action_log_before_update
BEFORE UPDATE ON action_log FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Прямое обновление записей action_log запрещено: используйте восстановление из before_json';
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_applications_before_update`;
DELIMITER $$
CREATE TRIGGER trg_applications_before_update
BEFORE UPDATE ON applications FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_citizens_before_update`;
DELIMITER $$
CREATE TRIGGER trg_citizens_before_update
BEFORE UPDATE ON citizens FOR EACH ROW
BEGIN
    IF NEW.reg_status = 'прекращена' AND NEW.reg_end_date IS NULL THEN
        SET NEW.reg_end_date = CURDATE();
    END IF;
    IF NEW.reg_end_date IS NOT NULL AND NEW.reg_end_date < NEW.reg_start_date THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Дата окончания регистрации не может быть ранее даты её начала';
    END IF;
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_passports_before_insert`;
DELIMITER $$
CREATE TRIGGER trg_passports_before_insert
BEFORE INSERT ON passports FOR EACH ROW
BEGIN
    IF NEW.series NOT REGEXP '^[0-9]{4}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Серия паспорта должна содержать ровно 4 цифры';
    END IF;
    IF NEW.number NOT REGEXP '^[0-9]{6}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Номер паспорта должен содержать ровно 6 цифр';
    END IF;
    IF NEW.status = 'действует' AND fn_active_passport_id(NEW.citizen_id) IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'У гражданина уже есть паспорт со статусом «действует»';
    END IF;
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_passports_before_update`;
DELIMITER $$
CREATE TRIGGER trg_passports_before_update
BEFORE UPDATE ON passports FOR EACH ROW
BEGIN
    IF NEW.series NOT REGEXP '^[0-9]{4}$' OR NEW.number NOT REGEXP '^[0-9]{6}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Серия (4 цифры) и номер (6 цифр) паспорта заданы неверно';
    END IF;
    IF OLD.status IN ('заменён', 'аннулирован') AND NEW.status = 'действует' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Заменённый или аннулированный паспорт нельзя вернуть в статус «действует»';
    END IF;
    IF NEW.status = 'действует' AND OLD.status <> 'действует'
       AND fn_active_passport_id(NEW.citizen_id) IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'У гражданина уже есть действующий паспорт';
    END IF;
END$$
DELIMITER ;