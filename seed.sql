-- ====================================================================
-- TRIPISTRY COMPREHENSIVE SEED DATASET
-- Designed for rigorous testing of all relational, transactional, and dynamic UI elements.
-- All mock users share the password 'password123' (hashed using standard PHP BCRYPT).
-- ====================================================================

-- Disable constraints to ensure clean deletion and insertion sequence
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE Traveller_Preferences;
TRUNCATE TABLE TravelAgency_Contacts;
TRUNCATE TABLE Package_Destination;
TRUNCATE TABLE Package_Flight;
TRUNCATE TABLE Package_Accommodation;
TRUNCATE TABLE Package_Attraction;
TRUNCATE TABLE Package_Restaurant;
TRUNCATE TABLE Notification;
TRUNCATE TABLE Review;
TRUNCATE TABLE Booking;
TRUNCATE TABLE GroupTrip;
TRUNCATE TABLE TravelPackage;
TRUNCATE TABLE Restaurant;
TRUNCATE TABLE Attraction;
TRUNCATE TABLE Accommodation;
TRUNCATE TABLE Flight;
TRUNCATE TABLE Destination;
TRUNCATE TABLE Traveller;
TRUNCATE TABLE TravelAgency;
TRUNCATE TABLE User;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------
-- 1. BASE SUPERCLASS: User Table
-- ---------------------------------------------------------
-- Seed 10 Users: 3 Agencies (IDs 1, 2, 6) and 7 Travellers (IDs 3, 4, 5, 7, 8, 9, 10)
-- All accounts use BCRYPT hash for 'password123'
INSERT INTO User (UserID, Email, PasswordHash, DateJoined, AccountStatus) VALUES 
(1, 'contact@wanderlust.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'Active'),
(2, 'info@globetrek.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', DATE_SUB(CURDATE(), INTERVAL 45 DAY), 'Active'),
(3, 'alice@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Active'),
(4, 'bob@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Active'),
(5, 'charlie@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'Active'),
(6, 'apex@agency.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'Active'),
(7, 'diana@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Active'),
(8, 'evan@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Active'),
(9, 'fiona@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Active'),
(10, 'george@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', CURDATE(), 'Active');

-- ---------------------------------------------------------
-- 2. SUBCLASSES: TravelAgency Table
-- ---------------------------------------------------------
INSERT INTO TravelAgency (UserID, AgencyName, RegistrationNumber, AverageRating, Address_Street, Address_City, Address_Zip) VALUES 
(1, 'Wanderlust Travels', 'REG-1001', 4.80, '123 Explorer St', 'New York', '10001'),
(2, 'GlobeTrek Expeditions', 'REG-2002', 3.20, '45 Adventure Blvd', 'London', 'E1 6AN'),
(6, 'Apex Alpine Adventures', 'REG-3003', 4.50, '789 Summit Rd', 'Denver', '80202');

-- ---------------------------------------------------------
-- 3. SUBCLASSES: Traveller Table
-- ---------------------------------------------------------
INSERT INTO Traveller (UserID, FirstName, LastName, DOB, SoloBudget) VALUES 
(3, 'Alice', 'Smith', '1995-05-15', 5500.00),
(4, 'Bob', 'Jones', '1990-10-20', 1600.00),
(5, 'Charlie', 'Brown', '1988-03-25', 2900.00),
(7, 'Diana', 'Prince', '1992-06-18', 8500.00),
(8, 'Evan', 'Wright', '1998-11-05', 1800.00),
(9, 'Fiona', 'Gallagher', '1994-04-12', 1200.00),
(10, 'George', 'Lucas', '1975-05-14', 25000.00);

-- ---------------------------------------------------------
-- 4. MULTIVALUED ATTRIBUTES: Traveller_Preferences
-- ---------------------------------------------------------
-- Preferences are cross-referenced by our compatibility matchmaking algorithm!
INSERT INTO Traveller_Preferences (UserID, Preference) VALUES
(3, 'Paris'),
(3, 'Japan'),
(3, 'Tokyo'),
(3, 'Kyoto'),
(3, 'Eiffel Tower'),
(3, 'Luxury Stay'),
(4, 'Bali'),
(4, 'Asia'),
(4, 'Indonesian Cuisine'),
(4, 'Beaches'),
(5, 'Tokyo'),
(5, 'Japan'),
(5, 'Rome'),
(5, 'Colosseum'),
(7, 'Swiss Alps'),
(7, 'Hiking'),
(7, 'Luxury Stay'),
(7, 'Paris'),
(8, 'Bali'),
(8, 'Budget Travel'),
(8, 'Surfing'),
(9, 'New York'),
(9, 'Museums'),
(9, 'Budget Travel'),
(10, 'Tokyo'),
(10, 'Rome'),
(10, 'Luxury Stay'),
(10, 'Fine Dining');

-- ---------------------------------------------------------
-- 5. MULTIVALUED ATTRIBUTES: TravelAgency_Contacts
-- ---------------------------------------------------------
INSERT INTO TravelAgency_Contacts (UserID, ContactNumber) VALUES
(1, '+1-555-0199'),
(1, '+1-555-0210'),
(2, '+44-20-7946-0958'),
(6, '+1-303-444-9876');

-- ---------------------------------------------------------
-- 6. INDEPENDENT ENTITIES: Destination Table
-- ---------------------------------------------------------
INSERT INTO Destination (DestID, Name, Country, Region, PopularityScore, Coordinates_Lat, Coordinates_Long) VALUES 
(1, 'Paris', 'France', 'Europe', 95, 48.85660000, 2.35220000),
(2, 'Tokyo', 'Japan', 'Asia', 98, 35.67620000, 139.65030000),
(3, 'Kyoto', 'Japan', 'Asia', 88, 35.01160000, 135.76810000),
(4, 'Bali', 'Indonesia', 'Asia', 92, -8.40950000, 115.18890000),
(5, 'Rome', 'Italy', 'Europe', 90, 41.90280000, 12.49640000),
(6, 'Swiss Alps (Zermatt)', 'Switzerland', 'Europe', 89, 46.02070000, 7.74910000),
(7, 'New York City', 'USA', 'North America', 94, 40.71280000, -74.00600000);

-- ---------------------------------------------------------
-- 7. INDEPENDENT ENTITIES: Flight Table
-- ---------------------------------------------------------
INSERT INTO Flight (FlightID, Airline, FlightNum, DepTime, ArrTime, Cost, DepAirport_Code, DepAirport_Name, ArrAirport_Code, ArrAirport_Name) VALUES
(1, 'Air France', 'AF015', DATE_ADD(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 10 DAY) + INTERVAL 8 HOUR, 450.00, 'JFK', 'John F. Kennedy International Airport', 'CDG', 'Charles de Gaulle Airport'),
(2, 'Japan Airlines', 'JL006', DATE_ADD(NOW(), INTERVAL 15 DAY), DATE_ADD(NOW(), INTERVAL 15 DAY) + INTERVAL 14 HOUR, 850.00, 'LAX', 'Los Angeles International Airport', 'HND', 'Haneda Airport'),
(3, 'Emirates', 'EK201', DATE_ADD(NOW(), INTERVAL 20 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY) + INTERVAL 12 HOUR, 600.00, 'DXB', 'Dubai International Airport', 'FCO', 'Leonardo da Vinci-Fiumicino Airport'),
(4, 'Qantas', 'QF001', DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY) + INTERVAL 22 HOUR, 1200.00, 'SYD', 'Sydney Airport', 'DPS', 'Ngurah Rai International Airport'),
(5, 'Swiss Air', 'LX018', DATE_ADD(NOW(), INTERVAL 12 DAY), DATE_ADD(NOW(), INTERVAL 12 DAY) + INTERVAL 9 HOUR, 550.00, 'ORD', 'O-Hare International Airport', 'ZRH', 'Zurich Airport'),
(6, 'Delta Airlines', 'DL102', DATE_ADD(NOW(), INTERVAL 8 DAY), DATE_ADD(NOW(), INTERVAL 8 DAY) + INTERVAL 2 HOUR, 190.00, 'ATL', 'Hartsfield-Jackson Atlanta Airport', 'JFK', 'John F. Kennedy International Airport');

-- ---------------------------------------------------------
-- 8. INDEPENDENT ENTITIES: Accommodation Table
-- ---------------------------------------------------------
INSERT INTO Accommodation (AccommID, Name, Type, PricePerNight, StarRating, Address_Street, Address_City, Address_Zip, Coordinates_Lat, Coordinates_Long) VALUES 
(1, 'Eiffel View Suites', 'Hotel', 250.00, 4, '15 Avenue de la Bourdonnais', 'Paris', '75007', 48.85840000, 2.29450000),
(2, 'Shinjuku Neon Inn', 'Hotel', 180.00, 3, '1-19 Shinjuku', 'Tokyo', '160-0022', 35.69090000, 139.70030000),
(3, 'Bali Beach Villa', 'Resort', 320.00, 5, 'Jalan Pantai Kuta No. 8', 'Kuta', '80361', -8.72240000, 115.17060000),
(4, 'Rome Palace Hotel', 'Hotel', 210.00, 4, 'Via dei Condotti, 12', 'Rome', '00187', 41.90560000, 12.48230000),
(5, 'The Matterhorn Peak Chalet', 'Chalet', 450.00, 5, 'Winkelmattenweg 15', 'Zermatt', '3920', 46.01230000, 7.74450000),
(6, 'Manhattan Broadway Club', 'Hotel', 150.00, 3, '1650 Broadway', 'New York', '10019', 40.76150000, -73.98410000);

-- ---------------------------------------------------------
-- 9. INDEPENDENT ENTITIES: Attraction Table
-- ---------------------------------------------------------
INSERT INTO Attraction (AttractionID, Name, Category, EntryFee, Coordinates_Lat, Coordinates_Long) VALUES
(1, 'Eiffel Tower', 'Landmark', 25.00, 48.85840000, 2.29450000),
(2, 'Louvre Museum', 'Art Museum', 17.00, 48.86060000, 2.33760000),
(3, 'Senso-ji Temple', 'Buddhist Temple', 0.00, 35.71480000, 139.79670000),
(4, 'Fushimi Inari Shrine', 'Shinto Shrine', 0.00, 34.96710000, 135.77270000),
(5, 'Ubud Monkey Forest', 'Nature Reserve', 5.00, -8.51860000, 115.25830000),
(6, 'Colosseum', 'Amphitheater', 16.00, 41.89020000, 12.49220000),
(7, 'Matterhorn Glacier Paradise', 'Mountain Peak', 85.00, 45.93830000, 7.72890000),
(8, 'Empire State Building', 'Observation Deck', 42.00, 40.74840000, -73.98570000);

-- ---------------------------------------------------------
-- 10. INDEPENDENT ENTITIES: Restaurant Table
-- ---------------------------------------------------------
INSERT INTO Restaurant (RestaurantID, Name, CuisineType, AverageCost, Coordinates_Lat, Coordinates_Long) VALUES
(1, 'Le Jules Verne', 'French Fine Dining', 200.00, 48.85840000, 2.29450000),
(2, 'Shinjuku Ramen Haru', 'Japanese Ramen', 15.00, 35.69090000, 139.70030000),
(3, 'Ubud Organic Cafe', 'Vegetarian', 12.00, -8.50690000, 115.26250000),
(4, 'Roma Trattoria Da Enzo', 'Italian Pasta', 35.00, 41.89020000, 12.49220000),
(5, 'Zermatt Alpine Fondue', 'Swiss Traditional', 60.00, 46.02070000, 7.74910000),
(6, 'Joe-s Pizza Times Square', 'American Pizza', 8.00, 40.75620000, -73.98680000);

-- ---------------------------------------------------------
-- 11. TravelPackage Table (Dependencies: TravelAgency)
-- ---------------------------------------------------------
INSERT INTO TravelPackage (PackageID, Title, Description, BasePrice, DurationDays, MaxCapacity, AIGeneratedSummary, AgencyID) VALUES 
(1, 'Romantic Paris Getaway', 'Experience the city of love with guided tours, historical museum access, and exquisite dining with spectacular tower views.', 1500.00, 5, 12, 'Includes high-end accommodations at Eiffel View Suites, direct Air France transit, and dining inside the iconic Eiffel Tower itself.', 1),
(2, 'Ultimate Japan Explorer', 'An epic two-week adventure through the high-tech neon blocks of Tokyo and the historic shrines of ancient Kyoto.', 2800.00, 14, 20, 'Perfectly balances modern pop-culture experiences with quiet spiritual walks in Fushimi Inari.', 2),
(3, 'Bali Island Retreat', 'Unwind in tranquil seaside resort villas and explore deep emerald rainforest reserves and volcanic hills.', 850.00, 7, 30, 'Features private luxury beach cottages, reef-snorkeling expeditions, and trips into the sanctuary of Ubud Monkey Forest.', 1),
(4, 'Taste of Italy', 'A premium historic and culinary excursion exploring Roman monuments and the best neighborhood trattorias.', 1200.00, 6, 15, 'Savor artisan pizzas and world-renowned pasta dishes while enjoying direct access to the Roman Colosseum.', 2),
(5, 'Tokyo Weekend Flash', 'An action-packed quick excursion into the pulsing heart of modern Tokyo.', 600.00, 3, 10, 'Designed for fast-paced travellers looking to capture the iconic sights and tastes of Shinjuku neon strips.', 1),
(6, 'Swiss Luxury Alps Skiing', 'Elevated high-altitude powder skiing and luxurious alpine fireside cabins in Zermatt.', 3800.00, 8, 8, 'Premium snow sports packages including full ski lift passes, Swiss fondue banquets, and beautiful Matterhorn views.', 6),
(7, 'New York City Lights Tour', 'Enjoy the theater, dynamic skyline landmarks, and culinary neighborhoods of the Big Apple.', 950.00, 4, 25, 'Walk Broadway street grids, ascend the historic Empire State Building, and grab legendary local slices.', 1);

-- ---------------------------------------------------------
-- 12. ASSOCIATIVE TABLES (M:N Relations)
-- ---------------------------------------------------------

-- 12a. Package_Destination
INSERT INTO Package_Destination (PackageID, DestID) VALUES 
(1, 1), -- Paris
(2, 2), (2, 3), -- Tokyo, Kyoto
(3, 4), -- Bali
(4, 5), -- Rome
(5, 2), -- Tokyo
(6, 6), -- Swiss Alps
(7, 7); -- New York

-- 12b. Package_Flight
INSERT INTO Package_Flight (PackageID, FlightID) VALUES
(1, 1),
(2, 2),
(3, 4),
(4, 3),
(5, 2),
(6, 5),
(7, 6);

-- 12c. Package_Accommodation
INSERT INTO Package_Accommodation (PackageID, AccommID) VALUES 
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 2),
(6, 5),
(7, 6);

-- 12d. Package_Attraction
INSERT INTO Package_Attraction (PackageID, AttractionID) VALUES
(1, 1), (1, 2),
(2, 3), (2, 4),
(3, 5),
(4, 6),
(5, 3),
(6, 7),
(7, 8);

-- 12e. Package_Restaurant
INSERT INTO Package_Restaurant (PackageID, RestaurantID) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 2),
(6, 5),
(7, 6);

