-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 12, 2026 at 07:07 PM
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
-- Database: `mechanic_tracer`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `mechanic_id` int(11) NOT NULL,
  `service_requested` varchar(255) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `booking_status` enum('pending','accepted','completed','cancelled') DEFAULT 'pending',
  `accepted_at` datetime DEFAULT NULL,
  `driver_latitude` decimal(9,6) DEFAULT NULL,
  `driver_longitude` decimal(9,6) DEFAULT NULL,
  `driver_address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `driver_id`, `mechanic_id`, `service_requested`, `vehicle_type`, `booking_status`, `accepted_at`, `driver_latitude`, `driver_longitude`, `driver_address`, `notes`, `created_at`, `updated_at`) VALUES
(2, 6, 30, 'Engine Repair', 'Car', 'completed', NULL, 0.000000, 0.000000, NULL, 'engine cant come on', '2026-03-09 18:33:58', '2026-03-11 22:13:58'),
(3, 6, 30, 'Engine Repair', 'Car', 'cancelled', NULL, -0.825284, 34.617654, NULL, 'car has a lot of smoke\r\n', '2026-03-09 20:42:39', '2026-03-09 22:09:17'),
(4, 6, 30, 'Engine Repair', 'Car', 'cancelled', NULL, -0.825284, 34.617654, NULL, 'tire burst', '2026-03-09 20:51:31', '2026-03-09 21:54:13'),
(5, 6, 30, 'Engine Repair', 'Car', 'completed', NULL, -0.825849, 34.610439, NULL, 'hot engine im fearing it my break up', '2026-03-11 12:58:03', '2026-03-11 22:24:20'),
(6, 6, 30, 'Engine Repair', 'Car', 'completed', NULL, -0.825734, 34.610291, NULL, 'hot engine im fearing it my break up', '2026-03-11 12:59:29', '2026-03-11 22:20:43'),
(7, 6, 30, 'Engine Repair', 'Car', 'completed', NULL, -0.825734, 34.610291, NULL, 'hot engine im fearing it my break up', '2026-03-11 13:00:02', '2026-03-11 22:13:33');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `vehicle_make` varchar(50) NOT NULL,
  `vehicle_model` varchar(50) NOT NULL,
  `vehicle_year` year(4) NOT NULL,
  `service_preferences` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `user_id`, `vehicle_type`, `vehicle_make`, `vehicle_model`, `vehicle_year`, `service_preferences`, `created_at`) VALUES
(6, 2, 'Car', 'toyota', 'y7', '2003', 'Engine Repair', '2026-02-26 16:37:14'),
(11, 5, 'Truck', 'toyota', 'y7', '2003', 'Tire Replacement,Brake Adjustment', '2026-02-26 19:09:23');

--
-- Triggers `drivers`
--
DELIMITER $$
CREATE TRIGGER `trg_drivers_after_delete` AFTER DELETE ON `drivers` FOR EACH ROW BEGIN
    UPDATE users
    SET role='pending', profile_completed=0
    WHERE id = OLD.user_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_drivers_after_insert` AFTER INSERT ON `drivers` FOR EACH ROW BEGIN
    UPDATE users
    SET role='driver', profile_completed=1
    WHERE id = NEW.user_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_drivers_after_update` AFTER UPDATE ON `drivers` FOR EACH ROW BEGIN
    UPDATE users
    SET role='driver', profile_completed=1
    WHERE id = NEW.user_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `mechanics`
--

CREATE TABLE `mechanics` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `garage_name` varchar(255) NOT NULL,
  `experience` int(11) NOT NULL,
  `certifications` text DEFAULT NULL,
  `vehicle_types` text NOT NULL,
  `services_offered` text NOT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  `availability` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `service_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`service_ids`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mechanics`
--

INSERT INTO `mechanics` (`id`, `user_id`, `garage_name`, `experience`, `certifications`, `vehicle_types`, `services_offered`, `latitude`, `longitude`, `availability`, `created_at`, `service_ids`) VALUES
(30, 4, 'kitere repair', 2, 'BRAKE EXPERT', 'Motorbike', 'Engine Repair', -0.825893, 34.609497, 0, '2026-02-26 19:15:33', NULL);

--
-- Triggers `mechanics`
--
DELIMITER $$
CREATE TRIGGER `trg_mechanics_after_delete` AFTER DELETE ON `mechanics` FOR EACH ROW BEGIN
    UPDATE users
    SET role='pending', profile_completed=0
    WHERE id = OLD.user_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_mechanics_after_insert` AFTER INSERT ON `mechanics` FOR EACH ROW BEGIN
    UPDATE users
    SET role='mechanic', profile_completed=1
    WHERE id = NEW.user_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_mechanics_after_update` AFTER UPDATE ON `mechanics` FOR EACH ROW BEGIN
    UPDATE users
    SET role='mechanic', profile_completed=1
    WHERE id = NEW.user_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `service_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `category`, `service_name`) VALUES
