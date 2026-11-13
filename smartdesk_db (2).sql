-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 12, 2026 at 12:41 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smartdesk_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `ticket_number` varchar(30) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(60) NOT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('open','assigned','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `ticket_number`, `requester_id`, `technician_id`, `title`, `description`, `category`, `priority`, `status`, `created_at`, `updated_at`) VALUES
(1, 'SD-E18B3E966897', 1, NULL, 'Computer cannot connect to Wi-Fi', 'The computer cannot access the network even though the router is running.', 'Network', 'high', 'open', '2026-10-19 05:46:19', '2026-10-19 05:46:19'),
(2, 'SD-5E9961E9C5EF', 1, 3, 'jjsanajh', 'dajxhiuhusydui', 'Other', 'critical', 'resolved', '2026-10-19 05:52:16', '2026-11-11 07:30:16'),
(3, 'SD-5930C929D3D5', 1, 5, '.', '.', 'Software', 'low', 'resolved', '2026-10-19 05:55:01', '2026-10-19 06:24:54'),
(4, 'SD-5EBB0AD16A2B', 8, 7, 'CUM HAVE A PROBLEM', 'AUGH', 'Software', 'medium', 'resolved', '2026-11-11 07:47:48', '2026-11-11 07:49:15');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_history`
--

CREATE TABLE `ticket_history` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `remarks` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_history`
--

INSERT INTO `ticket_history` (`id`, `ticket_id`, `changed_by`, `old_status`, `new_status`, `remarks`, `changed_at`) VALUES
(1, 1, 1, NULL, 'open', 'Ticket created', '2026-10-19 05:46:19'),
(2, 2, 1, NULL, 'open', 'Ticket created', '2026-10-19 05:52:16'),
(3, 3, 1, NULL, 'open', 'Ticket created', '2026-10-19 05:55:01'),
(4, 3, 4, 'open', 'assigned', 'Ticket assigned to technician ID 5', '2026-10-19 06:20:27'),
(5, 3, 5, 'assigned', 'in_progress', 'Technician updated ticket status', '2026-10-19 06:24:37'),
(6, 3, 5, 'in_progress', 'resolved', 'Technician updated ticket status', '2026-10-19 06:24:54'),
(7, 2, 4, 'open', 'assigned', 'Ticket assigned to technician ID 3', '2026-10-19 06:53:34'),
(8, 2, 4, 'assigned', 'assigned', 'Ticket assigned to technician ID 5', '2026-10-19 06:53:57'),
(9, 2, 4, 'assigned', 'assigned', 'Ticket assigned to technician ID 3', '2026-10-19 06:54:02'),
(10, 2, 3, 'assigned', 'in_progress', 'Technician updated ticket status', '2026-10-19 06:54:14'),
(11, 2, 4, 'in_progress', 'assigned', 'Ticket assigned to technician ID 5', '2026-10-19 06:54:39'),
(12, 2, 4, 'assigned', 'assigned', 'Ticket assigned to technician ID 3', '2026-10-19 06:58:31'),
(13, 2, 3, 'assigned', 'in_progress', 'Technician updated ticket status', '2026-10-19 06:58:43'),
(14, 2, 4, 'in_progress', 'assigned', 'Ticket assigned to technician ID 3', '2026-11-10 05:43:13'),
(15, 2, 3, 'assigned', 'in_progress', 'Technician updated ticket status', '2026-11-11 07:29:44'),
(16, 2, 3, 'in_progress', 'resolved', 'Technician updated ticket status', '2026-11-11 07:30:16'),
(17, 4, 8, NULL, 'open', 'Ticket created', '2026-11-11 07:47:48'),
(18, 4, 6, 'open', 'assigned', 'Ticket assigned to technician ID 7', '2026-11-11 07:48:11'),
(19, 4, 6, 'assigned', 'assigned', 'Ticket assigned to technician ID 3', '2026-11-11 07:48:18'),
(20, 4, 6, 'assigned', 'assigned', 'Ticket assigned to technician ID 7', '2026-11-11 07:48:23'),
(21, 4, 7, 'assigned', 'in_progress', 'Technician updated ticket status', '2026-11-11 07:48:45'),
(22, 4, 7, 'in_progress', 'resolved', 'Technician updated ticket status', '2026-11-11 07:49:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','technician','requester') NOT NULL DEFAULT 'requester',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `created_at`, `account_status`) VALUES
(1, 'hannah', 'jasvillafranca08@gmail.com', '$2y$10$sva9qh4QSB62jruY28gYEeLB7Xq.dGbqz6yhUoHqoRlYGVgiQDmKa', 'requester', '2026-10-19 05:32:34', 'active'),
(3, 'Jasper', 'pajaresmarie67@gmail.com', '$2y$10$ORbqurrhk1UscW3l7XkbM.QU4FInoLY9nuS3ON/9wlIntLnRWH4FO', 'technician', '2026-10-19 05:59:50', 'active'),
(4, 'Test Administrator', 'mariepajares@gmail.com', '$2y$10$hOJhr4AtT8.CKgQXeGKsB.ThltL1A9eUo.KlHcMQC/s4l2QPGaomm', 'admin', '2026-10-19 06:09:36', 'active'),
(5, 'Test Technician', 'jaspp@gmail.com', '$2y$10$2UZLZxWIBrR/uSwCNQYcE.5IAWJBP.3/sl5bW1kLiLDib8zVyyWlq', 'technician', '2026-10-19 06:10:46', 'active'),
(6, 'ADMIN', 'admin@gmail.com', '$2y$10$RuAzQKLq2EbCPyk5KzAiyO.Nwe8jlOlL/g0h3sIlgki8mKHnWy3J2', 'admin', '2026-11-11 07:41:09', 'active'),
(7, 'TECHNICIAN', 'technician@gmail.com', '$2y$10$V3WdlfTg59c2zqePq44brOKlAKQ/j7FnptwZPtzIqZJWEfFYqkeCi', 'technician', '2026-11-11 07:46:17', 'active'),
(8, 'REQUESTER', 'requester@gmail.com', '$2y$10$UXbbXCR5LLCo9Kzmo66abOUGh5ABOoZHkLUT4/V1NxdFqq/leZHgu', 'requester', '2026-11-11 07:46:53', 'active'),
(9, 'jas', 'jas@gmail.com', '$2y$10$kMzXpE0ganBgmyYnYw5mleyW4tqarnGea3K/N1D/CDnbkMrP83.mG', 'admin', '2026-11-12 11:13:51', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_user` (`user_id`,`is_read`),
  ADD KEY `fk_notification_ticket` (`ticket_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `idx_ticket_status` (`status`),
  ADD KEY `idx_ticket_priority` (`priority`),
  ADD KEY `idx_ticket_requester` (`requester_id`),
  ADD KEY `idx_ticket_technician` (`technician_id`);

--
-- Indexes for table `ticket_history`
--
ALTER TABLE `ticket_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_history_ticket` (`ticket_id`),
  ADD KEY `fk_history_user` (`changed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `email_2` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ticket_history`
--
ALTER TABLE `ticket_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_ticket_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ticket_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_history`
--
ALTER TABLE `ticket_history`
  ADD CONSTRAINT `fk_history_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
