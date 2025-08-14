-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 14, 2025 at 03:56 AM
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
-- Database: `pos_system`
--
CREATE DATABASE IF NOT EXISTS `pos_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pos_system`;

-- --------------------------------------------------------

--
-- Table structure for table `credithistory`
--
-- Error reading structure for table pos_system.credithistory: #1932 - Table &#039;pos_system.credithistory&#039; doesn&#039;t exist in engine
-- Error reading data for table pos_system.credithistory: #1064 - You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near &#039;FROM `pos_system`.`credithistory`&#039; at line 1

-- --------------------------------------------------------

--
-- Table structure for table `creditpayments`
--

DROP TABLE IF EXISTS `creditpayments`;
CREATE TABLE `creditpayments` (
  `CreditPaymentID` int(11) NOT NULL,
  `CustomerID` int(11) NOT NULL,
  `TransactionID` int(11) NOT NULL,
  `Amount` decimal(10,2) NOT NULL CHECK (`Amount` > 0),
  `PaymentDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `IsPaid` tinyint(1) DEFAULT 0,
  `CollectedBy` int(11) DEFAULT NULL,
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `CustomerID` int(11) NOT NULL,
  `CustomerName` varchar(100) NOT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Phone` varchar(20) DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `CreditLimit` decimal(10,2) DEFAULT 0.00,
  `Balance` decimal(10,2) DEFAULT 0.00,
  `Notes` text DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`CustomerID`, `CustomerName`, `Email`, `Phone`, `Address`, `CreditLimit`, `Balance`, `Notes`, `CreatedAt`, `UpdatedAt`) VALUES
(1, 'Alice Santos', 'alice@example.com', '09171234567', '123 Market St, Manila', 1000.00, 1000.00, NULL, '2025-05-20 08:37:51', '2025-05-20 08:37:51'),
(2, 'Brandon Cruz', 'brandon@example.com', '09221234567', '456 Rizal Ave, Cebu', 500.00, 500.00, NULL, '2025-05-20 08:37:51', '2025-05-20 08:37:51'),
(3, 'Carla Reyes', 'carla@example.com', '09331234567', '789 Bonifacio Blvd, Davao', 0.00, 0.00, NULL, '2025-05-20 08:37:51', '2025-05-20 08:37:51'),
(4, 'Danilo Gomez', 'danilo@example.com', '09441234567', '101 Quezon Hwy, Baguio', 200.00, 200.00, NULL, '2025-05-20 08:37:51', '2025-05-20 08:37:51'),
(5, 'Elena Lopez', 'elena@example.com', '09551234567', '202 Mabini St, Iloilo', 300.00, 300.00, NULL, '2025-05-20 08:37:51', '2025-05-20 08:37:51');

-- --------------------------------------------------------

--
-- Table structure for table `inventorylog`
--

DROP TABLE IF EXISTS `inventorylog`;
CREATE TABLE `inventorylog` (
  `LogID` int(11) NOT NULL,
  `ProductID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `Adjustment` int(11) NOT NULL,
  `OldQuantity` int(11) NOT NULL,
  `NewQuantity` int(11) NOT NULL,
  `Reason` enum('sale','restock','adjustment','damaged') DEFAULT NULL,
  `Notes` text DEFAULT NULL,
  `LogDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `ProductID` int(11) NOT NULL,
  `ProductName` varchar(100) NOT NULL,
  `Description` text DEFAULT NULL,
  `Price` decimal(10,2) NOT NULL CHECK (`Price` > 0),
  `Cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Category` varchar(50) NOT NULL,
  `StockQuantity` int(11) NOT NULL DEFAULT 0 CHECK (`StockQuantity` >= 0),
  `Barcode` varchar(50) DEFAULT NULL,
  `Image` varchar(255) DEFAULT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`ProductID`, `ProductName`, `Description`, `Price`, `Cost`, `Category`, `StockQuantity`, `Barcode`, `Image`, `IsActive`, `CreatedAt`, `UpdatedAt`) VALUES
