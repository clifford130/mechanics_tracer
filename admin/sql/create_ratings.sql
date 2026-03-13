-- Ratings: drivers rate mechanics after completed bookings
-- Run in phpMyAdmin or: mysql -u root mechanic_tracer < create_ratings.sql

CREATE TABLE IF NOT EXISTS `ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `mechanic_id` int(11) NOT NULL,
  `stars` tinyint(1) NOT NULL CHECK (`stars` >= 1 AND `stars` <= 5),
  `review` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rating_booking` (`booking_id`),
  KEY `fk_rating_driver` (`driver_id`),
  KEY `fk_rating_mechanic` (`mechanic_id`),
  CONSTRAINT `fk_rating_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rating_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rating_mechanic` FOREIGN KEY (`mechanic_id`) REFERENCES `mechanics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
