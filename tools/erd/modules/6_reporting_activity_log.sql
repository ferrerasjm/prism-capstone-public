-- PRISM ERD - Module 6: Reporting and Activity Log Module
-- 6 module tables + 2 external reference stubs
-- All FKs retained: for support tables the acting user is the domain relationship.

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` enum('create','update','void','override','login','logout','sync','generate_report','flag','import') COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=964 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `notifications_notifiable_read_index` (`notifiable_type`,`notifiable_id`,`read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `announcements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint unsigned DEFAULT NULL,
  `target_user_id` bigint unsigned DEFAULT NULL,
  `target_role` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_academic_unit_id` bigint unsigned DEFAULT NULL,
  `kind` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system_update',
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_created_by_foreign` (`created_by`),
  KEY `announcements_target_user_id_created_at_index` (`target_user_id`,`created_at`),
  KEY `announcements_target_role_index` (`target_role`),
  KEY `announcements_target_academic_unit_id_foreign` (`target_academic_unit_id`),
  KEY `announcements_role_unit_created_index` (`target_role`,`target_academic_unit_id`,`created_at`),
  CONSTRAINT `announcements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `announcements_target_academic_unit_id_foreign` FOREIGN KEY (`target_academic_unit_id`) REFERENCES `academic_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `announcements_target_user_id_foreign` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `csv_import_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `import_batch_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `imported_by` bigint unsigned DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_rows` int NOT NULL,
  `created_count` int NOT NULL,
  `skipped_count` int NOT NULL,
  `error_count` int NOT NULL,
  `results_json` json NOT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `csv_import_logs_import_batch_id_unique` (`import_batch_id`),
  KEY `csv_import_logs_imported_by_foreign` (`imported_by`),
  CONSTRAINT `csv_import_logs_imported_by_foreign` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scheduled_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_type` enum('monthly_violation_summary','student_case_report','case_summary','us_compliance','upcc_case_report','registration_hold_report','escalation_report','risk_analytics_report') COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `generated_by` bigint unsigned DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generated_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `scheduled_reports_generated_by_foreign` (`generated_by`),
  CONSTRAINT `scheduled_reports_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `number_sequences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sequence_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` smallint unsigned NOT NULL,
  `current_value` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `number_sequences_sequence_key_unique` (`sequence_key`),
  KEY `number_sequences_prefix_year_index` (`prefix`,`year`)
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `academic_units` (
  `id` bigint unsigned NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