(1, 'Widget A', 'Standard widget', 100.00, 60.00, 'Widgets', 36, 'WIDG-A', NULL, 1, '2025-05-20 08:37:51', '2025-08-14 01:44:36'),
(2, 'Widget B', 'Advanced widget', 150.00, 90.00, 'Widgets', 8, 'WIDG-B', NULL, 1, '2025-05-20 08:37:51', '2025-08-14 01:44:36'),
(3, 'Gadget X', 'Basic gadget', 75.00, 40.00, 'Gadgets', 74, 'GADG-X', NULL, 1, '2025-05-20 08:37:51', '2025-08-14 01:45:25'),
(4, 'Gadget Y', 'Premium gadget', 200.00, 120.00, 'Gadgets', 0, 'GADG-Y', NULL, 1, '2025-05-20 08:37:51', '2025-07-07 07:55:23'),
(5, 'Accessory 1', 'Widget accessory', 25.00, 10.00, 'Accessories', 191, 'ACC-1', NULL, 1, '2025-05-20 08:37:51', '2025-08-14 01:44:36'),
(6, 'alcohol 1 liter', '', 250.00, 0.00, 'med', 36, '4806534000921', NULL, 1, '2025-07-01 01:48:02', '2025-08-14 01:45:25'),
(7, 'glade', '', 280.00, 0.00, 'Category A', 232, '0046500029837', NULL, 1, '2025-07-01 01:50:09', '2025-08-14 01:44:36'),
(8, 'shoes', '', 499.00, 0.00, 'footwear', 200, '', NULL, 1, '2025-08-08 07:50:32', '2025-08-08 07:50:32');

-- --------------------------------------------------------

--
-- Table structure for table `transactionitems`
--

