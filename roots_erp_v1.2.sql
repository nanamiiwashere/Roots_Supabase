-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 02, 2026 at 10:45 AM
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
-- Database: `roots_erp`
--

-- --------------------------------------------------------

--
-- Table structure for table `analytics_activity`
--

CREATE TABLE `analytics_activity` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `activity_type` enum('order','finance') NOT NULL DEFAULT 'order',
  `activity_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `analytics_activity`
--

INSERT INTO `analytics_activity` (`id`, `workspace_id`, `title`, `code`, `status`, `activity_type`, `activity_date`, `created_at`) VALUES
(2300, 2, 'New order from Renren - Lemon Pie', 'CRH-4163', 'On Progress', 'order', '2026-05-31', '2026-06-02 08:44:59'),
(2301, 2, 'New order from Rey - Matcha Pie', 'CRH-2034', 'Delivered', 'order', '2026-06-01', '2026-06-02 08:44:59'),
(2302, 2, 'New order from Baki - Choco Pie', 'CRH-4676', 'Delivered', 'order', '2026-06-01', '2026-06-02 08:44:59'),
(2303, 2, 'New order from Nadia - Lemon Pie', 'CRH-8801', 'Delivered', 'order', '2026-06-01', '2026-06-02 08:44:59'),
(2304, 2, 'New order from Hasan - Choco Pie', 'CRH-6620', 'Cancelled', 'order', '2026-06-01', '2026-06-02 08:44:59'),
(2305, 2, 'New order from Lila - Strawberry Pie', 'CRH-7743', 'Delivered', 'order', '2026-05-31', '2026-06-02 08:44:59'),
(2306, 2, 'New order from Danu - Blueberry Pie', 'CRH-5012', 'On Progress', 'order', '2026-05-31', '2026-06-02 08:44:59'),
(2307, 2, 'New order from Sinta - Apple Pie', 'CRH-3891', 'Delivered', 'order', '2026-05-31', '2026-06-02 08:44:59'),
(2308, 2, 'Expense paid: Loan', 'TX-7891', 'Success', 'finance', '2026-06-02', '2026-06-02 08:44:59'),
(2309, 2, 'Income received: Retail Sales', 'TX-5406', 'Success', 'finance', '2026-06-02', '2026-06-02 08:44:59'),
(2310, 2, 'Income received: Marketplace Bonus', 'TX-6884', 'Success', 'finance', '2026-06-01', '2026-06-02 08:44:59'),
(2311, 2, 'Expense paid: Internet ', 'TX-8890', 'Success', 'finance', '2026-06-01', '2026-06-02 08:44:59'),
(2312, 2, 'Income received: Retail Sales', 'TX-6852', 'Success', 'finance', '2026-06-01', '2026-06-02 08:44:59'),
(2313, 2, 'Expense paid: Bahan Baku', 'TX-6967', 'Success', 'finance', '2026-06-01', '2026-06-02 08:44:59'),
(2314, 2, 'Income received: Retail Sale', 'TX-3849', 'Success', 'finance', '2026-06-01', '2026-06-02 08:44:59'),
(2315, 2, 'Expense paid: Stock (Bahan Baku)', 'TX-8011', 'Success', 'finance', '2026-06-01', '2026-06-02 08:44:59'),
(2316, 2, 'Income received: Retail Sale', 'CRH-2034', 'Success', 'finance', '2026-06-01', '2026-06-02 08:44:59'),
(2317, 2, 'Income received: Retail Sale Marketplace', 'CRH-4676', 'Success', 'finance', '2026-05-31', '2026-06-02 08:44:59');

-- --------------------------------------------------------

--
-- Table structure for table `analytics_metrics`
--

CREATE TABLE `analytics_metrics` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `metric_key` varchar(50) NOT NULL,
  `metric_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `change_percent` decimal(6,2) DEFAULT NULL,
  `trend` enum('up','down','flat') NOT NULL DEFAULT 'up',
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `analytics_metrics`
--

