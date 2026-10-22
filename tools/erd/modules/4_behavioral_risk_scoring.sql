-- PRISM ERD - Module 4: Behavioral Risk Scoring Module
-- 5 module tables + 1 external reference stubs
-- Audit-only FKs to `users` (created_by/updated_by/...) omitted for readability.

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `risk_scores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned DEFAULT NULL,
  `composite_score` decimal(5,2) NOT NULL,
  `risk_band` enum('low','moderate','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL,
  `trend` enum('increasing','stable','decreasing') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contributing_factors` json NOT NULL,
  `computed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `risk_scores_student_id_unique` (`student_id`),
  CONSTRAINT `risk_scores_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `risk_score_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'event',
  `student_id` bigint unsigned DEFAULT NULL,
  `composite_score` decimal(5,2) NOT NULL,
  `previous_composite_score` decimal(5,2) DEFAULT NULL,
  `risk_band` enum('low','moderate','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_risk_band` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contributing_factors` json DEFAULT NULL,
  `factor_changes` json DEFAULT NULL,
  `methodology_version` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `triggered_by` bigint unsigned DEFAULT NULL,
  `term` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unspecified',
  `snapshot_date` date NOT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `risk_score_snapshots_triggered_by_foreign` (`triggered_by`),
  KEY `risk_snapshots_student_created_index` (`student_id`,`created_at`),
  CONSTRAINT `risk_score_snapshots_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `risk_interventions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned DEFAULT NULL,
  `risk_score_snapshot_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `action_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_plan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `risk_score` decimal(5,2) NOT NULL,
  `risk_band` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `methodology_version` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `follow_up_on` date NOT NULL,
  `outcome` text COLLATE utf8mb4_unicode_ci,
  `started_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `closed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risk_interventions_risk_score_snapshot_id_foreign` (`risk_score_snapshot_id`),
  KEY `risk_interventions_created_by_foreign` (`created_by`),
  KEY `risk_interventions_closed_by_foreign` (`closed_by`),
  KEY `risk_interventions_student_status_index` (`student_id`,`status`),
  KEY `risk_interventions_assignee_followup_index` (`assigned_to`,`status`,`follow_up_on`),
  CONSTRAINT `risk_interventions_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risk_interventions_risk_score_snapshot_id_foreign` FOREIGN KEY (`risk_score_snapshot_id`) REFERENCES `risk_score_snapshots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risk_interventions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `risk_factor_weights` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `factor_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `factor_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `factor_type` enum('count','boolean') COLLATE utf8mb4_unicode_ci NOT NULL,
  `count_cap` tinyint unsigned DEFAULT NULL,
  `weight` decimal(5,4) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `risk_factor_weights_factor_code_unique` (`factor_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_monitoring_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint unsigned DEFAULT NULL,
  `flag_type` enum('registration_hold','monitoring','clearance_hold') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monitoring',
  `flagged_by` bigint unsigned DEFAULT NULL,
  `flag_reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL,
  `deactivated_by` bigint unsigned DEFAULT NULL,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_monitoring_flags_student_id_foreign` (`student_id`),
  KEY `student_monitoring_flags_flagged_by_foreign` (`flagged_by`),
  KEY `student_monitoring_flags_deactivated_by_foreign` (`deactivated_by`),
  CONSTRAINT `student_monitoring_flags_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
