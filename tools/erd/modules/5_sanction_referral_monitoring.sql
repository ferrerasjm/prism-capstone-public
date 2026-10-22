-- PRISM ERD - Module 5: Sanction and Referral Monitoring Module
-- 6 module tables + 3 external reference stubs
-- Audit-only FKs to `users` (created_by/updated_by/...) omitted for readability.

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `sanctions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_resolution_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `sanction_type` enum('written_warning','university_service','counseling_referral','discipline_education','evaluation_monitoring','probation','non_readmission','exclusion','expulsion') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','in_progress','completed','non_compliant','waived') COLLATE utf8mb4_unicode_ci NOT NULL,
  `due_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `completion_document_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `intervention_notification_sent_at` timestamp NULL DEFAULT NULL,
  `intervention_reminder_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sanctions_case_resolution_id_foreign` (`case_resolution_id`),
  KEY `sanctions_verified_by_foreign` (`verified_by`),
  KEY `sanctions_status_due_index` (`status`,`due_date`),
  KEY `sanctions_type_created_index` (`sanction_type`,`created_at`),
  KEY `sanctions_student_status_index` (`student_id`,`status`),
  CONSTRAINT `sanctions_case_resolution_id_foreign` FOREIGN KEY (`case_resolution_id`) REFERENCES `case_resolutions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sanctions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `counseling_referrals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sanction_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `guidance_officer_id` bigint unsigned DEFAULT NULL,
  `referred_by` bigint unsigned DEFAULT NULL,
  `referred_at` timestamp NOT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `referral_reason` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','completed','incomplete') COLLATE utf8mb4_unicode_ci NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `incomplete_reason` text COLLATE utf8mb4_unicode_ci,
  `confirmed_by` bigint unsigned DEFAULT NULL,
  `reassigned_from` bigint unsigned DEFAULT NULL,
  `reassigned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `counseling_referrals_sanction_id_unique` (`sanction_id`),
  KEY `counseling_referrals_student_id_foreign` (`student_id`),
  KEY `counseling_referrals_guidance_officer_id_foreign` (`guidance_officer_id`),
  KEY `counseling_referrals_referred_by_foreign` (`referred_by`),
  KEY `counseling_referrals_confirmed_by_foreign` (`confirmed_by`),
  KEY `counseling_referrals_reassigned_from_foreign` (`reassigned_from`),
  CONSTRAINT `counseling_referrals_guidance_officer_id_foreign` FOREIGN KEY (`guidance_officer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_referrals_referred_by_foreign` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_referrals_sanction_id_foreign` FOREIGN KEY (`sanction_id`) REFERENCES `sanctions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_referrals_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `discipline_education_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sanction_id` bigint unsigned NOT NULL,
  `session_at` datetime NOT NULL,
  `status` enum('scheduled','present','absent') COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `recorded_by` bigint unsigned NOT NULL,
  `notification_sent_at` timestamp NULL DEFAULT NULL,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discipline_education_attendances_recorded_by_foreign` (`recorded_by`),
  KEY `de_attendance_sanction_session_index` (`sanction_id`,`session_at`),
  CONSTRAINT `discipline_education_attendances_sanction_id_foreign` FOREIGN KEY (`sanction_id`) REFERENCES `sanctions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `university_service_offices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `university_service_offices_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `upcc_referrals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint unsigned DEFAULT NULL,
  `referred_by` bigint unsigned DEFAULT NULL,
  `referred_at` timestamp NOT NULL,
  `scheduled_conference_at` datetime NOT NULL,
  `conference_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_attended` tinyint(1) DEFAULT NULL,
  `student_no_show_recorded_by` bigint unsigned DEFAULT NULL,
  `proceedings_started_at` timestamp NULL DEFAULT NULL,
  `proceedings_ended_at` timestamp NULL DEFAULT NULL,
  `status` enum('scheduled','in_progress','concluded','reconsideration_filed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `proceedings_type` enum('full_hearing','summary') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full_hearing',
  `sdao_head_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dean_or_program_chair_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cross_program_faculty_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_representative_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `presiding_officer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_summary` text COLLATE utf8mb4_unicode_ci,
  `decision_issued_at` timestamp NULL DEFAULT NULL,
  `final_report_submitted_at` timestamp NULL DEFAULT NULL,
  `decision_distributed_at` timestamp NULL DEFAULT NULL,
  `appeal_window_start` date DEFAULT NULL,
  `appeal_window_end` date DEFAULT NULL,
  `appeal_filed` tinyint(1) NOT NULL DEFAULT '0',
  `appeal_filed_by` bigint unsigned DEFAULT NULL,
  `appeal_filed_at` timestamp NULL DEFAULT NULL,
  `appeal_source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appeal_waived_by` bigint unsigned DEFAULT NULL,
  `appeal_waived_at` timestamp NULL DEFAULT NULL,
  `appeal_notes` text COLLATE utf8mb4_unicode_ci,
  `decision_final_at` timestamp NULL DEFAULT NULL,
  `records_filed_at` timestamp NULL DEFAULT NULL,
  `submitted_to_presidents_council_at` timestamp NULL DEFAULT NULL,
  `presidents_council_action` enum('approved','modified') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `presidents_council_notes` text COLLATE utf8mb4_unicode_ci,
  `presidents_council_acted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `upcc_referrals_case_id_foreign` (`case_id`),
  KEY `upcc_referrals_referred_by_foreign` (`referred_by`),
  KEY `upcc_referrals_student_no_show_recorded_by_foreign` (`student_no_show_recorded_by`),
  KEY `upcc_referrals_appeal_filed_by_foreign` (`appeal_filed_by`),
  KEY `upcc_referrals_appeal_waived_by_foreign` (`appeal_waived_by`),
  CONSTRAINT `upcc_referrals_case_id_foreign` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `upcc_referrals_referred_by_foreign` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `upcc_appeal_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `upcc_referral_id` bigint unsigned NOT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size_bytes` int unsigned NOT NULL,
  `file_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_configured',
  `scanned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `upcc_appeal_attachments_upcc_referral_id_foreign` (`upcc_referral_id`),
  KEY `upcc_appeal_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `upcc_appeal_attachments_upcc_referral_id_foreign` FOREIGN KEY (`upcc_referral_id`) REFERENCES `upcc_referrals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `case_resolutions` (
  `id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `cases` (
  `id` bigint unsigned NOT NULL,
  `case_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
