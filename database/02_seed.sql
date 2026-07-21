TRUNCATE users, drivers, passengers, admins, vehicles, rides, bus_routes RESTART IDENTITY CASCADE;

-- 1. ADMIN USER
INSERT INTO users (first_name, last_name, email, password_hash, phone, role, nic, gender)
VALUES ('Sandali', 'Wijerathne', 'admin@transit.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0711111111', 'admin', '200212345678', 'Female');

INSERT INTO admins (user_id) 
VALUES (1);

-- 2. DRIVER USERS
INSERT INTO users (first_name, last_name, email, password_hash, phone, role, nic, gender) VALUES
('Kamal', 'Silva', 'kamal@driver.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0772222222', 'driver', '199512345678', 'Male'),
('Nimal', 'Perera', 'nimal@driver.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0773333333', 'driver', '199487654321', 'Male'),
('Ruwan', 'Fernando', 'ruwan@driver.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0774444444', 'driver', '199634567891', 'Male'),
('Anura', 'Kumara', 'anura@driver.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0775555555', 'driver', '199398765432', 'Male'),
('Priyantha', 'Jayakody', 'priyantha@driver.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0776666666', 'driver', '199111223344', 'Male');

INSERT INTO drivers (user_id, license_number, verification_status, average_rating, total_rides) VALUES
(2, 'B1234567', 'Pending', 4.8, 12),
(3, 'B7654321', 'Pending', 4.9, 45),
(4, 'B3456789', 'Verified', 5.0, 8),
(5, 'B9876543', 'Verified', 4.7, 23),
(6, 'B1122334', 'Verified', 4.6, 60);

INSERT INTO vehicles (driver_id, make, model, plate_number, color, seat_capacity) VALUES
(1, 'Toyota', 'Prius', 'WP CAS-1122', 'White', 4),
(2, 'Honda', 'Fit', 'WP CBD-5566', 'Silver', 4),
(3, 'Suzuki', 'WagonR', 'WP CBB-9900', 'Black', 4),
(4, 'Toyota', 'Aqua', 'WP CAA-4433', 'Blue', 4),
(5, 'Nissan', 'Leaf', 'WP CCE-8877', 'Red', 4);

-- 3. PASSENGER USERS
INSERT INTO users (first_name, last_name, email, password_hash, phone, role, nic, gender) VALUES
('Saman', 'Gunawardena', 'saman@passenger.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0712223331', 'passenger', '199855667788', 'Male'),
('Dilini', 'Rodrigo', 'dilini@passenger.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0712223332', 'passenger', '199944332211', 'Female'),
('Asanka', 'Fonseka', 'asanka@passenger.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0712223333', 'passenger', '199700112233', 'Male'),
('Menaka', 'Alwis', 'menaka@passenger.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0712223334', 'passenger', '200066778899', 'Female'),
('Kasun', 'Rajapaksha', 'kasun@passenger.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0712223335', 'passenger', '199699887766', 'Male'),
('Chathuri', 'Herath', 'chathuri@passenger.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0712223336', 'passenger', '200122334455', 'Female'),
('Thilina', 'Bandara', 'thilina@passenger.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0712223337', 'passenger', '199588776655', 'Male'),
('Nadeesha', 'Ranaweera', 'nadeesha@passenger.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0712223338', 'passenger', '199411447788', 'Female'),
('Roshan', 'Wickramasinghe', 'roshan@passenger.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0712223339', 'passenger', '199322558899', 'Male'),
('Ishara', 'Mendis', 'ishara@passenger.com', '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze', '0712223340', 'passenger', '200299001122', 'Female');

INSERT INTO passengers (user_id, average_rating, total_trips) VALUES
(7, 4.5, 5),
(8, 4.7, 14),
(9, 4.2, 2),
(10, 5.0, 20),
(11, 4.9, 32),
(12, 4.6, 8),
(13, 4.8, 19),
(14, 4.4, 7),
(15, 3.9, 11),
(16, 5.0, 1);

-- 4. SEED BUS ROUTES (GREEN LINES ON MAP)
INSERT INTO bus_routes (route_number, route_name, route_geom) VALUES
(
    '138', 
    'Colombo - Maharagama', 
    ST_GeomFromText('LINESTRING(79.8438 6.9319, 79.8622 6.9175, 79.8885 6.8923, 79.9248 6.8481)', 4326)
),
(
    '120', 
    'Colombo - Horana', 
    ST_GeomFromText('LINESTRING(79.8438 6.9319, 79.8591 6.8953, 79.8784 6.8312, 79.9515 6.7842, 80.0632 6.7161)', 4326)
),
(
    '177', 
    'Colombo - Kaduwela', 
    ST_GeomFromText('LINESTRING(79.8438 6.9319, 79.8752 6.9272, 79.9234 6.9112, 79.9822 6.9364)', 4326)
),
(
    '154', 
    'Kiribathgoda - Angoda', 
    ST_GeomFromText('LINESTRING(79.9286 6.9794, 79.9312 6.9582, 79.9185 6.9421, 79.9333 6.9300)', 4326)
),
(
    '101', 
    'Colombo - Moratuwa', 
    ST_GeomFromText('LINESTRING(79.8438 6.9319, 79.8512 6.8643, 79.8654 6.7972, 79.8797 6.7731)', 4326)
);

-- 5. SEED ACTIVE RIDES (BLUE LINES ON MAP)
INSERT INTO rides (driver_id, vehicle_id, start_address, destination_address, departure_time, available_seats, price, status, route_geom) VALUES
(
    1, 1, 'Colombo', 'Kandy', 
    '2026-07-20 08:30:00', 4, 2500.00, 'Open',
    ST_GeomFromText('LINESTRING(79.8438 6.9319, 79.9242 7.0234, 80.1245 7.1567, 80.4561 7.2212, 80.6350 7.2906)', 4326)
),
(
    2, 2, 'Colombo', 'Galle', 
    '2026-07-20 14:00:00', 3, 3000.00, 'Open',
    ST_GeomFromText('LINESTRING(79.8438 6.9319, 79.8823 6.7214, 79.9341 6.5852, 80.0421 6.2341, 80.2117 6.0535)', 4326)
),
(
    3, 3, 'Kotte', 'Moratuwa', 
    '2026-07-21 07:15:00', 4, 800.00, 'Open',
    ST_GeomFromText('LINESTRING(79.9036 6.9012, 79.8892 6.8521, 79.8794 6.8104, 79.8797 6.7731)', 4326)
),
(
    4, 4, 'Kadawatha', 'Colombo', 
    '2026-07-21 17:30:00', 2, 650.00, 'Open',
    ST_GeomFromText('LINESTRING(79.9517 7.0014, 79.9214 6.9684, 79.8721 6.9412, 79.8438 6.9319)', 4326)
),
(
    5, 5, 'Negombo', 'Colombo', 
    '2026-07-22 06:00:00', 4, 1500.00, 'Open',
    ST_GeomFromText('LINESTRING(79.8416 7.2089, 79.8741 7.0982, 79.8912 7.0124, 79.8438 6.9319)', 4326)
);

UPDATE users 
SET password_hash = '$2y$10$x8.zf00q8emI4qyS8sH50OzicC/aJcYfitEu8r.6DmYkfdRW6qEze';
select * FROM users ;