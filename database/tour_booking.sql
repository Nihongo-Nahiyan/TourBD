CREATE DATABASE tour_booking_db;

USE tour_booking_db;


CREATE TABLE users(
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(120) UNIQUE NOT NULL,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE destinations(
    destination_id INT AUTO_INCREMENT PRIMARY KEY,
    destination_name VARCHAR(100) NOT NULL,
    location VARCHAR(120),
    description TEXT,
    image_url VARCHAR(500),
    active INT NOT NULL
);


CREATE TABLE hotels(
    hotel_id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT NOT NULL,
    hotel_name VARCHAR(120) NOT NULL,
    contact VARCHAR(80),

    FOREIGN KEY(destination_id)
    REFERENCES destinations(destination_id)
);


CREATE TABLE transport(
    transport_id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(60) NOT NULL,
    provider VARCHAR(120) NOT NULL,
    route VARCHAR(180),
    price_per_person DECIMAL(10,2) NOT NULL
);


CREATE TABLE tour_packages(
    package_id INT AUTO_INCREMENT PRIMARY KEY,

    destination_id INT NOT NULL,
    hotel_id INT NOT NULL,
    transport_id INT NOT NULL,

    package_name VARCHAR(160) NOT NULL,
    description TEXT,

    duration_days INT NOT NULL,
    duration_nights INT NOT NULL,

    price DECIMAL(10,2) NOT NULL,

    total_seats INT NOT NULL,
    available_seats INT NOT NULL,

    departure_date DATE NOT NULL,

    image_url VARCHAR(500),

    status VARCHAR(20) NOT NULL,


    FOREIGN KEY(destination_id)
    REFERENCES destinations(destination_id),

    FOREIGN KEY(hotel_id)
    REFERENCES hotels(hotel_id),

    FOREIGN KEY(transport_id)
    REFERENCES transport(transport_id)
);


CREATE TABLE bookings(
    booking_id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,
    package_id INT NOT NULL,

    travelers INT NOT NULL,

    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    travel_date DATE NOT NULL,

    total_amount DECIMAL(10,2) NOT NULL,

    status VARCHAR(20) NOT NULL,


    FOREIGN KEY(user_id)
    REFERENCES users(user_id),

    FOREIGN KEY(package_id)
    REFERENCES tour_packages(package_id)
);



CREATE TABLE payments(
    payment_id INT AUTO_INCREMENT PRIMARY KEY,

    booking_id INT UNIQUE NOT NULL,

    amount DECIMAL(10,2) NOT NULL,

    method VARCHAR(30) NOT NULL,

    payment_status VARCHAR(20) NOT NULL,

    transaction_ref VARCHAR(80),

    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    FOREIGN KEY(booking_id)
    REFERENCES bookings(booking_id)
);