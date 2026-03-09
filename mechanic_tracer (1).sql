-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 09, 2026 at 03:51 PM
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
  `service_requested` varchar(100) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `booking_status` enum('pending','accepted','completed','cancelled') DEFAULT 'pending',
  `driver_latitude` decimal(9,6) DEFAULT NULL,
  `driver_longitude` decimal(9,6) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mechanics`
--

INSERT INTO `mechanics` (`id`, `user_id`, `garage_name`, `experience`, `certifications`, `vehicle_types`, `services_offered`, `latitude`, `longitude`, `availability`, `created_at`) VALUES
(30, 4, 'kitere repair', 2, 'BRAKE EXPERT', 'Motorbike', 'Engine Repair', -0.825893, 34.609497, 0, '2026-02-26 19:15:33');

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
(5, 'Clifford Onchomba', 'cliffordisaboke11@gmail.com', '$2y$10$DLJnGuicmdrykb1oMMEf3et1c369vR.3ico0K28IHJ4nuCCdzaanK', '10698450', 'driver', 1, '2026-02-26 16:55:48');

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
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