(1, 'Engine Services', 'Engine Repair'),
(2, 'Engine Services', 'Engine Diagnostics'),
(3, 'Engine Services', 'Engine Overhaul'),
(4, 'Engine Services', 'Fuel System Repair'),
(5, 'Engine Services', 'Radiator Repair'),
(6, 'Electrical Services', 'Battery Replacement'),
(7, 'Electrical Services', 'Alternator Repair'),
(8, 'Electrical Services', 'Starter Motor Repair'),
(9, 'Electrical Services', 'Car Wiring Repair'),
(10, 'Electrical Services', 'ECU Diagnostics'),
(11, 'Brake System', 'Brake Pad Replacement'),
(12, 'Brake System', 'Brake Fluid Replacement'),
(13, 'Brake System', 'Brake System Repair'),
(14, 'Brake System', 'ABS Repair'),
(15, 'Tire & Wheel', 'Tire Replacement'),
(16, 'Tire & Wheel', 'Tire Puncture Repair'),
(17, 'Tire & Wheel', 'Wheel Balancing'),
(18, 'Tire & Wheel', 'Wheel Alignment'),
(19, 'Transmission', 'Clutch Repair'),
(20, 'Transmission', 'Gearbox Repair'),
(21, 'Transmission', 'Transmission Service'),
(22, 'Suspension & Steering', 'Suspension Repair'),
(23, 'Suspension & Steering', 'Shock Absorber Replacement'),
(24, 'Suspension & Steering', 'Steering Repair'),
(25, 'General Maintenance', 'Oil Change'),
(26, 'General Maintenance', 'Air Filter Replacement'),
(27, 'General Maintenance', 'Spark Plug Replacement'),
(28, 'General Maintenance', 'Coolant Replacement'),
(29, 'General Maintenance', 'Filter Cleaning'),
(30, 'Emergency Roadside', 'Jump Start'),
(31, 'Emergency Roadside', 'Fuel Delivery'),
(32, 'Emergency Roadside', 'Emergency Roadside Repair'),
(33, 'Emergency Roadside', 'Towing Service'),
(34, 'Emergency Roadside', 'Flat Tire Replacement');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `role` enum('driver','mechanic','pending') DEFAULT 'pending',
  `profile_completed` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `role`, `profile_completed`, `created_at`) VALUES
(2, 'Clifford Onchomba', 'cliffordisaboke1@gmail.com', '$2y$10$fMkNbnfhb1saBEHBAfbS8O8CJJIFifDJJqdl6WXUn50.vnHScWvmy', '0710698450', 'driver', 1, '2026-02-21 22:05:02'),
(4, 'clifford isaboke', 'cliffordonchomba483@gmail.com', '$2y$10$hrPlsUbgQoPOBzy96tBOA.n0x0DSxav41qSGJXLSpijuaIsCGr1Qm', '0710698450', 'mechanic', 1, '2026-02-26 16:23:53'),
(5, 'Clifford Onchomba', 'cliffordisaboke11@gmail.com', '$2y$10$DLJnGuicmdrykb1oMMEf3et1c369vR.3ico0K28IHJ4nuCCdzaanK', '10698450', 'driver', 1, '2026-02-26 16:55:48'),
(6, 'jane', 'jane@mail.com', '$2y$10$JcTNQOuu0S7H9WqU04TmjOa.QYySohgrDQtd0EMEQOOMr.qAJsz6K', '10698450', 'pending', 0, '2026-03-12 20:42:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booking_driver` (`driver_id`),
  ADD KEY `fk_booking_mechanic` (`mechanic_id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_drivers_user` (`user_id`);

--
-- Indexes for table `mechanics`
--
ALTER TABLE `mechanics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mechanics_user` (`user_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `mechanics`
--
ALTER TABLE `mechanics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_mechanic` FOREIGN KEY (`mechanic_id`) REFERENCES `mechanics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `drivers`
--
ALTER TABLE `drivers`
  ADD CONSTRAINT `drivers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_drivers_user_cascade` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mechanics`
--
ALTER TABLE `mechanics`
  ADD CONSTRAINT `fk_mechanics_user_cascade` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mechanics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
