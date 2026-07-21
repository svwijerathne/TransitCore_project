-- ============================================================
-- TransitCore — Advanced Route Matching & Ride Requests
-- Migration: migration_ride_requests.sql
-- Run once:  psql transitcoredb -f migration_ride_requests.sql
-- Requires:  PostGIS extension already enabled on the database
-- ============================================================

-- 1. Track when a ride request was made (needed to sort / display
--    "My Ride Requests" and incoming request lists chronologically).
ALTER TABLE ride_requests
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- 2. Spatial indexes so ST_DWithin / ST_Distance lookups on
--    passenger pickup & dropoff points stay fast as the table grows.
CREATE INDEX IF NOT EXISTS idx_ride_requests_pickup
    ON ride_requests USING GIST(pickup_location);

CREATE INDEX IF NOT EXISTS idx_ride_requests_dropoff
    ON ride_requests USING GIST(dropoff_location);

-- 3. Spatial index on rides.route_geom for the advanced matching
--    search. (01_scheme.sql already creates idx_ride_route on this
--    column — IF NOT EXISTS keeps this migration safe to re-run
--    even if that index is present under a different name.)
CREATE INDEX IF NOT EXISTS idx_rides_route_geom
    ON rides USING GIST(route_geom);

-- 4. Helpful plain-column indexes for the filters used on every
--    search / dashboard query.
CREATE INDEX IF NOT EXISTS idx_rides_status ON rides(status);
CREATE INDEX IF NOT EXISTS idx_ride_requests_status ON ride_requests(status);
CREATE INDEX IF NOT EXISTS idx_ride_requests_ride_id ON ride_requests(ride_id);
CREATE INDEX IF NOT EXISTS idx_ride_requests_passenger_id ON ride_requests(passenger_id);

-- 5. Convenience view: open rides joined with driver, user, and
--    vehicle info. search_rides_advanced.php uses this as its base
--    query so PostGIS distance math is the only thing layered on top.
CREATE OR REPLACE VIEW matching_rides AS
SELECT
    r.ride_id,
    r.driver_id,
    r.vehicle_id,
    r.start_address,
    r.destination_address,
    r.departure_time,
    r.available_seats,
    r.price,
    r.status,
    r.route_geom,
    u.first_name,
    u.last_name,
    u.phone,
    d.average_rating   AS driver_rating,
    d.total_rides,
    d.verification_status,
    v.make,
    v.model,
    v.color,
    v.plate_number,
    v.seat_capacity
FROM rides r
JOIN drivers d   ON r.driver_id = d.driver_id
JOIN users u     ON d.user_id = u.user_id
JOIN vehicles v  ON r.vehicle_id = v.vehicle_id
WHERE r.status = 'Open';

-- ============================================================
-- Sample PostGIS queries (for reference — not executed here)
-- ============================================================

-- Find rides whose route passes within 2km of a passenger's
-- pickup point (lng, lat order for ST_GeomFromText/POINT):
--
-- SELECT ride_id
-- FROM rides
-- WHERE status = 'Open'
--   AND ST_DWithin(
--         route_geom::geography,
--         ST_SetSRID(ST_MakePoint(:pickup_lng, :pickup_lat), 4326)::geography,
--         2000
--       );

-- Distance (in km) from a point to a ride's route:
--
-- SELECT
--   ST_Distance(
--     route_geom::geography,
--     ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)::geography
--   ) / 1000 AS distance_km
-- FROM rides
-- WHERE ride_id = :ride_id;