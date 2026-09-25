-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 25, 2026 at 08:14 PM
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
-- Database: `tour_booking_db`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `available_packages`
-- (See below for the actual view)
--
CREATE TABLE `available_packages` (
`package_id` int(11)
,`destination_id` int(11)
,`destination_name` varchar(100)
,`hotel_id` int(11)
,`hotel_name` varchar(120)
,`transport_id` int(11)
,`transport_type` varchar(60)
,`transport_provider` varchar(120)
,`package_name` varchar(160)
,`description` text
,`duration_days` int(11)
,`duration_nights` int(11)
,`price` decimal(10,2)
,`total_seats` int(11)
,`available_seats` int(11)
,`departure_date` date
,`image_url` varchar(500)
,`status` varchar(20)
);

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `travelers` int(11) NOT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `travel_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `user_id`, `package_id`, `travelers`, `booking_date`, `travel_date`, `total_amount`, `status`) VALUES
(1, 6, 1, 1, '2026-09-25 13:05:16', '2026-12-15', 8500.00, 'cancelled'),
(2, 6, 2, 2, '2026-09-25 13:06:49', '2026-12-20', 14000.00, 'confirmed'),
(3, 7, 5, 2, '2026-09-25 17:46:52', '2026-09-29', 16000.00, 'confirmed');

-- --------------------------------------------------------

--
-- Table structure for table `destinations`
--

CREATE TABLE `destinations` (
  `destination_id` int(11) NOT NULL,
  `destination_name` varchar(100) NOT NULL,
  `location` varchar(120) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `active` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `destinations`
--

INSERT INTO `destinations` (`destination_id`, `destination_name`, `location`, `description`, `image_url`, `active`) VALUES
(1, 'Cox\'s Bazar', 'Chittagong', 'World famous sea beach', 'cox.jpg', 1),
(2, 'Sajek Valley', 'Rangamati', 'Beautiful hill destination', 'sajek.jpg', 1),
(5, 'Kaptai Lake', 'Rangamati', 'The absolute heart of Rangamati, this is the largest artificial lake in South Asia. Created in 1960 for a hydroelectric project, it completely transformed the region. Renting a local wooden engine boat to cruise its vast, calm waters surrounded by steep hills is the quintessential Rangamati experience.', 'kaptai_lake.jpg', 1),
(6, 'Polwel Park', 'Rangamati', 'This premium lakeside amusement park features the famous \"Love Point\" monument, swimming pools, and manicured walking trails right by the water.', 'Polwel_Park.jpg', 1),
(7, 'Rangamati Main Town', 'Rangamati', 'Widely regarded as the most premium and secure holiday park in main Rangamati town, Aronnak is beautifully landscaped into a green hillock almost entirely surrounded by Kaptai Lake water.', 'rangamati_main_town.jfif', 1),
(8, 'Foy\'s Lake', 'Chittagong', 'A peaceful, man-made lake nestled amidst lush green hills. The complex features a thrill park, a massive water park, and a neighboring zoo.', 'Foys_Lake.jpg', 1),
(9, 'Patenga Coastline', 'Chittagong', 'A lively oceanfront walkway where the Karnaphuli River flows into the Bay of Bengal. Travelers gather along the rock-paved promenade to view colossal cargo ships and sample spicy local crab fry.', 'Patenga.jpg', 1),
(10, 'Ruilui Para', 'Sajek Valley', 'Ruilui Para is the vibrant and central gateway neighborhood of the famous Sajek Valley in Rangamati, Bangladesh. Established in 1885 and sitting at an altitude of approximately 1,720 to 1,800 feet above sea level, it serves as the primary hub where almost all the major resorts, restaurants, and tourist facilities are clustered.', 'Ruilui_Para.jpg', 1),
(11, 'Konglak Para & VIP Zone', 'Sajek Valley', 'Located at the very end of the main road, roughly 1.5 to 2 kilometers past Ruilui Para, this zone sits on the highest accessible hill of the entire valley (about 1,800 feet high).', 'Konglak_Pahar.jfif', 1),
(12, 'Sreemangal (The Tea Capital)', 'Sylhet', 'Malnicherra Tea Estate and the surrounding hills of Sreemangal feature miles of rolling green tea bushes. You can also trek into the nearby Lawachara National Park to spot rare wildlife.', 'Sylhet.jfif', 1),
(13, 'Ratargul Swamp Forest & Khadimnagar', 'Sylhet', 'Ratargul Swamp Forest is the only freshwater swamp forest in Bangladesh. You board small wooden boats to float right between submerged trees growing out of clear, dark waters.', 'Ratargul_Swamp_Forest.jfif', 1),
(14, 'Sylhet City Center (', 'Sylhet', 'Staying in the city center gives you immediate access to the historic Hazrat Shahjalal Mazar Sharif. It serves as the ultimate base to hire cars for day trips to Jaflong (where crystal streams flow over smooth river stones beneath the Indian hills).', 'Sylhet_City_Center.jfif', 1);

-- --------------------------------------------------------

--
-- Table structure for table `hotels`
--

CREATE TABLE `hotels` (
  `hotel_id` int(11) NOT NULL,
  `destination_id` int(11) NOT NULL,
  `hotel_name` varchar(120) NOT NULL,
  `contact` varchar(80) DEFAULT NULL,
  `stars` tinyint(4) NOT NULL DEFAULT 3,
  `price_per_night` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amenities` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotels`
--

INSERT INTO `hotels` (`hotel_id`, `destination_id`, `hotel_name`, `contact`, `stars`, `price_per_night`, `amenities`) VALUES
(1, 1, 'Sea Pearl Hotel', '01800000000', 3, 0.00, ''),
(2, 2, 'Sajek Resort', '01900000000', 3, 0.00, ''),
(5, 5, 'Polwel Park & Cottage', '01786473905', 3, 7000.00, 'breakfast, swimming pool access, and park entry'),
(6, 7, 'Aronnak Holiday Resort', '01786473883', 3, 4500.00, 'Scenic walkways, kid-friendly play zones, and private kayaking/boating docks.'),
(7, 5, 'Wildwood Island Resortv', '01786473449', 3, 12000.00, 'Infinity pool villas that look directly over the rolling hills and expansive waters.'),
(8, 8, 'Foy\'s Lake Resort', '0178643349', 3, 4000.00, 'Rustic water-facing cottages or premium building rooms isolated entirely from city noise.'),
(9, 9, 'Hotel Sea World Patenga', '0178647903', 3, 3500.00, 'Coastal lodgings for those who prefer to wake up directly next to the sea spray.'),
(10, 10, 'Meghpunji Resort', '0178647235', 3, 5000.00, 'Unique wooden eco-cottages, wide glass walls and deep private balconies .'),
(11, 10, 'D\'more Sajek Valley Hotel & Resort', '0178643905', 3, 5500.00, 'Provides standard modern rooms, air conditioning, and premium linen beds right in the hills.'),
(12, 11, 'Sky Eco Resort', '0178643490', 3, 3990.00, 'Duplex and couple cottages, hanging swinging cots.'),
(13, 12, 'Grand Sultan Tea Resort & Golf', '017864590', 5, 8000.00, 'Offering a 9-hole golf course, multi-tiered swimming pools, and sweeping views of the tea gardens right from your balcony.'),
(14, 13, 'Shuktara Nature Retreat', '0178644850', 3, 4000.00, 'It features rustic stone-and-wood rooms, an outdoor pool, and viewing decks that look out onto deep green forest lines.');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(30) NOT NULL,
  `payment_status` varchar(20) NOT NULL,
  `transaction_ref` varchar(80) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `booking_id`, `amount`, `method`, `payment_status`, `transaction_ref`, `payment_date`) VALUES
(1, 1, 8500.00, 'bKash', 'paid', '0178273995', '2026-09-25 13:05:47'),
(2, 2, 14000.00, 'Card', 'paid', '9909 3479 3390', '2026-09-25 13:07:26'),
(3, 3, 16000.00, 'Cash', 'paid', NULL, '2026-09-25 17:47:05');

-- --------------------------------------------------------

--
-- Table structure for table `tour_packages`
--

CREATE TABLE `tour_packages` (
  `package_id` int(11) NOT NULL,
  `destination_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `transport_id` int(11) NOT NULL,
  `package_name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_days` int(11) NOT NULL,
  `duration_nights` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total_seats` int(11) NOT NULL,
  `available_seats` int(11) NOT NULL,
  `departure_date` date NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tour_packages`
