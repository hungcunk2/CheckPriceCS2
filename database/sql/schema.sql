-- CheckPriceCS2 — bảng dữ liệu chính
-- Import: phpMyAdmin → chọn DB → Import file này
-- Hoặc: mysql -u root -p checkpricecs2 < database/sql/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `price_history_points`;
DROP TABLE IF EXISTS `tracked_inventories`;

CREATE TABLE `tracked_inventories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) DEFAULT NULL,
  `url` text NOT NULL,
  `steam_id` varchar(20) DEFAULT NULL,
  `steam_persona_name` varchar(255) DEFAULT NULL,
  `steam_avatar_url` varchar(512) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint unsigned NOT NULL DEFAULT 0,
  `last_checked_at` timestamp NULL DEFAULT NULL,
  `last_total_cny` decimal(14,2) DEFAULT NULL,
  `last_total_vnd` bigint unsigned DEFAULT NULL,
  `item_count` int unsigned NOT NULL DEFAULT 0,
  `priced_count` int unsigned NOT NULL DEFAULT 0,
  `failed_count` int unsigned NOT NULL DEFAULT 0,
  `last_snapshot` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tracked_inventories_is_public_index` (`is_public`),
  KEY `tracked_inventories_updated_at_index` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `price_history_points` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_hash` char(32) NOT NULL,
  `market_hash_name` varchar(512) NOT NULL,
  `recorded_at` timestamp NOT NULL,
  `price_cny` decimal(12,2) NOT NULL,
  `sell_num` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `price_history_points_unique` (`item_hash`,`recorded_at`,`price_cny`,`sell_num`),
  KEY `price_history_points_item_hash_recorded_at_index` (`item_hash`,`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
