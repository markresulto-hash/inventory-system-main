-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 06, 2026 at 01:04 AM
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
-- Database: `inventory_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `acc`
--

CREATE TABLE `acc` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `acc`
--

INSERT INTO `acc` (`id`, `name`, `pass`, `created_at`) VALUES
(1, 'admin', '$2y$10$YourHashedPasswordHere', '2026-02-19 04:30:39');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`) VALUES
(1, 'Medicine', '2026-02-11 05:17:53'),
(2, 'Food', '2026-02-11 05:17:53'),
(3, 'Hygiene', '2026-02-11 05:17:53'),
(4, 'Medical Supplies', '2026-02-11 05:17:53'),
(5, 'Equipment', '2026-02-11 05:17:53'),
(6, 'Cleaning', '2026-02-11 05:17:53'),
(7, 'Beverages', '2026-02-11 07:03:53'),
(8, 'Snacks', '2026-02-11 07:03:53'),
(9, 'Dairy', '2026-02-11 07:03:53'),
(10, 'Office Supplies', '2026-02-11 07:03:53'),
(11, 'Electronics', '2026-02-11 07:03:53');

-- --------------------------------------------------------

--
-- Stand-in structure for view `current_stock`
-- (See below for the actual view)
--
CREATE TABLE `current_stock` (
`product_id` int(11)
,`name` varchar(255)
,`category` varchar(100)
,`unit` varchar(50)
,`min_stock` int(11)
,`current_quantity` decimal(33,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` int(11) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `min_stock` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `expiry_date` date DEFAULT NULL,
  `has_expiry` tinyint(1) DEFAULT 1,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category_id`, `unit`, `min_stock`, `created_at`, `is_active`, `expiry_date`, `has_expiry`, `image_path`) VALUES
(1, 'Diapers', 3, 'Pack', 40, '2026-03-05 01:00:00', 1, NULL, 1, 'uploads/products/69a8d640e253c_1772672576.jpg'),
(2, 'Saka Rice', 2, '5kg', 20, '2026-03-05 01:10:29', 0, NULL, 1, ''),
(3, 'Saka Rice', 2, '5kg', 20, '2026-03-05 01:19:58', 1, NULL, 1, 'uploads/products/69a8da3e9eebd_1772673598.jpg'),
(4, 'Rice', 2, '25kg', 10, '2026-03-05 01:22:12', 0, NULL, 1, NULL),
(5, 'Rebisco 10 packs', 2, 'Pack', 10, '2026-03-05 01:28:42', 1, NULL, 1, 'uploads/products/69a8f17e61f84_1772679550.jpg'),
(6, 'Sky flakes', 2, 'Pack', 10, '2026-03-05 01:34:04', 1, NULL, 1, 'uploads/products/69a8df16225fb_1772674838.jpg'),
(7, 'Fita 15 packs', 2, 'Pack', 15, '2026-03-05 01:39:06', 1, NULL, 1, 'uploads/products/69a8e13040d5e_1772675376.jpg'),
(8, 'Fita 10 packs', 2, 'Pack', 10, '2026-03-05 01:49:05', 1, NULL, 1, 'uploads/products/69a8e111aaf1c_1772675345.jpg'),
(9, 'Sky flakes box plastic', 2, 'Box', 10, '2026-03-05 01:58:41', 1, NULL, 1, 'uploads/products/69a8e3929a3e1_1772675986.jpg'),
(10, 'Fita 600g', 2, 'Tub', 1, '2026-03-05 02:06:31', 1, NULL, 1, 'uploads/products/69a8e72465766_1772676900.jpg'),
(11, 'Hansel Crackers', 2, 'Pack', 50, '2026-03-05 02:16:06', 1, NULL, 1, 'uploads/products/69a8edaa0fce9_1772678570.jpg'),
(12, 'Butter Coconut 10 packs', 2, 'Pack', 10, '2026-03-05 02:34:40', 1, NULL, 1, 'uploads/products/69a8ebc047ed8_1772678080.jpg'),
(13, 'Hansel Crackers Cheese', 2, 'Pack', 50, '2026-03-05 02:47:05', 1, NULL, 1, 'uploads/products/69a8eea9bff8b_1772678825.jpg'),
(14, 'Milky Marie 10 packs', 2, 'Pack', 10, '2026-03-05 02:47:39', 1, NULL, 1, 'uploads/products/69a8eeeeead9f_1772678894.jpg'),
(15, 'Magic Flakes', 2, 'Pack', 50, '2026-03-05 02:58:35', 1, NULL, 1, 'uploads/products/69a8f15bd6b99_1772679515.jpg'),
(16, 'Sky flakes  piece', 2, 'Piece', 50, '2026-03-05 02:59:33', 1, NULL, 1, 'uploads/products/69a8f19583234_1772679573.jpg'),
(17, 'Rebisco 20 packs', 8, '20 packs ', 0, '2026-03-05 03:01:19', 1, NULL, 1, 'uploads/products/69a8f230b8240_1772679728.jpg'),
(18, 'Butter Cookies 800g', 2, 'Tub', 1, '2026-03-05 03:01:44', 1, NULL, 1, 'uploads/products/69a8f2186f86e_1772679704.jpg'),
(19, 'Magic Junior 10 packs', 2, 'Pack', 10, '2026-03-05 03:05:03', 1, NULL, 1, 'uploads/products/69a8f2dfcb611_1772679903.jpg'),
(20, 'Sunflower Crackers 600g', 2, 'Tub', 1, '2026-03-05 03:08:13', 1, NULL, 1, 'uploads/products/69a8f3cba61e5_1772680139.jpg'),
(21, 'Galinco (Butter Cookies) 385g', 2, 'Tub', 1, '2026-03-05 03:11:35', 1, NULL, 1, 'uploads/products/69a8f52f894fd_1772680495.jpg'),
(22, 'Sunflower Crackers Original', 2, 'Pack', 50, '2026-03-05 03:12:05', 1, NULL, 1, 'uploads/products/69a8f4854e85b_1772680325.jpg'),
(23, 'New Dinmark (Butter Cookies) 454g', 2, 'Tub', 1, '2026-03-05 03:14:08', 1, NULL, 1, 'uploads/products/69a8f50084b16_1772680448.jpg'),
(24, 'Granbisco Malt and Milk', 2, 'Pack', 50, '2026-03-05 03:16:52', 1, NULL, 1, 'uploads/products/69a8f5a4ba4c6_1772680612.jpg'),
(25, 'Grandbisco Malt and Milk', 2, 'Pack', 0, '2026-03-05 03:19:10', 0, NULL, 1, 'uploads/products/69a8f62e26990_1772680750.jpg'),
(26, 'Graham Chocolate 250g', 2, 'Pack', 1, '2026-03-05 03:20:21', 1, NULL, 1, 'uploads/products/69a8f6757d45a_1772680821.jpg'),
(27, 'Bravo! With sesame seeds  10 pack', 2, 'Pack', 10, '2026-03-05 03:25:52', 1, NULL, 1, 'uploads/products/product_27_1772687957.jpg'),
(28, 'Eggnog (Cookies)', 2, 'Pack', 10, '2026-03-05 03:28:25', 1, NULL, 1, 'uploads/products/69a8f8594a950_1772681305.jpg'),
(29, 'Bread stix 10 packs', 2, 'Pack', 10, '2026-03-05 03:29:52', 1, NULL, 1, 'uploads/products/69a8f8b08abfb_1772681392.jpg'),
(30, 'Magic Flake Onion Chives', 2, '10', 50, '2026-03-05 03:31:03', 1, NULL, 1, 'uploads/products/69a8f8f7cd580_1772681463.jpg'),
(31, 'ButterCream', 2, 'Pack', 10, '2026-03-05 03:31:07', 1, NULL, 1, 'uploads/products/69a8f8fb55af7_1772681467.jpg'),
(32, 'Malkist 10 packs', 2, 'Pack', 10, '2026-03-05 03:32:36', 1, NULL, 1, 'uploads/products/69a8f954e70ee_1772681556.jpg'),
(33, 'Fibisco (Marie) 10 packs', 2, 'Pack', 10, '2026-03-05 03:36:07', 1, NULL, 1, 'uploads/products/69a8fa27eed1f_1772681767.jpg'),
(34, 'Sky Flakes Sweet Butter', 2, '10 pack', 50, '2026-03-05 03:36:54', 1, NULL, 1, 'uploads/products/69a8fa566656f_1772681814.jpg'),
(35, 'Marie biscuit bucket', 8, 'Bucket', 5, '2026-03-05 03:38:48', 1, NULL, 1, 'uploads/products/69a8fac807409_1772681928.jpg'),
(36, 'Wafer Choco (Nissin)', 2, 'Pack', 20, '2026-03-05 03:39:05', 1, NULL, 1, 'uploads/products/69a8fad90677f_1772681945.jpg'),
(37, 'Butter Cream', 2, '10 packs', 50, '2026-03-05 03:39:48', 1, NULL, 1, 'uploads/products/69a8fb043caef_1772681988.jpg'),
(38, 'Sm bonus mixed biscuits bucket', 8, 'Bucket', 5, '2026-03-05 03:41:13', 1, NULL, 1, 'uploads/products/69a8fb592e5ca_1772682073.jpg'),
(39, 'Assorted Biscuits Croley Foods', 2, 'bucket', 5, '2026-03-05 03:43:58', 1, NULL, 1, 'uploads/products/69a8fbfe8ee23_1772682238.jpg'),
(40, 'test', 2, 'pack', 10, '2026-03-05 05:33:08', 0, NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_audit`
--

CREATE TABLE `product_audit` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `action` varchar(20) NOT NULL,
  `old_data` text DEFAULT NULL,
  `new_data` text DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_audit`
--

INSERT INTO `product_audit` (`id`, `product_id`, `action`, `old_data`, `new_data`, `staff_id`, `created_at`) VALUES
(1, 1, 'ADD', NULL, '{\"name\":\"Diapers\",\"category_id\":\"3\",\"category_name\":\"Hygiene\",\"unit\":\"Pack\",\"min_stock\":\"40\",\"has_expiry\":1,\"image_path\":null}', 1, '2026-03-05 01:00:00'),
(2, 1, 'UPDATE', '{\"old\":{\"name\":\"Diapers\",\"category_id\":3,\"category_name\":\"Hygiene\",\"unit\":\"Pack\",\"min_stock\":40,\"has_expiry\":1,\"image_path\":null},\"new\":{\"name\":\"Diapers\",\"category_id\":3,\"category_name\":\"Hygiene\",\"unit\":\"Pack\",\"min_stock\":40,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8d640e253c_1772672576.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 01:02:56'),
(3, 2, 'ADD', NULL, '{\"name\":\"Saka Rice\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"5kg\",\"min_stock\":\"20\",\"has_expiry\":1,\"image_path\":null}', 1, '2026-03-05 01:10:29'),
(4, 2, 'UPDATE', '{\"old\":{\"name\":\"Saka Rice\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"5kg\",\"min_stock\":20,\"has_expiry\":1,\"image_path\":null},\"new\":{\"name\":\"Saka Rice\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"5kg\",\"min_stock\":20,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8d968959ea_1772673384.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 01:16:24'),
(5, 2, 'UPDATE', '{\"old\":{\"name\":\"Saka Rice\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"5kg\",\"min_stock\":20,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8d968959ea_1772673384.jpg\"},\"new\":{\"name\":\"Saka Rice\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"5kg\",\"min_stock\":20,\"has_expiry\":1,\"image_path\":\"\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 01:17:20'),
(6, 2, 'DELETE', '{\"name\":\"Saka Rice\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"5kg\",\"min_stock\":20,\"has_expiry\":1}', NULL, 1, '2026-03-05 01:17:39'),
(7, 3, 'ADD', NULL, '{\"name\":\"Saka Rice\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"5kg\",\"min_stock\":\"20\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8da3e9eebd_1772673598.jpg\"}', 1, '2026-03-05 01:19:58'),
(8, 4, 'ADD', NULL, '{\"name\":\"Rice\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"25kg\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":null}', 1, '2026-03-05 01:22:12'),
(9, 4, 'DELETE', '{\"name\":\"Rice\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"25kg\",\"min_stock\":10,\"has_expiry\":1}', NULL, 1, '2026-03-05 01:22:49'),
(10, 5, 'ADD', NULL, '{\"name\":\"Rebisco\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":null}', 1, '2026-03-05 01:28:42'),
(11, 5, 'UPDATE', '{\"old\":{\"name\":\"Rebisco\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":null},\"new\":{\"name\":\"Rebisco\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8dd13afa99_1772674323.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 01:32:03'),
(12, 6, 'ADD', NULL, '{\"name\":\"Sky flakes\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":null}', 1, '2026-03-05 01:34:04'),
(13, 7, 'ADD', NULL, '{\"name\":\"Fita\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"15\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8deba8df2c_1772674746.jpg\"}', 1, '2026-03-05 01:39:06'),
(14, 6, 'UPDATE', '{\"old\":{\"name\":\"Sky flakes\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":null},\"new\":{\"name\":\"Sky flakes\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8df16225fb_1772674838.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 01:40:38'),
(15, 8, 'ADD', NULL, '{\"name\":\"Fita 10 packs\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8e111aaf1c_1772675345.jpg\"}', 1, '2026-03-05 01:49:05'),
(16, 7, 'UPDATE', '{\"old\":{\"name\":\"Fita\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":15,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8deba8df2c_1772674746.jpg\"},\"new\":{\"name\":\"Fita 15 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":15,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8deba8df2c_1772674746.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 01:49:17'),
(17, 7, 'UPDATE', '{\"old\":{\"name\":\"Fita 15 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":15,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8deba8df2c_1772674746.jpg\"},\"new\":{\"name\":\"Fita 15 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":15,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8e13040d5e_1772675376.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 01:49:36'),
(18, 9, 'ADD', NULL, '{\"name\":\"Sky flakes box plastic\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Box\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":null}', 1, '2026-03-05 01:58:41'),
(19, 9, 'UPDATE', '{\"old\":{\"name\":\"Sky flakes box plastic\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Box\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":null},\"new\":{\"name\":\"Sky flakes box plastic\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Box\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8e3929a3e1_1772675986.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 01:59:46'),
(20, 10, 'ADD', NULL, '{\"name\":\"Fita 600g\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Box\",\"min_stock\":\"1\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8e5272aa45_1772676391.jpg\"}', 1, '2026-03-05 02:06:31'),
(21, 10, 'UPDATE', '{\"old\":{\"name\":\"Fita 600g\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Box\",\"min_stock\":1,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8e5272aa45_1772676391.jpg\"},\"new\":{\"name\":\"Fita 600g\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":1,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8e5272aa45_1772676391.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:14:30'),
(22, 10, 'UPDATE', '{\"old\":{\"name\":\"Fita 600g\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":1,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8e5272aa45_1772676391.jpg\"},\"new\":{\"name\":\"Fita 600g\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":1,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8e72465766_1772676900.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:15:00'),
(23, 11, 'ADD', NULL, '{\"name\":\"Hansel\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"50\",\"has_expiry\":1,\"image_path\":null}', 1, '2026-03-05 02:16:06'),
(24, 11, 'UPDATE', '{\"old\":{\"name\":\"Hansel\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":50,\"has_expiry\":1,\"image_path\":null},\"new\":{\"name\":\"Hansel\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":50,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8e7db6e339_1772677083.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:18:03'),
(25, 5, 'UPDATE', '{\"old\":{\"name\":\"Rebisco\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8dd13afa99_1772674323.jpg\"},\"new\":{\"name\":\"Rebisco 20 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8dd13afa99_1772674323.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:26:18'),
(26, 5, 'UPDATE', '{\"old\":{\"name\":\"Rebisco 20 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8dd13afa99_1772674323.jpg\"},\"new\":{\"name\":\"Rebisco 20 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8ea8047edb_1772677760.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:29:20'),
(27, 12, 'ADD', NULL, '{\"name\":\"Butter Coconut 10 packs\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8ebc047ed8_1772678080.jpg\"}', 1, '2026-03-05 02:34:40'),
(28, 11, 'UPDATE', '{\"old\":{\"name\":\"Hansel\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":50,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8e7db6e339_1772677083.jpg\"},\"new\":{\"name\":\"Hansel Crackers\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":50,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8e7db6e339_1772677083.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:38:42'),
(29, 11, 'UPDATE', '{\"old\":{\"name\":\"Hansel Crackers\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":50,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8e7db6e339_1772677083.jpg\"},\"new\":{\"name\":\"Hansel Crackers\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":50,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8edaa0fce9_1772678570.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:42:50'),
(30, 13, 'ADD', NULL, '{\"name\":\"Hansel Crackers Cheese\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"50\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8eea9bff8b_1772678825.jpg\"}', 1, '2026-03-05 02:47:05'),
(31, 14, 'ADD', NULL, '{\"name\":\"Milky Marie 10 packs\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":0,\"image_path\":\"uploads\\/products\\/69a8eecb836bd_1772678859.jpg\"}', 1, '2026-03-05 02:47:39'),
(32, 14, 'UPDATE', '{\"old\":{\"name\":\"Milky Marie 10 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":0,\"image_path\":\"uploads\\/products\\/69a8eecb836bd_1772678859.jpg\"},\"new\":{\"name\":\"Milky Marie 10 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8eecb836bd_1772678859.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:48:06'),
(33, 14, 'UPDATE', '{\"old\":{\"name\":\"Milky Marie 10 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8eecb836bd_1772678859.jpg\"},\"new\":{\"name\":\"Milky Marie 10 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8eeeeead9f_1772678894.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:48:14'),
(34, 5, 'UPDATE', '{\"old\":{\"name\":\"Rebisco 20 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8ea8047edb_1772677760.jpg\"},\"new\":{\"name\":\"Rebisco 10 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8ea8047edb_1772677760.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:49:22'),
(35, 15, 'ADD', NULL, '{\"name\":\"Magic Flakes\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"50\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f15bd6b99_1772679515.jpg\"}', 1, '2026-03-05 02:58:35'),
(36, 5, 'UPDATE', '{\"old\":{\"name\":\"Rebisco 10 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8ea8047edb_1772677760.jpg\"},\"new\":{\"name\":\"Rebisco 10 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f17e61f84_1772679550.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 02:59:10'),
(37, 16, 'ADD', NULL, '{\"name\":\"Sky flakes  piece\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Piece\",\"min_stock\":\"50\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f19583234_1772679573.jpg\"}', 1, '2026-03-05 02:59:33'),
(38, 17, 'ADD', NULL, '{\"name\":\"Rebisco 20 packs\",\"category_id\":\"8\",\"category_name\":\"Snacks\",\"unit\":\"660 grams\",\"min_stock\":\"0\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f1fff27f0_1772679679.jpg\"}', 1, '2026-03-05 03:01:20'),
(39, 18, 'ADD', NULL, '{\"name\":\"Butter Cookies 800g\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":\"1\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f2186f86e_1772679704.jpg\"}', 1, '2026-03-05 03:01:44'),
(40, 17, 'UPDATE', '{\"old\":{\"name\":\"Rebisco 20 packs\",\"category_id\":8,\"category_name\":\"Snacks\",\"unit\":\"660 grams\",\"min_stock\":0,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f1fff27f0_1772679679.jpg\"},\"new\":{\"name\":\"Rebisco 20 packs\",\"category_id\":8,\"category_name\":\"Snacks\",\"unit\":\"20 packs \",\"min_stock\":0,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8f1fff27f0_1772679679.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 03:01:50'),
(41, 17, 'UPDATE', '{\"old\":{\"name\":\"Rebisco 20 packs\",\"category_id\":8,\"category_name\":\"Snacks\",\"unit\":\"20 packs \",\"min_stock\":0,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8f1fff27f0_1772679679.jpg\"},\"new\":{\"name\":\"Rebisco 20 packs\",\"category_id\":8,\"category_name\":\"Snacks\",\"unit\":\"20 packs \",\"min_stock\":0,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f230b8240_1772679728.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 03:02:08'),
(42, 19, 'ADD', NULL, '{\"name\":\"Magic Junior 10 packs\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f2dfcb611_1772679903.jpg\"}', 1, '2026-03-05 03:05:03'),
(43, 20, 'ADD', NULL, '{\"name\":\"Sunflower Crackers\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":\"1\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f39d02e3e_1772680093.jpg\"}', 1, '2026-03-05 03:08:13'),
(44, 20, 'UPDATE', '{\"old\":{\"name\":\"Sunflower Crackers\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":1,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f39d02e3e_1772680093.jpg\"},\"new\":{\"name\":\"Sunflower Crackers 600g\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":1,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f3cba61e5_1772680139.jpg\"},\"image_replaced\":true}', NULL, 1, '2026-03-05 03:08:59'),
(45, 21, 'ADD', NULL, '{\"name\":\"Butter Cookies 385g\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":\"1\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f467739a4_1772680295.jpg\"}', 1, '2026-03-05 03:11:35'),
(46, 22, 'ADD', NULL, '{\"name\":\"Sunflower Crackers Original\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"50\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f4854e85b_1772680325.jpg\"}', 1, '2026-03-05 03:12:05'),
(47, 23, 'ADD', NULL, '{\"name\":\"New Dinmark (Butter Cookies) 454g\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":\"1\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f50084b16_1772680448.jpg\"}', 1, '2026-03-05 03:14:08'),
(48, 21, 'UPDATE', '{\"old\":{\"name\":\"Butter Cookies 385g\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":1,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f467739a4_1772680295.jpg\"},\"new\":{\"name\":\"Galinco (Butter Cookies) 385g\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Tub\",\"min_stock\":1,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f52f894fd_1772680495.jpg\"},\"image_replaced\":true}', NULL, 1, '2026-03-05 03:14:55'),
(49, 24, 'ADD', NULL, '{\"name\":\"Granbisco Malt and Milk\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"50\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f5a4ba4c6_1772680612.jpg\"}', 1, '2026-03-05 03:16:52'),
(50, 25, 'ADD', NULL, '{\"name\":\"Grandbisco Malt and Milk\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"00\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f62e26990_1772680750.jpg\"}', 1, '2026-03-05 03:19:10'),
(51, 25, 'DELETE', '{\"name\":\"Grandbisco Malt and Milk\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":0,\"has_expiry\":1}', NULL, 1, '2026-03-05 03:19:47'),
(52, 26, 'ADD', NULL, '{\"name\":\"Graham Chocolate 250g\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"1\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f6757d45a_1772680821.jpg\"}', 1, '2026-03-05 03:20:21'),
(53, 27, 'ADD', NULL, '{\"name\":\"Barvo! With sesame seeds  10 packs\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f7c0c50f8_1772681152.jpg\"}', 1, '2026-03-05 03:25:52'),
(54, 28, 'ADD', NULL, '{\"name\":\"Eggnog (Cookies)\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f8594a950_1772681305.jpg\"}', 1, '2026-03-05 03:28:25'),
(55, 29, 'ADD', NULL, '{\"name\":\"Bread stix 10 packs\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f8b08abfb_1772681392.jpg\"}', 1, '2026-03-05 03:29:52'),
(56, 30, 'ADD', NULL, '{\"name\":\"Magic Flake Onion Chives\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"10\",\"min_stock\":\"50\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f8f7cd580_1772681463.jpg\"}', 1, '2026-03-05 03:31:03'),
(57, 31, 'ADD', NULL, '{\"name\":\"ButterCream\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f8fb55af7_1772681467.jpg\"}', 1, '2026-03-05 03:31:07'),
(58, 32, 'ADD', NULL, '{\"name\":\"Malkist 10 packs\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f954e70ee_1772681556.jpg\"}', 1, '2026-03-05 03:32:36'),
(59, 33, 'ADD', NULL, '{\"name\":\"Fibisco (Marie) 10 packs\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8fa27eed1f_1772681767.jpg\"}', 1, '2026-03-05 03:36:07'),
(60, 34, 'ADD', NULL, '{\"name\":\"Sky Flakes Sweet Butter\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"10 pack\",\"min_stock\":\"50\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8fa566656f_1772681814.jpg\"}', 1, '2026-03-05 03:36:54'),
(61, 35, 'ADD', NULL, '{\"name\":\"Marie biscuit bucket\",\"category_id\":\"8\",\"category_name\":\"Snacks\",\"unit\":\"Bucket\",\"min_stock\":\"5\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8fac807409_1772681928.jpg\"}', 1, '2026-03-05 03:38:48'),
(62, 36, 'ADD', NULL, '{\"name\":\"Wafer Choco (Nissin)\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":\"20\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8fad90677f_1772681945.jpg\"}', 1, '2026-03-05 03:39:05'),
(63, 37, 'ADD', NULL, '{\"name\":\"Butter Cream\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"10 packs\",\"min_stock\":\"50\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8fb043caef_1772681988.jpg\"}', 1, '2026-03-05 03:39:48'),
(64, 38, 'ADD', NULL, '{\"name\":\"Sm bonus mixed biscuits bucket\",\"category_id\":\"8\",\"category_name\":\"Snacks\",\"unit\":\"Bucket\",\"min_stock\":\"5\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8fb592e5ca_1772682073.jpg\"}', 1, '2026-03-05 03:41:13'),
(65, 39, 'ADD', NULL, '{\"name\":\"Assorted Biscuits Croley Foods\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"bucket\",\"min_stock\":\"5\",\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8fbfe8ee23_1772682238.jpg\"}', 1, '2026-03-05 03:43:58'),
(66, 27, 'UPDATE', '{\"old\":{\"name\":\"Barvo! With sesame seeds  10 packs\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a8f7c0c50f8_1772681152.jpg\"},\"new\":{\"name\":\"Barvo! With sesame seeds  10 pack\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8f7c0c50f8_1772681152.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 05:07:45'),
(67, 27, 'UPDATE', '{\"old\":{\"name\":\"Barvo! With sesame seeds  10 pack\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"\\/inventory-system-main\\/uploads\\/products\\/69a8f7c0c50f8_1772681152.jpg\"},\"new\":{\"name\":\"Barvo! With sesame seeds  10 pack\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/product_27_1772687957.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 05:19:17'),
(68, 27, 'UPDATE', '{\"old\":{\"name\":\"Barvo! With sesame seeds  10 pack\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/product_27_1772687957.jpg\"},\"new\":{\"name\":\"Bravo! With sesame seeds  10 pack\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/product_27_1772687957.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 05:19:32'),
(69, 27, 'UPDATE', '{\"old\":{\"name\":\"Bravo! With sesame seeds  10 pack\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/product_27_1772687957.jpg\"},\"new\":{\"name\":\"Bravo With sesame seeds  10 pack\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/product_27_1772687957.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 05:19:48'),
(70, 40, 'ADD', NULL, '{\"name\":\"test\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":\"10\",\"has_expiry\":1,\"image_path\":null}', 1, '2026-03-05 05:33:08'),
(71, 40, 'DELETE', '{\"name\":\"test\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":10,\"has_expiry\":1}', NULL, 1, '2026-03-05 05:34:28'),
(72, 27, 'UPDATE', '{\"old\":{\"name\":\"Bravo With sesame seeds  10 pack\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/product_27_1772687957.jpg\"},\"new\":{\"name\":\"Bravo! With sesame seeds  10 pack\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"Pack\",\"min_stock\":10,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/product_27_1772687957.jpg\"},\"image_replaced\":false}', NULL, 1, '2026-03-05 23:59:36');

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `name`, `role`, `created_at`) VALUES
(1, 'Maria Santos', 'Nurse', '2026-02-11 05:18:02'),
(2, 'Juan Dela Cruz', 'Caregiver', '2026-02-11 05:18:02'),
(3, 'Ana Reyes', 'Volunteer', '2026-02-11 05:18:02');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `type` enum('IN','OUT') NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` > 0),
  `note` varchar(255) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `staff_id`, `supplier_id`, `type`, `quantity`, `note`, `reason`, `expiry_date`, `created_at`) VALUES
(1, 1, 1, NULL, 'IN', 50, 'Donation ', NULL, '2030-03-05', '2026-03-05 01:00:53'),
(2, 5, 1, NULL, 'IN', 18, 'Donation ', NULL, '2026-07-05', '2026-03-05 01:31:27'),
(3, 5, 1, NULL, 'IN', 1, NULL, NULL, '2026-07-05', '2026-03-05 01:32:38'),
(4, 6, 1, NULL, 'IN', 5, NULL, NULL, '2026-07-09', '2026-03-05 01:35:04'),
(5, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-21', '2026-03-05 01:35:38'),
(6, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-21', '2026-03-05 01:36:08'),
(7, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-25', '2026-03-05 01:36:52'),
(8, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-07-24', '2026-03-05 01:37:16'),
(9, 5, 1, NULL, 'IN', 5, NULL, NULL, '2026-05-01', '2026-03-05 01:38:25'),
(10, 5, 1, NULL, 'IN', 5, NULL, NULL, '2026-05-01', '2026-03-05 01:38:26'),
(11, 5, 1, NULL, 'OUT', 10, NULL, NULL, '2026-05-01', '2026-03-05 01:38:51'),
(12, 5, 1, NULL, 'IN', 5, NULL, NULL, '2026-05-01', '2026-03-05 01:39:42'),
(13, 6, 1, NULL, 'IN', 2, NULL, NULL, '2026-07-09', '2026-03-05 01:43:17'),
(14, 7, 1, NULL, 'IN', 10, 'Donation', NULL, '2026-07-01', '2026-03-05 01:43:39'),
(15, 6, 1, NULL, 'IN', 5, NULL, NULL, '2026-07-17', '2026-03-05 01:45:05'),
(16, 7, 1, NULL, 'IN', 5, 'Donation', NULL, '2027-04-01', '2026-03-05 01:46:10'),
(17, 6, 1, NULL, 'IN', 2, NULL, NULL, '2026-07-27', '2026-03-05 01:47:22'),
(18, 6, 1, NULL, 'IN', 7, NULL, NULL, '2026-07-26', '2026-03-05 01:50:03'),
(19, 7, 1, NULL, 'OUT', 5, NULL, NULL, '2027-04-01', '2026-03-05 01:50:09'),
(20, 7, 1, NULL, 'IN', 5, 'Donation', NULL, '2026-04-01', '2026-03-05 01:50:37'),
(21, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-07-26', '2026-03-05 01:50:40'),
(22, 8, 1, NULL, 'IN', 2, 'Donation', NULL, '2026-04-01', '2026-03-05 01:51:17'),
(23, 6, 1, NULL, 'IN', 2, NULL, NULL, '2026-07-24', '2026-03-05 01:52:03'),
(24, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-07-25', '2026-03-05 01:53:19'),
(25, 7, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-07-01', '2026-03-05 01:53:31'),
(26, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-06-03', '2026-03-05 01:54:06'),
(27, 8, 1, NULL, 'IN', 5, 'Donation', NULL, '2026-07-01', '2026-03-05 01:54:22'),
(28, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-10', '2026-03-05 01:54:43'),
(29, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-24', '2026-03-05 01:56:00'),
(30, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-25', '2026-03-05 01:56:19'),
(31, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-30', '2026-03-05 01:56:59'),
(32, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-10', '2026-03-05 01:57:22'),
(33, 8, 1, NULL, 'IN', 14, 'Donation', NULL, '2026-07-01', '2026-03-05 01:57:50'),
(34, 9, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-31', '2026-03-05 01:59:20'),
(35, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-15', '2026-03-05 02:00:43'),
(36, 8, 1, NULL, 'IN', 8, 'Donation', NULL, '2026-06-01', '2026-03-05 02:00:59'),
(37, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-21', '2026-03-05 02:01:15'),
(38, 7, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-07-01', '2026-03-05 02:01:17'),
(39, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-06-13', '2026-03-05 02:01:46'),
(40, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-15', '2026-03-05 02:02:23'),
(41, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-06-05', '2026-03-05 02:02:46'),
(42, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-11', '2026-03-05 02:03:02'),
(43, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-13', '2026-03-05 02:03:18'),
(44, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-08', '2026-03-05 02:03:34'),
(45, 8, 1, NULL, 'IN', 24, 'Donation', NULL, '2026-07-01', '2026-03-05 02:03:38'),
(46, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-08', '2026-03-05 02:03:58'),
(47, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-21', '2026-03-05 02:05:36'),
(48, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-14', '2026-03-05 02:06:05'),
(49, 6, 1, NULL, 'IN', 2, NULL, NULL, '2026-05-15', '2026-03-05 02:06:49'),
(50, 10, 1, NULL, 'IN', 5, 'Donation', NULL, '2026-06-01', '2026-03-05 02:07:00'),
(51, 10, 1, NULL, 'OUT', 5, NULL, NULL, '2026-06-01', '2026-03-05 02:07:43'),
(52, 10, 1, NULL, 'IN', 5, 'Donation', NULL, '2026-06-30', '2026-03-05 02:09:08'),
(53, 10, 1, NULL, 'IN', 2, 'Donation', NULL, '2026-08-14', '2026-03-05 02:09:35'),
(54, 6, 1, NULL, 'OUT', 2, NULL, NULL, '2026-07-27', '2026-03-05 02:09:58'),
(55, 6, 1, NULL, 'IN', 2, NULL, NULL, '2026-07-26', '2026-03-05 02:10:09'),
(56, 10, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-07-23', '2026-03-05 02:10:59'),
(57, 9, 1, NULL, 'IN', 4, NULL, NULL, '2026-08-07', '2026-03-05 02:11:36'),
(58, 10, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-03-11', '2026-03-05 02:11:56'),
(59, 9, 1, NULL, 'IN', 1, NULL, NULL, '2026-08-14', '2026-03-05 02:11:56'),
(60, 10, 1, NULL, 'IN', 2, 'Donation', NULL, '2026-05-20', '2026-03-05 02:12:22'),
(61, 9, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-31', '2026-03-05 02:12:42'),
(62, 6, 1, NULL, 'IN', 4, NULL, NULL, '2026-05-15', '2026-03-05 02:13:37'),
(63, 6, 1, NULL, 'IN', 14, NULL, NULL, '2026-05-15', '2026-03-05 02:14:52'),
(64, 11, 1, NULL, 'IN', 4, 'Donation', NULL, '2026-05-01', '2026-03-05 02:19:31'),
(65, 8, 1, NULL, 'IN', 7, 'Donation', NULL, '2026-09-01', '2026-03-05 02:19:52'),
(66, 7, 1, NULL, 'IN', 12, 'Donation', NULL, '2026-09-01', '2026-03-05 02:20:25'),
(67, 6, 1, NULL, 'IN', 15, NULL, NULL, '2026-06-01', '2026-03-05 02:20:25'),
(68, 11, 1, NULL, 'IN', 26, 'Donation', NULL, '2026-04-12', '2026-03-05 02:21:59'),
(69, 8, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-05-01', '2026-03-05 02:24:51'),
(70, 6, 1, NULL, 'IN', 30, NULL, NULL, '2026-05-24', '2026-03-05 02:25:10'),
(71, 10, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-04-30', '2026-03-05 02:25:37'),
(72, 8, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-07-01', '2026-03-05 02:26:28'),
(73, 6, 1, NULL, 'IN', 2, NULL, NULL, '2026-05-21', '2026-03-05 02:29:04'),
(74, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-22', '2026-03-05 02:29:30'),
(75, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-30', '2026-03-05 02:29:58'),
(76, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-21', '2026-03-05 02:30:21'),
(77, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-29', '2026-03-05 02:30:53'),
(78, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-21', '2026-03-05 02:31:30'),
(79, 5, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-01', '2026-03-05 02:31:30'),
(80, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-24', '2026-03-05 02:32:50'),
(81, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-24', '2026-03-05 02:33:13'),
(82, 6, 1, NULL, 'IN', 2, NULL, NULL, '2026-05-15', '2026-03-05 02:33:30'),
(83, 6, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-21', '2026-03-05 02:33:48'),
(84, 11, 1, NULL, 'IN', 3, 'Donation ', NULL, '2026-06-20', '2026-03-05 02:34:52'),
(85, 12, 1, NULL, 'IN', 1, 'Donatin', NULL, '2026-07-17', '2026-03-05 02:38:04'),
(86, 12, 1, NULL, 'IN', 4, 'Donation', NULL, '2026-06-24', '2026-03-05 02:41:24'),
(87, 12, 1, NULL, 'IN', 4, 'Donation', NULL, '2026-06-24', '2026-03-05 02:41:26'),
(88, 12, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-06-30', '2026-03-05 02:41:51'),
(89, 12, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-06-09', '2026-03-05 02:42:34'),
(90, 12, 1, NULL, 'OUT', 6, NULL, NULL, '2026-06-24', '2026-03-05 02:45:35'),
(91, 13, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-05-01', '2026-03-05 02:47:55'),
(92, 14, 1, NULL, 'IN', 52, 'Donation', NULL, '2026-06-01', '2026-03-05 02:50:28'),
(93, 14, 1, NULL, 'IN', 7, 'Donation', NULL, '2026-06-01', '2026-03-05 02:52:18'),
(94, 12, 1, NULL, 'OUT', 1, NULL, NULL, '2026-06-24', '2026-03-05 02:53:04'),
(95, 5, 1, NULL, 'OUT', 5, NULL, NULL, '2026-05-01', '2026-03-05 02:55:29'),
(96, 16, 1, NULL, 'IN', 72, NULL, NULL, '2026-05-20', '2026-03-05 03:00:34'),
(97, 15, 1, NULL, 'IN', 5, 'Donation', NULL, '2026-04-01', '2026-03-05 03:01:46'),
(98, 18, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-05-22', '2026-03-05 03:02:02'),
(99, 16, 1, NULL, 'IN', 21, NULL, NULL, '2026-04-23', '2026-03-05 03:02:27'),
(100, 16, 1, NULL, 'IN', 2, NULL, NULL, '2026-04-22', '2026-03-05 03:02:47'),
(101, 15, 1, NULL, 'IN', 3, 'Donation', NULL, '2026-07-01', '2026-03-05 03:03:05'),
(102, 16, 1, NULL, 'IN', 13, NULL, NULL, '2026-04-10', '2026-03-05 03:03:24'),
(103, 17, 1, NULL, 'IN', 2, NULL, NULL, '2026-04-03', '2026-03-05 03:03:57'),
(104, 15, 1, NULL, 'IN', 5, 'Donation', NULL, '2026-06-18', '2026-03-05 03:04:59'),
(105, 17, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-12', '2026-03-05 03:05:11'),
(106, 15, 1, NULL, 'IN', 2, 'Donation', NULL, '2026-07-01', '2026-03-05 03:05:31'),
(107, 17, 1, NULL, 'IN', 5, NULL, NULL, '2026-04-18', '2026-03-05 03:05:50'),
(108, 17, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-19', '2026-03-05 03:06:06'),
(109, 19, 1, NULL, 'IN', 12, 'Donation', NULL, '2026-04-08', '2026-03-05 03:06:15'),
(110, 17, 1, NULL, 'IN', 1, NULL, NULL, '2026-03-25', '2026-03-05 03:06:26'),
(111, 15, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-05-21', '2026-03-05 03:06:27'),
(112, 15, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-03-12', '2026-03-05 03:06:57'),
(113, 15, 1, NULL, 'OUT', 1, NULL, NULL, '2026-03-12', '2026-03-05 03:07:44'),
(114, 20, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-10-01', '2026-03-05 03:09:31'),
(115, 16, 1, NULL, 'IN', 81, NULL, NULL, '2026-05-20', '2026-03-05 03:09:41'),
(116, 17, 1, NULL, 'IN', 7, NULL, NULL, '2026-05-01', '2026-03-05 03:10:06'),
(117, 16, 1, NULL, 'IN', 23, NULL, NULL, '2026-05-23', '2026-03-05 03:11:32'),
(118, 17, 1, NULL, 'IN', 8, NULL, NULL, '2026-05-09', '2026-03-05 03:11:33'),
(119, 17, 1, NULL, 'IN', 7, NULL, NULL, '2026-06-20', '2026-03-05 03:12:47'),
(120, 21, 1, NULL, 'IN', 3, 'Donation', NULL, '2026-10-18', '2026-03-05 03:12:49'),
(121, 22, 1, NULL, 'IN', 5, 'Donation', NULL, '2026-12-01', '2026-03-05 03:13:50'),
(122, 17, 1, NULL, 'IN', 6, NULL, NULL, '2026-06-13', '2026-03-05 03:13:52'),
(123, 22, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-08-01', '2026-03-05 03:14:15'),
(124, 23, 1, NULL, 'IN', 2, 'Donation', NULL, '2026-08-18', '2026-03-05 03:15:48'),
(125, 22, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-08-01', '2026-03-05 03:18:10'),
(126, 26, 1, NULL, 'IN', 20, 'Donation', NULL, '2026-07-19', '2026-03-05 03:20:42'),
(127, 19, 1, NULL, 'IN', 30, NULL, NULL, '2026-05-25', '2026-03-05 03:21:13'),
(128, 8, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-12', '2026-03-05 03:23:34'),
(129, 7, 1, NULL, 'IN', 1, NULL, NULL, '2026-08-15', '2026-03-05 03:24:14'),
(130, 15, 1, NULL, 'IN', 1, NULL, NULL, '2026-06-18', '2026-03-05 03:25:09'),
(131, 24, 1, NULL, 'IN', 10, NULL, NULL, '2026-10-10', '2026-03-05 03:25:22'),
(132, 24, 1, NULL, 'IN', 10, NULL, NULL, '2026-10-10', '2026-03-05 03:25:56'),
(133, 27, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-04-14', '2026-03-05 03:26:14'),
(134, 28, 1, NULL, 'IN', 2, 'Donation', NULL, '2026-07-06', '2026-03-05 03:28:51'),
(135, 15, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-09', '2026-03-05 03:29:14'),
(136, 29, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-05-26', '2026-03-05 03:30:23'),
(137, 31, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-08-05', '2026-03-05 03:31:30'),
(138, 30, 1, NULL, 'IN', 7, NULL, NULL, '2026-04-01', '2026-03-05 03:33:08'),
(139, 29, 1, NULL, 'IN', 2, 'Donation', NULL, '2026-05-12', '2026-03-05 03:33:20'),
(140, 31, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-10-21', '2026-03-05 03:34:01'),
(141, 33, 1, NULL, 'IN', 2, 'Donation', NULL, '2026-08-25', '2026-03-05 03:37:13'),
(142, 34, 1, NULL, 'IN', 1, NULL, NULL, '2026-05-01', '2026-03-05 03:37:27'),
(143, 33, 1, NULL, 'IN', 2, 'Donation', NULL, '2026-06-29', '2026-03-05 03:37:36'),
(144, 34, 1, NULL, 'OUT', 1, NULL, NULL, '2026-05-01', '2026-03-05 03:38:15'),
(145, 34, 1, NULL, 'IN', 3, NULL, NULL, '2026-05-26', '2026-03-05 03:38:29'),
(146, 35, 1, NULL, 'IN', 7, NULL, NULL, '2026-05-28', '2026-03-05 03:39:44'),
(147, 35, 1, NULL, 'IN', 3, NULL, NULL, '2026-06-20', '2026-03-05 03:39:58'),
(148, 36, 1, NULL, 'IN', 1, 'Donation', NULL, '2026-05-01', '2026-03-05 03:40:27'),
(149, 37, 1, NULL, 'IN', 2, NULL, NULL, '2026-11-14', '2026-03-05 03:40:35'),
(150, 27, 1, NULL, 'IN', 1, NULL, NULL, '2026-04-01', '2026-03-05 03:41:04'),
(151, 38, 1, NULL, 'IN', 1, NULL, NULL, '2026-11-20', '2026-03-05 03:42:14'),
(152, 38, 1, NULL, 'IN', 1, NULL, NULL, '2026-11-03', '2026-03-05 03:42:27'),
(153, 39, 1, NULL, 'IN', 1, NULL, NULL, '2026-07-05', '2026-03-05 03:44:21'),
(154, 39, 1, NULL, 'IN', 1, NULL, NULL, '2027-01-16', '2026-03-05 03:44:38'),
(155, 40, 1, NULL, 'IN', 10, NULL, NULL, '2026-10-13', '2026-03-05 05:33:33'),
(156, 40, 1, NULL, 'IN', 12, NULL, NULL, '2026-07-14', '2026-03-05 05:34:00');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `pass`, `created_at`, `remember_token`) VALUES
(1, 'admin', 'admin123', '2026-02-19 05:16:18', NULL),
(2, 'staff', 'staff123', '2026-02-19 05:16:18', NULL),
(3, 'manager', 'manager123', '2026-02-19 05:16:18', NULL),
(4, 'jm', 'Admin@123', '2026-02-19 07:46:15', NULL),
(5, 'jmm', 'Admin@123', '2026-02-19 07:47:08', NULL),
(6, 'AADMIN', 'Admin@12345', '2026-02-20 06:44:42', NULL),
(7, 'markmark', 'Markmark@1', '2026-02-25 03:48:44', NULL);

-- --------------------------------------------------------

--
-- Structure for view `current_stock`
--
DROP TABLE IF EXISTS `current_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `current_stock`  AS SELECT `p`.`id` AS `product_id`, `p`.`name` AS `name`, `c`.`name` AS `category`, `p`.`unit` AS `unit`, `p`.`min_stock` AS `min_stock`, coalesce(sum(case when `sm`.`type` = 'IN' then `sm`.`quantity` when `sm`.`type` = 'OUT' then -`sm`.`quantity` end),0) AS `current_quantity` FROM ((`products` `p` join `categories` `c` on(`p`.`category_id` = `c`.`id`)) left join `stock_movements` `sm` on(`p`.`id` = `sm`.`product_id`)) GROUP BY `p`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `acc`
--
ALTER TABLE `acc`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_audit`
--
ALTER TABLE `product_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `acc`
--
ALTER TABLE `acc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `product_audit`
--
ALTER TABLE `product_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `product_audit`
--
ALTER TABLE `product_audit`
  ADD CONSTRAINT `product_audit_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_audit_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
