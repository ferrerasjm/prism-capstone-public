-- PRISM ERD - Module 1: User Management and Authentication Module
-- 7 module tables
-- Audit-only FKs to `users` (created_by/updated_by/...) omitted for readability.

SET FOREIGN_KEY_CHECKS=0;

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
  CONSTRAINT `users_academic_unit_id_foreign` FOREIGN KEY (`academic_unit_id`) REFERENCES `academic_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `account_setup_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
