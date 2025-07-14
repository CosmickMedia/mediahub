-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 14, 2025 at 01:25 AM
-- Server version: 11.4.7-MariaDB-ubu2404
-- PHP Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cmuploader`
--

-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

CREATE TABLE `articles` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `excerpt` text DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected') DEFAULT 'submitted',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `ip` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `ip` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `name`, `value`) VALUES
(1, 'drive_base_folder', '1QFpGOZTtzCHYtobXvxuAw5Z-SnbSJGVu'),
(2, 'notification_email', 'carley@cosmickmedia.com, kim@cosmickmedia.com, cassandra@cosmickmedia.com, jennifer@cosmickmedia.com, crystal@cosmickmedia.com'),
(15, 'email_from_name', 'Cosmick Media'),
(16, 'email_from_address', 'noreply@cosmickmedia.com'),
(17, 'admin_notification_subject', 'New uploads from {store_name}'),
(18, 'store_notification_subject', 'Content Submission Confirmation - Cosmick Media'),
(19, 'store_message_subject', 'New message from Cosmick Media'),
(20, 'admin_article_notification_subject', 'New article submission from {store_name}'),
(21, 'store_article_notification_subject', 'Article Submission Confirmation - Cosmick Media'),
(22, 'article_approval_subject', 'Article Status Update - Cosmick Media'),
(23, 'max_article_length', '50000');

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `pin` varchar(50) NOT NULL,
  `admin_email` varchar(255) DEFAULT NULL,
  `drive_folder` varchar(255) DEFAULT NULL,
  `hootsuite_token` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `marketing_report_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `name`, `pin`, `admin_email`, `drive_folder`, `hootsuite_token`, `first_name`, `last_name`, `phone`, `address`, `marketing_report_url`) VALUES
