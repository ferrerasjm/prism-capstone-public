-- PRISM ERD - Module 3: Offense Classification and Escalation Support Module
-- 3 module tables + 1 external reference stubs
-- Audit-only FKs to `users` (created_by/updated_by/...) omitted for readability.

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `offense_catalog` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `offense_type` enum('minor','major') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `handbook_section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `handbook_page` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keywords` json NOT NULL,
  `special_consequence` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `offense_catalog_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sanction_matrix` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `offense_catalog_id` bigint unsigned NOT NULL,
  `offense_count` tinyint unsigned NOT NULL,
  `sanction_category` tinyint unsigned NOT NULL,
  `us_hours_recommended` decimal(6,2) DEFAULT NULL,
  `us_hours_min` decimal(6,2) DEFAULT NULL,
  `us_hours_max` decimal(6,2) DEFAULT NULL,
  `us_hours_guidance` text COLLATE utf8mb4_unicode_ci,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sanction_matrix_offense_catalog_id_foreign` (`offense_catalog_id`),
  CONSTRAINT `sanction_matrix_offense_catalog_id_foreign` FOREIGN KEY (`offense_catalog_id`) REFERENCES `offense_catalog` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `offense_recommendation_audits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint unsigned NOT NULL,
  `context_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recommendations` json DEFAULT NULL,
  `no_match` tinyint(1) NOT NULL DEFAULT '0',
  `guardrail_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` bigint unsigned DEFAULT NULL,
  `selected_offense_id` bigint unsigned DEFAULT NULL,
  `decision_was_recommended` tinyint(1) DEFAULT NULL,
  `decided_by` bigint unsigned DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `offense_recommendation_audits_case_id_context_hash_unique` (`case_id`,`context_hash`),
  KEY `offense_recommendation_audits_requested_by_foreign` (`requested_by`),
  KEY `offense_recommendation_audits_selected_offense_id_foreign` (`selected_offense_id`),
  KEY `offense_recommendation_audits_decided_by_foreign` (`decided_by`),
  KEY `offense_recommendation_audits_source_status_index` (`source`,`status`),
  CONSTRAINT `offense_recommendation_audits_case_id_foreign` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `offense_recommendation_audits_selected_offense_id_foreign` FOREIGN KEY (`selected_offense_id`) REFERENCES `offense_catalog` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `cases` (
  `id` bigint unsigned NOT NULL,
  `case_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