INSERT INTO `analytics_metrics` (`id`, `workspace_id`, `metric_key`, `metric_value`, `change_percent`, `trend`, `sort_order`) VALUES
(1, 2, 'revenue', 3714000.00, NULL, 'up', 1),
(2, 2, 'sales', 29.00, NULL, 'up', 2),
(3, 2, 'orders', 7.00, NULL, 'up', 3),
(4, 2, 'profit', 2644000.00, NULL, 'up', 4),
(287, 1, 'revenue', 0.00, NULL, 'up', 1),
(288, 1, 'sales', 0.00, NULL, 'up', 2),
(289, 1, 'orders', 0.00, NULL, 'up', 3),
(290, 1, 'profit', 0.00, NULL, 'up', 4);

-- --------------------------------------------------------

--
-- Table structure for table `analytics_monthly`
--

CREATE TABLE `analytics_monthly` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `month_label` varchar(20) NOT NULL,
  `sales` int(11) NOT NULL DEFAULT 0,
  `revenue` decimal(18,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `analytics_monthly`
--

INSERT INTO `analytics_monthly` (`id`, `workspace_id`, `month_label`, `sales`, `revenue`, `sort_order`) VALUES
(285, 2, 'May', 4, 2997000.00, 1),
(286, 2, 'Jun', 3, 2191000.00, 2);

-- --------------------------------------------------------

--
-- Table structure for table `analytics_reviews`
--

CREATE TABLE `analytics_reviews` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `reviewer_name` varchar(255) NOT NULL,
  `rating` tinyint(1) NOT NULL DEFAULT 5,
  `comment` text DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `total_purchase` decimal(18,2) NOT NULL DEFAULT 0.00,
  `last_transaction` date DEFAULT NULL,
  `segment` enum('new','regular') NOT NULL DEFAULT 'new',
  `contacted` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `workspace_id`, `name`, `email`, `total_purchase`, `last_transaction`, `segment`, `contacted`, `created_at`) VALUES
(7, 2, 'Baki', 'baki@gmail.com', 64000.00, '2026-06-01', 'new', 0, '2026-05-31 19:44:12'),
(8, 2, 'Rey', 'reykawaii@gmail.com', 175000.00, '2026-06-01', 'new', 1, '2026-06-01 12:32:02'),
(9, 2, 'Renren', 'renren@gmail.com', 320000.00, '2026-06-01', 'regular', 1, '2026-06-01 15:58:22');

-- --------------------------------------------------------

--
-- Table structure for table `finance_transactions`
--

CREATE TABLE `finance_transactions` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `tx_code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('income','expense') NOT NULL DEFAULT 'income',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('success','pending') NOT NULL DEFAULT 'success',
  `tx_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `finance_transactions`
--

