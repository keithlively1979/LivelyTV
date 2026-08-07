-- LivelyTV — complete database schema
-- Updated to include users table, corrected data types, and AUTO_INCREMENT keys
--
-- Server version: 8.0.x
-- To import: mysql -u admin -p tv < tv.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Database

CREATE DATABASE IF NOT EXISTS `tv`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
USE `tv`;

-- channels

DROP TABLE IF EXISTS `channels`;
CREATE TABLE `channels` (
  `channel_id`         int          NOT NULL AUTO_INCREMENT,
  `channel_name`       varchar(100) NOT NULL DEFAULT '',
  `channel_stream_url` varchar(255) NOT NULL DEFAULT '',
  `channel_logo`       varchar(255) NOT NULL DEFAULT '',
  `channel_visible`    tinyint(1)   NOT NULL DEFAULT 1,
  `root_dir`           text         NOT NULL,
  PRIMARY KEY (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- commercials

DROP TABLE IF EXISTS `commercials`;
CREATE TABLE `commercials` (
  `commercial_id`       int        NOT NULL AUTO_INCREMENT,
  `commercial_duration` int        NOT NULL DEFAULT '30',
  `commercial_file`     text       NOT NULL,
  `commercial_title`    text       NOT NULL,
  `commercial_mature`   tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`commercial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- shows

DROP TABLE IF EXISTS `shows`;
CREATE TABLE `shows` (
  `show_id`             int  NOT NULL AUTO_INCREMENT,
  `show_title`          text NOT NULL,
  `show_basedir`        text NOT NULL,
  `show_desc`           text NOT NULL,
  `show_total_episodes` int  NOT NULL DEFAULT '0',
  `show_lastplayed`     int  NOT NULL DEFAULT '0',
  `show_bumperout`      text NOT NULL,
  `show_bumperin`       text NOT NULL,
  PRIMARY KEY (`show_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- episodes
-- episode_id and show_id promoted from smallint to int to support large libraries

DROP TABLE IF EXISTS `episodes`;
CREATE TABLE `episodes` (
  `episode_id`       int      NOT NULL AUTO_INCREMENT,
  `show_id`          int      NOT NULL,
  `episode_index`    int      NOT NULL,
  `episode_title`    tinytext NOT NULL,
  `episode_summary`  text     NOT NULL,
  `episode_duration` int      NOT NULL DEFAULT '0',
  `episode_file`     text     NOT NULL,
  PRIMARY KEY (`episode_id`),
  KEY `show_id` (`show_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- movies

DROP TABLE IF EXISTS `movies`;
CREATE TABLE `movies` (
  `movie_id`          int        NOT NULL AUTO_INCREMENT,
  `movie_title`       tinytext   NOT NULL,
  `movie_summary`     text       NOT NULL,
  `movie_genre`       text       NOT NULL,
  `movie_duration`    int        NOT NULL DEFAULT '0',
  `movie_rating`      tinytext   NOT NULL,
  `movie_year`        smallint   NOT NULL DEFAULT '0',
  `movie_releasedate` date       NOT NULL DEFAULT '1970-01-01',
  `movie_file`        tinytext   NOT NULL,
  `movie_trailer`     tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`movie_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- schedule

DROP TABLE IF EXISTS `schedule`;
CREATE TABLE `schedule` (
  `schedule_id`   int NOT NULL AUTO_INCREMENT,
  `channel_id`    int NOT NULL,
  `schedule_dow`  int NOT NULL COMMENT '0=Sunday 6=Saturday',
  `schedule_hour` int NOT NULL,
  `schedule_min`  int NOT NULL COMMENT '0 or 30',
  `show_id`       int NOT NULL,
  PRIMARY KEY (`schedule_id`),
  KEY `channel_id` (`channel_id`),
  KEY `show_id`    (`show_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- playlog

DROP TABLE IF EXISTS `playlog`;
CREATE TABLE `playlog` (
  `playlogid`    int      NOT NULL AUTO_INCREMENT,
  `playlogdt`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `playlogchan`  text     NOT NULL,
  `playlogchanid`int      NOT NULL DEFAULT 0,
  `playlogtitle` text     NOT NULL,
  PRIMARY KEY (`playlogid`),
  KEY `playlogdt`    (`playlogdt`),
  KEY `playlogchanid`(`playlogchanid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- users (new - required for admin panel)

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id`        int                  NOT NULL AUTO_INCREMENT,
  `user_name`      varchar(100)         NOT NULL,
  `user_password`  varchar(255)         NOT NULL COMMENT 'bcrypt hash',
  `user_theme`     enum('light','dark') NOT NULL DEFAULT 'light',
  `user_theme_key` varchar(50)          NOT NULL DEFAULT 'blue',
  `user_is_admin`  tinyint(1)           NOT NULL DEFAULT 0,
  `user_created`   datetime             NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_name` (`user_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Initial admin user - password is: admin
-- Change immediately after first login, or generate your own hash:
--   php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT);"
-- then: UPDATE users SET user_password='<hash>' WHERE user_name='admin';
INSERT INTO `users` (`user_name`, `user_password`, `user_theme`, `user_theme_key`, `user_is_admin`)
VALUES ('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'light', 'blue', 1);

-- settings (new - global application settings)

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key`   varchar(100) NOT NULL,
  `setting_value` text         NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('app_name', 'LivelyTV'),
  ('app_logo', ''),
  ('player_theme', 'dark');

-- content_paths (new - scraper source directories)

DROP TABLE IF EXISTS `content_paths`;
CREATE TABLE `content_paths` (
  `path_id`      int                 NOT NULL AUTO_INCREMENT,
  `path_label`   varchar(255)        NOT NULL DEFAULT '',
  `path_dir`     text                NOT NULL,
  `path_type`    enum('tv','movies') NOT NULL DEFAULT 'tv',
  `path_enabled` tinyint(1)          NOT NULL DEFAULT 1,
  PRIMARY KEY (`path_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

COMMIT;
