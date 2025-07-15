-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 15, 2025 at 03:14 AM
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

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `store_id`, `action`, `message`, `created_at`, `ip`) VALUES
(1, NULL, 'dripley_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 20:27:51', '66.152.148.22'),
(2, NULL, 'dripley_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 20:30:04', '66.152.148.22'),
(3, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 21:10:42', '66.152.148.22'),
(4, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 21:19:17', '66.152.148.22'),
(5, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 21:23:31', '66.152.148.22'),
(6, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 21:25:11', '66.152.148.22'),
(7, NULL, 'groundhogg_test', 'GET cosmickmedia.com/wp-json/gh/v4/ping HTTP 301 Response: <html>\r\n<head><title>301 Moved Permanently</title></head>\r\n<body>\r\n<center><h1>301 Moved Permanently</h1></center>\r\n<hr><center>nginx</center>\r\n</body>\r\n</html>\r\n', '2025-07-14 21:31:31', '66.152.148.22'),
(8, NULL, 'groundhogg_test', 'GET www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 301 Response: <html>\r\n<head><title>301 Moved Permanently</title></head>\r\n<body>\r\n<center><h1>301 Moved Permanently</h1></center>\r\n<hr><center>nginx</center>\r\n</body>\r\n</html>\r\n', '2025-07-14 21:31:47', '66.152.148.22'),
(9, NULL, 'groundhogg_test', 'GET www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 301 Response: <html>\r\n<head><title>301 Moved Permanently</title></head>\r\n<body>\r\n<center><h1>301 Moved Permanently</h1></center>\r\n<hr><center>nginx</center>\r\n</body>\r\n</html>\r\n', '2025-07-14 21:31:59', '66.152.148.22'),
(10, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 21:32:06', '66.152.148.22'),
(11, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 21:39:57', '66.152.148.22'),
(12, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 21:48:21', '66.152.148.22'),
(13, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 22:28:47', '66.152.148.22'),
(14, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 22:28:57', '66.152.148.22'),
(15, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 22:29:32', '66.152.148.22'),
(16, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 22:43:52', '66.152.148.22'),
(17, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 22:44:02', '66.152.148.22'),
(18, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 22:54:33', '66.152.148.22'),
(19, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 22:55:50', '66.152.148.22'),
(20, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/ping HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 22:56:01', '66.152.148.22'),
(21, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:11:07', '66.152.148.22'),
(22, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:11:10', '66.152.148.22'),
(23, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:11:17', '66.152.148.22'),
(24, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:12:49', '66.152.148.22'),
(25, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:13:18', '66.152.148.22'),
(26, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:13:19', '66.152.148.22'),
(27, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:13:19', '66.152.148.22'),
(28, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:13:19', '66.152.148.22'),
(29, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:13:20', '66.152.148.22'),
(30, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:13:20', '66.152.148.22'),
(31, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:13:20', '66.152.148.22'),
(32, 1, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:13:21', '66.152.148.22'),
(33, 2, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:13:21', '66.152.148.22'),
(34, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:14:19', '66.152.148.22'),
(35, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:14:36', '66.152.148.22'),
(36, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:14:51', '66.152.148.22'),
(37, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:17:43', '66.152.148.22'),
(38, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/wp-json/gh/v4/contacts?limit=1 HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 23:18:10', '66.152.148.22'),
(39, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v3/wp-json/gh/v4/contacts?limit=1 HTTP 404 Response: {\"code\":\"rest_no_route\",\"message\":\"No route was found matching the URL and request method.\",\"data\":{\"status\":404}}', '2025-07-14 23:18:31', '66.152.148.22'),
(40, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:18:50', '66.152.148.22'),
(41, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:20:18', '66.152.148.22'),
(42, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:21:27', '66.152.148.22'),
(43, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:21:45', '66.152.148.22'),
(44, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:25:50', '66.152.148.22'),
(45, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:26:07', '66.152.148.22'),
(46, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:41:16', '66.152.148.22'),
(47, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:41:57', '66.152.148.22'),
(48, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:42:25', '66.152.148.22'),
(49, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:43:50', '66.152.148.22'),
(50, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:43:54', '66.152.148.22'),
(51, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:44:03', '66.152.148.22'),
(52, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:44:29', '66.152.148.22'),
(53, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:46:19', '66.152.148.22'),
(54, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:46:23', '66.152.148.22'),
(55, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:46:52', '66.152.148.22'),
(56, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:46:54', '66.152.148.22'),
(57, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:47:07', '66.152.148.22'),
(58, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:48:14', '66.152.148.22'),
(59, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:48:18', '66.152.148.22'),
(60, NULL, 'groundhogg_test', 'GET https://www.cosmickmedia.com/wp-json/gh/v4/contacts?limit=1 HTTP 401 Response: {\"code\":\"rest_forbidden\",\"message\":\"Sorry, you are not allowed to do that.\",\"data\":{\"status\":401}}', '2025-07-14 23:48:38', '66.152.148.22'),
(61, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":2,\"data\":{\"email\":\"cosmicktechnologies@gmail.com\",\"first_name\":\"Carley\",\"last_name\":\"Kuehner\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:47\",\"date_optin_status_changed\":\"2025-07-14 23:48:47\",\"ID\":2,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/fe9c34611d9c5a6429b563d89f6b6539e897995e2c9b3193b0349b658e34f1d7?s=300&d=mm&r=g\",\"full_name\":\"Carley Kuehner\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=2&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-14 23:48:47', '66.152.148.22'),
(62, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":3,\"data\":{\"email\":\"ckuehner@cosmickmedia.com\",\"first_name\":\"Tatiana\",\"last_name\":\"Marchenko\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:48\",\"date_optin_status_changed\":\"2025-07-14 23:48:48\",\"ID\":3,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/175e7ff3ce9a93bd275a656805858335bb8e945a1f1b87b824178d013c866fcf?s=300&d=mm&r=g\",\"full_name\":\"Tatiana Marchenko\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=3&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-14 23:48:48', '66.152.148.22'),
(63, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":4,\"data\":{\"email\":\"crystal@cosmickmedia.com\",\"first_name\":\"Crystal\",\"last_name\":\"Jones\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:48\",\"date_optin_status_changed\":\"2025-07-14 23:48:48\",\"ID\":4,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/460b912c9b359869535b1a11fc221c784ab77c357dcb9d3e106567764a8fd1a3?s=300&d=mm&r=g\",\"full_name\":\"Crystal Jones\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=4&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-14 23:48:48', '66.152.148.22'),
(64, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":5,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"Kim\",\"last_name\":\"Frassinelli\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:48\",\"date_optin_status_changed\":\"2025-07-14 23:48:48\",\"ID\":5,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"Kim Frassinelli\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=5&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-14 23:48:48', '66.152.148.22'),
(65, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":5,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:48\",\"date_optin_status_changed\":\"2025-07-14 23:48:48\",\"ID\":5,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=5&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-14 23:48:49', '66.152.148.22'),
(66, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":6,\"data\":{\"email\":\"kaykaykuehner@gmail.com\",\"first_name\":\"Kayley\",\"last_name\":\"Kuehner\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:49\",\"date_optin_status_changed\":\"2025-07-14 23:48:49\",\"ID\":6,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/f33376adc266ae843f9b791576c2d27bc868fcd1d7098ff3146bdd54a69de194?s=300&d=mm&r=g\",\"full_name\":\"Kayley Kuehner\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=6&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-14 23:48:49', '66.152.148.22'),
(67, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":5,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"Kim\",\"last_name\":\"Frassinelli\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:48\",\"date_optin_status_changed\":\"2025-07-14 23:48:48\",\"ID\":5,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"Kim Frassinelli\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=5&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"2 seconds\"}},\"status\":\"success\"}', '2025-07-14 23:48:50', '66.152.148.22'),
(68, 1, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":7,\"data\":{\"email\":\"test@none.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:50\",\"date_optin_status_changed\":\"2025-07-14 23:48:50\",\"ID\":7,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/dfdf488c59c7ec78e5f87ce24d7a3d7e3d0f7a86c7b99a2170e6531b52c428c6?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=7&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-14 23:48:50', '66.152.148.22'),
(69, 2, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":7,\"data\":{\"email\":\"test@none.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:50\",\"date_optin_status_changed\":\"2025-07-14 23:48:50\",\"ID\":7,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/dfdf488c59c7ec78e5f87ce24d7a3d7e3d0f7a86c7b99a2170e6531b52c428c6?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=7&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-14 23:48:50', '66.152.148.22'),
(70, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":8,\"data\":{\"email\":\"jim@yalley.com\",\"first_name\":\"Jim\",\"last_name\":\"Talley\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:50:35\",\"date_optin_status_changed\":\"2025-07-14 23:50:35\",\"ID\":8,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/1ee8043d679b0e30875d4a01aeeb9ff92f2afacb6a48606c88e61308479d4191?s=300&d=mm&r=g\",\"full_name\":\"Jim Talley\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=8&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-14 23:50:35', '66.152.148.22'),
(71, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":9,\"data\":{\"email\":\"sdfdf@none.com\",\"first_name\":\"Cosmick Media Inc.\",\"last_name\":\"sdfsfdsdf\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:55:10\",\"date_optin_status_changed\":\"2025-07-14 23:55:10\",\"ID\":9,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/21ee9b03f27fefcfd5012191423e8e8522abb7ba347b165e9c541a4e6cf9ed66?s=300&d=mm&r=g\",\"full_name\":\"Cosmick Media Inc. Sdfsfdsdf\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=9&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-14 23:55:10', '66.152.148.22'),
(72, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":2,\"data\":{\"email\":\"cosmicktechnologies@gmail.com\",\"first_name\":\"Carley\",\"last_name\":\"Kuehner\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:47\",\"date_optin_status_changed\":\"2025-07-14 23:48:47\",\"ID\":2,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/fe9c34611d9c5a6429b563d89f6b6539e897995e2c9b3193b0349b658e34f1d7?s=300&d=mm&r=g\",\"full_name\":\"Carley Kuehner\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=2&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"24 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:52', '66.152.148.22'),
(73, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":3,\"data\":{\"email\":\"ckuehner@cosmickmedia.com\",\"first_name\":\"Tatiana\",\"last_name\":\"Marchenko\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:48\",\"date_optin_status_changed\":\"2025-07-14 23:48:48\",\"ID\":3,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/175e7ff3ce9a93bd275a656805858335bb8e945a1f1b87b824178d013c866fcf?s=300&d=mm&r=g\",\"full_name\":\"Tatiana Marchenko\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=3&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"24 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:52', '66.152.148.22'),
(74, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":4,\"data\":{\"email\":\"crystal@cosmickmedia.com\",\"first_name\":\"Crystal\",\"last_name\":\"Jones\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:48\",\"date_optin_status_changed\":\"2025-07-14 23:48:48\",\"ID\":4,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/460b912c9b359869535b1a11fc221c784ab77c357dcb9d3e106567764a8fd1a3?s=300&d=mm&r=g\",\"full_name\":\"Crystal Jones\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=4&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"24 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:53', '66.152.148.22'),
(75, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":8,\"data\":{\"email\":\"jim@yalley.com\",\"first_name\":\"Jim\",\"last_name\":\"Talley\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:50:35\",\"date_optin_status_changed\":\"2025-07-14 23:50:35\",\"ID\":8,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/1ee8043d679b0e30875d4a01aeeb9ff92f2afacb6a48606c88e61308479d4191?s=300&d=mm&r=g\",\"full_name\":\"Jim Talley\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=8&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"22 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:53', '66.152.148.22'),
(76, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":5,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"Kim\",\"last_name\":\"Frassinelli\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:48\",\"date_optin_status_changed\":\"2025-07-14 23:48:48\",\"ID\":5,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"Kim Frassinelli\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=5&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"24 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:53', '66.152.148.22'),
(77, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":9,\"data\":{\"email\":\"sdfdf@none.com\",\"first_name\":\"Cosmick Media Inc.\",\"last_name\":\"sdfsfdsdf\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:55:10\",\"date_optin_status_changed\":\"2025-07-14 23:55:10\",\"ID\":9,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/21ee9b03f27fefcfd5012191423e8e8522abb7ba347b165e9c541a4e6cf9ed66?s=300&d=mm&r=g\",\"full_name\":\"Cosmick Media Inc. Sdfsfdsdf\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=9&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"18 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:54', '66.152.148.22'),
(78, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":5,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:48\",\"date_optin_status_changed\":\"2025-07-14 23:48:48\",\"ID\":5,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=5&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"24 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:54', '66.152.148.22'),
(79, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":6,\"data\":{\"email\":\"kaykaykuehner@gmail.com\",\"first_name\":\"Kayley\",\"last_name\":\"Kuehner\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:49\",\"date_optin_status_changed\":\"2025-07-14 23:48:49\",\"ID\":6,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/f33376adc266ae843f9b791576c2d27bc868fcd1d7098ff3146bdd54a69de194?s=300&d=mm&r=g\",\"full_name\":\"Kayley Kuehner\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=6&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"24 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:54', '66.152.148.22');
INSERT INTO `logs` (`id`, `store_id`, `action`, `message`, `created_at`, `ip`) VALUES
(80, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":5,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"Kim\",\"last_name\":\"Frassinelli\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:48\",\"date_optin_status_changed\":\"2025-07-14 23:48:48\",\"ID\":5,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"Kim Frassinelli\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=5&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"24 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:55', '66.152.148.22'),
(81, 1, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":7,\"data\":{\"email\":\"test@none.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:50\",\"date_optin_status_changed\":\"2025-07-14 23:48:50\",\"ID\":7,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/dfdf488c59c7ec78e5f87ce24d7a3d7e3d0f7a86c7b99a2170e6531b52c428c6?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=7&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"24 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:55', '66.152.148.22'),
(82, 2, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":7,\"data\":{\"email\":\"test@none.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-14 23:48:50\",\"date_optin_status_changed\":\"2025-07-14 23:48:50\",\"ID\":7,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/dfdf488c59c7ec78e5f87ce24d7a3d7e3d0f7a86c7b99a2170e6531b52c428c6?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":2,\"data\":{\"tag_id\":2,\"tag_slug\":\"store-onboarding\",\"tag_name\":\"store-onboarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=2&action=edit\"},{\"ID\":1,\"data\":{\"tag_id\":1,\"tag_slug\":\"media-hub\",\"tag_name\":\"media-hub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=1&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=7&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"24 minutes\"}},\"status\":\"success\"}', '2025-07-15 00:12:55', '66.152.148.22'),
(83, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":10,\"data\":{\"email\":\"cosmicktechnologies@gmail.com\",\"first_name\":\"Carley\",\"last_name\":\"Kuehner\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:17\",\"date_optin_status_changed\":\"2025-07-15 00:13:17\",\"ID\":10,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/fe9c34611d9c5a6429b563d89f6b6539e897995e2c9b3193b0349b658e34f1d7?s=300&d=mm&r=g\",\"full_name\":\"Carley Kuehner\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=10&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-15 00:13:17', '66.152.148.22'),
(84, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":11,\"data\":{\"email\":\"ckuehner@cosmickmedia.com\",\"first_name\":\"Tatiana\",\"last_name\":\"Marchenko\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:17\",\"date_optin_status_changed\":\"2025-07-15 00:13:17\",\"ID\":11,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/175e7ff3ce9a93bd275a656805858335bb8e945a1f1b87b824178d013c866fcf?s=300&d=mm&r=g\",\"full_name\":\"Tatiana Marchenko\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=11&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-15 00:13:17', '66.152.148.22'),
(85, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":12,\"data\":{\"email\":\"crystal@cosmickmedia.com\",\"first_name\":\"Crystal\",\"last_name\":\"Jones\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:18\",\"date_optin_status_changed\":\"2025-07-15 00:13:18\",\"ID\":12,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/460b912c9b359869535b1a11fc221c784ab77c357dcb9d3e106567764a8fd1a3?s=300&d=mm&r=g\",\"full_name\":\"Crystal Jones\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=12&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-15 00:13:18', '66.152.148.22'),
(86, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":13,\"data\":{\"email\":\"jim@yalley.com\",\"first_name\":\"Jim\",\"last_name\":\"Talley\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:18\",\"date_optin_status_changed\":\"2025-07-15 00:13:18\",\"ID\":13,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/1ee8043d679b0e30875d4a01aeeb9ff92f2afacb6a48606c88e61308479d4191?s=300&d=mm&r=g\",\"full_name\":\"Jim Talley\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=13&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-15 00:13:18', '66.152.148.22'),
(87, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":14,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"Kim\",\"last_name\":\"Frassinelli\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:18\",\"date_optin_status_changed\":\"2025-07-15 00:13:18\",\"ID\":14,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"Kim Frassinelli\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=14&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-15 00:13:19', '66.152.148.22'),
(88, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":15,\"data\":{\"email\":\"sdfdf@none.com\",\"first_name\":\"Cosmick Media Inc.\",\"last_name\":\"sdfsfdsdf\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:19\",\"date_optin_status_changed\":\"2025-07-15 00:13:19\",\"ID\":15,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/21ee9b03f27fefcfd5012191423e8e8522abb7ba347b165e9c541a4e6cf9ed66?s=300&d=mm&r=g\",\"full_name\":\"Cosmick Media Inc. Sdfsfdsdf\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=15&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-15 00:13:19', '66.152.148.22'),
(89, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":14,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:18\",\"date_optin_status_changed\":\"2025-07-15 00:13:18\",\"ID\":14,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=14&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-15 00:13:19', '66.152.148.22'),
(90, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":16,\"data\":{\"email\":\"kaykaykuehner@gmail.com\",\"first_name\":\"Kayley\",\"last_name\":\"Kuehner\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:20\",\"date_optin_status_changed\":\"2025-07-15 00:13:20\",\"ID\":16,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/f33376adc266ae843f9b791576c2d27bc868fcd1d7098ff3146bdd54a69de194?s=300&d=mm&r=g\",\"full_name\":\"Kayley Kuehner\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=16&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-15 00:13:20', '66.152.148.22'),
(91, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":14,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"Kim\",\"last_name\":\"Frassinelli\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:18\",\"date_optin_status_changed\":\"2025-07-15 00:13:18\",\"ID\":14,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"Kim Frassinelli\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=14&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"2 seconds\"}},\"status\":\"success\"}', '2025-07-15 00:13:20', '66.152.148.22'),
(92, 1, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":17,\"data\":{\"email\":\"test@none.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:20\",\"date_optin_status_changed\":\"2025-07-15 00:13:20\",\"ID\":17,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/dfdf488c59c7ec78e5f87ce24d7a3d7e3d0f7a86c7b99a2170e6531b52c428c6?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":100,\"lead_score_level_id\":6,\"lead_score_level_slug\":\"on-fire\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"},{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=17&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-15 00:13:20', '66.152.148.22'),
(93, 2, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":17,\"data\":{\"email\":\"test@none.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:20\",\"date_optin_status_changed\":\"2025-07-15 00:13:20\",\"ID\":17,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/dfdf488c59c7ec78e5f87ce24d7a3d7e3d0f7a86c7b99a2170e6531b52c428c6?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=17&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"1 second\"}},\"status\":\"success\"}', '2025-07-15 00:13:21', '66.152.148.22'),
(94, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":10,\"data\":{\"email\":\"cosmicktechnologies@gmail.com\",\"first_name\":\"Carley\",\"last_name\":\"Kuehner\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:17\",\"date_optin_status_changed\":\"2025-07-15 00:13:17\",\"ID\":10,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/fe9c34611d9c5a6429b563d89f6b6539e897995e2c9b3193b0349b658e34f1d7?s=300&d=mm&r=g\",\"full_name\":\"Carley Kuehner\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=10&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:52', '66.152.148.22'),
(95, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":11,\"data\":{\"email\":\"ckuehner@cosmickmedia.com\",\"first_name\":\"Tatiana\",\"last_name\":\"Marchenko\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:17\",\"date_optin_status_changed\":\"2025-07-15 00:13:17\",\"ID\":11,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/175e7ff3ce9a93bd275a656805858335bb8e945a1f1b87b824178d013c866fcf?s=300&d=mm&r=g\",\"full_name\":\"Tatiana Marchenko\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=11&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:52', '66.152.148.22'),
(96, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":12,\"data\":{\"email\":\"crystal@cosmickmedia.com\",\"first_name\":\"Crystal\",\"last_name\":\"Jones\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:18\",\"date_optin_status_changed\":\"2025-07-15 00:13:18\",\"ID\":12,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/460b912c9b359869535b1a11fc221c784ab77c357dcb9d3e106567764a8fd1a3?s=300&d=mm&r=g\",\"full_name\":\"Crystal Jones\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=12&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:53', '66.152.148.22'),
(97, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":13,\"data\":{\"email\":\"jim@yalley.com\",\"first_name\":\"Jim\",\"last_name\":\"Talley\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:18\",\"date_optin_status_changed\":\"2025-07-15 00:13:18\",\"ID\":13,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/1ee8043d679b0e30875d4a01aeeb9ff92f2afacb6a48606c88e61308479d4191?s=300&d=mm&r=g\",\"full_name\":\"Jim Talley\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=13&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:53', '66.152.148.22'),
(98, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":14,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"Kim\",\"last_name\":\"Frassinelli\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:18\",\"date_optin_status_changed\":\"2025-07-15 00:13:18\",\"ID\":14,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"Kim Frassinelli\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=14&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:53', '66.152.148.22'),
(99, 3, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":15,\"data\":{\"email\":\"sdfdf@none.com\",\"first_name\":\"Cosmick Media Inc.\",\"last_name\":\"sdfsfdsdf\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:19\",\"date_optin_status_changed\":\"2025-07-15 00:13:19\",\"ID\":15,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/21ee9b03f27fefcfd5012191423e8e8522abb7ba347b165e9c541a4e6cf9ed66?s=300&d=mm&r=g\",\"full_name\":\"Cosmick Media Inc. Sdfsfdsdf\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=15&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:54', '66.152.148.22'),
(100, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":14,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:18\",\"date_optin_status_changed\":\"2025-07-15 00:13:18\",\"ID\":14,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=14&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:54', '66.152.148.22'),
(101, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":16,\"data\":{\"email\":\"kaykaykuehner@gmail.com\",\"first_name\":\"Kayley\",\"last_name\":\"Kuehner\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:20\",\"date_optin_status_changed\":\"2025-07-15 00:13:20\",\"ID\":16,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/f33376adc266ae843f9b791576c2d27bc868fcd1d7098ff3146bdd54a69de194?s=300&d=mm&r=g\",\"full_name\":\"Kayley Kuehner\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=16&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:54', '66.152.148.22'),
(102, 4, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":14,\"data\":{\"email\":\"kim@cosmickmedia.com\",\"first_name\":\"Kim\",\"last_name\":\"Frassinelli\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:18\",\"date_optin_status_changed\":\"2025-07-15 00:13:18\",\"ID\":14,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/55367d49693f977a97541f0cb18c3d2f89209eb6dd4e7b22fe3bdba32f845c78?s=300&d=mm&r=g\",\"full_name\":\"Kim Frassinelli\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=14&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:55', '66.152.148.22'),
(103, 1, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":17,\"data\":{\"email\":\"test@none.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:20\",\"date_optin_status_changed\":\"2025-07-15 00:13:20\",\"ID\":17,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/dfdf488c59c7ec78e5f87ce24d7a3d7e3d0f7a86c7b99a2170e6531b52c428c6?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=17&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:55', '66.152.148.22'),
(104, 2, 'groundhogg_contact', 'POST https://www.cosmickmedia.com/wp-json/gh/v4/contacts HTTP 200 Response: {\"item\":{\"ID\":17,\"data\":{\"email\":\"test@none.com\",\"first_name\":\"\",\"last_name\":\"\",\"user_id\":0,\"owner_id\":2,\"optin_status\":1,\"date_created\":\"2025-07-15 00:13:20\",\"date_optin_status_changed\":\"2025-07-15 00:13:20\",\"ID\":17,\"gravatar\":\"https:\\/\\/secure.gravatar.com\\/avatar\\/dfdf488c59c7ec78e5f87ce24d7a3d7e3d0f7a86c7b99a2170e6531b52c428c6?s=300&d=mm&r=g\",\"full_name\":\"\",\"age\":false},\"meta\":{\"locale\":\"en_US\",\"lead_score_points\":\"100\",\"lead_score_level_slug\":\"on-fire\",\"lead_score_level_id\":\"6\",\"mobile_phone\":\"\",\"country\":\"\",\"birthday\":\"\"},\"tags\":[{\"ID\":4,\"data\":{\"tag_id\":4,\"tag_slug\":\"on-boarding\",\"tag_name\":\"on-boarding\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=4&action=edit\"},{\"ID\":3,\"data\":{\"tag_id\":3,\"tag_slug\":\"mediahub\",\"tag_name\":\"mediahub\",\"tag_description\":\"\",\"show_as_preference\":\"0\"},\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_tags&tag=3&action=edit\"}],\"user\":false,\"admin\":\"https:\\/\\/www.cosmickmedia.com\\/wp-admin\\/admin.php?page=gh_contacts&contact=17&action=edit\",\"is_marketable\":true,\"is_deliverable\":true,\"i18n\":{\"created\":\"3 hours\"}},\"status\":\"success\"}', '2025-07-15 02:54:55', '66.152.148.22');

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
(23, 'max_article_length', '50000'),
(138, 'groundhogg_site_url', 'https://www.cosmickmedia.com'),
(139, 'groundhogg_username', 'cosmick'),
(140, 'groundhogg_app_password', 'XiOwiVTYKBqw4RMnCQR7yzs9'),
(379, 'groundhogg_debug', '0'),
(440, 'groundhogg_public_key', '557318b75a3cef5bb2cdb2cc21ae3544'),
(441, 'groundhogg_token', '63a545cc5225886b6ebaec2daf1be8d5'),
(442, 'groundhogg_secret_key', 'fc2e4b24f14564c6090dc59831f7b238'),
(1228, 'groundhogg_contact_tags', 'mediahub,on-boarding');

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
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `marketing_report_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `name`, `pin`, `admin_email`, `drive_folder`, `hootsuite_token`, `first_name`, `last_name`, `phone`, `address`, `city`, `state`, `zip_code`, `country`, `marketing_report_url`) VALUES
(1, 'test', '1111', 'test@none.com', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'testing', '1234', 'test@none.com', '16FMaL4Lv0V6_ZVxBQRpg-3GaUyfeu0G3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Petland Cosmick', '2547', 'cosmicktechnologies@gmail.com', '1srY5v90SaXNgWsl56K_e9F0YaSN43Hc-', '', 'Carley', 'Kuehner', '', '1147 Jacobsburg Road', 'Wind Gap', 'PA', '18091', 'United States', NULL),
(4, 'Petland Phoenix', '2345', 'kim@cosmickmedia.com', '1VvZT3W4_ADzo1nRXPg98n8wOROIov9lC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `store_messages`
--

CREATE TABLE `store_messages` (
  `id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `sender` enum('admin','store') DEFAULT 'admin',
  `parent_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_reply` tinyint(1) DEFAULT 0,
  `upload_id` int(11) DEFAULT NULL,
  `article_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `read_by_admin` tinyint(1) DEFAULT 0,
  `read_by_store` tinyint(1) DEFAULT 0,
  `like_by_store` tinyint(1) DEFAULT 0,
  `like_by_admin` tinyint(1) DEFAULT 0,
  `love_by_store` tinyint(1) DEFAULT 0,
  `love_by_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_messages`
--

INSERT INTO `store_messages` (`id`, `store_id`, `sender`, `parent_id`, `message`, `is_reply`, `upload_id`, `article_id`, `created_at`, `read_by_admin`, `read_by_store`, `like_by_store`, `like_by_admin`, `love_by_store`, `love_by_admin`) VALUES
(1, 2, 'admin', NULL, 'Dont forget to create your content.', 0, NULL, NULL, '2025-07-03 04:26:20', 0, 0, 0, 0, 0, 0),
(2, 4, 'admin', NULL, 'Where do these messages go?', 0, NULL, NULL, '2025-07-03 13:46:08', 0, 1, 0, 0, 0, 0),
(3, 4, 'admin', NULL, 'Please don\'t forget to upload new social content!', 0, NULL, NULL, '2025-07-03 13:49:44', 0, 1, 0, 0, 0, 0),
(4, 3, 'admin', NULL, 'Hey there, it\'s Cassandra. Great to see you here! Don\'t forget to upload your content!', 0, NULL, NULL, '2025-07-03 13:52:29', 0, 1, 0, 0, 0, 0),
(5, 4, 'admin', NULL, 'Please don\'t forget to upload new social content!', 0, NULL, NULL, '2025-07-03 18:43:53', 0, 1, 0, 0, 0, 0),
(6, 3, 'admin', NULL, 'Hello', 0, NULL, NULL, '2025-07-14 01:29:52', 0, 1, 0, 0, 0, 0),
(7, 3, 'admin', NULL, 'hello testing', 0, NULL, NULL, '2025-07-14 01:49:18', 0, 1, 0, 0, 0, 0),
(8, 3, 'store', NULL, 'This is a reply from user', 0, NULL, NULL, '2025-07-14 01:50:15', 1, 0, 0, 0, 0, 0),
(9, 3, 'admin', NULL, 'hi user', 0, NULL, NULL, '2025-07-14 01:50:24', 0, 1, 0, 0, 0, 0),
(10, 3, 'store', NULL, 'Can you upload my graphics?', 0, NULL, NULL, '2025-07-14 01:50:43', 1, 0, 0, 0, 0, 0),
(11, 3, 'admin', NULL, 'I am testing the notifications bell.', 0, NULL, NULL, '2025-07-14 02:32:30', 1, 1, 0, 0, 0, 0),
(12, 3, 'store', NULL, 'I just uploaded a new images i need to get this published right away.', 0, NULL, NULL, '2025-07-14 02:34:21', 1, 1, 0, 0, 0, 0),
(13, 3, 'admin', NULL, 'hello', 0, NULL, NULL, '2025-07-14 02:36:56', 1, 1, 0, 0, 0, 0),
(14, 3, 'store', NULL, 'Can you see if this is working?', 0, NULL, NULL, '2025-07-14 02:37:32', 1, 1, 0, 0, 0, 0),
(15, 3, 'admin', NULL, 'This is a test Broadcast Message', 0, NULL, NULL, '2025-07-14 02:45:29', 0, 1, 0, 0, 0, 0),
(16, 4, 'store', NULL, 'Hello does this work?', 0, NULL, NULL, '2025-07-14 02:58:54', 1, 1, 0, 1, 0, 0),
(17, 4, 'store', NULL, 'Hello', 0, NULL, NULL, '2025-07-14 02:59:24', 1, 1, 0, 0, 0, 0),
(18, 4, 'store', NULL, 'test', 0, NULL, NULL, '2025-07-14 02:59:25', 1, 1, 0, 0, 0, 0),
(19, 4, 'store', NULL, 'lakjsdlakjsd', 0, NULL, NULL, '2025-07-14 02:59:26', 1, 1, 0, 0, 0, 0),
(20, 4, 'store', NULL, 'lkajsdlkjalsd', 0, NULL, NULL, '2025-07-14 02:59:29', 1, 1, 0, 1, 0, 1),
(21, 4, 'store', NULL, 'hello', 0, NULL, NULL, '2025-07-14 03:08:15', 1, 1, 0, 0, 0, 0),
(22, 4, 'store', NULL, 'testing', 0, NULL, NULL, '2025-07-14 03:21:26', 1, 1, 0, 0, 0, 0),
(23, 4, 'admin', NULL, 'Hi Kayley', 0, NULL, NULL, '2025-07-14 03:21:39', 1, 1, 0, 0, 1, 0),
(24, 4, 'store', NULL, 'I am testing from Kayley', 0, NULL, NULL, '2025-07-14 03:43:11', 1, 1, 0, 0, 0, 0),
(25, 4, 'admin', NULL, 'Hello Petland Pheonix', 0, NULL, NULL, '2025-07-14 03:44:44', 0, 1, 0, 0, 0, 0),
(26, 4, 'admin', NULL, 'Please upload your latest content', 0, NULL, NULL, '2025-07-14 03:45:31', 0, 1, 0, 0, 0, 0),
(27, 3, 'admin', NULL, 'Hi Tatiana I have everything i need here.', 0, NULL, NULL, '2025-07-14 04:44:44', 1, 1, 0, 1, 0, 0),
(29, 4, 'store', NULL, 'I updated it already.', 0, NULL, NULL, '2025-07-14 05:09:50', 1, 1, 0, 1, 0, 1),
(34, 3, 'admin', NULL, 'Testing broadcasts', 0, NULL, NULL, '2025-07-15 00:59:50', 1, 0, 0, 0, 0, 0);

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
  `mobile_phone` varchar(50) DEFAULT NULL,
  `opt_in_status` enum('unconfirmed','confirmed','unsubscribed','subscribed_weekly','subscribed_monthly','bounced','spam','complained','blocked') DEFAULT 'confirmed',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `store_users`
--

INSERT INTO `store_users` (`id`, `store_id`, `email`, `first_name`, `last_name`, `mobile_phone`, `opt_in_status`, `created_at`) VALUES
(1, 3, 'ckuehner@cosmickmedia.com', 'Tatiana', 'Marchenko', NULL, 'confirmed', '2025-07-13 20:38:47'),
(3, 4, 'kaykaykuehner@gmail.com', 'Kayley', 'Kuehner', NULL, 'confirmed', '2025-07-14 02:58:12'),
(4, 3, 'crystal@cosmickmedia.com', 'Crystal', 'Jones', NULL, 'confirmed', '2025-07-14 15:21:35'),
(5, 3, 'kim@cosmickmedia.com', 'Kim', 'Frassinelli', NULL, 'confirmed', '2025-07-14 15:25:08'),
(6, 4, 'kim@cosmickmedia.com', 'Kim', 'Frassinelli', NULL, 'confirmed', '2025-07-14 18:23:46'),
(7, 3, 'jim@yalley.com', 'Jim', 'Talley', NULL, 'confirmed', '2025-07-14 23:50:35'),
(9, 3, 'sdfdf@none.com', 'Cosmick Media Inc.', 'sdfsfdsdf', NULL, 'confirmed', '2025-07-14 23:55:09');

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
(23, 4, '77324285643__BB0B555A-5505-47AB-941C-4B86C2969CB6.MOV', '', 'Goldfish chaos', '2025-07-03 13:49:55', '12.75.117.33', 'video/quicktime', 358185, '1DeL3AN_oV3FcP0f7sBqp-G10Po_yxrIY', 1),
(24, 4, '77324285643__BB0B555A-5505-47AB-941C-4B86C2969CB6.MOV', '', 'Goldfish chaos', '2025-07-03 13:49:56', '12.75.117.33', 'video/quicktime', 358185, '1qscNWv-cFDHh_xpeN9lEUlmk9FdB2ZZh', 1),
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
(1, 'Reviewed', '#00b35f'),
(2, 'Pending Submission', '#e0a800'),
(3, 'Scheduled', '#3e0df2');

-- --------------------------------------------------------

--
-- Table structure for table `upload_status_history`
--

CREATE TABLE `upload_status_history` (
  `id` int(11) NOT NULL,
  `upload_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_status_id` int(11) DEFAULT NULL,
  `new_status_id` int(11) DEFAULT NULL,
  `changed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `upload_status_history`
--

INSERT INTO `upload_status_history` (`id`, `upload_id`, `user_id`, `old_status_id`, `new_status_id`, `changed_at`) VALUES
(1, 24, 1, 9, 21, '2025-07-14 15:10:08'),
(2, 24, 1, 21, 20, '2025-07-14 16:51:33');

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
  `mobile_phone` varchar(50) DEFAULT NULL,
  `opt_in_status` enum('unconfirmed','confirmed','unsubscribed','subscribed_weekly','subscribed_monthly','bounced','spam','complained','blocked') DEFAULT 'confirmed',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `first_name`, `last_name`, `email`, `mobile_phone`, `opt_in_status`, `created_at`) VALUES
(1, 'admin', '$2y$10$aIKmnAZxd/D5WdCHFMmto.tMsL3os10L8yUC5W4XMSdeKee/8vGpi', 'Carley', 'Kuehner', 'carley@cosmickmedia.com', NULL, 'confirmed', '2025-07-13 20:14:56'),
(14, 'Cassandra', '$2y$10$eLQoBqTNE7dBqF0ViWYBlOXKHZvMPrLPjuF8cr0wyneOnTS6rW62y', 'Cassandra', 'Dayoub', 'cassandra@cosmickmedia.com', NULL, 'confirmed', '2025-07-14 15:20:05'),
(15, 'Kim', '$2y$10$AB5EDYNdWqv/Xjvrj1RPCO.Cig7KH1Os.HvQYH2yvgNW62Q5nl0Qy', 'Kim', 'Frassinelli', 'kim@cosmickmedia.com', NULL, 'confirmed', '2025-07-14 15:21:17'),
(16, 'JBirgl', '$2y$10$cw3QNqgGumA3tEfCc9EP0OT77dy210tqNg/EhNY/KP5kALV4iEqza', 'Jennifer', 'Birgl', 'jennifer@cosmickmedia.com', NULL, 'confirmed', '2025-07-14 15:21:38'),
(18, 'Crystal Jones', '$2y$10$JqU85tn1smnru0itTPDK/OyPQ16V9JqYzcysxk6OtImVjOm/h6yZC', 'Crystal', 'Jones', 'crystal@cosmickmedia.com', NULL, 'confirmed', '2025-07-14 16:16:55');

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
-- Indexes for table `upload_status_history`
--
ALTER TABLE `upload_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_upload_id` (`upload_id`),
  ADD KEY `idx_changed_at` (`changed_at`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1463;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `store_messages`
--
ALTER TABLE `store_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `store_users`
--
ALTER TABLE `store_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `upload_statuses`
--
ALTER TABLE `upload_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `upload_status_history`
--
ALTER TABLE `upload_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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

--
-- Constraints for table `upload_status_history`
--
ALTER TABLE `upload_status_history`
  ADD CONSTRAINT `upload_status_history_ibfk_1` FOREIGN KEY (`upload_id`) REFERENCES `uploads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `upload_status_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
