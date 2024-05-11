-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 11, 2024 at 03:04 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mechanicstracer`
--

-- --------------------------------------------------------

--
-- Table structure for table `driver`
--

CREATE TABLE `driver` (
  `Fullname` varchar(50) NOT NULL,
  `email` varchar(40) NOT NULL,
  `phonenumber` int(11) DEFAULT NULL,
  `password` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `driver`
--

INSERT INTO `driver` (`Fullname`, `email`, `phonenumber`, `password`) VALUES
('sean', 'clifford3@gmail.com', 799527871, 'Sean.9467'),
('sean', 'clifford@gmail.com', 799527872, 'Sean.9467'),
('clifford', 'cliffordisaboke1@gmail.com', 710698450, 'Sean.9467'),
('sean', 'cliffordisaboke@gmail.com', 710698451, 'Sean.9467');

-- --------------------------------------------------------

--
-- Table structure for table `mechanic`
--

CREATE TABLE `mechanic` (
  `Fullname` varchar(100) NOT NULL,
  `email` varchar(40) NOT NULL,
  `phonenumber` int(11) NOT NULL,
  `businessname` varchar(100) NOT NULL,
  `Location` varchar(100) NOT NULL,
  `areaofexpertise` varchar(100) NOT NULL,
  `password` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mechanic`
--

INSERT INTO `mechanic` (`Fullname`, `email`, `phonenumber`, `businessname`, `Location`, `areaofexpertise`, `password`) VALUES
('sean onchomba', 'cliffordisaboke11@gmail.com', 710698445, 'mec', '', 'oil change', 'Sean.9467'),
('clifford', 'cliffordisaboke@gmail.com', 710698451, 'car attendant', '-0.8257337139396455,34.60990733581546', 'oil cleaning', 'Sean.9467'),
('clifford', 'sean1112@gmail.com', 710698450, 'sean', '-0.8257310851066734,34.609867741330746', 'sean', 'Sean.9467'),
('clifford', 'sean21@gmail.com', 2147483647, 'auto mechanic', '-0.826834858347886,34.617912600281485', 'oil cleaning', 'Sean.9467'),
('clifford', 'sean@gmail.com', 710698452, 'auto mechanic', '-0.8267402571273846,34.61769704342011', 'oil cleaning', 'Sean.9467');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `driver`
--
ALTER TABLE `driver`
  ADD PRIMARY KEY (`email`),
  ADD UNIQUE KEY `email` (`email`,`Fullname`),
  ADD UNIQUE KEY `phonenumber` (`phonenumber`);

--
-- Indexes for table `mechanic`
--
ALTER TABLE `mechanic`
  ADD PRIMARY KEY (`email`),
  ADD UNIQUE KEY `phonenumber` (`phonenumber`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