--

INSERT INTO `tour_packages` (`package_id`, `destination_id`, `hotel_id`, `transport_id`, `package_name`, `description`, `duration_days`, `duration_nights`, `price`, `total_seats`, `available_seats`, `departure_date`, `image_url`, `status`) VALUES
(1, 1, 1, 1, 'Cox\'s Bazar Beach Tour', 'Enjoy the longest sea beach with hotel stay', 3, 2, 8500.00, 40, 39, '2026-12-15', 'cox.jpg', 'Available'),
(2, 2, 2, 2, 'Sajek Valley Adventure', 'Mountain view and nature experience', 3, 2, 7000.00, 30, 28, '2026-12-20', 'sajek.jpg', 'Available'),
(5, 7, 6, 1, 'Rangamati Town Tour', 'Rangamati Main Town is the tranquil, lakeside capital and administrative headquarters of the Rangamati Hill District in the southeastern Chittagong Hill Tracts of Bangladesh. Famously known as the \"Lake City of Bangladesh,\" the entire urban core is uniquely wrapped around the sprawling, finger-like branches of Kaptai Lake—the largest artificial lake in the country.', 3, 2, 8000.00, 20, 18, '2026-09-28', 'rangamati_main_town.jfif', 'Available'),
(6, 9, 9, 11, 'Chittagong Sea Tour', 'Come along on an adventure to visit the coastal beauties of Chittagong', 5, 4, 15000.00, 30, 30, '2026-09-29', 'Patenga.jpg', 'Available'),
(7, 8, 8, 10, 'World of lake', 'Take in the natural beauty of Chittagong by taking a dip in the world of lakes.', 5, 6, 20000.00, 25, 25, '2026-10-01', 'Foys_Lake.jpg', 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `transport`
--

CREATE TABLE `transport` (
  `transport_id` int(11) NOT NULL,
  `type` varchar(60) NOT NULL,
  `provider` varchar(120) NOT NULL,
  `route` varchar(180) DEFAULT NULL,
  `price_per_person` decimal(10,2) NOT NULL,
  `departure` varchar(50) NOT NULL DEFAULT 'Flexible'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transport`
--

INSERT INTO `transport` (`transport_id`, `type`, `provider`, `route`, `price_per_person`, `departure`) VALUES
(1, 'Bus', 'Green Line', 'Dhaka - Cox\'s Bazar', 1500.00, '10:15 pm'),
(2, 'Bus', 'Hanif', 'Dhaka - Sajek', 1200.00, '9:00 pm'),
(3, 'Bus', 'Hanif', 'Dhaka - Sajek', 1200.00, '10:50 pm'),
(4, 'Flight', 'US-Bangla', 'Dhaka - Cox\'s Bazar', 4000.00, '8:00 am'),
(6, 'Bus', 'Green Line', 'Dhaka - Rangamati', 1500.00, '9:00 pm'),
(7, 'Bus', 'Hanif', 'Dhaka - Rangamati', 1400.00, '10:15 pm'),
(8, 'Flight', 'Biman Bangladesh', 'Dhaka - Sylhet', 5749.00, '7:30 am'),
(9, 'Train', 'Parabat Express', 'Dhaka - Sylhet', 2000.00, '6:30 pm'),
(10, 'Flight', 'Novoair', 'Dhaka - Chittagong', 4049.00, '12:00 pm'),
(11, 'Train', 'Parjatak Express', 'Dhaka - Chittagong', 2150.00, '6:43 am'),
(12, 'Bus', 'Shanti Paribahan', 'Dhaka - Sajek', 1200.00, '11:00 pm');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `phone`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Rahim Ahmed', 'rahim@gmail.com', '01700000000', '123456', 'admin', '2026-09-10 13:02:32'),
(2, 'Test User', 'testuser@test.com', '0172345172', '$2y$10$1nJWzu7K58TttMZsaHwMJu3evuiWfFOkdlVQ4hY0GOXL7Fk.HazLG', 'user', '2026-09-17 13:51:06'),
(3, 'admin', 'admin@gmail.com', '01700000000', '$2y$10$RaymaF3eNOcnqdxQZQ47QeCO2YCsECzyr3.rGMnMgy8ZetRRZaYtW', 'admin', '2026-09-18 15:56:45'),
(6, 'Nahiyan', 'nahiyan@gmail.com', '0178273995', '$2y$10$QHaHUA2fLophy94zWT8CFO8wE0ZsQvNv0ADpaZWVEfbNBWgVZRxVO', 'user', '2026-09-25 13:00:40'),
(7, 'Nihongo', 'nihon@gmail.com', '01782734974', '$2y$10$Y1.Q5x6nW22yRqOLfpZm3OlmmqTKEUMF99.lYKiJ1lbFsSUNNhWhG', 'user', '2026-09-25 17:45:57');

-- --------------------------------------------------------

--
-- Structure for view `available_packages`
--
DROP TABLE IF EXISTS `available_packages`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `available_packages`  AS SELECT `tp`.`package_id` AS `package_id`, `tp`.`destination_id` AS `destination_id`, `d`.`destination_name` AS `destination_name`, `tp`.`hotel_id` AS `hotel_id`, `h`.`hotel_name` AS `hotel_name`, `tp`.`transport_id` AS `transport_id`, `t`.`type` AS `transport_type`, `t`.`provider` AS `transport_provider`, `tp`.`package_name` AS `package_name`, `tp`.`description` AS `description`, `tp`.`duration_days` AS `duration_days`, `tp`.`duration_nights` AS `duration_nights`, `tp`.`price` AS `price`, `tp`.`total_seats` AS `total_seats`, `tp`.`available_seats` AS `available_seats`, `tp`.`departure_date` AS `departure_date`, `tp`.`image_url` AS `image_url`, `tp`.`status` AS `status` FROM (((`tour_packages` `tp` join `destinations` `d` on(`tp`.`destination_id` = `d`.`destination_id`)) left join `hotels` `h` on(`tp`.`hotel_id` = `h`.`hotel_id`)) left join `transport` `t` on(`tp`.`transport_id` = `t`.`transport_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `package_id` (`package_id`);

--
-- Indexes for table `destinations`
--
ALTER TABLE `destinations`
  ADD PRIMARY KEY (`destination_id`);

--
-- Indexes for table `hotels`
--
ALTER TABLE `hotels`
  ADD PRIMARY KEY (`hotel_id`),
  ADD KEY `destination_id` (`destination_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`);

--
-- Indexes for table `tour_packages`
--
ALTER TABLE `tour_packages`
  ADD PRIMARY KEY (`package_id`),
  ADD KEY `destination_id` (`destination_id`),
  ADD KEY `hotel_id` (`hotel_id`),
  ADD KEY `transport_id` (`transport_id`);

--
-- Indexes for table `transport`
--
ALTER TABLE `transport`
  ADD PRIMARY KEY (`transport_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `destinations`
--
ALTER TABLE `destinations`
  MODIFY `destination_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `hotels`
--
ALTER TABLE `hotels`
  MODIFY `hotel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tour_packages`
--
ALTER TABLE `tour_packages`
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `transport`
--
ALTER TABLE `transport`
  MODIFY `transport_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `tour_packages` (`package_id`);

--
-- Constraints for table `hotels`
--
ALTER TABLE `hotels`
  ADD CONSTRAINT `hotels_ibfk_1` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`destination_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`);

--
-- Constraints for table `tour_packages`
--
ALTER TABLE `tour_packages`
  ADD CONSTRAINT `tour_packages_ibfk_1` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`destination_id`),
  ADD CONSTRAINT `tour_packages_ibfk_2` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`hotel_id`),
  ADD CONSTRAINT `tour_packages_ibfk_3` FOREIGN KEY (`transport_id`) REFERENCES `transport` (`transport_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
