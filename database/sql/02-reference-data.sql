-- =====================================================================
-- EDU — 02-reference-data.sql                         PostgreSQL 13+
-- Reference data every environment needs (from the app's own seeders @
-- 5d06b5e): roles, permissions + role grants (spatie), states, districts,
-- complaint categories, school and teacher rating components.
-- Import AFTER 01-schema.sql. ON CONFLICT DO NOTHING, so re-importing is
-- harmless. Creates NO user accounts — see 03.
-- =====================================================================

--
-- PostgreSQL database dump
--



SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: complaint_categories; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.complaint_categories (id, name, slug, description, is_child_safety, is_active, created_at, updated_at) VALUES
	(1, 'Teaching', 'teaching', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(2, 'Teacher Behaviour', 'teacher-behaviour', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(3, 'Fees', 'fees', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(4, 'Books', 'books', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(5, 'Uniform', 'uniform', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(6, 'Stationery', 'stationery', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(7, 'Transport', 'transport', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(8, 'Infrastructure', 'infrastructure', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(9, 'Safety', 'safety', NULL, true, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(10, 'Bullying', 'bullying', NULL, true, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(11, 'Harassment', 'harassment', NULL, true, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(12, 'Discrimination', 'discrimination', NULL, true, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(13, 'Food', 'food', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(14, 'Hygiene', 'hygiene', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(15, 'Sports', 'sports', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(16, 'Counselling', 'counselling', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(17, 'Special Needs', 'special-needs', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(18, 'Attendance', 'attendance', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(19, 'Communication', 'communication', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(20, 'Management', 'management', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(21, 'Career Guidance', 'career-guidance', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(22, 'Other', 'other', NULL, false, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35') ON CONFLICT DO NOTHING;


--
-- Data for Name: states; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.states (id, name, code, created_at, updated_at) VALUES
	(1, 'Maharashtra', 'MH', '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(2, 'Karnataka', 'KA', '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(3, 'Delhi', 'DL', '2026-10-09 21:10:35', '2026-10-09 21:10:35') ON CONFLICT DO NOTHING;


--
-- Data for Name: districts; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.districts (id, state_id, name, code, created_at, updated_at) VALUES
	(1, 1, 'Pune', 'PUN', '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(2, 1, 'Mumbai', 'MUM', '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(3, 2, 'Bengaluru Urban', 'BLR', '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(4, 2, 'Mysuru', 'MYS', '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(5, 3, 'New Delhi', 'NDL', '2026-10-09 21:10:35', '2026-10-09 21:10:35') ON CONFLICT DO NOTHING;


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.permissions (id, name, guard_name, created_at, updated_at) VALUES
	(1, 'submit-complaint', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(2, 'submit-feedback', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(3, 'view-own-complaints', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(4, 'respond-to-complaint', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(5, 'manage-school-profile', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(6, 'review-district-complaints', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(7, 'review-state-complaints', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(8, 'access-protected-identity', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(9, 'view-audit-logs', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(10, 'manage-admin-settings', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(11, 'view-national-analytics', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34') ON CONFLICT DO NOTHING;


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.roles (id, name, guard_name, created_at, updated_at) VALUES
	(1, 'public', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(2, 'parent', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(3, 'student', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(4, 'teacher', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(5, 'school_admin', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(6, 'district_officer', 'web', '2026-10-09 21:10:34', '2026-10-09 21:10:34'),
	(7, 'state_officer', 'web', '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(8, 'national_admin', 'web', '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(9, 'researcher', 'web', '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(10, 'system_admin', 'web', '2026-10-09 21:10:35', '2026-10-09 21:10:35') ON CONFLICT DO NOTHING;


--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.role_has_permissions (permission_id, role_id) VALUES
	(1, 2),
	(2, 2),
	(3, 2),
	(1, 3),
	(2, 3),
	(3, 3),
	(3, 4),
	(4, 5),
	(5, 5),
	(6, 6),
	(8, 6),
	(7, 7),
	(8, 7),
	(11, 7),
	(7, 8),
	(8, 8),
	(9, 8),
	(11, 8),
	(11, 9),
	(1, 10),
	(2, 10),
	(3, 10),
	(4, 10),
	(5, 10),
	(6, 10),
	(7, 10),
	(8, 10),
	(9, 10),
	(10, 10),
	(11, 10) ON CONFLICT DO NOTHING;


--
-- Data for Name: school_rating_components; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.school_rating_components (id, key, label, weight, is_active, created_at, updated_at) VALUES
	(1, 'teaching_learning', 'Teaching & Learning', 10.00, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(2, 'teacher_quality', 'Teacher Quality', 10.00, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(3, 'student_development', 'Student Development', 10.00, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(4, 'safety_wellbeing', 'Safety & Wellbeing', 10.00, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(5, 'parent_experience', 'Parent Experience', 10.00, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(6, 'transparency', 'Transparency', 10.00, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(7, 'infrastructure', 'Infrastructure', 10.00, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(8, 'sports_activities', 'Sports & Activities', 10.00, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(9, 'career_guidance', 'Career Guidance', 10.00, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(10, 'complaint_resolution', 'Complaint Resolution', 10.00, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35') ON CONFLICT DO NOTHING;


--
-- Data for Name: teacher_rating_components; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.teacher_rating_components (id, key, label, weight, is_active, created_at, updated_at) VALUES
	(1, 'subject_knowledge', 'Subject Knowledge', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(2, 'explanation', 'Explanation', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(3, 'communication', 'Communication', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(4, 'engagement', 'Engagement', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(5, 'fairness', 'Fairness', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(6, 'classroom_management', 'Classroom Management', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(7, 'individual_attention', 'Individual Attention', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(8, 'practical_learning', 'Practical Learning', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(9, 'feedback', 'Feedback', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(10, 'homework', 'Homework', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(11, 'student_support', 'Student Support', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35'),
	(12, 'punctuality', 'Punctuality', 8.33, true, '2026-10-09 21:10:35', '2026-10-09 21:10:35') ON CONFLICT DO NOTHING;


--
-- Name: complaint_categories_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.complaint_categories_id_seq', 22, true);


--
-- Name: districts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.districts_id_seq', 5, true);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permissions_id_seq', 11, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 10, true);


--
-- Name: school_rating_components_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.school_rating_components_id_seq', 10, true);


--
-- Name: states_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.states_id_seq', 3, true);


--
-- Name: teacher_rating_components_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.teacher_rating_components_id_seq', 12, true);


--
-- PostgreSQL database dump complete
--


