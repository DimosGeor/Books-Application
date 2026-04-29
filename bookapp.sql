-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Εξυπηρετητής: localhost:3306
-- Χρόνος δημιουργίας: 29 Απρ 2026 στις 09:46:02
-- Έκδοση διακομιστή: 8.0.45-0ubuntu0.22.04.1
-- Έκδοση PHP: 8.1.2-1ubuntu2.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Βάση δεδομένων: `bookapp`
--

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `action` enum('submit','unsubmit','edit_selection') COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Άδειασμα δεδομένων του πίνακα `activity_log`
--

INSERT INTO `activity_log` (`id`, `student_id`, `action`, `details`, `ip`, `created_at`) VALUES
(1, 2, 'edit_selection', 'Επιλογή:  | Σύνολο: 162€', '192.168.0.53', '2026-04-29 09:22:32'),
(2, 2, 'submit', 'Υποβολή αίτησης | Σύνολο: 162€', '192.168.0.53', '2026-04-29 09:22:33'),
(3, 2, 'unsubmit', 'Τροποποίηση αίτησης', '192.168.0.53', '2026-04-29 09:39:43'),
(4, 2, 'submit', 'Υποβολή αίτησης | Σύνολο: 162€', '192.168.0.53', '2026-04-29 09:39:58');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `books`
--

CREATE TABLE `books` (
  `id` int NOT NULL,
  `title` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `publisher` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cost` decimal(8,2) NOT NULL,
  `direction` enum('A','B','C') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Άδειασμα δεδομένων του πίνακα `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `publisher`, `cost`, `direction`, `created_at`) VALUES
(47, 'Στατιστική Ανάλυση', 'Κ. Λύτρας', 'Τζιόλα', '44.00', 'B', '2026-04-28 11:01:59'),
(48, 'Πιθανότητες', 'Γ. Μανώλης', 'Σοφία', '39.00', 'B', '2026-04-28 11:01:59'),
(49, 'Μάρκετινγκ — Βασικές Αρχές', 'Π. Αλεξίου', 'Broken Hill', '38.00', 'C', '2026-04-28 11:01:59'),
(51, 'Οργάνωση & Διοίκηση Επιχειρήσεων', 'Ν. Τζούμα', 'Gutenberg', '41.00', 'C', '2026-04-28 11:01:59'),
(53, 'Εισαγωγή στην Πληροφορική', 'Κ. Παπαδόπουλος', 'Κλειδάριθμος', '45.00', 'A', '2026-04-28 11:30:04'),
(54, 'Αλγόριθμοι & Δομές Δεδομένων', 'Θ. Νικολάου', 'Gutenberg', '62.00', 'A', '2026-04-28 11:30:04'),
(55, 'Βάσεις Δεδομένων', 'Α. Σταματίου', 'Κλειδάριθμος', '55.00', 'A', '2026-04-28 11:30:04'),
(56, 'Δίκτυα Υπολογιστών', 'Π. Ρήγας', 'Τζιόλα', '58.00', 'A', '2026-04-28 11:30:04'),
(57, 'Ανάλυση I', 'Δ. Χρήστου', 'Σοφία', '48.00', 'B', '2026-04-28 11:30:04'),
(58, 'Γραμμική Άλγεβρα', 'Σ. Μπένος', 'Gutenberg', '52.00', 'B', '2026-04-28 11:30:04'),
(62, 'Χρηματοοικονομική Διοίκηση', 'Γ. Σπανός', 'Broken Hill', '67.00', 'C', '2026-04-28 11:30:04'),
(64, 'Λογιστική', 'Κ. Δεληγιάννης', 'Κλειδάριθμος', '49.00', 'C', '2026-04-28 11:30:04');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `selections`
--