INSERT INTO `finance_transactions` (`id`, `workspace_id`, `tx_code`, `title`, `type`, `amount`, `status`, `tx_date`, `created_at`) VALUES
(1, 2, 'CRH-4676', 'Retail Sale Marketplace', 'income', 50000.00, 'success', '2026-05-31', '2026-06-01 11:56:30'),
(2, 2, 'CRH-2034', 'Retail Sale', 'income', 175000.00, 'success', '2026-06-01', '2026-06-01 12:32:31'),
(3, 2, 'TX-8011', 'Stock (Bahan Baku)', 'expense', 50000.00, 'success', '2026-06-01', '2026-06-01 12:37:50'),
(4, 2, 'TX-3849', 'Retail Sale', 'income', 1520000.00, 'success', '2026-06-01', '2026-06-01 13:52:12'),
(5, 2, 'TX-6967', 'Bahan Baku', 'expense', 500000.00, 'success', '2026-06-01', '2026-06-01 13:52:35'),
(7, 2, 'TX-6852', 'Retail Sales', 'income', 264000.00, 'success', '2026-06-01', '2026-06-01 15:24:55'),
(8, 2, 'TX-8890', 'Internet ', 'expense', 395000.00, 'success', '2026-06-01', '2026-06-01 15:42:03'),
(9, 2, 'TX-6884', 'Marketplace Bonus', 'income', 255000.00, 'success', '2026-06-01', '2026-06-01 16:29:55'),
(10, 2, 'TX-5406', 'Retail Sales', 'income', 1450000.00, 'success', '2026-06-02', '2026-06-02 06:06:49'),
(11, 2, 'TX-7891', 'Loan', 'expense', 125000.00, 'success', '2026-06-02', '2026-06-02 07:06:51');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `category` varchar(120) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `workspace_id`, `sku`, `product_name`, `category`, `stock`, `price`, `created_at`) VALUES
(1, 2, 'PM-001', 'MozarellaBerry Pie', 'Backery & Pastry', 2, 35000.00, '2026-05-31 19:39:55'),
(2, 2, 'PC-001', ' Choco Pie', 'Backery & Pastry', 30, 32000.00, '2026-05-31 19:43:36'),
(3, 2, 'PA-002', 'Apple Pie', 'Backery & Pastry', 25, 30000.00, '2026-06-01 12:46:08'),
(4, 2, 'PM-002', 'Matcha Pie', 'Backery & Pastry', 15, 35000.00, '2026-06-01 12:46:08'),
(5, 2, 'PL-001', 'Lemon Pie', 'Backery & Pastry', 12, 33000.00, '2026-06-01 12:46:08'),
(6, 2, 'PS-001', 'Strawberry Pie', 'Backery & Pastry', 5, 37000.00, '2026-06-01 12:46:08'),
(7, 2, 'PB-001', 'Blueberry Pie', 'Backery & Pastry', 18, 36000.00, '2026-06-01 12:46:08'),
(8, 2, 'PT-001', 'Taro Pie', 'Backery & Pastry', 12, 34000.00, '2026-06-01 12:46:08'),
(9, 2, 'PO-001', 'Oreo Pie', 'Backery & Pastry', 3, 38000.00, '2026-06-01 12:46:08'),
(10, 2, 'PP-001', 'Pandan Pie', 'Backery & Pastry', 22, 31000.00, '2026-06-01 12:46:08'),
(11, 2, 'PD-001', 'Dark Choco Pie', 'Backery & Pastry', 0, 40000.00, '2026-06-01 12:46:08'),
(12, 2, 'PC-002', 'Cheese Pie', 'Backery & Pastry', 8, 39000.00, '2026-06-01 12:46:08'),
(13, 1, 'PK-001', 'Kikil sapi', 'Food', 50, 321000.00, '2026-06-02 07:26:43'),
(14, 1, 'PA-001', 'Ati Ampela', 'Food', 50, 250000.00, '2026-06-02 07:27:09');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `order_code` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `item` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('on_progress','on_hold','pending_payment','delivered','cancelled') NOT NULL DEFAULT 'on_progress',
  `order_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `workspace_id`, `order_code`, `customer_name`, `item`, `quantity`, `price`, `status`, `order_date`, `created_at`) VALUES
(1, 2, 'CRH-4676', 'Baki', 'Choco Pie', 2, 64000.00, 'delivered', '2026-06-01', '2026-05-31 19:20:34'),
(2, 2, 'CRH-2034', 'Rey', 'Matcha Pie', 5, 175000.00, 'delivered', '2026-06-01', '2026-06-01 12:31:08'),
(3, 2, 'CRH-3891', 'Sinta', 'Apple Pie', 3, 90000.00, 'delivered', '2026-05-31', '0000-00-00 00:00:00'),
(4, 2, 'CRH-5012', 'Danu', 'Blueberry Pie', 1, 55000.00, 'on_progress', '2026-05-31', '0000-00-00 00:00:00'),
(5, 2, 'CRH-7743', 'Lila', 'Strawberry Pie', 4, 140000.00, 'delivered', '2026-05-31', '0000-00-00 00:00:00'),
(6, 2, 'CRH-6620', 'Hasan', 'Choco Pie', 2, 64000.00, 'cancelled', '2026-06-01', '0000-00-00 00:00:00'),
(7, 2, 'CRH-8801', 'Nadia', 'Lemon Pie', 6, 198000.00, 'delivered', '2026-06-01', '0000-00-00 00:00:00'),
(8, 2, 'CRH-4163', 'Renren', 'Lemon Pie', 8, 264000.00, 'on_progress', '2026-05-31', '2026-06-01 15:23:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` int(50) DEFAULT NULL,
  `password` varchar(125) NOT NULL,
  `membership` enum('starter','pro','scale','admin') NOT NULL,
  `role` enum('Finance Manager','Staff','Manager','Founder','Personal') NOT NULL,
  `oauth_provider` varchar(50) DEFAULT NULL,
  `oauth_uid` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `avatar`, `name`, `email`, `phone`, `password`, `membership`, `role`, `oauth_provider`, `oauth_uid`) VALUES
