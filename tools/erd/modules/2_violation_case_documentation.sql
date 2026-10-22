-- PRISM ERD - Module 2: Violation and Case Documentation Module
-- 11 module tables + 3 external reference stubs
-- Audit-only FKs to `users` (created_by/updated_by/...) omitted for readability.

SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `incident_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` enum('violation_slip','student_complaint','faculty_report','security_report','direct_encoding') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reported_by_user_id` bigint unsigned DEFAULT NULL,
  `reported_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `academic_period_id` bigint unsigned DEFAULT NULL,
  `date_of_incident` date NOT NULL,
  `time_of_incident` time DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `violation_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'F-009 checkbox selection - for violation slips only',
  `status` enum('pending_review','under_review','converted_to_case','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `dismissal_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `incident_reports_report_number_unique` (`report_number`),
  KEY `incident_reports_reported_by_user_id_foreign` (`reported_by_user_id`),
  KEY `incident_reports_student_id_foreign` (`student_id`),
  KEY `incident_reports_reviewed_by_foreign` (`reviewed_by`),
  KEY `incident_reports_academic_period_id_foreign` (`academic_period_id`),
  KEY `incident_reports_status_created_index` (`status`,`created_at`),
  CONSTRAINT `incident_reports_academic_period_id_foreign` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incident_reports_reported_by_user_id_foreign` FOREIGN KEY (`reported_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incident_reports_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `incident_report_subjects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `incident_report_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `incident_report_subjects_incident_report_id_foreign` (`incident_report_id`),
  KEY `incident_report_subjects_student_id_foreign` (`student_id`),
  CONSTRAINT `incident_report_subjects_incident_report_id_foreign` FOREIGN KEY (`incident_report_id`) REFERENCES `incident_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incident_report_subjects_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `incident_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `incident_report_id` bigint unsigned DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size_bytes` int unsigned NOT NULL,
  `file_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_configured',
  `scanned_at` timestamp NULL DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `incident_attachments_incident_report_id_foreign` (`incident_report_id`),
  KEY `incident_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `incident_attachments_incident_report_id_foreign` FOREIGN KEY (`incident_report_id`) REFERENCES `incident_reports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `academic_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `academic_year` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `term_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_periods_academic_year_term_name_unique` (`academic_year`,`term_name`),
  KEY `academic_periods_starts_on_ends_on_index` (`starts_on`,`ends_on`),
  KEY `academic_periods_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `incident_report_id` bigint unsigned DEFAULT NULL,
  `student_id` bigint unsigned DEFAULT NULL,
  `case_type` enum('minor','major') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `case_type_locked` tinyint(1) NOT NULL DEFAULT '0',
  `case_type_change_reason` text COLLATE utf8mb4_unicode_ci,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `status` enum('open','pending_nte','awaiting_explanation','under_review','pending_upcc','pending_resolution','resolved','dismissed','voided') COLLATE utf8mb4_unicode_ci NOT NULL,
  `suggested_offense_id` bigint unsigned DEFAULT NULL,
  `confirmed_offense_id` bigint unsigned DEFAULT NULL,
  `classification_override_reason` text COLLATE utf8mb4_unicode_ci,
  `prior_offense_count` tinyint unsigned NOT NULL,
  `suggested_sanction_category` tinyint unsigned DEFAULT NULL,
  `assigned_sanction_category` tinyint unsigned DEFAULT NULL,
  `sanction_override_reason` text COLLATE utf8mb4_unicode_ci,
  `escalation_flagged` tinyint(1) NOT NULL DEFAULT '0',
  `escalation_flag_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `escalation_acknowledged_by` bigint unsigned DEFAULT NULL,
  `escalation_acknowledged_at` timestamp NULL DEFAULT NULL,
  `path_recommendation` enum('direct_resolution','upcc_referral') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path_recommendation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `forwarded_to_coordinator` tinyint(1) NOT NULL DEFAULT '0',
  `forwarded_at` timestamp NULL DEFAULT NULL,
  `forwarded_reason` text COLLATE utf8mb4_unicode_ci,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `dismissal_reason` text COLLATE utf8mb4_unicode_ci,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `void_requires_coordinator_approval` tinyint(1) NOT NULL DEFAULT '0',
  `void_coordinator_approved_by` bigint unsigned DEFAULT NULL,
  `void_coordinator_approved_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` text COLLATE utf8mb4_unicode_ci,
  `voided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cases_case_number_unique` (`case_number`),
  KEY `cases_incident_report_id_foreign` (`incident_report_id`),
  KEY `cases_student_id_foreign` (`student_id`),
  KEY `cases_assigned_to_foreign` (`assigned_to`),
  KEY `cases_suggested_offense_id_foreign` (`suggested_offense_id`),
  KEY `cases_confirmed_offense_id_foreign` (`confirmed_offense_id`),
  KEY `cases_escalation_acknowledged_by_foreign` (`escalation_acknowledged_by`),
  KEY `cases_resolved_by_foreign` (`resolved_by`),
  KEY `cases_void_coordinator_approved_by_foreign` (`void_coordinator_approved_by`),
  KEY `cases_voided_by_foreign` (`voided_by`),
  KEY `cases_status_updated_index` (`status`,`updated_at`),
  KEY `cases_type_status_index` (`case_type`,`status`),
  KEY `cases_status_resolved_index` (`status`,`resolved_at`),
  CONSTRAINT `cases_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_confirmed_offense_id_foreign` FOREIGN KEY (`confirmed_offense_id`) REFERENCES `offense_catalog` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_incident_report_id_foreign` FOREIGN KEY (`incident_report_id`) REFERENCES `incident_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_suggested_offense_id_foreign` FOREIGN KEY (`suggested_offense_id`) REFERENCES `offense_catalog` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `case_offenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint unsigned NOT NULL,
  `offense_catalog_id` bigint unsigned NOT NULL,
  `status` enum('confirmed','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmed',
  `prior_offense_count` tinyint unsigned NOT NULL DEFAULT '0',
  `classification_reason` text COLLATE utf8mb4_unicode_ci,
  `confirmed_by` bigint unsigned DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `case_offenses_case_id_offense_catalog_id_unique` (`case_id`,`offense_catalog_id`),
  KEY `case_offenses_offense_catalog_id_foreign` (`offense_catalog_id`),
  KEY `case_offenses_confirmed_by_foreign` (`confirmed_by`),
  CONSTRAINT `case_offenses_case_id_foreign` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `case_offenses_offense_catalog_id_foreign` FOREIGN KEY (`offense_catalog_id`) REFERENCES `offense_catalog` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `case_explanations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NOT NULL,
  `submission_channel` enum('portal','physical_encoded_by_do','no_response') COLLATE utf8mb4_unicode_ci NOT NULL,
  `admission_type` enum('full_admission','partial_admission','full_denial','no_response') COLLATE utf8mb4_unicode_ci NOT NULL,
  `explanation_text` text COLLATE utf8mb4_unicode_ci,
  `word_count` smallint DEFAULT NULL,
  `evaluated_by` bigint unsigned DEFAULT NULL,
  `evaluated_at` timestamp NULL DEFAULT NULL,
  `evaluation_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `case_explanations_case_id_foreign` (`case_id`),
  KEY `case_explanations_evaluated_by_foreign` (`evaluated_by`),
  CONSTRAINT `case_explanations_case_id_foreign` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `case_explanation_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_explanation_id` bigint unsigned DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` enum('pdf','jpg','png') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size_bytes` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `case_explanation_attachments_case_explanation_id_foreign` (`case_explanation_id`),
  KEY `case_explanation_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `case_explanation_attachments_case_explanation_id_foreign` FOREIGN KEY (`case_explanation_id`) REFERENCES `case_explanations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `case_notices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint unsigned DEFAULT NULL,
  `notice_type` enum('notice_to_explain','notice_of_hearing','notice_of_formative_intervention') COLLATE utf8mb4_unicode_ci NOT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `issued_at` timestamp NOT NULL,
  `student_handbook_id` bigint unsigned DEFAULT NULL,
  `handbook_section` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `handbook_page` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `incident_date` date NOT NULL,
  `response_deadline` date DEFAULT NULL,
  `deadline_reminder_sent_at` timestamp NULL DEFAULT NULL,
  `overdue_flagged_at` timestamp NULL DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `scheduled_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_content` json DEFAULT NULL,
  `student_acknowledged_at` timestamp NULL DEFAULT NULL,
  `is_voided` tinyint(1) NOT NULL DEFAULT '0',
  `voided_by` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `void_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `case_notices_case_id_foreign` (`case_id`),
  KEY `case_notices_issued_by_foreign` (`issued_by`),
  KEY `case_notices_voided_by_foreign` (`voided_by`),
  KEY `case_notices_student_handbook_id_foreign` (`student_handbook_id`),
  KEY `case_notices_type_voided_deadline_index` (`notice_type`,`is_voided`,`response_deadline`),
  CONSTRAINT `case_notices_case_id_foreign` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `case_notices_student_handbook_id_foreign` FOREIGN KEY (`student_handbook_id`) REFERENCES `student_handbooks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `case_resolutions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `case_id` bigint unsigned DEFAULT NULL,
  `upcc_referral_id` bigint unsigned DEFAULT NULL,
  `resolution_level` enum('sdo_level','upcc','presidents_council') COLLATE utf8mb4_unicode_ci NOT NULL,
  `intervention_category` tinyint unsigned NOT NULL,
  `us_hours_assigned` decimal(6,2) DEFAULT NULL,
  `counseling_referred` tinyint(1) NOT NULL DEFAULT '0',
  `discipline_education_required` tinyint(1) NOT NULL DEFAULT '0',
  `evaluation_monitoring_required` tinyint(1) NOT NULL DEFAULT '0',
  `probation_terms` tinyint unsigned DEFAULT NULL,
  `probation_end_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NOT NULL,
  `appeal_deadline` date NOT NULL,
  `appeal_filed` tinyint(1) NOT NULL DEFAULT '0',
  `appeal_filed_at` timestamp NULL DEFAULT NULL,
  `appeal_outcome` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `case_resolutions_case_id_unique` (`case_id`),
  KEY `case_resolutions_upcc_referral_id_foreign` (`upcc_referral_id`),
  KEY `case_resolutions_resolved_by_foreign` (`resolved_by`),
  CONSTRAINT `case_resolutions_case_id_foreign` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `case_resolutions_upcc_referral_id_foreign` FOREIGN KEY (`upcc_referral_id`) REFERENCES `upcc_referrals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_handbooks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `edition` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL,
  `checksum_sha256` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `published_by` bigint unsigned DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_handbooks_edition_unique` (`edition`),
  KEY `student_handbooks_uploaded_by_foreign` (`uploaded_by`),
  KEY `student_handbooks_published_by_foreign` (`published_by`),
  KEY `student_handbooks_status_effective_date_index` (`status`,`effective_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `offense_catalog` (
  `id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- [external reference - full definition lives in its own module]
CREATE TABLE `upcc_referrals` (
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
