-- PRISM ERD - Module 7: Mobile University Service Tracking Module
-- 3 module tables + 2 external reference stubs
-- Audit-only FKs to `users` (created_by/updated_by/...) omitted for readability.

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `us_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_resolution_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `supervisor_id` bigint unsigned DEFAULT NULL,
  `contact_person_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `office_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_hours_required` decimal(6,2) NOT NULL,
  `hours_rendered` decimal(6,2) NOT NULL DEFAULT '0.00',
  `start_date` date NOT NULL,
  `target_completion_date` date NOT NULL,
  `status` enum('pending','in_progress','completed','non_compliant') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `totp_secret` text COLLATE utf8mb4_unicode_ci,
  `totp_secret_generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `us_assignments_student_id_foreign` (`student_id`),
  KEY `us_assignments_supervisor_id_foreign` (`supervisor_id`),
  KEY `us_assignments_case_resolution_id_foreign` (`case_resolution_id`),
  KEY `us_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `us_assignments_status_target_index` (`status`,`target_completion_date`),
  CONSTRAINT `us_assignments_case_resolution_id_foreign` FOREIGN KEY (`case_resolution_id`) REFERENCES `case_resolutions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `us_assignments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `us_assignments_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `us_attendance_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `us_assignment_id` bigint unsigned DEFAULT NULL,
  `scanned_by` bigint unsigned DEFAULT NULL,
  `event_type` enum('time_in','time_out') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_timestamp` datetime DEFAULT NULL,
  `server_timestamp` datetime DEFAULT NULL,
  `hours_computed` decimal(5,2) DEFAULT NULL,
  `is_manual_entry` tinyint(1) NOT NULL DEFAULT '0',
  `is_synced` tinyint(1) NOT NULL DEFAULT '0',
  `sync_batch_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_status` enum('pending','accepted','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `sync_rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `date_of_service` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `hours_rendered` decimal(5,2) DEFAULT NULL,
  `tasks_performed` text COLLATE utf8mb4_unicode_ci,
  `log_method` enum('qr_scan','manual_web','no_show') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual_web',
  `totp_verified` tinyint(1) DEFAULT NULL,
  `client_scanned_at` timestamp NULL DEFAULT NULL,
  `override_reason` text COLLATE utf8mb4_unicode_ci,
  `logged_by` bigint unsigned DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `us_attendance_logs_us_assignment_id_foreign` (`us_assignment_id`),
  KEY `us_attendance_logs_scanned_by_foreign` (`scanned_by`),
  KEY `us_attendance_logs_student_id_foreign` (`student_id`),
  KEY `us_attendance_logs_logged_by_foreign` (`logged_by`),
  KEY `us_attendance_logs_verified_by_foreign` (`verified_by`),
  CONSTRAINT `us_attendance_logs_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `us_attendance_logs_us_assignment_id_foreign` FOREIGN KEY (`us_assignment_id`) REFERENCES `us_assignments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mobile_otp_challenges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `otp_token` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `expires_at` timestamp NOT NULL,
  `resend_available_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile_otp_challenges_otp_token_unique` (`otp_token`),
  KEY `mobile_otp_challenges_user_id_used_at_index` (`user_id`,`used_at`),
  CONSTRAINT `mobile_otp_challenges_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `case_resolutions` (
  `id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