(1, 'test', '1111', 'test@none.com', '', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'testing', '1234', 'test@none.com', '16FMaL4Lv0V6_ZVxBQRpg-3GaUyfeu0G3', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Petland Cosmick', '2547', 'cosmicktechnologies@gmail.com', '1srY5v90SaXNgWsl56K_e9F0YaSN43Hc-', '', 'Carley', 'Kuehner', '', '1147 Jacobsburg Road, Wind Gap, PA 18091', NULL),
(4, 'Petland Phoenix', '2345', 'kim@cosmickmedia.com', '1VvZT3W4_ADzo1nRXPg98n8wOROIov9lC', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `store_messages`
--

CREATE TABLE `store_messages` (
  `id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_reply` tinyint(1) DEFAULT 0,
  `upload_id` int(11) DEFAULT NULL,
  `article_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_messages`
--

INSERT INTO `store_messages` (`id`, `store_id`, `message`, `is_reply`, `upload_id`, `article_id`, `created_at`) VALUES
(1, 2, 'Dont forget to create your content.', 0, NULL, NULL, '2025-07-03 04:26:20'),
(2, 4, 'Where do these messages go?', 0, NULL, NULL, '2025-07-03 13:46:08'),
(3, 4, 'Please don\'t forget to upload new social content!', 0, NULL, NULL, '2025-07-03 13:49:44'),
(4, 3, 'Hey there, it\'s Cassandra. Great to see you here! Don\'t forget to upload your content!', 0, NULL, NULL, '2025-07-03 13:52:29'),
(5, 4, 'Please don\'t forget to upload new social content!', 0, NULL, NULL, '2025-07-03 18:43:53');

-- --------------------------------------------------------

--
-- Table structure for table `store_users`
--

CREATE TABLE `store_users` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_users`
--

INSERT INTO `store_users` (`id`, `store_id`, `email`, `first_name`, `last_name`, `created_at`) VALUES
(1, 3, 'ckuehner@cosmickmedia.com', 'dfgfg', 'dfgdfgdf', '2025-07-13 20:38:47');

-- --------------------------------------------------------

--
-- Table structure for table `uploads`
--

CREATE TABLE `uploads` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `custom_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `ip` varchar(45) NOT NULL,
  `mime` varchar(100) NOT NULL,
  `size` int(11) NOT NULL,
  `drive_id` varchar(255) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uploads`
--

INSERT INTO `uploads` (`id`, `store_id`, `filename`, `description`, `custom_message`, `created_at`, `ip`, `mime`, `size`, `drive_id`, `status_id`) VALUES
(1, 1, 'cosmick-media-dark (2).png', '', NULL, '2025-07-02 16:25:14', '24.115.186.198', 'image/png', 1799025, NULL, NULL),
(2, 1, 'Available Kittens Banner (1).png', 'Kittne banner', NULL, '2025-07-02 17:36:30', '24.115.186.198', 'image/png', 3289377, NULL, NULL),
(3, 2, '20250702_2009_Robot Overlooks Nerd_remix_01jz6st9hrf299c8r09pedecrb.png', 'Robot', NULL, '2025-07-03 03:51:16', '24.115.186.198', 'image/png', 1507264, '1UOUzJG8Dwg0hZOD4xmpOzX_IrB89Q8i1', NULL),
(4, 2, '20250702_2009_Robot Overlooks Nerd_remix_01jz6st9hte9a8qkvs7kcm7wsz.png', 'Hello', 'THis is my test message that goes along with my submissions.', '2025-07-03 04:19:51', '24.115.186.198', 'image/png', 1253574, '1L8v6aeWXtIpozxUCGMrApKJZEeuU5Rvx', NULL),
(5, 2, 'Add-Health-Routines-‹-Petland-Murfreesboro-Tennessee-—-WordPress-03-11-2025_09_37_PM.png', 'hero', 'this is a message that goes along with these uploads.', '2025-07-03 04:49:33', '24.115.186.198', 'image/png', 956162, '1Ri4VBqM2t_siEri8baA4197K9Ir434yG', NULL),
(6, 3, 'AdobeStock_65404043.jpeg', 'adobe stock', 'THis is a my test instructions', '2025-07-03 05:00:52', '24.115.186.198', 'image/jpeg', 5816833, '1AFVV6s1WCRdyEjvl7HSFh0nmJVdsEQIz', NULL),
(7, 3, 'kanguro.jpg', '', '', '2025-07-03 05:33:44', '24.115.186.198', 'image/jpeg', 270962, '1pv8fpdk65kxw8HDWtxu0tTORRHCRLEZf', NULL),
(9, 3, 'image.jpg', '', 'My luggage lol', '2025-07-03 13:31:22', '12.75.117.33', 'image/jpeg', 2269028, '1jRUZ8rF6MiidUfoV6IW2YwCJD-U4HQ8t', NULL),
(10, 3, 'image.jpg', '', 'My luggage lol', '2025-07-03 13:31:23', '12.75.117.33', 'image/jpeg', 2269028, '1Sg00LLqOqUoK_4-TcwkihFxL3VzxIkeo', NULL),
(11, 3, '77324235760__9FF03D95-1AC9-40D3-9A20-7077AA1D19CC.MOV', '', '', '2025-07-03 13:33:08', '12.75.117.33', 'video/quicktime', 1170292, '124i5vRRavjAC2E5gOsRxhmxaY4xVALtG', NULL),
(12, 3, '77324235760__9FF03D95-1AC9-40D3-9A20-7077AA1D19CC.MOV', '', '', '2025-07-03 13:33:09', '12.75.117.33', 'video/quicktime', 1170292, '1YyZENtHBd2bF21oPHYHlBfLyvdT0ZfsM', NULL),
(13, 4, 'image.jpg', '', '', '2025-07-03 13:37:43', '12.75.117.33', 'image/jpeg', 2708753, '1y24HQ8qkHY5axp0vg2Jy5TS1KgzITbEp', NULL),
(14, 4, 'image.jpg', '', '', '2025-07-03 13:37:45', '12.75.117.33', 'image/jpeg', 2334883, '1Yi6uFH02uexryKiuNhleSAyRHaVSmshz', NULL),
(15, 4, 'image.jpg', '', '', '2025-07-03 13:37:46', '12.75.117.33', 'image/jpeg', 2334883, '1AKYq3TBWjXJ8YGGapvvKPGbZPckY_N2C', NULL),
(16, 4, '77324285643__BB0B555A-5505-47AB-941C-4B86C2969CB6.MOV', '', 'Goldfish chaos', '2025-07-03 13:41:21', '12.75.117.33', 'video/quicktime', 358185, '1Y9W6d-dUdBO1dxpCGEk5ktwUYoQAnJAq', NULL),
(17, 4, '77324285643__BB0B555A-5505-47AB-941C-4B86C2969CB6.MOV', '', 'Goldfish chaos', '2025-07-03 13:41:22', '12.75.117.33', 'video/quicktime', 358185, '1NkKIeuKQcCcsJmTsdZWRstdUAjRJygnw', NULL),
(18, 3, 'pet-safety-4th-of-july (2).jpg', '', '', '2025-07-03 13:41:49', '64.121.214.159', 'image/jpeg', 262914, '19EqS0ojQA4tYGpngXSywsesxpxdBnOIz', NULL),
(19, 3, 'pet-safety-4th-of-july (2).jpg', '', '', '2025-07-03 13:41:50', '64.121.214.159', 'image/jpeg', 262914, '1XXAdH_7PlADU4bwwS6QziAGbGTODpzDS', NULL),
(20, 3, 'image.jpg', '', 'Desk', '2025-07-03 13:44:02', '64.121.214.159', 'image/jpeg', 2636725, '1c1C2oDcQyLa2ycocqE7ddtMagdmL2Aaw', NULL),
(21, 3, 'image.jpg', '', 'Desk', '2025-07-03 13:44:03', '64.121.214.159', 'image/jpeg', 2636725, '1I-9Rb8_NtknSGUNNJMobF_MydPJxrw1d', NULL),
(22, 3, '77324318489__37964C8D-2C9C-4278-9186-51383A935488.MOV', '', 'My front window ', '2025-07-03 13:46:37', '131.106.93.49', 'video/quicktime', 201723, '1Mq5zlgm1aUKb2IWQC0dm9a6WKRjPnp_K', NULL),
(23, 4, '77324285643__BB0B555A-5505-47AB-941C-4B86C2969CB6.MOV', '', 'Goldfish chaos', '2025-07-03 13:49:55', '12.75.117.33', 'video/quicktime', 358185, '1DeL3AN_oV3FcP0f7sBqp-G10Po_yxrIY', 8),
(24, 4, '77324285643__BB0B555A-5505-47AB-941C-4B86C2969CB6.MOV', '', 'Goldfish chaos', '2025-07-03 13:49:56', '12.75.117.33', 'video/quicktime', 358185, '1qscNWv-cFDHh_xpeN9lEUlmk9FdB2ZZh', 9),
(25, 4, 'IMG_6074.jpeg', '', 'Adding from my photo library ', '2025-07-03 21:11:48', '108.147.173.95', 'image/jpeg', 4163896, '1lTyd6YuaJZO6u63MgdH_clxfnubk1oYE', 1);

-- --------------------------------------------------------

--
-- Table structure for table `upload_statuses`
--

CREATE TABLE `upload_statuses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `upload_statuses`
--

INSERT INTO `upload_statuses` (`id`, `name`, `color`) VALUES
(1, 'Reviewed', '#198754'),
(2, 'Pending Submission', '#ffc107'),
(3, 'Scheduled', '#0dcaf0'),
(4, 'Reviewed', '#198754'),
(5, 'Pending Submission', '#ffc107'),
(6, 'Scheduled', '#0dcaf0'),
(7, 'Reviewed', '#198754'),
(8, 'Pending Submission', '#ffc107'),
(9, 'Scheduled', '#0dcaf0'),
(10, 'Reviewed', '#198754'),
(11, 'Pending Submission', '#ffc107'),
(12, 'Scheduled', '#0dcaf0');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `first_name`, `last_name`, `email`, `created_at`) VALUES
(1, 'admin', '$2y$10$aIKmnAZxd/D5WdCHFMmto.tMsL3os10L8yUC5W4XMSdeKee/8vGpi', 'Carley', 'Kuehner', 'carley@cosmickmedia.com', '2025-07-13 20:14:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store_id` (`store_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pin` (`pin`);

--
-- Indexes for table `store_messages`
--
ALTER TABLE `store_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store_id` (`store_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_upload_id` (`upload_id`),
  ADD KEY `fk_article_id` (`article_id`);

--
-- Indexes for table `store_users`
--
ALTER TABLE `store_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_email_unique` (`store_id`,`email`);

--
-- Indexes for table `uploads`
--
ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `fk_status_id` (`status_id`);

--
-- Indexes for table `upload_statuses`
--
ALTER TABLE `upload_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `store_messages`
--
ALTER TABLE `store_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `store_users`
--
ALTER TABLE `store_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `upload_statuses`
--
ALTER TABLE `upload_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_messages`
--
ALTER TABLE `store_messages`
  ADD CONSTRAINT `fk_article_id` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_upload_id` FOREIGN KEY (`upload_id`) REFERENCES `uploads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `store_messages_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_users`
--
ALTER TABLE `store_users`
  ADD CONSTRAINT `store_users_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `uploads`
--
ALTER TABLE `uploads`
  ADD CONSTRAINT `fk_status_id` FOREIGN KEY (`status_id`) REFERENCES `upload_statuses` (`id`),
  ADD CONSTRAINT `uploads_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
