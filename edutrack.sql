-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Erstellungszeit: 10. Mrz 2026 um 18:48
-- Server-Version: 10.4.32-MariaDB
-- PHP-Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `edutrack`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `attendance`
--

CREATE TABLE `attendance` (
  `id` bigint(20) NOT NULL,
  `teacher_user_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `status` enum('arrived','departed','absent') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `event_time` datetime NOT NULL,
  `worked_minutes` int(11) NOT NULL DEFAULT 0,
  `created_by_user_id` int(11) NOT NULL,
  `validation_status` enum('pending','validated','rejected') NOT NULL DEFAULT 'pending',
  `validated_by_user_id` int(11) DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `attendance`
--

INSERT INTO `attendance` (`id`, `teacher_user_id`, `class_id`, `status`, `reason`, `event_time`, `worked_minutes`, `created_by_user_id`, `validation_status`, `validated_by_user_id`, `validated_at`, `created_at`) VALUES
(1, 3, NULL, 'arrived', NULL, '2026-03-04 03:31:00', 890, 1, 'validated', 1, '2026-03-04 03:32:22', '2026-03-04 02:32:15'),
(2, 3, 1, 'arrived', NULL, '2026-03-04 04:51:00', 160, 13, 'validated', 1, '2026-03-04 05:16:23', '2026-03-04 03:51:42'),
(3, 3, 1, 'departed', NULL, '2026-03-04 05:00:00', 110, 1, 'validated', 1, '2026-03-04 05:16:58', '2026-03-04 04:00:21'),
(4, 10, 2, 'arrived', NULL, '2026-03-04 05:01:00', 0, 1, 'validated', 1, '2026-03-04 21:33:30', '2026-03-04 04:01:29'),
(5, 10, 2, 'departed', NULL, '2026-03-04 21:24:56', 984, 2, 'validated', 1, '2026-03-04 21:33:30', '2026-03-04 20:24:56');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `grade_level` varchar(40) DEFAULT NULL,
  `room_number` varchar(40) DEFAULT NULL,
  `class_master_user_id` int(11) DEFAULT NULL,
  `prefect_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `classes`
--

INSERT INTO `classes` (`id`, `name`, `grade_level`, `room_number`, `class_master_user_id`, `prefect_user_id`) VALUES
(1, 'Form 1A', 'Form 1', 'A1', 10, 13),
(2, 'Form 2B', 'Form 2', 'B2', 3, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `periods`
--

CREATE TABLE `periods` (
  `id` int(11) NOT NULL,
  `label` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `sort_order` int(11) NOT NULL,
  `is_teaching_period` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `periods`
--

INSERT INTO `periods` (`id`, `label`, `start_time`, `end_time`, `sort_order`, `is_teaching_period`) VALUES
(1, 'P1', '03:30:00', '08:50:00', 1, 1),
(2, 'P2', '08:55:00', '09:45:00', 2, 1),
(3, 'P3', '09:50:00', '10:40:00', 3, 1),
(4, 'BREAK', '10:40:00', '11:00:00', 4, 0),
(5, 'P4', '11:00:00', '11:50:00', 5, 1),
(6, 'P5', '21:20:00', '22:35:00', 6, 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `prefect_password_audit`
--

CREATE TABLE `prefect_password_audit` (
  `id` bigint(20) NOT NULL,
  `prefect_user_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `plain_password` varchar(120) NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `prefect_password_audit`
--

INSERT INTO `prefect_password_audit` (`id`, `prefect_user_id`, `class_id`, `email`, `plain_password`, `created_by_user_id`, `created_at`) VALUES
(1, 11, 1, 'linda.ndi.prefect903@edutrack.local', 'qrb5csZr6v@', 1, '2026-03-04 03:16:22'),
(2, 12, 1, 'linda.ndi.prefect352@edutrack.local', 'R5zpuPmxTT@', 1, '2026-03-04 03:16:29'),
(3, 13, 1, 'linda.ndi.prefect869@edutrack.local', 'dxbd4yWfm8$', 1, '2026-03-04 03:16:34');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `school_settings`
--

CREATE TABLE `school_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `school_name` varchar(190) NOT NULL DEFAULT 'EduTrack School',
  `logo_path` varchar(255) DEFAULT NULL,
  `watermark_path` varchar(255) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'XAF',
  `timezone` varchar(60) NOT NULL DEFAULT 'Africa/Douala',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `school_settings`
--

INSERT INTO `school_settings` (`id`, `school_name`, `logo_path`, `watermark_path`, `currency`, `timezone`, `updated_at`) VALUES
(1, 'School Demo', 'uploads/logo_1772592147.png', 'uploads/watermark_1772994039_8685.jpg', 'XAF', 'Africa/Douala', '2026-03-08 18:20:39');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(120) NOT NULL,
  `hod_user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `subjects`
--

INSERT INTO `subjects` (`id`, `code`, `name`, `hod_user_id`, `is_active`) VALUES
(1, 'MATH', 'Mathematics', NULL, 1),
(2, 'ENG', 'English', NULL, 1),
(3, 'PHY', 'Physics', NULL, 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `salary_type` enum('hourly','fixed') NOT NULL DEFAULT 'hourly',
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `fixed_salary` decimal(10,2) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `teachers`
--

INSERT INTO `teachers` (`id`, `user_id`, `salary_type`, `hourly_rate`, `fixed_salary`, `phone`, `active`) VALUES
(1, 3, 'hourly', 1500.00, NULL, NULL, 1),
(3, 10, 'hourly', 1250.00, NULL, '', 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `teacher_assignments`
--

CREATE TABLE `teacher_assignments` (
  `id` int(11) NOT NULL,
  `teacher_user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `hours_per_week` int(11) NOT NULL DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `teacher_assignments`
--

INSERT INTO `teacher_assignments` (`id`, `teacher_user_id`, `subject_id`, `class_id`, `hours_per_week`) VALUES
(1, 3, 1, 1, 3),
(2, 10, 2, 1, 2);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `teacher_availability`
--

CREATE TABLE `teacher_availability` (
  `id` int(11) NOT NULL,
  `teacher_user_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `period_id` int(11) NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `timetable_entries`
--

CREATE TABLE `timetable_entries` (
  `id` bigint(20) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_user_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `period_id` int(11) NOT NULL,
  `source` enum('auto','manual') NOT NULL DEFAULT 'auto',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `room_override` varchar(40) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `timetable_entries`
--

INSERT INTO `timetable_entries` (`id`, `class_id`, `subject_id`, `teacher_user_id`, `day_of_week`, `period_id`, `source`, `is_locked`, `room_override`, `created_at`) VALUES
(4, 1, 1, 3, 1, 1, 'manual', 0, NULL, '2026-03-04 02:30:53'),
(11, 2, 2, 10, 1, 1, 'manual', 0, NULL, '2026-03-04 02:54:58'),
(12, 1, 1, 3, 1, 2, 'auto', 0, NULL, '2026-03-04 03:17:10'),
(13, 1, 1, 3, 1, 3, 'auto', 0, NULL, '2026-03-04 03:17:10'),
(14, 1, 1, 3, 1, 5, 'auto', 0, NULL, '2026-03-04 03:17:10'),
(16, 1, 2, 3, 3, 1, 'manual', 0, NULL, '2026-03-04 03:45:23'),
(17, 2, 2, 10, 3, 1, 'manual', 0, NULL, '2026-03-04 03:45:33'),
(18, 1, 2, 3, 3, 6, 'manual', 0, NULL, '2026-03-04 20:23:34'),
(19, 2, 1, 10, 3, 6, 'manual', 0, NULL, '2026-03-04 20:23:51');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `timetable_signatories`
--

CREATE TABLE `timetable_signatories` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `title` varchar(120) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 10,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `timetable_signatories`
--

INSERT INTO `timetable_signatories` (`id`, `name`, `title`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Ayuk Etang John', 'Prinicipal', 1, 1, '2026-03-04 04:18:23');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `timetable_signature_settings`
--

CREATE TABLE `timetable_signature_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `signature_slots` int(11) NOT NULL DEFAULT 2,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `timetable_signature_settings`
--

INSERT INTO `timetable_signature_settings` (`id`, `signature_slots`, `updated_at`) VALUES
(1, 3, '2026-03-04 04:00:09');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','principal','prefect','teacher') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `is_active`, `created_at`) VALUES
(1, 'Carlson Tantoh', 'admin@edutrack.local', '$2y$10$KxMNlopLaBv2f4/KtjnEU.TCz8Wl1.vbp7Gqju0CrNJmZHSK6CW/.', 'admin', 1, '2026-03-04 02:07:31'),
(2, 'School Principal', 'principal@edutrack.local', '$2y$10$kKULALgKDU4RQ3ZR.v0LiuQqGKeRgRNtccbE4diN.mdAhSEfPxLE6', 'principal', 1, '2026-03-04 02:07:31'),
(3, 'Demo Teacher', 'teacher@edutrack.local', '$2y$10$rQ9RGA7x7tGftJ1W6HN7Y.jCJnXH07ItEgYu.yGXvirMwakfkq8vi', 'teacher', 1, '2026-03-04 02:07:31'),
(10, 'Quiat Reggs', 'carlson25@gmail.com', '$2y$10$B41brTSO4SaTegUVmh6xme1BtKuB57e4lUs2.5rRySfGbU6g60Ln2', 'teacher', 1, '2026-03-04 02:51:56'),
(13, 'Linda Ndi', 'linda.ndi.prefect869@edutrack.local', '$2y$10$CmF.jo/ZZYiOtlLNMx52/u1pyaFYM3dC57p.dg.mA5EVxWQq90TXK', 'prefect', 1, '2026-03-04 03:16:34');

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_user_id` (`teacher_user_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `validated_by_user_id` (`validated_by_user_id`);

--
-- Indizes für die Tabelle `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_master_user_id` (`class_master_user_id`),
  ADD KEY `prefect_user_id` (`prefect_user_id`);

--
-- Indizes für die Tabelle `periods`
--
ALTER TABLE `periods`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `prefect_password_audit`
--
ALTER TABLE `prefect_password_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prefect_user` (`prefect_user_id`),
  ADD KEY `idx_class` (`class_id`);

--
-- Indizes für die Tabelle `school_settings`
--
ALTER TABLE `school_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `hod_user_id` (`hod_user_id`);

--
-- Indizes für die Tabelle `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `teacher_assignments`
--
ALTER TABLE `teacher_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_assignment` (`teacher_user_id`,`subject_id`,`class_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indizes für die Tabelle `teacher_availability`
--
ALTER TABLE `teacher_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_avail` (`teacher_user_id`,`day_of_week`,`period_id`),
  ADD KEY `period_id` (`period_id`);

--
-- Indizes für die Tabelle `timetable_entries`
--
ALTER TABLE `timetable_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_slot` (`class_id`,`day_of_week`,`period_id`),
  ADD KEY `idx_tt_class_day_period` (`class_id`,`day_of_week`,`period_id`),
  ADD KEY `idx_tt_teacher_day_period` (`teacher_user_id`,`day_of_week`,`period_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `period_id` (`period_id`);

--
-- Indizes für die Tabelle `timetable_signatories`
--
ALTER TABLE `timetable_signatories`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `timetable_signature_settings`
--
ALTER TABLE `timetable_signature_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `periods`
--
ALTER TABLE `periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT für Tabelle `prefect_password_audit`
--
ALTER TABLE `prefect_password_audit`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `teacher_assignments`
--
ALTER TABLE `teacher_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `teacher_availability`
--
ALTER TABLE `teacher_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `timetable_entries`
--
ALTER TABLE `timetable_entries`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT für Tabelle `timetable_signatories`
--
ALTER TABLE `timetable_signatories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`teacher_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_4` FOREIGN KEY (`validated_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints der Tabelle `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`class_master_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`prefect_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`hod_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `teacher_assignments`
--
ALTER TABLE `teacher_assignments`
  ADD CONSTRAINT `teacher_assignments_ibfk_1` FOREIGN KEY (`teacher_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `teacher_assignments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `teacher_assignments_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`);

--
-- Constraints der Tabelle `teacher_availability`
--
ALTER TABLE `teacher_availability`
  ADD CONSTRAINT `teacher_availability_ibfk_1` FOREIGN KEY (`teacher_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `teacher_availability_ibfk_2` FOREIGN KEY (`period_id`) REFERENCES `periods` (`id`);

--
-- Constraints der Tabelle `timetable_entries`
--
ALTER TABLE `timetable_entries`
  ADD CONSTRAINT `timetable_entries_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `timetable_entries_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `timetable_entries_ibfk_3` FOREIGN KEY (`teacher_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `timetable_entries_ibfk_4` FOREIGN KEY (`period_id`) REFERENCES `periods` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