(1, NULL, 'John Doe', 'johndoe@gmail.com', NULL, '$2y$10$8cwS5BdsBsmrNhuTWHAEg.uttL5s21CZzwvsYYhWTgqyg1.xXVtZi', 'starter', 'Finance Manager', NULL, NULL),
(2, 'uploads/avatars/avatar_2_1780388678.jpg', 'Mr Friday', 'johndoee@gmail.com', 0, '$2y$10$telO/9T2Kol7EgkoYck6beFugIM98a9uqPeT2yp6TsamPJRmKOrnm', 'pro', 'Staff', NULL, NULL),
(3, '/uploads/avatars/avatar_3_1780385401.jpg', 'mr subarasi', 'subarasi99@gmail.com', 0, '$2y$10$rGXmpyQ1mEJp5E1bSN.Bl.WEWh59Kygm215jIAO7gVV52Ezx4QBQO', 'admin', 'Staff', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `workspaces`
--

CREATE TABLE `workspaces` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `logo` varchar(500) DEFAULT NULL,
  `industry` varchar(100) DEFAULT 'retail_ecommerce',
  `company_size` varchar(50) DEFAULT '1-10',
  `created_at` datetime NOT NULL,
  `setup_completed_at` datetime DEFAULT NULL,
  `monthly_target` decimal(18,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workspaces`
--

INSERT INTO `workspaces` (`id`, `name`, `logo`, `industry`, `company_size`, `created_at`, `setup_completed_at`, `monthly_target`) VALUES
(1, 'Cungkring Mr Friday', NULL, 'retail_ecommerce', '1-10', '2026-05-30 21:56:38', '2026-05-30 21:56:38', 0.00),
(2, 'Pie Susu Mr Friday', 'uploads/workspaces/workspace_2_1780238377.jpg', 'other', '1-10', '2026-05-31 21:39:37', '2026-05-31 21:39:37', 1182000.00);

-- --------------------------------------------------------

--
-- Table structure for table `workspace_members`
--

CREATE TABLE `workspace_members` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('owner','admin','member') DEFAULT 'member',
  `joined_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workspace_members`
--

INSERT INTO `workspace_members` (`id`, `workspace_id`, `user_id`, `role`, `joined_at`) VALUES
(1, 1, 2, 'owner', '2026-05-30 21:56:38'),
(2, 2, 2, 'owner', '2026-05-31 21:39:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics_activity`
--
ALTER TABLE `analytics_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aa_ws` (`workspace_id`);

--
-- Indexes for table `analytics_metrics`
--
ALTER TABLE `analytics_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_am_ws_key` (`workspace_id`,`metric_key`),
  ADD KEY `idx_am_ws` (`workspace_id`);

--
-- Indexes for table `analytics_monthly`
--
ALTER TABLE `analytics_monthly`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_amo_ws` (`workspace_id`);

--
-- Indexes for table `analytics_reviews`
--
ALTER TABLE `analytics_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ar_ws` (`workspace_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cust_ws` (`workspace_id`);

--
-- Indexes for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fin_ws` (`workspace_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inv_ws` (`workspace_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ord_ws` (`workspace_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`);

--
-- Indexes for table `workspaces`
--
ALTER TABLE `workspaces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_setup_completed` (`setup_completed_at`);

--
-- Indexes for table `workspace_members`
--
ALTER TABLE `workspace_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_workspace_user` (`workspace_id`,`user_id`),
  ADD KEY `idx_workspace` (`workspace_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `analytics_activity`
--
ALTER TABLE `analytics_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2318;

--
-- AUTO_INCREMENT for table `analytics_metrics`
--
ALTER TABLE `analytics_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=875;

--
-- AUTO_INCREMENT for table `analytics_monthly`
--
ALTER TABLE `analytics_monthly`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=287;

--
-- AUTO_INCREMENT for table `analytics_reviews`
--
ALTER TABLE `analytics_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `finance_transactions`
--
ALTER TABLE `finance_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `workspaces`
--
ALTER TABLE `workspaces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `workspace_members`
--
ALTER TABLE `workspace_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `workspace_members`
--
ALTER TABLE `workspace_members`
  ADD CONSTRAINT `fk_workspace_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_workspace_members_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
