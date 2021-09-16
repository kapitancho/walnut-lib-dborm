CREATE TABLE `orgs` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `name` varchar(50) NOT NULL,
  `code` varchar(8) NOT NULL
);
INSERT INTO `orgs` VALUES (1, 'Org 1', 'org1');

CREATE TABLE `org_roles` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `org_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT '1',
  `sequence` int(11) DEFAULT '0',
  FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`)
);
INSERT INTO `org_roles` VALUES (1, 1, 'ADM', 'Admin', 1, 1);
INSERT INTO `org_roles` VALUES (2, 1, 'MGR', 'Manager', 1, 2);

CREATE TABLE `org_users` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `org_id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`)
);
INSERT INTO `org_users` VALUES (1, 1, 'User 1');
INSERT INTO `org_users` VALUES (2, 1, 'User 2');

CREATE TABLE `org_user_roles` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `org_users` (`id`),
  FOREIGN KEY (`role_id`) REFERENCES `org_roles` (`id`)
);
INSERT INTO `org_user_roles` VALUES (1, 1, 1);
INSERT INTO `org_user_roles` VALUES (2, 1, 2);
INSERT INTO `org_user_roles` VALUES (3, 2, 2);

CREATE TABLE `org_user_credentials` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `user_id` int(11) NOT NULL,
  `username` varchar(64) DEFAULT NULL,
  `password` varchar(64) DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `org_users` (`id`)
);
INSERT INTO `org_user_credentials` VALUES (1, 1, 'user', 'pass hash');

CREATE TABLE `org_tag_user_groups` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `org_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sequence` int(11) NOT NULL,
  FOREIGN KEY (`org_id`) REFERENCES `orgs` (`id`)
);
INSERT INTO `org_tag_user_groups` VALUES (1, 1, 'Grp1', 1);
INSERT INTO `org_tag_user_groups` VALUES (2, 1, 'Grp2', 2);

CREATE TABLE `org_tag_user_group_values` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `group_id` int(11) NOT NULL,
  `value` varchar(100) NOT NULL,
  `sequence` int(11) NOT NULL,
  FOREIGN KEY (`group_id`) REFERENCES `org_tag_user_groups` (`id`)
);
INSERT INTO `org_tag_user_group_values` VALUES (1, 1, 'Val11', 1);
INSERT INTO `org_tag_user_group_values` VALUES (2, 1, 'Val12', 2);
INSERT INTO `org_tag_user_group_values` VALUES (3, 2, 'Val21', 1);

CREATE TABLE `org_user_tags` (
  `id` int(11) NOT NULL PRIMARY KEY,
  `user_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `org_users` (`id`),
  FOREIGN KEY (`tag_id`) REFERENCES `org_tag_user_group_values` (`id`)
);
INSERT INTO `org_user_tags` VALUES (1, 1, 1);
INSERT INTO `org_user_tags` VALUES (2, 1, 3);