CREATE TABLE `selections` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `book_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Άδειασμα δεδομένων του πίνακα `selections`
--

INSERT INTO `selections` (`id`, `student_id`, `book_id`, `created_at`) VALUES
(53, 13, 47, '2026-04-28 12:18:11'),
(54, 13, 48, '2026-04-28 12:18:11'),
(55, 13, 57, '2026-04-28 12:18:11'),
(56, 13, 58, '2026-04-28 12:18:11'),
(73, 14, 53, '2026-04-28 12:20:42'),
(74, 14, 54, '2026-04-28 12:20:42'),
(75, 14, 55, '2026-04-28 12:20:42'),
(76, 14, 56, '2026-04-28 12:20:42'),
(96, 2, 53, '2026-04-29 09:22:32'),
(97, 2, 54, '2026-04-29 09:22:32'),
(98, 2, 55, '2026-04-29 09:22:32');

-- --------------------------------------------------------

--
-- Δομή πίνακα για τον πίνακα `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','student') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `direction` enum('A','B','C') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Άδειασμα δεδομένων του πίνακα `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `direction`, `submitted`, `created_at`) VALUES
(1, 'Administrator', 'admin', '$2y$10$Z5jGfZn7oCMzN4h6L3LcSuGHrI9geNQZKFNIUrwblVcPFkX.X8zEq', 'admin', NULL, 0, '2026-04-28 06:51:52'),
(2, 'Μαρία Παπαδοπούλου', 'student1', '$2y$10$f6taSGUoaXX.M27eHtDRnOMht9uY4u8M7jlTtzLwZ22tHXs2pv8f.', 'student', 'A', 1, '2026-04-28 06:51:52'),
(4, 'Ελένη Δημητρίου', 'student3', '$2y$12$cKmGMoQtrkSHWPkI5UaRxOIPP.mJBW3giyxcXQ7sYLKJJG/Mq8I26', 'student', 'C', 0, '2026-04-28 06:51:52'),
(6, 'student5', 'student5', '$2y$10$AurLAbiMod9MKyUjlAzGC.Lx.CfBLmD2ZhSPHiN6t6xcoTWR3uCSu', 'student', 'C', 0, '2026-04-28 10:30:45'),
(11, 'ΤΕΣΤ', 'TEST', '$2y$10$olEbvOAOOnLh11.ayKcbg.Blm1bHd.RmeDKx0WE9YPFseR.zpEb92', 'student', 'A', 0, '2026-04-28 12:16:44'),
(12, 'test2', 'tset2', '$2y$10$FEdTDcSOYeBZ5Eb1IdID4Or1zQ4DBK42TX30zP2g7Q7Ns1My0aO4C', 'student', 'B', 0, '2026-04-28 12:16:44'),
(13, 'test', 'test7', '$2y$10$DQZQyDunAOJkqp3N7t2IUeZnAqaR//Rtr5fhgRiop8cO3a.Qv68Z2', 'student', 'B', 1, '2026-04-28 12:18:02'),
(14, 'student', 'test3', '$2y$10$JYs8EVHB39eSutCBmZJfG.EJMDXDSRfC6StP0ybc7RWOlXbz0Ima2', 'student', 'B', 0, '2026-04-28 12:20:28');

--
-- Ευρετήρια για άχρηστους πίνακες
--

--
-- Ευρετήρια για πίνακα `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Ευρετήρια για πίνακα `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Ευρετήρια για πίνακα `selections`
--
ALTER TABLE `selections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_book` (`student_id`,`book_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Ευρετήρια για πίνακα `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT για άχρηστους πίνακες
--

--
-- AUTO_INCREMENT για πίνακα `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT για πίνακα `books`
--
ALTER TABLE `books`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT για πίνακα `selections`
--
ALTER TABLE `selections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT για πίνακα `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Περιορισμοί για άχρηστους πίνακες
--

--
-- Περιορισμοί για πίνακα `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Περιορισμοί για πίνακα `selections`
--
ALTER TABLE `selections`
  ADD CONSTRAINT `selections_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `selections_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
