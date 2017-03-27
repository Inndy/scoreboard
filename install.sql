SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
CREATE TABLE `rank_view` (
`name` varchar(32)
,`last_time` datetime
,`points` decimal(32,0)
,`count` bigint(21)
);

CREATE TABLE `records` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `name` varchar(32) COLLATE utf8mb4_bin NOT NULL,
  `time` datetime NOT NULL,
  `ip` varchar(255) COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE `records_view` (
`id` int(11)
,`name` varchar(32)
,`time` datetime
,`challenge` varchar(255)
);

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `ordering` int(11) NOT NULL,
  `type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `flag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `points` int(11) NOT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `tasks_view` (
`id` int(11)
,`ordering` int(11)
,`type` varchar(16)
,`name` varchar(255)
,`link` varchar(255)
,`flag` varchar(255)
,`points` int(11)
,`text` text
,`solved_count` bigint(21)
);
DROP TABLE IF EXISTS `rank_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `rank_view`  AS  select `records`.`name` AS `name`,max(`records`.`time`) AS `last_time`,sum(`tasks`.`points`) AS `points`,count(`records`.`name`) AS `count` from (`records` left join `tasks` on((`tasks`.`id` = `records`.`task_id`))) group by `records`.`name` order by `points` desc,`last_time` ;
DROP TABLE IF EXISTS `records_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `records_view`  AS  select `records`.`id` AS `id`,`records`.`name` AS `name`,`records`.`time` AS `time`,`tasks`.`name` AS `challenge` from (`records` left join `tasks` on((`records`.`task_id` = `tasks`.`id`))) order by `records`.`time` ;
DROP TABLE IF EXISTS `tasks_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `tasks_view`  AS  select `tasks`.`id` AS `id`,`tasks`.`ordering` AS `ordering`,`tasks`.`type` AS `type`,`tasks`.`name` AS `name`,`tasks`.`link` AS `link`,`tasks`.`flag` AS `flag`,`tasks`.`points` AS `points`,`tasks`.`text` AS `text`,count(`records`.`id`) AS `solved_count` from (`tasks` left join `records` on((`records`.`task_id` = `tasks`.`id`))) group by `tasks`.`id` order by `tasks`.`ordering`,`tasks`.`id` ;


ALTER TABLE `records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id` (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `task_id` (`task_id`);

ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ordering` (`ordering`);


ALTER TABLE `records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `records`
  ADD CONSTRAINT `records_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`);