-- ---------------------------------------------------------
-- 13. GroupTrip Table
-- ---------------------------------------------------------
-- Includes active, future, and past trips for analytical dashboard rendering!
INSERT INTO GroupTrip (PackageID, TripDateID, StartDate, EndDate, Status) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'Scheduled'),
(1, 2, DATE_ADD(CURDATE(), INTERVAL 40 DAY), DATE_ADD(CURDATE(), INTERVAL 45 DAY), 'Scheduled'),
(2, 1, DATE_ADD(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 29 DAY), 'Scheduled'),
(3, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Scheduled'),
(3, 2, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Completed'),
(4, 1, DATE_ADD(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 26 DAY), 'Scheduled'),
(5, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Ongoing'),
(6, 1, DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 38 DAY), 'Scheduled'),
(7, 1, DATE_ADD(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 29 DAY), 'Scheduled');

-- ---------------------------------------------------------
-- 14. Booking Table (Simulates financial contributions & seat holds!)
-- ---------------------------------------------------------
INSERT INTO Booking (BookingID, BookingDate, TotalAmount, PaymentStatus, PartySize, TravellerID, Trip_PackageID, Trip_TripDateID) VALUES
-- Active paid bookings for Wanderlust Travels (Agency ID 1)
(1, DATE_SUB(NOW(), INTERVAL 10 DAY), 3000.00, 'Paid', 2, 3, 1, 1), -- Alice Smith booked Paris (2 people)
(2, DATE_SUB(NOW(), INTERVAL 8 DAY), 1500.00, 'Paid', 1, 7, 1, 1),  -- Diana Prince booked Paris
(3, DATE_SUB(NOW(), INTERVAL 5 DAY), 850.00, 'Paid', 1, 5, 3, 1),   -- Charlie Brown booked Bali
(4, DATE_SUB(NOW(), INTERVAL 1 DAY), 1700.00, 'Paid', 2, 10, 3, 1),  -- George Lucas booked Bali (2 people)

-- Pending bookings (held seats)
(5, DATE_SUB(NOW(), INTERVAL 3 DAY), 2800.00, 'Pending', 1, 4, 2, 1), -- Bob Jones pending on Japan
(6, DATE_SUB(NOW(), INTERVAL 2 DAY), 11200.00, 'Paid', 4, 10, 2, 1),  -- George Lucas paid on Japan (4 people)
(7, NOW(), 3800.00, 'Pending', 1, 7, 6, 1),                            -- Diana Prince pending on Swiss Alps

-- Failed/Refunded bookings for safety checks
(8, DATE_SUB(NOW(), INTERVAL 12 DAY), 850.00, 'Refunded', 1, 8, 3, 2), -- Evan Wright refunded on Completed Bali trip
(9, DATE_SUB(NOW(), INTERVAL 15 DAY), 600.00, 'Failed', 1, 9, 5, 1);   -- Fiona Gallagher failed booking on Tokyo Flash

-- ---------------------------------------------------------
-- 15. Review Table
-- ---------------------------------------------------------
-- Highly tailored feedback commenting to test our sentiment lexicon engine!
-- Scores: POSITIVE (0.3 to 1.0), NEGATIVE (-0.3 to -1.0), NEUTRAL (-0.2 to 0.2)
INSERT INTO Review (TravellerID, ReviewID, Rating, Comment, DatePosted, SentimentScore, TargetAgencyID, TargetPackageID) VALUES 
-- Package Reviews
(3, 1, 5, 'Absolutely magical experience in Paris! The Eiffel suites were perfect and food was lovely.', DATE_SUB(NOW(), INTERVAL 5 DAY), 0.90, NULL, 1),
(4, 1, 4, 'Great trip, exciting attractions, but the long flight was rather tiring.', DATE_SUB(NOW(), INTERVAL 4 DAY), 0.35, NULL, 2),
(5, 1, 3, 'It was average. The beach was good but the weather got horrible on Tuesday.', DATE_SUB(NOW(), INTERVAL 3 DAY), -0.10, NULL, 3),
(7, 1, 5, 'Exquisite fine dining and absolute luxury hospitality! Very happy.', DATE_SUB(NOW(), INTERVAL 2 DAY), 0.85, NULL, 1),
(8, 1, 1, 'Terrible coordination. The booking was delayed and the resort rooms were awful and dirty.', DATE_SUB(NOW(), INTERVAL 1 DAY), -0.80, NULL, 3),

-- Direct Agency Reviews
(3, 2, 5, 'Highly professional agency. Wanderlust travels is absolutely amazing and helpful.', DATE_SUB(NOW(), INTERVAL 5 DAY), 0.95, 1, NULL),
(4, 2, 2, 'Unprofessional service. Very bad communication during our flight delays.', DATE_SUB(NOW(), INTERVAL 4 DAY), -0.65, 2, NULL),
(10, 1, 5, 'Incredible service! Perfect organization of our large party booking. Exquisite support.', NOW(), 0.98, 1, NULL);

-- Recalculate average agency ratings based on our seeded values
UPDATE TravelAgency SET AverageRating = 4.90 WHERE UserID = 1;
UPDATE TravelAgency SET AverageRating = 3.00 WHERE UserID = 2;

-- ---------------------------------------------------------
-- 16. Notification Table
-- ---------------------------------------------------------
INSERT INTO Notification (NotificationID, UserID, Title, Message, IsRead, CreatedAt) VALUES 
-- Notifications for Alice (Traveller UserID 3)
(1, 3, 'Booking Confirmed!', 'Your premium booking for Romantic Paris Getaway has been paid successfully.', 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 3, 'Review Sentiment Scored', 'Thank you! Your recent review for Paris Getaway scored positively at +0.90. The agency appreciates your review!', 0, DATE_SUB(NOW(), INTERVAL 5 DAY)),

-- Notifications for Wanderlust Travels (Agency UserID 1)
(3, 1, 'New Paid Booking!', 'Explorer Alice Smith has completed booking for Romantic Paris Getaway (2 seats).', 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(4, 1, 'Review Received', 'Traveller Diana Prince left a 5-star rating for Wanderlust Travels: "Incredible service!".', 0, NOW()),

-- Notification for Bob (Traveller UserID 4)
(5, 4, 'Payment Reminder', 'Your booking for Ultimate Japan Explorer is currently pending. Complete checkout to secure your seats!', 0, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Disable constraints back to default
SET FOREIGN_KEY_CHECKS = 1;