DROP TABLE IF EXISTS `transactionitems`;
CREATE TABLE `transactionitems` (
  `TransactionItemID` int(11) NOT NULL,
  `TransactionID` int(11) NOT NULL,
  `ProductID` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL CHECK (`Quantity` > 0),
  `Price` decimal(10,2) NOT NULL CHECK (`Price` >= 0),
  `Cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Discount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactionitems`
--

INSERT INTO `transactionitems` (`TransactionItemID`, `TransactionID`, `ProductID`, `Quantity`, `Price`, `Cost`, `Discount`) VALUES
(1, 5, 5, 2, 25.00, 10.00, 0.00),
(2, 5, 3, 3, 75.00, 40.00, 0.00),
(3, 5, 4, 2, 200.00, 120.00, 0.00),
(4, 5, 1, 2, 100.00, 60.00, 0.00),
(5, 5, 2, 2, 150.00, 90.00, 0.00),
(6, 7, 2, 4, 150.00, 90.00, 0.00),
(7, 7, 4, 2, 200.00, 120.00, 0.00),
(8, 7, 1, 2, 100.00, 60.00, 0.00),
(9, 12, 1, 5, 100.00, 60.00, 0.00),
(10, 12, 5, 1, 25.00, 10.00, 0.00),
(11, 12, 3, 2, 75.00, 40.00, 0.00),
(12, 13, 5, 3, 25.00, 10.00, 0.00),
(13, 13, 3, 3, 75.00, 40.00, 0.00),
(14, 14, 2, 1, 150.00, 90.00, 0.00),
(15, 14, 1, 5, 100.00, 60.00, 0.00),
(16, 15, 1, 3, 100.00, 60.00, 0.00),
(17, 15, 4, 3, 200.00, 120.00, 0.00),
(18, 16, 1, 2, 100.00, 60.00, 0.00),
(19, 17, 3, 1, 75.00, 40.00, 0.00),
(20, 17, 5, 1, 25.00, 10.00, 0.00),
(21, 17, 4, 1, 200.00, 120.00, 0.00),
(22, 18, 3, 1, 75.00, 40.00, 0.00),
(23, 18, 4, 2, 200.00, 120.00, 0.00),
(24, 18, 1, 2, 100.00, 60.00, 0.00),
(25, 18, 2, 1, 150.00, 90.00, 0.00),
(26, 19, 2, 1, 150.00, 90.00, 0.00),
(27, 20, 2, 3, 150.00, 90.00, 0.00),
(28, 21, 4, 2, 200.00, 120.00, 0.00),
(29, 21, 3, 2, 75.00, 40.00, 0.00),
(30, 22, 2, 5, 150.00, 90.00, 0.00),
(31, 23, 3, 5, 75.00, 40.00, 0.00),
(32, 23, 6, 2, 250.00, 0.00, 0.00),
(33, 24, 3, 2, 75.00, 40.00, 0.00),
(34, 25, 6, 4, 250.00, 0.00, 0.00),
(35, 26, 7, 1, 280.00, 0.00, 0.00),
(36, 26, 3, 1, 75.00, 40.00, 0.00),
(37, 26, 4, 1, 200.00, 120.00, 0.00),
(38, 26, 6, 2, 250.00, 0.00, 0.00),
(39, 27, 7, 5, 280.00, 0.00, 0.00),
(40, 28, 4, 4, 200.00, 120.00, 0.00),
(41, 29, 4, 3, 200.00, 120.00, 0.00),
(42, 29, 2, 3, 150.00, 90.00, 0.00),
(43, 30, 5, 1, 25.00, 10.00, 0.00),
(44, 30, 7, 10, 280.00, 0.00, 0.00),
(45, 31, 6, 2, 250.00, 0.00, 0.00),
(46, 31, 3, 3, 75.00, 40.00, 0.00),
(47, 31, 2, 2, 150.00, 90.00, 0.00),
(48, 31, 7, 2, 280.00, 0.00, 0.00),
(49, 31, 5, 1, 25.00, 10.00, 0.00),
(50, 31, 1, 1, 100.00, 60.00, 0.00),
(51, 32, 6, 4, 250.00, 0.00, 0.00),
(52, 32, 3, 3, 75.00, 40.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `TransactionID` int(11) NOT NULL,
  `CustomerID` int(11) DEFAULT NULL,
  `UserID` int(11) NOT NULL,
  `TotalAmount` decimal(10,2) NOT NULL CHECK (`TotalAmount` >= 0),
  `AmountTendered` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ChangeDue` decimal(10,2) NOT NULL DEFAULT 0.00,
  `PaymentMethod` enum('cash','credit','card','mobile') DEFAULT 'cash',
  `TransactionDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `Notes` text DEFAULT NULL,
  `Status` enum('completed','voided','refunded') DEFAULT 'completed',
  `CreditCleared` tinyint(1) DEFAULT 0,
  `IsCleared` tinyint(1) DEFAULT 0,
  `ClearedDate` datetime DEFAULT NULL,
  `ClearedBy` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`TransactionID`, `CustomerID`, `UserID`, `TotalAmount`, `AmountTendered`, `ChangeDue`, `PaymentMethod`, `TransactionDate`, `Notes`, `Status`, `CreditCleared`, `IsCleared`, `ClearedDate`, `ClearedBy`) VALUES
(5, NULL, 3, 1175.00, 1500.00, 325.00, 'cash', '2025-05-21 01:06:40', NULL, 'completed', 0, 0, NULL, NULL),
(7, 1, 3, 1200.00, 2000.00, 800.00, 'cash', '2025-05-21 02:21:16', NULL, 'completed', 0, 0, NULL, NULL),
(12, 1, 3, 675.00, 0.00, 0.00, 'credit', '2025-05-22 04:28:43', NULL, 'completed', 0, 0, NULL, NULL),
(13, 1, 3, 300.00, 0.00, 0.00, 'credit', '2025-05-22 04:29:34', NULL, 'completed', 0, 0, NULL, NULL),
(14, 1, 3, 650.00, 0.00, 0.00, 'credit', '2025-05-22 04:29:52', NULL, 'completed', 0, 0, NULL, NULL),
(15, 1, 3, 900.00, 0.00, 0.00, 'credit', '2025-05-22 04:32:31', NULL, 'completed', 0, 0, NULL, NULL),
(16, 5, 3, 200.00, 0.00, 0.00, 'credit', '2025-05-30 01:04:32', NULL, 'completed', 0, 0, NULL, NULL),
(17, 5, 3, 300.00, 500.00, 200.00, 'cash', '2025-05-30 01:12:51', NULL, 'completed', 0, 0, NULL, NULL),
(18, 1, 3, 825.00, 0.00, 0.00, 'credit', '2025-06-17 00:58:08', NULL, 'completed', 0, 0, NULL, NULL),
(19, 1, 3, 150.00, 0.00, 0.00, 'credit', '2025-06-17 00:58:24', NULL, 'completed', 0, 0, NULL, NULL),
(20, NULL, 3, 450.00, 500.00, 50.00, 'cash', '2025-06-17 01:07:08', NULL, 'completed', 0, 0, NULL, NULL),
(21, NULL, 3, 550.00, 900.00, 350.00, 'cash', '2025-06-17 01:08:03', NULL, 'completed', 0, 0, NULL, NULL),
(22, NULL, 3, 750.00, 1000.00, 250.00, 'cash', '2025-07-01 01:43:54', NULL, 'completed', 0, 0, NULL, NULL),
(23, NULL, 3, 875.00, 1000.00, 125.00, 'cash', '2025-07-01 06:23:01', NULL, 'completed', 0, 0, NULL, NULL),
(24, 5, 3, 150.00, 0.00, 0.00, 'credit', '2025-07-01 06:24:17', NULL, 'completed', 0, 0, NULL, NULL),
(25, 4, 3, 1000.00, 0.00, 0.00, 'credit', '2025-07-01 06:26:46', NULL, 'completed', 0, 0, NULL, NULL),
(26, NULL, 3, 1055.00, 1100.00, 45.00, 'cash', '2025-07-03 01:48:35', NULL, 'completed', 0, 0, NULL, NULL),
(27, 1, 3, 1400.00, 0.00, 0.00, 'credit', '2025-07-03 01:48:54', NULL, 'completed', 0, 0, NULL, NULL),
(28, 1, 3, 800.00, 1000.00, 200.00, 'cash', '2025-07-03 01:49:10', NULL, 'completed', 0, 0, NULL, NULL),
(29, 4, 3, 1050.00, 0.00, 0.00, 'credit', '2025-07-07 07:55:23', NULL, 'completed', 0, 0, NULL, NULL),
(30, 3, 3, 2825.00, 0.00, 0.00, 'credit', '2025-07-07 08:34:02', NULL, 'completed', 0, 0, NULL, NULL),
(31, NULL, 3, 1710.00, 2000.00, 290.00, 'cash', '2025-08-14 01:44:36', NULL, 'completed', 0, 0, NULL, NULL),
(32, 5, 3, 1225.00, 0.00, 0.00, 'credit', '2025-08-14 01:45:25', NULL, 'completed', 0, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('admin','cashier') NOT NULL DEFAULT 'cashier',
  `LastLogin` datetime DEFAULT NULL,
  `FailedAttempts` int(11) DEFAULT 0,
  `LastAttemptIP` varchar(45) DEFAULT NULL,
  `IsLocked` tinyint(1) DEFAULT 0,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `Username`, `Password`, `Role`, `LastLogin`, `FailedAttempts`, `LastAttemptIP`, `IsLocked`, `CreatedAt`, `UpdatedAt`) VALUES
(3, 'admin', '$2y$12$EDLvtVTZitZR.Sq7U98KNOqO.VUK8qaYHmN70WHPSpfu5NiKGaEz6', 'admin', '2025-08-14 01:42:44', 0, NULL, 0, '2025-05-20 06:52:28', '2025-08-14 01:42:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `creditpayments`
--
ALTER TABLE `creditpayments`
  ADD PRIMARY KEY (`CreditPaymentID`),
  ADD KEY `TransactionID` (`TransactionID`),
  ADD KEY `CollectedBy` (`CollectedBy`),
  ADD KEY `idx_credit_payment_date` (`PaymentDate`),
  ADD KEY `idx_credit_payment_status` (`IsPaid`),
  ADD KEY `idx_credit_customer` (`CustomerID`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`CustomerID`),
  ADD KEY `idx_customer_name` (`CustomerName`),
  ADD KEY `idx_customer_contact` (`Email`,`Phone`);

--
-- Indexes for table `inventorylog`
--
ALTER TABLE `inventorylog`
  ADD PRIMARY KEY (`LogID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `idx_inventory_product` (`ProductID`),
  ADD KEY `idx_inventory_date` (`LogDate`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`ProductID`),
  ADD UNIQUE KEY `Barcode` (`Barcode`),
  ADD KEY `idx_product_name` (`ProductName`),
  ADD KEY `idx_category` (`Category`),
  ADD KEY `idx_stock` (`StockQuantity`);
ALTER TABLE `products` ADD FULLTEXT KEY `idx_search` (`ProductName`,`Description`,`Category`);

--
-- Indexes for table `transactionitems`
--
ALTER TABLE `transactionitems`
  ADD PRIMARY KEY (`TransactionItemID`),
  ADD KEY `ProductID` (`ProductID`),
  ADD KEY `idx_transaction_product` (`TransactionID`,`ProductID`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`TransactionID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `idx_transaction_date` (`TransactionDate`),
  ADD KEY `idx_transaction_status` (`Status`),
  ADD KEY `idx_transaction_customer` (`CustomerID`),
  ADD KEY `ClearedBy` (`ClearedBy`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `idx_user_role` (`Role`),
  ADD KEY `idx_user_status` (`IsLocked`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `creditpayments`
--
ALTER TABLE `creditpayments`
  MODIFY `CreditPaymentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `CustomerID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventorylog`
--
ALTER TABLE `inventorylog`
  MODIFY `LogID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `ProductID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `transactionitems`
--
ALTER TABLE `transactionitems`
  MODIFY `TransactionItemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `TransactionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `creditpayments`
--
ALTER TABLE `creditpayments`
  ADD CONSTRAINT `creditpayments_ibfk_1` FOREIGN KEY (`CustomerID`) REFERENCES `customers` (`CustomerID`) ON DELETE CASCADE,
  ADD CONSTRAINT `creditpayments_ibfk_2` FOREIGN KEY (`TransactionID`) REFERENCES `transactions` (`TransactionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `creditpayments_ibfk_3` FOREIGN KEY (`CollectedBy`) REFERENCES `users` (`UserID`) ON DELETE SET NULL;

--
-- Constraints for table `inventorylog`
--
ALTER TABLE `inventorylog`
  ADD CONSTRAINT `inventorylog_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventorylog_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `transactionitems`
--
ALTER TABLE `transactionitems`
  ADD CONSTRAINT `transactionitems_ibfk_1` FOREIGN KEY (`TransactionID`) REFERENCES `transactions` (`TransactionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactionitems_ibfk_2` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`CustomerID`) REFERENCES `customers` (`CustomerID`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`ClearedBy`) REFERENCES `users` (`UserID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
