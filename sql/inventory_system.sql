-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 24, 2026 at 03:27 AM
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
  `expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category_id`, `unit`, `min_stock`, `created_at`, `is_active`, `expiry_date`) VALUES
(1, 'sack rice', 2, 'sack 25 kg', 5, '2026-02-24 01:49:48', 1, NULL),
(2, 'sanitary wipes', 3, 'packs', 10, '2026-02-24 02:13:36', 1, NULL);

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
(1, 1, NULL, NULL, 'IN', 10, 'purchase', NULL, '2026-05-14', '2026-02-24 01:50:16'),
(2, 1, NULL, NULL, 'IN', 4, NULL, NULL, '2026-05-14', '2026-02-24 01:50:27'),
(3, 1, NULL, NULL, 'IN', 3, 'purchase', NULL, '2026-05-14', '2026-02-24 01:50:48'),
(4, 1, NULL, NULL, 'IN', 10, 'purchase', NULL, '2026-04-17', '2026-02-24 01:51:06'),
(5, 1, NULL, NULL, 'OUT', 2, 'used', NULL, '2026-04-17', '2026-02-24 01:51:32'),
(6, 1, NULL, NULL, 'OUT', 4, 'used', NULL, '2026-04-17', '2026-02-24 01:51:41'),
(7, 2, NULL, NULL, 'IN', 15, NULL, NULL, '2028-10-12', '2026-02-24 02:14:06');

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
(1, 'admin', 'admin123', '2026-02-19 05:16:18', '9f94c8aa9a5c3575d899368135314aa83ddb021e680f9875652c830e9fe048b8'),
(2, 'staff', 'staff123', '2026-02-19 05:16:18', NULL),
(3, 'manager', 'manager123', '2026-02-19 05:16:18', NULL),
(4, 'jm', 'Admin@123', '2026-02-19 07:46:15', NULL),
(5, 'jmm', 'Admin@123', '2026-02-19 07:47:08', NULL),
(6, 'AADMIN', 'Admin@12345', '2026-02-20 06:44:42', NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
