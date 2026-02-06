-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 24, 2025 at 10:31 AM
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
-- Database: `tow_gas`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `user_id`, `login_time`, `logout_time`, `ip_address`) VALUES
(1, 1, '2025-12-22 06:58:38', '2025-12-22 12:48:34', '::1'),
(2, 1, '2025-12-22 12:49:18', '2025-12-22 12:50:17', '::1'),
(3, 5, '2025-12-22 12:50:24', '2025-12-22 13:04:57', '::1'),
(4, 1, '2025-12-22 13:05:23', '2025-12-22 13:11:01', '::1'),
(5, 1, '2025-12-22 13:11:17', '2025-12-22 13:12:46', '::1'),
(6, 1, '2025-12-22 13:13:10', '2025-12-22 15:59:50', '::1'),
(7, 5, '2025-12-22 15:59:56', '2025-12-22 16:22:06', '::1'),
(8, 1, '2025-12-22 16:22:33', '2025-12-22 16:23:13', '::1'),
(9, 5, '2025-12-22 16:23:20', '2025-12-22 16:24:08', '::1'),
(10, 1, '2025-12-22 16:24:20', '2025-12-22 16:25:24', '::1'),
(11, 5, '2025-12-22 16:25:39', '2025-12-22 17:07:20', '::1'),
(12, 1, '2025-12-22 17:07:30', '2025-12-22 17:08:45', '::1'),
(13, 5, '2025-12-22 17:08:55', '2025-12-22 17:32:19', '::1'),
(14, 1, '2025-12-22 17:33:05', '2025-12-22 17:34:39', '::1'),
(15, 5, '2025-12-22 17:35:04', '2025-12-22 17:35:31', '::1'),
(16, 1, '2025-12-22 17:36:06', '2025-12-22 17:37:02', '::1'),
(17, 5, '2025-12-22 17:37:08', '2025-12-22 17:38:21', '::1'),
(18, 1, '2025-12-22 17:38:54', '2025-12-22 17:39:41', '::1'),
(19, 5, '2025-12-22 17:40:01', NULL, '::1'),
(20, 1, '2025-12-22 17:43:37', '2025-12-22 17:44:27', '::1'),
(21, 5, '2025-12-22 17:44:47', NULL, '::1'),
(22, 5, '2025-12-23 03:54:17', '2025-12-23 04:08:43', '::1'),
(23, 5, '2025-12-23 04:11:05', '2025-12-23 04:25:38', '::1'),
(24, 1, '2025-12-23 04:25:47', '2025-12-23 04:39:54', '::1'),
(25, 1, '2025-12-23 05:03:15', '2025-12-23 05:03:41', '::1'),
(26, 5, '2025-12-23 11:37:03', '2025-12-23 11:47:10', '::1'),
(27, 1, '2025-12-23 12:00:54', '2025-12-23 12:12:20', '::1'),
(28, 5, '2025-12-23 12:12:38', '2025-12-23 12:12:45', '::1'),
(29, 5, '2025-12-23 12:12:51', '2025-12-23 12:13:00', '::1'),
(30, 5, '2025-12-23 12:13:10', '2025-12-23 12:13:19', '::1'),
(31, 1, '2025-12-23 12:14:10', '2025-12-23 12:20:27', '::1'),
(32, 1, '2025-12-23 12:20:41', '2025-12-23 12:56:35', '::1'),
(33, 1, '2025-12-23 12:59:35', '2025-12-23 13:00:27', '::1'),
(34, 1, '2025-12-23 13:01:05', '2025-12-23 13:02:03', '::1'),
(35, 5, '2025-12-23 13:02:15', NULL, '::1'),
(36, 1, '2025-12-23 13:05:05', '2025-12-23 13:05:54', '::1'),
(37, 5, '2025-12-23 13:06:12', '2025-12-23 13:08:33', '::1'),
(38, 1, '2025-12-23 13:09:05', '2025-12-23 13:09:57', '::1'),
(39, 5, '2025-12-23 13:10:18', '2025-12-23 13:10:52', '::1'),
(40, 5, '2025-12-23 13:11:23', NULL, '::1'),
(41, 1, '2025-12-23 15:05:47', NULL, '::1'),
(42, 1, '2025-12-23 15:11:24', '2025-12-23 15:11:58', '::1'),
(43, 1, '2025-12-23 15:13:16', '2025-12-23 15:14:05', '::1'),
(44, 1, '2025-12-23 15:14:54', '2025-12-23 15:15:40', '::1'),
(45, 1, '2025-12-23 15:16:17', NULL, '::1'),
(46, 1, '2025-12-24 01:27:47', '2025-12-24 01:37:31', '::1'),
(47, 5, '2025-12-24 01:37:40', '2025-12-24 01:47:02', '::1'),
(48, 1, '2025-12-24 01:52:14', '2025-12-24 01:52:26', '::1'),
(49, 1, '2025-12-24 01:52:56', '2025-12-24 01:53:07', '::1'),
(50, 5, '2025-12-24 02:04:09', '2025-12-24 02:04:38', '::1'),
(51, 1, '2025-12-24 02:04:53', '2025-12-24 02:06:37', '::1'),
(52, 5, '2025-12-24 02:06:42', '2025-12-24 02:07:46', '::1'),
(53, 5, '2025-12-24 02:08:13', '2025-12-24 02:08:20', '::1'),
(54, 1, '2025-12-24 02:09:11', '2025-12-24 02:12:58', '::1'),
(55, 5, '2025-12-24 02:13:13', '2025-12-24 02:14:10', '::1'),
(56, 5, '2025-12-24 02:16:16', '2025-12-24 02:18:59', '::1'),
(57, 1, '2025-12-24 02:19:12', '2025-12-24 02:22:20', '::1'),
(58, 5, '2025-12-24 02:22:32', '2025-12-24 02:24:46', '::1'),
(59, 5, '2025-12-24 02:24:54', '2025-12-24 02:24:57', '::1'),
(60, 1, '2025-12-24 02:25:06', '2025-12-24 02:25:29', '::1'),
(61, 5, '2025-12-24 02:27:08', '2025-12-24 02:49:56', '::1'),
(62, 1, '2025-12-24 02:50:10', '2025-12-24 02:50:19', '::1'),
(63, 1, '2025-12-24 02:52:45', '2025-12-24 02:52:52', '::1'),
(64, 1, '2025-12-24 02:54:25', '2025-12-24 02:54:37', '::1'),
(65, 1, '2025-12-24 02:55:15', '2025-12-24 02:55:35', '::1'),
(66, 1, '2025-12-24 02:55:41', '2025-12-24 02:55:45', '::1'),
(67, 1, '2025-12-24 02:56:10', '2025-12-24 02:57:07', '::1'),
(68, 5, '2025-12-24 02:58:18', '2025-12-24 02:58:46', '::1'),
(69, 5, '2025-12-24 02:59:18', '2025-12-24 03:05:40', '::1'),
(70, 5, '2025-12-24 03:06:07', NULL, '::1'),
(71, 5, '2025-12-24 03:12:18', NULL, '::1'),
(72, 1, '2025-12-24 03:16:48', '2025-12-24 03:18:19', '::1'),
(73, 5, '2025-12-24 03:18:36', '2025-12-24 03:19:39', '::1'),
(74, 1, '2025-12-24 06:16:01', '2025-12-24 07:48:51', '::1'),
(75, 5, '2025-12-24 07:51:08', '2025-12-24 07:51:17', '::1'),
(76, 1, '2025-12-24 07:58:35', NULL, '::1'),
(77, 1, '2025-12-24 08:56:19', '2025-12-24 09:21:08', '::1'),
(78, 5, '2025-12-24 09:21:24', '2025-12-24 09:21:51', '::1'),
(79, 1, '2025-12-24 09:25:19', '2025-12-24 09:25:41', '::1'),
(80, 5, '2025-12-24 09:25:49', NULL, '::1');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_data` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `table_name`, `record_id`, `old_value`, `new_data`, `new_value`, `ip_address`, `created_at`) VALUES
(1, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 12:48:34'),
(2, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 12:49:18'),
(3, 1, 'CREATE', 'users', 5, NULL, NULL, '{\"username\":\"staff1\",\"email\":\"raymartalejado10202005@gmail.com\"}', '::1', '2025-12-22 12:50:08'),
(4, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 12:50:17'),
(5, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-22 12:50:24'),
(6, 5, 'LOGOUT', 'users', 5, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 13:04:57'),
(7, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 13:05:23'),
(8, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 13:11:01'),
(9, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 13:11:17'),
(10, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 13:12:46'),
(11, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 13:13:10'),
(12, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 15:59:50'),
(13, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-22 15:59:56'),
(14, 5, 'LOGOUT', 'users', 5, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 16:22:07'),
(15, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 16:22:33'),
(16, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 16:23:14'),
(17, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-22 16:23:20'),
(18, 5, 'LOGOUT', 'users', 5, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 16:24:08'),
(19, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 16:24:20'),
(20, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 16:25:24'),
(21, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-22 16:25:39'),
(22, 5, 'Created sale with invoice #INV-20251223-64B3AE', 'sales', 3, NULL, 'Invoice: INV-20251223-64B3AE, Amount: 850', NULL, '::1', '2025-12-22 16:26:30'),
(23, 5, 'Created sale with invoice #INV-20251223-7CEDCE', 'sales', 4, NULL, 'Invoice: INV-20251223-7CEDCE, Amount: 850', NULL, '::1', '2025-12-22 16:27:03'),
(24, 5, 'Created sale with invoice #INV-20251223-641708', 'sales', 5, NULL, 'Invoice: INV-20251223-641708, Amount: 850', NULL, '::1', '2025-12-22 16:29:26'),
(25, 5, 'LOGOUT', 'users', 5, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 17:07:20'),
(26, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 17:07:30'),
(27, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 17:08:45'),
(28, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-22 17:08:55'),
(29, 5, 'LOGOUT', 'users', 5, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 17:32:19'),
(30, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 17:33:05'),
(31, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 17:34:39'),
(32, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-22 17:35:04'),
(33, 5, 'LOGOUT', 'users', 5, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 17:35:31'),
(34, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 17:36:06'),
(35, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 17:37:02'),
(36, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-22 17:37:08'),
(37, 5, 'LOGOUT', 'users', 5, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 17:38:21'),
(38, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 17:38:54'),
(39, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 17:39:42'),
(40, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-22 17:40:01'),
(41, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-22 17:43:37'),
(42, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-22 17:44:27'),
(43, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-22 17:44:47'),
(44, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 03:54:17'),
(45, 5, 'LOGOUT', 'users', 5, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-23 04:08:43'),
(46, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 04:11:05'),
(47, 5, 'LOGOUT', 'users', 5, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-23 04:25:38'),
(48, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 04:25:47'),
(49, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-23 04:39:54'),
(50, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 05:03:15'),
(51, 1, 'LOGOUT', 'users', 1, NULL, NULL, '{\"action\":\"User logged out\"}', '::1', '2025-12-23 05:03:41'),
(52, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 11:47:10'),
(53, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 12:00:54'),
(54, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 12:12:21'),
(55, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 12:12:38'),
(56, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 12:12:45'),
(57, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 12:12:51'),
(58, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 12:13:00'),
(59, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 12:13:10'),
(60, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 12:13:19'),
(61, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 12:14:10'),
(62, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 12:20:27'),
(63, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 12:20:41'),
(64, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 12:56:35'),
(65, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 12:59:35'),
(66, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 13:00:27'),
(67, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 13:01:05'),
(68, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 13:02:03'),
(69, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 13:02:15'),
(70, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 13:05:05'),
(71, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 13:05:55'),
(72, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 13:06:12'),
(73, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 13:08:33'),
(74, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 13:09:05'),
(75, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 13:09:57'),
(76, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 13:10:18'),
(77, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 13:10:52'),
(78, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-23 13:11:23'),
(79, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 15:05:47'),
(80, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 15:11:24'),
(81, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 15:11:58'),
(82, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 15:13:16'),
(83, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 15:14:05'),
(84, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 15:14:54'),
(85, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 15:15:40'),
(86, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-23 15:16:17'),
(87, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 01:27:47'),
(88, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 01:37:31'),
(89, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 01:37:40'),
(90, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 01:47:02'),
(91, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 01:52:14'),
(92, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 01:52:26'),
(93, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 01:52:56'),
(94, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 01:53:07'),
(95, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:04:09'),
(96, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:04:38'),
(97, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:04:53'),
(98, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:06:37'),
(99, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:06:42'),
(100, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:07:46'),
(101, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:08:13'),
(102, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:08:20'),
(103, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:09:11'),
(104, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:12:58'),
(105, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:13:13'),
(106, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:14:10'),
(107, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:16:16'),
(108, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:18:59'),
(109, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:19:12'),
(110, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:22:20'),
(111, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:22:32'),
(112, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:24:46'),
(113, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:24:54'),
(114, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:24:57'),
(115, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:25:06'),
(116, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:25:29'),
(117, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:27:08'),
(118, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:49:56'),
(119, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:50:10'),
(120, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:50:19'),
(121, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:52:45'),
(122, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:52:52'),
(123, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:54:25'),
(124, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:54:37'),
(125, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:55:15'),
(126, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:55:36'),
(127, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:55:41'),
(128, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:55:45'),
(129, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:56:10'),
(130, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 02:57:07'),
(131, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:58:18'),
(132, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:58:46'),
(133, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 02:59:18'),
(134, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 03:05:40'),
(135, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 03:06:07'),
(136, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 03:12:18'),
(137, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 03:16:48'),
(138, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 03:18:19'),
(139, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 03:18:36'),
(140, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 03:19:39'),
(142, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 07:48:51'),
(143, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 07:51:08'),
(144, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 07:51:17'),
(145, 5, 'PASSWORD_RESET', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 07:55:23'),
(146, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 07:58:35'),
(147, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 08:56:19'),
(148, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 09:21:08'),
(149, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 09:21:24'),
(150, 5, 'LOGOUT', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 09:21:51'),
(151, 1, 'LOGIN', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 09:25:19'),
(152, 1, 'LOGOUT', 'users', 1, NULL, NULL, NULL, '::1', '2025-12-24 09:25:41'),
(153, 5, 'LOGIN', 'users', 5, NULL, NULL, NULL, '::1', '2025-12-24 09:25:49');

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `backup_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filesize` bigint(20) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `backups`
--

INSERT INTO `backups` (`backup_id`, `filename`, `filesize`, `created_by`, `created_at`) VALUES
(2, 'backup_2025-12-22_23-34-53.sql', 22701, 1, '2025-12-22 15:34:53');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `customer_type` enum('regular','commercial') DEFAULT 'regular',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `customer_name`, `contact`, `address`, `customer_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Walk-in Customer', '', '', 'regular', 'active', '2025-12-22 06:02:33', '2025-12-22 06:02:33'),
(2, 'Maria Santos', '09171234567', '123 Main St, Quezon City', 'regular', 'active', '2025-12-22 06:02:33', '2025-12-22 06:02:33'),
(3, 'Juan Garcia', '09281234567', '456 Rizal Ave, Manila', 'regular', 'active', '2025-12-22 06:02:33', '2025-12-22 06:02:33'),
(4, 'ABC Restaurant', '09391234567', '789 Commerce St, Makati', 'commercial', 'active', '2025-12-22 06:02:33', '2025-12-22 06:02:33');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `delivery_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rider_id` int(11) DEFAULT NULL,
  `delivery_address` text NOT NULL,
  `delivery_status` enum('pending','in_transit','delivered','cancelled') DEFAULT 'pending',
  `delivery_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`delivery_id`, `sale_id`, `customer_id`, `rider_id`, `delivery_address`, `delivery_status`, `delivery_date`, `notes`, `created_at`, `updated_at`) VALUES
(7, 15, 3, 1, '', '', NULL, NULL, '2025-12-22 17:24:30', '2025-12-22 17:25:18'),
(8, 16, 3, 5, '', '', NULL, NULL, '2025-12-22 17:35:28', '2025-12-22 17:37:39'),
(9, 17, 3, 5, '', 'delivered', NULL, NULL, '2025-12-22 17:37:29', '2025-12-23 03:54:57'),
(10, 18, 2, NULL, '', 'pending', NULL, NULL, '2025-12-22 17:40:16', '2025-12-22 17:40:16'),
(11, 19, 2, NULL, '', 'pending', NULL, NULL, '2025-12-22 17:41:14', '2025-12-22 17:41:14'),
(12, 22, 2, NULL, '', 'pending', NULL, NULL, '2025-12-23 13:11:41', '2025-12-23 13:11:41'),
(13, 23, 2, NULL, '', 'pending', NULL, NULL, '2025-12-24 02:28:07', '2025-12-24 02:28:07'),
(14, 24, 3, NULL, '', 'pending', NULL, NULL, '2025-12-24 02:59:55', '2025-12-24 02:59:55'),
(15, 26, 3, NULL, '', 'pending', NULL, NULL, '2025-12-24 03:08:37', '2025-12-24 03:08:37'),
(16, 28, 3, 3, '', 'delivered', NULL, NULL, '2025-12-24 03:19:01', '2025-12-24 03:19:29'),
(17, 29, 3, NULL, '', 'pending', NULL, NULL, '2025-12-24 06:37:49', '2025-12-24 06:37:49');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `opening_stock` int(11) DEFAULT 0,
  `closing_stock` int(11) DEFAULT 0,
  `current_stock` int(11) DEFAULT 0,
  `date` date NOT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `product_id`, `opening_stock`, `closing_stock`, `current_stock`, `date`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 100, 0, 100, '2025-12-22', 1, '2025-12-22 06:02:33', '2025-12-22 06:02:33'),
(2, 2, 80, 0, 80, '2025-12-22', 1, '2025-12-22 06:02:33', '2025-12-22 06:02:33'),
(3, 3, 50, 0, 50, '2025-12-22', 1, '2025-12-22 06:02:33', '2025-12-22 06:02:33'),
(4, 4, 30, 0, 30, '2025-12-22', 1, '2025-12-22 06:02:33', '2025-12-22 06:02:33'),
(5, 2, 10, 0, 63, '2025-12-23', 5, '2025-12-22 16:23:06', '2025-12-23 04:12:04'),
(6, 1, 0, 0, 58, '2025-12-23', 5, '2025-12-22 16:23:06', '2025-12-23 13:11:41'),
(7, 3, 0, 0, 50, '2025-12-23', 5, '2025-12-22 16:23:06', '2025-12-22 17:31:10'),
(8, 4, 0, 0, 60, '2025-12-23', 5, '2025-12-22 16:23:06', '2025-12-22 17:31:59'),
(9, 2, 50, 0, 46, '2025-12-24', 1, '2025-12-24 02:25:23', '2025-12-24 03:19:01'),
(10, 1, 50, 0, 48, '2025-12-24', 1, '2025-12-24 02:25:23', '2025-12-24 07:48:10'),
(11, 3, 50, 0, 50, '2025-12-24', 1, '2025-12-24 02:25:23', '2025-12-24 02:25:23'),
(12, 4, 50, 0, 48, '2025-12-24', 1, '2025-12-24 02:25:23', '2025-12-24 06:37:49');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `sale_id`, `payment_method`, `amount_paid`, `reference_number`, `payment_date`) VALUES
(1, 3, 'cash', 850.00, NULL, '2025-12-22 16:26:30'),
(2, 4, 'gcash', 850.00, NULL, '2025-12-22 16:27:03'),
(3, 5, 'cash', 850.00, NULL, '2025-12-22 16:29:26'),
(5, 7, 'cash', 1000.00, NULL, '2025-12-22 17:01:54'),
(6, 8, 'cash', 900.00, NULL, '2025-12-22 17:02:24'),
(8, 10, 'cash', 900.00, NULL, '2025-12-22 17:02:59'),
(13, 15, 'cash', 900.00, NULL, '2025-12-22 17:24:30'),
(14, 16, 'cash', 900.00, NULL, '2025-12-22 17:35:28'),
(15, 17, 'cash', 900.00, NULL, '2025-12-22 17:37:29'),
(16, 18, 'cash', 900.00, NULL, '2025-12-22 17:40:16'),
(17, 19, 'cash', 900.00, NULL, '2025-12-22 17:41:14'),
(18, 20, 'cash', 900.00, NULL, '2025-12-23 04:12:04'),
(19, 21, 'cash', 300.00, NULL, '2025-12-23 04:18:20'),
(20, 22, 'cash', 300.00, NULL, '2025-12-23 13:11:41'),
(21, 23, 'cash', 4000.00, NULL, '2025-12-24 02:28:07'),
(22, 24, 'cash', 900.00, NULL, '2025-12-24 02:59:55'),
(23, 25, 'cash', 900.00, NULL, '2025-12-24 03:06:53'),
(24, 26, 'cash', 300.00, NULL, '2025-12-24 03:08:37'),
(25, 27, 'cash', 900.00, NULL, '2025-12-24 03:09:09'),
(26, 28, 'cash', 900.00, NULL, '2025-12-24 03:19:01'),
(27, 29, 'gcash', 3500.00, NULL, '2025-12-24 06:37:49'),
(28, 30, 'cash', 300.00, NULL, '2025-12-24 07:48:10');

-- --------------------------------------------------------

--
-- Table structure for table `price_history`
--

CREATE TABLE `price_history` (
  `history_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `old_price` decimal(10,2) NOT NULL,
  `new_price` decimal(10,2) NOT NULL,
  `old_capital` decimal(10,2) NOT NULL,
  `new_capital` decimal(10,2) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `size` varchar(20) NOT NULL,
  `unit` varchar(20) DEFAULT 'kg',
  `image_path` varchar(255) DEFAULT NULL,
  `current_price` decimal(10,2) NOT NULL,
  `capital_cost` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `product_code`, `image`, `size`, `unit`, `image_path`, `current_price`, `capital_cost`, `status`, `created_at`, `updated_at`) VALUES
