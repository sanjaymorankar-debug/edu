-- =====================================================================
-- EDU (edutest.agtci.com) — 01-schema.sql
-- Full database schema: 50 tables (incl. Laravel `migrations`).
-- Generated from the Laravel migrations on main @ f35b82a with
-- `php artisan migrate --pretend` — the exact SQL `php artisan migrate`
-- would run. Works on MySQL 8 and MariaDB 10.6+.
--
-- HOW TO IMPORT (phpMyAdmin): select the (empty) database in the left
-- sidebar FIRST, then Import this file. No CREATE DATABASE/USE here, so it
-- works with Hostinger's prefixed database names (u123456789_edu).
-- Then import 02-reference-data.sql (and optionally 03-demo-data.sql).
--
-- The `migrations` table is filled at the end, so a later
-- `php artisan migrate --force` on the server is a no-op, not an error.
-- DO NOT import into a database that already has these tables.
-- =====================================================================

SET NAMES utf8mb4;

create table `migrations` (`id` int unsigned not null auto_increment primary key, `migration` varchar(255) not null, `batch` int not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

-- 0001_01_01_000000_create_users_table
create table `users` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `email` varchar(255) not null, `email_verified_at` timestamp null, `password` varchar(255) not null, `remember_token` varchar(100) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `users` add unique `users_email_unique`(`email`);
create table `password_reset_tokens` (`email` varchar(255) not null, `token` varchar(255) not null, `created_at` timestamp null, primary key (`email`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `sessions` (`id` varchar(255) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` text null, `payload` longtext not null, `last_activity` int not null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `sessions` add index `sessions_user_id_index`(`user_id`);
alter table `sessions` add index `sessions_last_activity_index`(`last_activity`);

-- 0001_01_01_000001_create_cache_table
create table `cache` (`key` varchar(255) not null, `value` mediumtext not null, `expiration` bigint not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `cache` add index `cache_expiration_index`(`expiration`);
create table `cache_locks` (`key` varchar(255) not null, `owner` varchar(255) not null, `expiration` bigint not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `cache_locks` add index `cache_locks_expiration_index`(`expiration`);

-- 0001_01_01_000002_create_jobs_table
create table `jobs` (`id` bigint unsigned not null auto_increment primary key, `queue` varchar(255) not null, `payload` longtext not null, `attempts` smallint unsigned not null, `reserved_at` int unsigned null, `available_at` int unsigned not null, `created_at` int unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `jobs` add index `jobs_queue_index`(`queue`);
create table `job_batches` (`id` varchar(255) not null, `name` varchar(255) not null, `total_jobs` int not null, `pending_jobs` int not null, `failed_jobs` int not null, `failed_job_ids` longtext not null, `options` mediumtext null, `cancelled_at` int null, `created_at` int not null, `finished_at` int null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `failed_jobs` (`id` bigint unsigned not null auto_increment primary key, `uuid` varchar(255) not null, `connection` varchar(255) not null, `queue` varchar(255) not null, `payload` longtext not null, `exception` longtext not null, `failed_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `failed_jobs` add index `failed_jobs_connection_queue_failed_at_index`(`connection`, `queue`, `failed_at`);
alter table `failed_jobs` add unique `failed_jobs_uuid_unique`(`uuid`);

-- 2026_08_25_011344_create_permission_tables
create table `permissions` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `guard_name` varchar(255) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `permissions` add unique `permissions_name_guard_name_unique`(`name`, `guard_name`);
create table `roles` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `guard_name` varchar(255) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `roles` add unique `roles_name_guard_name_unique`(`name`, `guard_name`);
create table `model_has_permissions` (`permission_id` bigint unsigned not null, `model_type` varchar(255) not null, `model_id` bigint unsigned not null, primary key (`permission_id`, `model_id`, `model_type`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `model_has_permissions` add index `model_has_permissions_model_id_model_type_index`(`model_id`, `model_type`);
alter table `model_has_permissions` add constraint `model_has_permissions_permission_id_foreign` foreign key (`permission_id`) references `permissions` (`id`) on delete cascade;
create table `model_has_roles` (`role_id` bigint unsigned not null, `model_type` varchar(255) not null, `model_id` bigint unsigned not null, primary key (`role_id`, `model_id`, `model_type`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `model_has_roles` add index `model_has_roles_model_id_model_type_index`(`model_id`, `model_type`);
alter table `model_has_roles` add constraint `model_has_roles_role_id_foreign` foreign key (`role_id`) references `roles` (`id`) on delete cascade;
create table `role_has_permissions` (`permission_id` bigint unsigned not null, `role_id` bigint unsigned not null, primary key (`permission_id`, `role_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `role_has_permissions` add constraint `role_has_permissions_permission_id_foreign` foreign key (`permission_id`) references `permissions` (`id`) on delete cascade;
alter table `role_has_permissions` add constraint `role_has_permissions_role_id_foreign` foreign key (`role_id`) references `roles` (`id`) on delete cascade;
delete from `cache` where `key` in ('education-accountability-platform-cache-spatie.permission.cache', 'education-accountability-platform-cache-illuminate:cache:flexible:created:spatie.permission.cache');

-- 2026_08_25_044125_create_states_table
create table `states` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `code` varchar(10) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `states` add unique `states_code_unique`(`code`);

-- 2026_08_25_044126_create_districts_table
create table `districts` (`id` bigint unsigned not null auto_increment primary key, `state_id` bigint unsigned not null, `name` varchar(255) not null, `code` varchar(20) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `districts` add constraint `districts_state_id_foreign` foreign key (`state_id`) references `states` (`id`) on delete cascade;
alter table `districts` add unique `districts_state_id_name_unique`(`state_id`, `name`);

-- 2026_08_25_044127_create_schools_table
create table `schools` (`id` bigint unsigned not null auto_increment primary key, `school_code` varchar(255) not null, `name` varchar(255) not null, `board` enum('CBSE', 'ICSE', 'STATE', 'IB', 'OTHER') not null default 'STATE', `management_type` enum('government', 'aided', 'private', 'international') not null default 'private', `state_id` bigint unsigned not null, `district_id` bigint unsigned not null, `address` varchar(255) not null, `city` varchar(255) not null, `pincode` varchar(10) not null, `phone` varchar(255) null, `email` varchar(255) null, `website` varchar(255) null, `recognition_status` enum('verified', 'pending', 'under_review') not null default 'pending', `classes_from` varchar(20) null, `classes_to` varchar(20) null, `student_count` int unsigned not null default '0', `teacher_count` int unsigned not null default '0', `established_year` smallint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `schools` add constraint `schools_state_id_foreign` foreign key (`state_id`) references `states` (`id`) on delete restrict;
alter table `schools` add constraint `schools_district_id_foreign` foreign key (`district_id`) references `districts` (`id`) on delete restrict;
alter table `schools` add index `schools_state_id_district_id_index`(`state_id`, `district_id`);
alter table `schools` add index `schools_name_index`(`name`);
alter table `schools` add index `schools_pincode_index`(`pincode`);
alter table `schools` add index `schools_board_index`(`board`);
alter table `schools` add unique `schools_school_code_unique`(`school_code`);

-- 2026_08_25_044128_create_school_profiles_table
create table `school_profiles` (`id` bigint unsigned not null auto_increment primary key, `school_id` bigint unsigned not null, `about` text null, `facilities` json null, `sports` json null, `fees` json null, `policies` text null, `has_transport` tinyint(1) not null default '0', `has_hostel` tinyint(1) not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `school_profiles` add constraint `school_profiles_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `school_profiles` add unique `school_profiles_school_id_unique`(`school_id`);

-- 2026_08_25_044129_create_anonymous_identities_table
create table `anonymous_identities` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `school_id` bigint unsigned not null, `context` enum('parent', 'student') not null, `anonymous_ref` varchar(40) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `anonymous_identities` add constraint `anonymous_identities_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `anonymous_identities` add constraint `anonymous_identities_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `anonymous_identities` add unique `anon_identity_unique`(`user_id`, `school_id`, `context`);
alter table `anonymous_identities` add unique `anonymous_identities_anonymous_ref_unique`(`anonymous_ref`);

-- 2026_08_25_044130_create_parent_profiles_table
create table `parent_profiles` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `phone` varchar(20) null, `verified_at` timestamp null, `verification_method` varchar(40) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `parent_profiles` add constraint `parent_profiles_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `parent_profiles` add unique `parent_profiles_user_id_unique`(`user_id`);

-- 2026_08_25_044131_create_student_profiles_table
create table `student_profiles` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `date_of_birth` date null, `gender` varchar(20) null, `verified_at` timestamp null, `verification_method` varchar(40) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `student_profiles` add constraint `student_profiles_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `student_profiles` add unique `student_profiles_user_id_unique`(`user_id`);

-- 2026_08_25_044132_create_teacher_profiles_table
create table `teacher_profiles` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `subject_specialization` varchar(255) null, `qualification` varchar(255) null, `joining_date` date null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `teacher_profiles` add constraint `teacher_profiles_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `teacher_profiles` add unique `teacher_profiles_user_id_unique`(`user_id`);

-- 2026_08_25_044133_create_school_staff_table
create table `school_staff` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `school_id` bigint unsigned not null, `designation` varchar(60) not null default 'School Admin', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `school_staff` add constraint `school_staff_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `school_staff` add constraint `school_staff_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `school_staff` add unique `school_staff_user_id_school_id_unique`(`user_id`, `school_id`);

-- 2026_08_25_044134_create_parent_school_relationships_table
create table `parent_school_relationships` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `school_id` bigint unsigned not null, `student_user_id` bigint unsigned null, `status` enum('pending', 'verified', 'rejected') not null default 'pending', `verified_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `parent_school_relationships` add constraint `parent_school_relationships_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `parent_school_relationships` add constraint `parent_school_relationships_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `parent_school_relationships` add constraint `parent_school_relationships_student_user_id_foreign` foreign key (`student_user_id`) references `users` (`id`) on delete set null;
alter table `parent_school_relationships` add unique `parent_school_rel_unique`(`user_id`, `school_id`, `student_user_id`);

-- 2026_08_25_044135_create_student_school_relationships_table
create table `student_school_relationships` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `school_id` bigint unsigned not null, `class_grade` varchar(20) null, `status` enum('pending', 'verified', 'rejected') not null default 'pending', `verified_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `student_school_relationships` add constraint `student_school_relationships_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `student_school_relationships` add constraint `student_school_relationships_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `student_school_relationships` add unique `student_school_relationships_user_id_school_id_unique`(`user_id`, `school_id`);

-- 2026_08_25_044136_create_teacher_school_relationships_table
create table `teacher_school_relationships` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `school_id` bigint unsigned not null, `status` enum('pending', 'verified', 'rejected') not null default 'pending', `verified_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `teacher_school_relationships` add constraint `teacher_school_relationships_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `teacher_school_relationships` add constraint `teacher_school_relationships_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `teacher_school_relationships` add unique `teacher_school_relationships_user_id_school_id_unique`(`user_id`, `school_id`);

-- 2026_08_25_044137_create_complaint_categories_table
create table `complaint_categories` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `slug` varchar(255) not null, `description` text null, `is_child_safety` tinyint(1) not null default '0', `is_active` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `complaint_categories` add unique `complaint_categories_slug_unique`(`slug`);

-- 2026_08_25_044138_create_complaints_table
create table `complaints` (`id` bigint unsigned not null auto_increment primary key, `complaint_number` varchar(40) not null, `school_id` bigint unsigned not null, `complaint_category_id` bigint unsigned not null, `district_id` bigint unsigned not null, `state_id` bigint unsigned not null, `anonymous_ref` varchar(40) not null, `submitted_role` enum('parent', 'student') not null, `subject` varchar(255) not null, `description` text not null, `severity` enum('low', 'medium', 'high', 'critical') not null default 'medium', `status` enum('submitted', 'under_review', 'school_responded', 'escalated', 'investigating', 'action_taken', 'resolved', 'closed') not null default 'submitted', `is_child_safety_flag` tinyint(1) not null default '0', `resolved_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `complaints` add constraint `complaints_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete restrict;
alter table `complaints` add constraint `complaints_complaint_category_id_foreign` foreign key (`complaint_category_id`) references `complaint_categories` (`id`) on delete restrict;
alter table `complaints` add constraint `complaints_district_id_foreign` foreign key (`district_id`) references `districts` (`id`) on delete restrict;
alter table `complaints` add constraint `complaints_state_id_foreign` foreign key (`state_id`) references `states` (`id`) on delete restrict;
alter table `complaints` add index `complaints_status_index`(`status`);
alter table `complaints` add index `complaints_anonymous_ref_index`(`anonymous_ref`);
alter table `complaints` add index `complaints_school_id_status_index`(`school_id`, `status`);
alter table `complaints` add index `complaints_district_id_status_index`(`district_id`, `status`);
alter table `complaints` add unique `complaints_complaint_number_unique`(`complaint_number`);

-- 2026_08_25_044139_create_complaint_evidence_table
create table `complaint_evidence` (`id` bigint unsigned not null auto_increment primary key, `complaint_id` bigint unsigned not null, `uploaded_by` enum('submitter', 'school') not null default 'submitter', `original_filename` varchar(255) not null, `stored_filename` varchar(255) not null, `mime_type` varchar(100) not null, `size_bytes` bigint unsigned not null, `disk` varchar(40) not null default 'local', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `complaint_evidence` add constraint `complaint_evidence_complaint_id_foreign` foreign key (`complaint_id`) references `complaints` (`id`) on delete cascade;

-- 2026_08_25_044140_create_complaint_responses_table
create table `complaint_responses` (`id` bigint unsigned not null auto_increment primary key, `complaint_id` bigint unsigned not null, `responder_type` enum('school', 'district', 'state') not null, `responder_user_id` bigint unsigned null, `message` text not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `complaint_responses` add constraint `complaint_responses_complaint_id_foreign` foreign key (`complaint_id`) references `complaints` (`id`) on delete cascade;
alter table `complaint_responses` add constraint `complaint_responses_responder_user_id_foreign` foreign key (`responder_user_id`) references `users` (`id`) on delete set null;

-- 2026_08_25_044141_create_complaint_status_history_table
create table `complaint_status_history` (`id` bigint unsigned not null auto_increment primary key, `complaint_id` bigint unsigned not null, `from_status` varchar(40) null, `to_status` varchar(40) not null, `changed_by_user_id` bigint unsigned null, `note` text null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `complaint_status_history` add constraint `complaint_status_history_complaint_id_foreign` foreign key (`complaint_id`) references `complaints` (`id`) on delete cascade;
alter table `complaint_status_history` add constraint `complaint_status_history_changed_by_user_id_foreign` foreign key (`changed_by_user_id`) references `users` (`id`) on delete set null;

-- 2026_08_25_044142_create_complaint_resolutions_table
create table `complaint_resolutions` (`id` bigint unsigned not null auto_increment primary key, `complaint_id` bigint unsigned not null, `resolution_summary` text null, `confirmed_by_submitter` enum('pending', 'yes', 'partially', 'no') not null default 'pending', `confirmed_at` timestamp null, `escalated` tinyint(1) not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `complaint_resolutions` add constraint `complaint_resolutions_complaint_id_foreign` foreign key (`complaint_id`) references `complaints` (`id`) on delete cascade;
alter table `complaint_resolutions` add unique `complaint_resolutions_complaint_id_unique`(`complaint_id`);

-- 2026_08_25_044143_create_school_feedback_table
create table `school_feedback` (`id` bigint unsigned not null auto_increment primary key, `school_id` bigint unsigned not null, `anonymous_ref` varchar(40) not null, `rater_role` enum('parent', 'student') not null, `dimension_scores` json not null, `overall_comment` text null, `submitted_at` timestamp not null default CURRENT_TIMESTAMP, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `school_feedback` add constraint `school_feedback_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `school_feedback` add index `school_feedback_school_id_submitted_at_index`(`school_id`, `submitted_at`);
alter table `school_feedback` add index `school_feedback_anonymous_ref_index`(`anonymous_ref`);

-- 2026_08_25_044144_create_school_rating_components_table
create table `school_rating_components` (`id` bigint unsigned not null auto_increment primary key, `key` varchar(60) not null, `label` varchar(255) not null, `weight` decimal(5, 2) not null default '10', `is_active` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `school_rating_components` add unique `school_rating_components_key_unique`(`key`);

-- 2026_08_25_044145_create_school_quality_scores_table
create table `school_quality_scores` (`id` bigint unsigned not null auto_increment primary key, `school_id` bigint unsigned not null, `score` decimal(5, 2) not null, `confidence` enum('high', 'medium', 'low', 'insufficient_data') not null, `response_count` int unsigned not null, `component_breakdown` json not null, `calculated_at` timestamp not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `school_quality_scores` add constraint `school_quality_scores_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `school_quality_scores` add index `school_quality_scores_school_id_calculated_at_index`(`school_id`, `calculated_at`);

-- 2026_08_25_044146_create_audit_logs_table
create table `audit_logs` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned null, `action` varchar(80) not null, `auditable_type` varchar(255) null, `auditable_id` bigint unsigned null, `ip_address` varchar(45) null, `metadata` json null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `audit_logs` add constraint `audit_logs_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete set null;
alter table `audit_logs` add index `audit_logs_auditable_type_auditable_id_index`(`auditable_type`, `auditable_id`);
alter table `audit_logs` add index `audit_logs_action_index`(`action`);

-- 2026_08_25_044147_create_identity_access_logs_table
create table `identity_access_logs` (`id` bigint unsigned not null auto_increment primary key, `officer_user_id` bigint unsigned not null, `anonymous_ref` varchar(40) not null, `action` varchar(80) not null, `reason` text null, `created_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `identity_access_logs` add constraint `identity_access_logs_officer_user_id_foreign` foreign key (`officer_user_id`) references `users` (`id`) on delete restrict;
alter table `identity_access_logs` add index `identity_access_logs_anonymous_ref_index`(`anonymous_ref`);
alter table `identity_access_logs` add index `identity_access_logs_officer_user_id_index`(`officer_user_id`);

-- 2026_08_25_044148_create_notifications_table
create table `notifications` (`id` char(36) not null, `type` varchar(255) not null, `notifiable_type` varchar(255) not null, `notifiable_id` bigint unsigned not null, `data` text not null, `read_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `notifications` add index `notifications_notifiable_type_notifiable_id_index`(`notifiable_type`, `notifiable_id`);

-- 2026_08_25_044427_create_officer_jurisdictions_table
create table `officer_jurisdictions` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `level` enum('district', 'state', 'national') not null, `district_id` bigint unsigned null, `state_id` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `officer_jurisdictions` add constraint `officer_jurisdictions_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `officer_jurisdictions` add constraint `officer_jurisdictions_district_id_foreign` foreign key (`district_id`) references `districts` (`id`) on delete cascade;
alter table `officer_jurisdictions` add constraint `officer_jurisdictions_state_id_foreign` foreign key (`state_id`) references `states` (`id`) on delete cascade;
alter table `officer_jurisdictions` add unique `officer_jurisdiction_unique`(`user_id`, `level`, `district_id`, `state_id`);

-- 2026_08_26_023118_create_retaliation_reports_table
create table `retaliation_reports` (`id` bigint unsigned not null auto_increment primary key, `complaint_id` bigint unsigned null, `school_id` bigint unsigned not null, `district_id` bigint unsigned not null, `state_id` bigint unsigned not null, `anonymous_ref` varchar(40) not null, `submitted_role` enum('parent', 'student') not null, `category` enum('intimidation', 'harassment', 'discrimination', 'punishment', 'academic_retaliation', 'threats', 'withdrawal_of_facilities', 'other') not null, `description` text not null, `status` enum('submitted', 'under_review', 'investigating', 'action_taken', 'resolved', 'closed') not null default 'submitted', `resolved_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `retaliation_reports` add constraint `retaliation_reports_complaint_id_foreign` foreign key (`complaint_id`) references `complaints` (`id`) on delete set null;
alter table `retaliation_reports` add constraint `retaliation_reports_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete restrict;
alter table `retaliation_reports` add constraint `retaliation_reports_district_id_foreign` foreign key (`district_id`) references `districts` (`id`) on delete restrict;
alter table `retaliation_reports` add constraint `retaliation_reports_state_id_foreign` foreign key (`state_id`) references `states` (`id`) on delete restrict;
alter table `retaliation_reports` add index `retaliation_reports_status_index`(`status`);
alter table `retaliation_reports` add index `retaliation_reports_anonymous_ref_index`(`anonymous_ref`);
alter table `retaliation_reports` add index `retaliation_reports_district_id_status_index`(`district_id`, `status`);

-- 2026_08_26_023130_create_teacher_feedback_table
create table `teacher_feedback` (`id` bigint unsigned not null auto_increment primary key, `teacher_user_id` bigint unsigned not null, `school_id` bigint unsigned not null, `anonymous_ref` varchar(40) not null, `rater_role` enum('parent', 'student') not null, `dimension_scores` json not null, `overall_comment` text null, `submitted_at` timestamp not null default CURRENT_TIMESTAMP, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `teacher_feedback` add constraint `teacher_feedback_teacher_user_id_foreign` foreign key (`teacher_user_id`) references `users` (`id`) on delete cascade;
alter table `teacher_feedback` add constraint `teacher_feedback_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `teacher_feedback` add index `teacher_feedback_teacher_user_id_submitted_at_index`(`teacher_user_id`, `submitted_at`);
alter table `teacher_feedback` add index `teacher_feedback_anonymous_ref_index`(`anonymous_ref`);

-- 2026_08_26_023143_create_teacher_effectiveness_scores_table
create table `teacher_effectiveness_scores` (`id` bigint unsigned not null auto_increment primary key, `teacher_user_id` bigint unsigned not null, `score` decimal(5, 2) not null, `confidence` enum('high', 'medium', 'low', 'insufficient_data') not null, `response_count` int unsigned not null, `component_breakdown` json not null, `calculated_at` timestamp not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `teacher_effectiveness_scores` add constraint `teacher_effectiveness_scores_teacher_user_id_foreign` foreign key (`teacher_user_id`) references `users` (`id`) on delete cascade;
alter table `teacher_effectiveness_scores` add index `teacher_effectiveness_scores_teacher_user_id_calculated_at_index`(`teacher_user_id`, `calculated_at`);

-- 2026_08_26_024026_create_teacher_rating_components_table
create table `teacher_rating_components` (`id` bigint unsigned not null auto_increment primary key, `key` varchar(60) not null, `label` varchar(255) not null, `weight` decimal(5, 2) not null default '10', `is_active` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `teacher_rating_components` add unique `teacher_rating_components_key_unique`(`key`);

-- 2026_08_26_071443_create_invitations_table
create table `invitations` (`id` bigint unsigned not null auto_increment primary key, `school_id` bigint unsigned not null, `invited_by_user_id` bigint unsigned not null, `email` varchar(255) not null, `role` enum('parent', 'student', 'teacher') not null, `student_name` varchar(255) null, `token` varchar(64) not null, `status` enum('pending', 'accepted', 'revoked') not null default 'pending', `accepted_by_user_id` bigint unsigned null, `accepted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `invitations` add constraint `invitations_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `invitations` add constraint `invitations_invited_by_user_id_foreign` foreign key (`invited_by_user_id`) references `users` (`id`) on delete cascade;
alter table `invitations` add constraint `invitations_accepted_by_user_id_foreign` foreign key (`accepted_by_user_id`) references `users` (`id`) on delete set null;
alter table `invitations` add index `invitations_school_id_status_index`(`school_id`, `status`);
alter table `invitations` add unique `invitations_token_unique`(`token`);

-- 2026_08_26_134827_create_student_academic_records_table
create table `student_academic_records` (`id` bigint unsigned not null auto_increment primary key, `student_user_id` bigint unsigned not null, `school_id` bigint unsigned not null, `subject` varchar(100) not null, `term` varchar(40) not null, `score` decimal(6, 2) not null, `max_score` decimal(6, 2) not null default '100', `recorded_by_user_id` bigint unsigned not null, `recorded_at` timestamp not null default CURRENT_TIMESTAMP, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `student_academic_records` add constraint `student_academic_records_student_user_id_foreign` foreign key (`student_user_id`) references `users` (`id`) on delete cascade;
alter table `student_academic_records` add constraint `student_academic_records_school_id_foreign` foreign key (`school_id`) references `schools` (`id`) on delete cascade;
alter table `student_academic_records` add constraint `student_academic_records_recorded_by_user_id_foreign` foreign key (`recorded_by_user_id`) references `users` (`id`) on delete restrict;
alter table `student_academic_records` add index `student_academic_records_student_user_id_subject_index`(`student_user_id`, `subject`);
alter table `student_academic_records` add index `student_academic_records_school_id_subject_term_index`(`school_id`, `subject`, `term`);

-- 2026_08_26_134833_create_fraud_flags_table
create table `fraud_flags` (`id` bigint unsigned not null auto_increment primary key, `flag_type` enum('feedback_spike', 'coordinated_review', 'duplicate_pattern', 'other') not null, `subject_type` enum('school', 'teacher') not null, `subject_id` bigint unsigned not null, `details` json null, `status` enum('open', 'reviewing', 'dismissed', 'confirmed') not null default 'open', `reviewed_by_user_id` bigint unsigned null, `reviewed_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `fraud_flags` add constraint `fraud_flags_reviewed_by_user_id_foreign` foreign key (`reviewed_by_user_id`) references `users` (`id`) on delete set null;
alter table `fraud_flags` add index `fraud_flags_subject_type_subject_id_index`(`subject_type`, `subject_id`);
alter table `fraud_flags` add index `fraud_flags_status_index`(`status`);

-- 2026_08_26_134834_create_appeals_table
create table `appeals` (`id` bigint unsigned not null auto_increment primary key, `complaint_id` bigint unsigned not null, `district_id` bigint unsigned not null, `state_id` bigint unsigned not null, `anonymous_ref` varchar(40) not null, `reason` text not null, `status` enum('submitted', 'under_review', 'upheld', 'denied') not null default 'submitted', `reviewed_by_user_id` bigint unsigned null, `decision_note` text null, `resolved_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `appeals` add constraint `appeals_complaint_id_foreign` foreign key (`complaint_id`) references `complaints` (`id`) on delete cascade;
alter table `appeals` add constraint `appeals_district_id_foreign` foreign key (`district_id`) references `districts` (`id`) on delete restrict;
alter table `appeals` add constraint `appeals_state_id_foreign` foreign key (`state_id`) references `states` (`id`) on delete restrict;
alter table `appeals` add constraint `appeals_reviewed_by_user_id_foreign` foreign key (`reviewed_by_user_id`) references `users` (`id`) on delete set null;
alter table `appeals` add unique `appeals_complaint_id_unique`(`complaint_id`);
alter table `appeals` add index `appeals_status_index`(`status`);
alter table `appeals` add index `appeals_state_id_status_index`(`state_id`, `status`);

-- 2026_08_26_134838_create_analytics_snapshots_table
create table `analytics_snapshots` (`id` bigint unsigned not null auto_increment primary key, `scope` enum('state', 'national') not null, `scope_id` bigint unsigned null, `metrics` json not null, `calculated_at` timestamp not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `analytics_snapshots` add index `analytics_snapshots_scope_scope_id_calculated_at_index`(`scope`, `scope_id`, `calculated_at`);

-- 2026_08_26_134839_create_two_factor_authentications_table
create table `two_factor_authentications` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `secret` text not null, `recovery_codes` text null, `confirmed_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `two_factor_authentications` add constraint `two_factor_authentications_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `two_factor_authentications` add unique `two_factor_authentications_user_id_unique`(`user_id`);

-- 2026_08_26_134840_create_settings_table
create table `settings` (`id` bigint unsigned not null auto_increment primary key, `key` varchar(100) not null, `value` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `settings` add unique `settings_key_unique`(`key`);

-- Record every migration as applied (batch 1)
INSERT INTO `migrations` (`migration`, `batch`) VALUES
  ('0001_01_01_000000_create_users_table', 1),
  ('0001_01_01_000001_create_cache_table', 1),
  ('0001_01_01_000002_create_jobs_table', 1),
  ('2026_08_25_011344_create_permission_tables', 1),
  ('2026_08_25_044125_create_states_table', 1),
  ('2026_08_25_044126_create_districts_table', 1),
  ('2026_08_25_044127_create_schools_table', 1),
  ('2026_08_25_044128_create_school_profiles_table', 1),
  ('2026_08_25_044129_create_anonymous_identities_table', 1),
  ('2026_08_25_044130_create_parent_profiles_table', 1),
  ('2026_08_25_044131_create_student_profiles_table', 1),
  ('2026_08_25_044132_create_teacher_profiles_table', 1),
  ('2026_08_25_044133_create_school_staff_table', 1),
  ('2026_08_25_044134_create_parent_school_relationships_table', 1),
  ('2026_08_25_044135_create_student_school_relationships_table', 1),
  ('2026_08_25_044136_create_teacher_school_relationships_table', 1),
  ('2026_08_25_044137_create_complaint_categories_table', 1),
  ('2026_08_25_044138_create_complaints_table', 1),
  ('2026_08_25_044139_create_complaint_evidence_table', 1),
  ('2026_08_25_044140_create_complaint_responses_table', 1),
  ('2026_08_25_044141_create_complaint_status_history_table', 1),
  ('2026_08_25_044142_create_complaint_resolutions_table', 1),
  ('2026_08_25_044143_create_school_feedback_table', 1),
  ('2026_08_25_044144_create_school_rating_components_table', 1),
  ('2026_08_25_044145_create_school_quality_scores_table', 1),
  ('2026_08_25_044146_create_audit_logs_table', 1),
  ('2026_08_25_044147_create_identity_access_logs_table', 1),
  ('2026_08_25_044148_create_notifications_table', 1),
  ('2026_08_25_044427_create_officer_jurisdictions_table', 1),
  ('2026_08_26_023118_create_retaliation_reports_table', 1),
  ('2026_08_26_023130_create_teacher_feedback_table', 1),
  ('2026_08_26_023143_create_teacher_effectiveness_scores_table', 1),
  ('2026_08_26_024026_create_teacher_rating_components_table', 1),
  ('2026_08_26_071443_create_invitations_table', 1),
  ('2026_08_26_134827_create_student_academic_records_table', 1),
  ('2026_08_26_134833_create_fraud_flags_table', 1),
  ('2026_08_26_134834_create_appeals_table', 1),
  ('2026_08_26_134838_create_analytics_snapshots_table', 1),
  ('2026_08_26_134839_create_two_factor_authentications_table', 1),
  ('2026_08_26_134840_create_settings_table', 1);

