-- PRISM schema (structure only, UTF-8) -- for ERD reverse-engineering
SET FOREIGN_KEY_CHECKS=0;

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

CREATE TABLE `academic_programs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `academic_unit_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_programs_name_unique` (`name`),
  KEY `academic_programs_academic_unit_id_is_active_name_index` (`academic_unit_id`,`is_active`,`name`),
  CONSTRAINT `academic_programs_academic_unit_id_foreign` FOREIGN KEY (`academic_unit_id`) REFERENCES `academic_units` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `academic_units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope_year_level` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_units_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `account_setup_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issued_by` bigint unsigned DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_setup_tokens_user_id_unique` (`user_id`),
  UNIQUE KEY `account_setup_tokens_token_hash_unique` (`token_hash`),
  KEY `account_setup_tokens_issued_by_foreign` (`issued_by`),
  KEY `account_setup_tokens_consumed_at_expires_at_index` (`consumed_at`,`expires_at`),
  CONSTRAINT `account_setup_tokens_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `account_setup_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `case_explanation_attachments_case_explanation_id_foreign` FOREIGN KEY (`case_explanation_id`) REFERENCES `case_explanations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `case_explanation_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `case_explanations_case_id_foreign` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `case_explanations_evaluated_by_foreign` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `case_notices_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `case_notices_student_handbook_id_foreign` FOREIGN KEY (`student_handbook_id`) REFERENCES `student_handbooks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `case_notices_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `case_offenses_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `case_offenses_offense_catalog_id_foreign` FOREIGN KEY (`offense_catalog_id`) REFERENCES `offense_catalog` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `case_resolutions_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `case_resolutions_upcc_referral_id_foreign` FOREIGN KEY (`upcc_referral_id`) REFERENCES `upcc_referrals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `cases_escalation_acknowledged_by_foreign` FOREIGN KEY (`escalation_acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_incident_report_id_foreign` FOREIGN KEY (`incident_report_id`) REFERENCES `incident_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_suggested_offense_id_foreign` FOREIGN KEY (`suggested_offense_id`) REFERENCES `offense_catalog` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_void_coordinator_approved_by_foreign` FOREIGN KEY (`void_coordinator_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cases_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `counseling_referrals_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_referrals_guidance_officer_id_foreign` FOREIGN KEY (`guidance_officer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_referrals_reassigned_from_foreign` FOREIGN KEY (`reassigned_from`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_referrals_referred_by_foreign` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_referrals_sanction_id_foreign` FOREIGN KEY (`sanction_id`) REFERENCES `sanctions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_referrals_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `discipline_education_attendances_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `discipline_education_attendances_sanction_id_foreign` FOREIGN KEY (`sanction_id`) REFERENCES `sanctions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `discipline_officer_academic_unit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `academic_unit_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `officer_academic_unit_unique` (`user_id`,`academic_unit_id`),
  KEY `discipline_officer_academic_unit_academic_unit_id_foreign` (`academic_unit_id`),
  CONSTRAINT `discipline_officer_academic_unit_academic_unit_id_foreign` FOREIGN KEY (`academic_unit_id`) REFERENCES `academic_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discipline_officer_academic_unit_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `guidance_officer_academic_unit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `academic_unit_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `guidance_officer_academic_unit_unique` (`user_id`,`academic_unit_id`),
  KEY `guidance_officer_academic_unit_academic_unit_id_foreign` (`academic_unit_id`),
  CONSTRAINT `guidance_officer_academic_unit_academic_unit_id_foreign` FOREIGN KEY (`academic_unit_id`) REFERENCES `academic_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `guidance_officer_academic_unit_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `incident_attachments_incident_report_id_foreign` FOREIGN KEY (`incident_report_id`) REFERENCES `incident_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incident_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `incident_reports_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incident_reports_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=769 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `offense_recommendation_audits_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `offense_recommendation_audits_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `offense_recommendation_audits_selected_offense_id_foreign` FOREIGN KEY (`selected_offense_id`) REFERENCES `offense_catalog` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
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
  CONSTRAINT `risk_interventions_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risk_interventions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risk_interventions_risk_score_snapshot_id_foreign` FOREIGN KEY (`risk_score_snapshot_id`) REFERENCES `risk_score_snapshots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risk_interventions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `risk_score_snapshots_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `risk_score_snapshots_triggered_by_foreign` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `sanctions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sanctions_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY `student_handbooks_status_effective_date_index` (`status`,`effective_date`),
  CONSTRAINT `student_handbooks_published_by_foreign` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_handbooks_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `student_monitoring_flags_deactivated_by_foreign` FOREIGN KEY (`deactivated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_monitoring_flags_flagged_by_foreign` FOREIGN KEY (`flagged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_monitoring_flags_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `student_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `program` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_contact` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_relationship` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_accuracy_acknowledged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_profiles_student_number_unique` (`student_number`),
  UNIQUE KEY `student_profiles_user_id_unique` (`user_id`),
  CONSTRAINT `student_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `university_service_offices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `university_service_offices_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `upcc_appeal_attachments_upcc_referral_id_foreign` FOREIGN KEY (`upcc_referral_id`) REFERENCES `upcc_referrals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `upcc_appeal_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `upcc_referrals_appeal_filed_by_foreign` FOREIGN KEY (`appeal_filed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `upcc_referrals_appeal_waived_by_foreign` FOREIGN KEY (`appeal_waived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `upcc_referrals_case_id_foreign` FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `upcc_referrals_referred_by_foreign` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `upcc_referrals_student_no_show_recorded_by_foreign` FOREIGN KEY (`student_no_show_recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `us_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
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
  CONSTRAINT `us_attendance_logs_logged_by_foreign` FOREIGN KEY (`logged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `us_attendance_logs_scanned_by_foreign` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `us_attendance_logs_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `us_attendance_logs_us_assignment_id_foreign` FOREIGN KEY (`us_assignment_id`) REFERENCES `us_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `us_attendance_logs_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institutional_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('super_admin','discipline_coordinator','discipline_officer','reporting_user','guidance_officer','us_supervisor','student') COLLATE utf8mb4_unicode_ci NOT NULL,
  `office_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `academic_unit_id` bigint unsigned DEFAULT NULL,
  `status` enum('pending_setup','active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL,
  `privacy_accepted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `profile_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_institutional_id_unique` (`institutional_id`),
  KEY `users_created_by_foreign` (`created_by`),
  KEY `users_academic_unit_id_foreign` (`academic_unit_id`),
  CONSTRAINT `users_academic_unit_id_foreign` FOREIGN KEY (`academic_unit_id`) REFERENCES `academic_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