(1, 'LPG Tank 2.7kg', 'LPG-2.7', NULL, '2.7', 'kg', 'uploads/products/69496a7c1b913.jpg', 250.00, 200.00, 'active', '2025-12-22 06:02:33', '2025-12-22 15:57:48'),
(2, 'LPG Tank 11kg', 'LPG-11', NULL, '11', 'kg', 'uploads/products/69496a7272416.jpg', 850.00, 700.00, 'active', '2025-12-22 06:02:33', '2025-12-22 15:57:38'),
(3, 'LPG Tank 22kg', 'LPG-22', NULL, '22', 'kg', 'uploads/products/69496a84efd48.jpg', 1650.00, 1400.00, 'active', '2025-12-22 06:02:33', '2025-12-22 15:57:56'),
(4, 'LPG Tank 50kg', 'LPG-50', NULL, '50', 'kg', 'uploads/products/69496a9c018ca.jpg', 3500.00, 3000.00, 'active', '2025-12-22 06:02:33', '2025-12-22 15:58:20');

-- --------------------------------------------------------

--
-- Table structure for table `riders`
--

CREATE TABLE `riders` (
  `rider_id` int(11) NOT NULL,
  `rider_name` varchar(100) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `pin` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `riders`
--

INSERT INTO `riders` (`rider_id`, `rider_name`, `contact`, `pin`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Pedro Reyes', '09451234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-12-22 06:02:33', '2025-12-24 01:46:39'),
(2, 'Jose Martinez', '09561234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-12-22 06:02:33', '2025-12-24 01:46:39'),
(3, 'Juan Dela Cruz', '09171234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-12-22 16:44:19', '2025-12-24 01:46:39'),
(4, 'Pedro Santos', '09181234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-12-22 16:44:19', '2025-12-24 01:46:39'),
(5, 'Maria Garcia', '09191234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', '2025-12-22 16:44:19', '2025-12-24 01:46:39');

-- --------------------------------------------------------

--
-- Table structure for table `rider_attendance`
--

CREATE TABLE `rider_attendance` (
  `attendance_id` int(11) NOT NULL,
  `rider_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL,
  `logout_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rider_attendance`
--

INSERT INTO `rider_attendance` (`attendance_id`, `rider_id`, `login_time`, `logout_time`, `ip_address`, `created_at`) VALUES
(1, 2, '2025-12-24 10:06:00', '2025-12-24 10:06:00', '::1', '2025-12-24 02:06:21');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `created_at`) VALUES
(1, 'Admin', '2025-12-22 06:02:33'),
(2, 'Staff', '2025-12-22 06:02:33');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `total_capital` decimal(10,2) NOT NULL,
  `total_profit` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','gcash','bank') NOT NULL,
  `status` enum('completed','pending','void') DEFAULT 'completed',
  `void_reason` text DEFAULT NULL,
  `voided_by` int(11) DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `invoice_number`, `customer_id`, `user_id`, `total_amount`, `total_capital`, `total_profit`, `payment_method`, `status`, `void_reason`, `voided_by`, `voided_at`, `created_at`) VALUES
(3, 'INV-20251223-64B3AE', NULL, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-22 16:26:30'),
(4, 'INV-20251223-7CEDCE', 2, 5, 850.00, 700.00, 150.00, 'gcash', 'completed', NULL, NULL, NULL, '2025-12-22 16:27:03'),
(5, 'INV-20251223-641708', 4, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-22 16:29:26'),
(7, 'INV-20251223-213F91', 3, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-22 17:01:54'),
(8, 'INV-20251223-0E3CBD', NULL, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-22 17:02:24'),
(10, 'INV-20251223-3D9BF7', NULL, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-22 17:02:59'),
(15, 'INV-20251223-E56406', 3, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-22 17:24:30'),
(16, 'INV-20251223-029CC6', 3, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-22 17:35:28'),
(17, 'INV-20251223-919D1A', 3, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-22 17:37:29'),
(18, 'INV-20251223-0CA323', 2, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-22 17:40:16'),
(19, 'INV-20251223-A5DD9A', 2, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-22 17:41:14'),
(20, 'INV-20251223-40C3FF', NULL, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-23 04:12:04'),
(21, 'INV-20251223-CC05BF', 3, 5, 250.00, 200.00, 50.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-23 04:18:20'),
(22, 'INV-20251223-D27F56', 2, 5, 250.00, 200.00, 50.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-23 13:11:41'),
(23, 'INV-20251224-777AA3', 2, 5, 3500.00, 3000.00, 500.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-24 02:28:07'),
(24, 'INV-20251224-BACB51', 3, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-24 02:59:55'),
(25, 'INV-20251224-D892C8', 3, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-24 03:06:53'),
(26, 'INV-20251224-57CC74', 3, 5, 250.00, 200.00, 50.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-24 03:08:37'),
(27, 'INV-20251224-5A254C', 2, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-24 03:09:09'),
(28, 'INV-20251224-57DF83', 3, 5, 850.00, 700.00, 150.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-24 03:19:01'),
(29, 'INV-20251224-DCEBF0', 3, 1, 3500.00, 3000.00, 500.00, 'gcash', 'completed', NULL, NULL, NULL, '2025-12-24 06:37:49'),
(30, 'INV-20251224-AB1E09', NULL, 1, 250.00, 200.00, 50.00, 'cash', 'completed', NULL, NULL, NULL, '2025-12-24 07:48:10');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `sale_item_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `unit_capital` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `subtotal_capital` decimal(10,2) NOT NULL,
  `subtotal_profit` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`sale_item_id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `unit_capital`, `subtotal`, `subtotal_capital`, `subtotal_profit`, `created_at`) VALUES
(1, 3, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 16:26:30'),
(2, 4, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 16:27:03'),
(3, 5, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 16:29:26'),
(5, 7, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 17:01:54'),
(6, 8, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 17:02:24'),
(8, 10, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 17:02:59'),
(13, 15, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 17:24:30'),
(14, 16, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 17:35:28'),
(15, 17, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 17:37:29'),
(16, 18, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 17:40:16'),
(17, 19, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-22 17:41:14'),
(18, 20, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-23 04:12:04'),
(19, 21, 1, 1, 250.00, 200.00, 250.00, 200.00, 50.00, '2025-12-23 04:18:20'),
(20, 22, 1, 1, 250.00, 200.00, 250.00, 200.00, 50.00, '2025-12-23 13:11:41'),
(21, 23, 4, 1, 3500.00, 3000.00, 3500.00, 3000.00, 500.00, '2025-12-24 02:28:07'),
(22, 24, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-24 02:59:55'),
(23, 25, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-24 03:06:53'),
(24, 26, 1, 1, 250.00, 200.00, 250.00, 200.00, 50.00, '2025-12-24 03:08:37'),
(25, 27, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-24 03:09:09'),
(26, 28, 2, 1, 850.00, 700.00, 850.00, 700.00, 150.00, '2025-12-24 03:19:01'),
(27, 29, 4, 1, 3500.00, 3000.00, 3500.00, 3000.00, 500.00, '2025-12-24 06:37:49'),
(28, 30, 1, 1, 250.00, 200.00, 250.00, 200.00, 50.00, '2025-12-24 07:48:10');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `store_name` varchar(255) NOT NULL DEFAULT 'Town Gas Store',
  `store_address` text DEFAULT NULL,
  `store_phone` varchar(50) DEFAULT NULL,
  `store_email` varchar(100) DEFAULT NULL,
  `gcash_number` varchar(20) DEFAULT NULL,
  `gcash_name` varchar(255) DEFAULT NULL,
  `gcash_qr_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `store_name`, `store_address`, `store_phone`, `store_email`, `gcash_number`, `gcash_name`, `gcash_qr_path`, `created_at`, `updated_at`) VALUES
(1, 'Town Gas Store', 'Your Store Address Here', '09XX-XXX-XXXX', NULL, '09123456789', 'Store Owner Name', NULL, '2025-12-22 16:41:38', '2025-12-22 16:41:38'),
(2, 'Town Gas Store', 'Your Store Address Here', '09XX-XXX-XXXX', NULL, '09123456789', 'Store Owner Name', NULL, '2025-12-22 16:42:13', '2025-12-22 16:42:13'),
(3, 'Town Gas Store', 'Your Store Address Here', '09XX-XXX-XXXX', NULL, '09123456789', 'Store Owner Name', NULL, '2025-12-22 16:44:19', '2025-12-22 16:44:19');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `updated_at`, `created_at`) VALUES
(1, 'company_name', 'Tow Gas', '2025-12-22 06:02:34', '2025-12-24 06:31:51'),
(2, 'company_address', 'Quezon City, Metro Manila', '2025-12-22 06:02:34', '2025-12-24 06:31:51'),
(3, 'company_contact', '09123456789', '2025-12-22 06:02:34', '2025-12-24 06:31:51'),
(4, 'receipt_footer', 'Thank you for your business!', '2025-12-22 06:02:34', '2025-12-24 06:31:51'),
(5, 'tax_rate', '0', '2025-12-22 06:02:34', '2025-12-24 06:31:51'),
(6, 'backup_schedule', 'daily', '2025-12-22 06:02:34', '2025-12-24 06:31:51'),
(7, 'business_name', 'Town Gas LPG', '2025-12-24 06:32:00', '2025-12-24 06:32:00'),
(8, 'business_contact', '', '2025-12-24 06:32:00', '2025-12-24 06:32:00'),
(9, 'business_address', 'test', '2025-12-24 06:32:00', '2025-12-24 06:32:00'),
(10, 'business_email', 'test@gmail.com', '2025-12-24 06:32:00', '2025-12-24 06:32:00'),
(11, 'tax_id', '', '2025-12-24 06:32:00', '2025-12-24 06:32:00'),
(12, 'currency', 'PHP', '2025-12-24 06:37:29', '2025-12-24 06:32:00'),
(13, 'delivery_fee', '50', '2025-12-24 06:32:00', '2025-12-24 06:32:00'),
(14, 'min_order_amount', '0', '2025-12-24 06:32:00', '2025-12-24 06:32:00'),
(15, 'low_stock_threshold', '10', '2025-12-24 06:32:00', '2025-12-24 06:32:00'),
(16, 'critical_stock_threshold', '5', '2025-12-24 06:32:01', '2025-12-24 06:32:01'),
(17, 'max_delivery_distance', '15', '2025-12-24 06:32:01', '2025-12-24 06:32:01'),
(18, 'avg_delivery_time', '45', '2025-12-24 06:32:01', '2025-12-24 06:32:01'),
(19, 'delivery_hours', '8:00 AM - 6:00 PM', '2025-12-24 06:32:01', '2025-12-24 06:32:01'),
(20, 'email_notifications', '1', '2025-12-24 06:32:01', '2025-12-24 06:32:01'),
(21, 'low_stock_alerts', '1', '2025-12-24 06:32:01', '2025-12-24 06:32:01'),
(22, 'daily_reports', '1', '2025-12-24 06:32:01', '2025-12-24 06:32:01'),
(23, 'receipt_prefix', 'TG', '2025-12-24 06:32:01', '2025-12-24 06:32:01'),
(24, 'date_format', 'Y-m-d', '2025-12-24 06:32:01', '2025-12-24 06:32:01'),
(25, 'timezone', 'Asia/Manila', '2025-12-24 06:32:53', '2025-12-24 06:32:01'),
(26, 'backup_frequency', 'daily', '2025-12-24 06:32:01', '2025-12-24 06:32:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `username`, `email`, `password`, `full_name`, `contact`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', 'admin@towngas.com', '$2y$10$AHZfApn1gYDmdbuWLsTroeAWIUindkzskqfWkuGcs1CiiAbdkb82C', 'Admin User', '09123456789', 'active', '2025-12-22 06:02:33', '2025-12-22 06:56:45'),
(2, 2, 'staff', 'staff@towgas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Dela Cruz', '09987654321', 'active', '2025-12-22 06:02:33', '2025-12-22 06:57:03'),
(5, 2, 'staff1', 'raymartalejado10202005@gmail.com', '$2y$10$6EeKp8SgxWJkLMkptsRUde4aVA5SKd2YPlQ1WSS/lrxL7lZF6rE/2', 'marimar aw', '0930894224', 'active', '2025-12-22 12:50:08', '2025-12-24 07:55:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_date` (`created_at`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`backup_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`delivery_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `rider_id` (`rider_id`),
  ADD KEY `idx_deliveries_status` (`delivery_status`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `unique_product_date` (`product_id`,`date`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_inventory_date` (`date`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `price_history`
--
ALTER TABLE `price_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `product_code` (`product_code`);

--
-- Indexes for table `riders`
--
ALTER TABLE `riders`
  ADD PRIMARY KEY (`rider_id`);

--
-- Indexes for table `rider_attendance`
--
ALTER TABLE `rider_attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `rider_id` (`rider_id`),
  ADD KEY `login_time` (`login_time`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `voided_by` (`voided_by`),
  ADD KEY `idx_sales_date` (`created_at`),
  ADD KEY `idx_sales_status` (`status`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`sale_item_id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=154;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `backup_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `delivery_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `price_history`
--
ALTER TABLE `price_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `riders`
--
ALTER TABLE `riders`
  MODIFY `rider_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `rider_attendance`
--
ALTER TABLE `rider_attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `sale_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `backups`
--
ALTER TABLE `backups`
  ADD CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`),
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `deliveries_ibfk_3` FOREIGN KEY (`rider_id`) REFERENCES `riders` (`rider_id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`);

--
-- Constraints for table `price_history`
--
ALTER TABLE `price_history`
  ADD CONSTRAINT `price_history_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `price_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `price_history_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `rider_attendance`
--
ALTER TABLE `rider_attendance`
  ADD CONSTRAINT `fk_rider_attendance_rider` FOREIGN KEY (`rider_id`) REFERENCES `riders` (`rider_id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`voided_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
