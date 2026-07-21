CREATE TABLE users (
    user_id SERIAL PRIMARY KEY,

    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,

    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    phone VARCHAR(20) UNIQUE,

    role VARCHAR(20) NOT NULL
        CHECK(role IN ('admin','driver','passenger')),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
    admin_id SERIAL PRIMARY KEY,

    user_id INTEGER UNIQUE NOT NULL,

    FOREIGN KEY(user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
);

CREATE TABLE drivers (

    driver_id SERIAL PRIMARY KEY,

    user_id INTEGER UNIQUE NOT NULL,

    license_number VARCHAR(50) UNIQUE NOT NULL,

    verification_status VARCHAR(20)
        DEFAULT 'Pending'
        CHECK(verification_status IN
        ('Pending','Verified','Rejected')),

    average_rating NUMERIC(2,1) DEFAULT 5.0,

    total_rides INTEGER DEFAULT 0,

    FOREIGN KEY(user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
);

CREATE TABLE passengers (

    passenger_id SERIAL PRIMARY KEY,

    user_id INTEGER UNIQUE NOT NULL,

    average_rating NUMERIC(2,1) DEFAULT 5.0,

    total_trips INTEGER DEFAULT 0,

    FOREIGN KEY(user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
);

CREATE TABLE vehicles (

    vehicle_id SERIAL PRIMARY KEY,

    driver_id INTEGER NOT NULL,

    make VARCHAR(50),
    model VARCHAR(50),

    plate_number VARCHAR(20) UNIQUE,

    color VARCHAR(30),

    seat_capacity INTEGER CHECK(seat_capacity > 0),

    FOREIGN KEY(driver_id)
        REFERENCES drivers(driver_id)
        ON DELETE CASCADE
);

CREATE TABLE bus_routes (

    route_id SERIAL PRIMARY KEY,

    route_number VARCHAR(20),

    route_name VARCHAR(100),

    route_geom GEOMETRY(LineString,4326)
);

CREATE TABLE rides (

    ride_id SERIAL PRIMARY KEY,

    driver_id INTEGER NOT NULL,

    vehicle_id INTEGER NOT NULL,

    start_address TEXT NOT NULL,

    destination_address TEXT NOT NULL,

    departure_time TIMESTAMP NOT NULL,

    available_seats INTEGER NOT NULL,

    price DECIMAL(8,2),

    status VARCHAR(20)
        DEFAULT 'Open'
        CHECK(status IN ('Open','Full','Completed','Cancelled')),

    route_geom GEOMETRY(LineString,4326),

    FOREIGN KEY(driver_id)
        REFERENCES drivers(driver_id),

    FOREIGN KEY(vehicle_id)
        REFERENCES vehicles(vehicle_id)
);

CREATE TABLE ride_requests (

    request_id SERIAL PRIMARY KEY,

    ride_id INTEGER,

    passenger_id INTEGER NOT NULL,

    pickup_location GEOMETRY(Point,4326),

    dropoff_location GEOMETRY(Point,4326),

    status VARCHAR(20)
        DEFAULT 'Pending'
        CHECK(status IN ('Pending','Accepted','Rejected','Cancelled')),

    FOREIGN KEY(ride_id)
        REFERENCES rides(ride_id),

    FOREIGN KEY(passenger_id)
        REFERENCES passengers(passenger_id)
);

CREATE TABLE ride_matches (

    match_id SERIAL PRIMARY KEY,

    ride_id INTEGER NOT NULL,

    request_id INTEGER NOT NULL,

    detour_distance DOUBLE PRECISION,

    match_score DOUBLE PRECISION,

    matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(ride_id)
        REFERENCES rides(ride_id),

    FOREIGN KEY(request_id)
        REFERENCES ride_requests(request_id)
);

CREATE TABLE ratings (

    rating_id SERIAL PRIMARY KEY,

    ride_id INTEGER,

    reviewer_id INTEGER,

    reviewee_id INTEGER,

    rating INTEGER
        CHECK(rating BETWEEN 1 AND 5),

    review TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(ride_id)
        REFERENCES rides(ride_id),

    FOREIGN KEY(reviewer_id)
        REFERENCES users(user_id),

    FOREIGN KEY(reviewee_id)
        REFERENCES users(user_id)
);

CREATE INDEX idx_bus_route_geom
ON bus_routes
USING GIST(route_geom);

CREATE INDEX idx_ride_route
ON rides
USING GIST(route_geom);

CREATE INDEX idx_pickup
ON ride_requests
USING GIST(pickup_location);

CREATE INDEX idx_dropoff
ON ride_requests
USING GIST(dropoff_location);

ALTER TABLE users ADD COLUMN nic VARCHAR(20) UNIQUE;
ALTER TABLE users ADD COLUMN gender VARCHAR(20) CHECK (gender IN ('Male', 'Female', 'Other'));


ALTER TABLE users ALTER COLUMN nic SET NOT NULL;
ALTER TABLE users ALTER COLUMN gender SET NOT NULL;

ALTER TABLE vehicles ALTER COLUMN plate_number SET NOT NULL;

select * FROM users ;