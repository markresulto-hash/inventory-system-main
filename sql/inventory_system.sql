-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 02:52 AM
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
(1, 'fita', 2, 'piece', 10, '2026-02-24 07:56:17', 0, NULL, 1, NULL),
(2, 'tissue', 3, 'pack', 10, '2026-02-24 07:56:34', 1, NULL, 0, 'uploads/products/69a4ed21dad59_1772416289.jpg'),
(3, 'medical gloves', 4, 'piece', 10, '2026-02-24 07:56:57', 1, NULL, 0, NULL),
(4, 'diapers', 3, 'piece', 30, '2026-02-24 08:16:29', 0, NULL, 0, NULL),
(5, 'rice sack', 2, 'kilogram', 50, '2026-02-24 08:17:05', 1, NULL, 1, NULL),
(6, 'hansel', 3, 'pack', 40, '2026-02-25 03:49:39', 0, NULL, 1, NULL),
(7, 'milo', 8, 'piece', 50, '2026-02-25 03:57:29', 1, NULL, 1, NULL),
(8, 'water botttles', 2, 'bottle', 60, '2026-02-25 04:08:37', 0, NULL, 1, NULL),
(9, 'Wipes', 3, 'pack', 20, '2026-02-25 07:29:31', 1, NULL, 0, NULL),
(10, 'HANSEL', 2, 'pack', 100, '2026-02-25 07:35:25', 1, NULL, 1, 'uploads/products/69a4eaab6ded2_1772415659.jpg'),
(11, 'water bottle', 2, 'piece', 30, '2026-03-02 01:19:50', 1, NULL, 1, NULL),
(12, 'boa', 2, 'piece', 1, '2026-03-02 01:32:38', 1, NULL, 0, 'uploads/products/69a4e8b6486a7_1772415158.jpg');

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
(1, 7, 'ADD', NULL, '{\"name\":\"milo\",\"category_id\":\"8\",\"category_name\":\"Snacks\",\"unit\":\"piece\",\"min_stock\":\"50\",\"has_expiry\":1}', 7, '2026-02-25 03:57:29'),
(2, 8, 'ADD', NULL, '{\"name\":\"water botttles\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"bottle\",\"min_stock\":\"60\",\"has_expiry\":1}', 7, '2026-02-25 04:08:37'),
(3, 9, 'ADD', NULL, '{\"name\":\"Wipes\",\"category_id\":\"3\",\"category_name\":\"Hygiene\",\"unit\":\"pack\",\"min_stock\":\"20\",\"has_expiry\":0}', 7, '2026-02-25 07:29:31'),
(4, 10, 'ADD', NULL, '{\"name\":\"HANSEL\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":\"100000\",\"has_expiry\":1}', 7, '2026-02-25 07:35:25'),
(5, 1, 'DELETE', '{\"name\":\"fita\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"piece\",\"min_stock\":10,\"has_expiry\":1}', NULL, 7, '2026-02-27 06:54:43'),
(6, 10, 'UPDATE', '{\"old\":{\"name\":\"HANSEL\",\"category_id\":2,\"unit\":\"pack\",\"min_stock\":100000,\"has_expiry\":1},\"new\":{\"name\":\"HANSEL\",\"category_id\":\"2\",\"unit\":\"pack\",\"min_stock\":\"100000\",\"has_expiry\":\"0\"}}', NULL, 7, '2026-02-27 07:16:39'),
(7, 10, 'UPDATE', '{\"old\":{\"name\":\"HANSEL\",\"category_id\":2,\"unit\":\"pack\",\"min_stock\":100000,\"has_expiry\":0},\"new\":{\"name\":\"HANSEL\",\"category_id\":\"2\",\"unit\":\"pack\",\"min_stock\":\"100000\",\"has_expiry\":\"1\"}}', NULL, 7, '2026-02-27 07:16:50'),
(8, 10, 'UPDATE', '{\"old\":{\"name\":\"HANSEL\",\"category_id\":2,\"unit\":\"pack\",\"min_stock\":100000,\"has_expiry\":1},\"new\":{\"name\":\"HANSEL\",\"category_id\":\"2\",\"unit\":\"pack\",\"min_stock\":\"1070\",\"has_expiry\":\"1\"}}', NULL, 7, '2026-02-27 07:17:06'),
(9, 10, 'UPDATE', '{\"old\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":1070,\"has_expiry\":1},\"new\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":200,\"has_expiry\":0}}', NULL, 7, '2026-02-27 07:25:07'),
(10, 10, 'UPDATE', '{\"old\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":200,\"has_expiry\":0},\"new\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1}}', NULL, 7, '2026-02-27 07:26:18'),
(11, 10, 'UPDATE', '{\"old\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1},\"new\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1}}', NULL, 1, '2026-03-02 01:16:23'),
(12, 10, 'UPDATE', '{\"old\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1},\"new\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1}}', NULL, 1, '2026-03-02 01:18:38'),
(13, 11, 'ADD', NULL, '{\"name\":\"water bottle\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"piece\",\"min_stock\":\"30\",\"has_expiry\":1}', 1, '2026-03-02 01:19:50'),
(14, 10, 'UPDATE', '{\"old\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1,\"image_path\":null},\"new\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a4e6d55470e_1772414677.jpg\"}}', NULL, 1, '2026-03-02 01:24:37'),
(15, 10, 'UPDATE', '{\"old\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a4e6d55470e_1772414677.jpg\"},\"new\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a4e7f82bc33_1772414968.jpg\"}}', NULL, 1, '2026-03-02 01:29:28'),
(16, 12, 'ADD', NULL, '{\"name\":\"boa\",\"category_id\":\"2\",\"category_name\":\"Food\",\"unit\":\"piece\",\"min_stock\":\"1\",\"has_expiry\":0,\"image_path\":\"uploads\\/products\\/69a4e8b6486a7_1772415158.jpg\"}', 1, '2026-03-02 01:32:38'),
(17, 10, 'UPDATE', '{\"old\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a4e7f82bc33_1772414968.jpg\"},\"new\":{\"name\":\"HANSEL\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"pack\",\"min_stock\":100,\"has_expiry\":1,\"image_path\":\"uploads\\/products\\/69a4eaab6ded2_1772415659.jpg\"}}', NULL, 1, '2026-03-02 01:40:59'),
(18, 8, 'DELETE', '{\"name\":\"water botttles\",\"category_id\":2,\"category_name\":\"Food\",\"unit\":\"bottle\",\"min_stock\":60,\"has_expiry\":1}', NULL, 1, '2026-03-02 01:44:13'),
(19, 2, 'UPDATE', '{\"old\":{\"name\":\"tissue\",\"category_id\":3,\"category_name\":\"Hygiene\",\"unit\":\"pack\",\"min_stock\":10,\"has_expiry\":0,\"image_path\":null},\"new\":{\"name\":\"tissue\",\"category_id\":3,\"category_name\":\"Hygiene\",\"unit\":\"pack\",\"min_stock\":10,\"has_expiry\":0,\"image_path\":\"uploads\\/products\\/69a4ed21dad59_1772416289.jpg\"}}', NULL, 1, '2026-03-02 01:51:29');

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
(1, 1, NULL, NULL, 'IN', 12, NULL, NULL, '2027-09-16', '2026-02-24 08:11:10'),
(2, 1, NULL, NULL, 'OUT', 12, NULL, NULL, '2027-09-16', '2026-02-24 08:11:19'),
(3, 5, NULL, NULL, 'IN', 25, NULL, NULL, '2027-11-19', '2026-02-24 08:17:23'),
(4, 5, NULL, NULL, 'IN', 10, 'donation', NULL, '2028-11-17', '2026-02-24 08:17:44'),
(5, 5, NULL, NULL, 'OUT', 10, 'consumed', NULL, '2028-11-17', '2026-02-24 08:18:15'),
(6, 4, 1, NULL, 'IN', 10, NULL, NULL, NULL, '2026-02-25 02:59:53'),
(7, 1, 1, NULL, 'IN', 20, NULL, NULL, '2027-07-16', '2026-02-25 03:00:29'),
(8, 4, 1, NULL, 'IN', 10, 'donation', NULL, NULL, '2026-02-25 03:41:02'),
(15, 7, 7, NULL, 'IN', 50, NULL, NULL, '2028-09-07', '2026-02-25 03:57:53'),
(16, 5, 7, NULL, 'IN', 1, NULL, NULL, '2028-03-25', '2026-02-25 05:28:58'),
(17, 5, 7, NULL, 'OUT', 1, NULL, NULL, '2027-11-19', '2026-02-25 05:29:58'),
(18, 5, 7, NULL, 'OUT', 1, NULL, NULL, '2027-11-19', '2026-02-25 05:30:07'),
(19, 5, 7, NULL, 'OUT', 1, NULL, NULL, '2027-11-19', '2026-02-25 05:30:34'),
(20, 1, 7, NULL, 'OUT', 20, 'Meryenda', NULL, '2027-07-16', '2026-02-25 07:31:11'),
(21, 10, 7, NULL, 'IN', 100, NULL, NULL, '2027-02-25', '2026-02-25 07:38:01'),
(22, 10, 7, NULL, 'OUT', 100, 'ubos na', NULL, '2027-02-25', '2026-02-25 07:38:29'),
(23, 10, 7, NULL, 'IN', 100, 'donation', NULL, '2030-06-12', '2026-02-27 07:34:18'),
(24, 10, 7, NULL, 'IN', 120, NULL, NULL, '2028-09-25', '2026-02-27 07:34:47'),
(25, 10, 7, NULL, 'OUT', 40, 'used', NULL, '2028-09-25', '2026-02-27 07:36:33'),
(26, 10, 1, NULL, 'OUT', 20, NULL, NULL, '2028-09-25', '2026-02-27 07:50:55'),
(27, 10, 1, NULL, 'IN', 10, NULL, NULL, '2028-09-25', '2026-03-02 01:17:50'),
(28, 10, 1, NULL, 'OUT', 10, NULL, NULL, '2028-09-25', '2026-03-02 01:18:02'),
(29, 12, 1, NULL, 'IN', 1, 'one and only boa', NULL, NULL, '2026-03-02 01:43:50');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `product_audit`
--
ALTER TABLE `product_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

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
