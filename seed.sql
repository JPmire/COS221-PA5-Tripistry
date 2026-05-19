-- 1. Create Mock Users (2 Agencies, 2 Travellers)
-- Passwords are just dummy hashes for testing
INSERT INTO User (Email, PasswordHash, DateJoined, AccountStatus) VALUES 
('contact@wanderlust.com', '$2y$10$dummyhash', CURDATE(), 'Active'),
('info@globetrek.com', '$2y$10$dummyhash', CURDATE(), 'Active'),
('alice@gmail.com', '$2y$10$dummyhash', CURDATE(), 'Active'),
('bob@gmail.com', '$2y$10$dummyhash', CURDATE(), 'Active');

-- 2. Define the Agencies (IDs 1 and 2)
INSERT INTO TravelAgency (UserID, AgencyName, RegistrationNumber, AverageRating, Address_Street, Address_City, Address_Zip) VALUES 
(1, 'Wanderlust Travels', 'REG-1001', 4.8, '123 Explorer St', 'New York', '10001'),
(2, 'GlobeTrek Expeditions', 'REG-2002', 3.2, '45 Adventure Blvd', 'London', 'E1 6AN');

-- 3. Define the Travellers (IDs 3 and 4)
INSERT INTO Traveller (UserID, FirstName, LastName, DOB) VALUES 
(3, 'Alice', 'Smith', '1995-05-15'),
(4, 'Bob', 'Jones', '1990-10-20');

-- 4. Create Destinations
INSERT INTO Destination (Name, Country, Region) VALUES 
('Paris', 'France', 'Europe'),
('Tokyo', 'Japan', 'Asia'),
('Kyoto', 'Japan', 'Asia'),
('Bali', 'Indonesia', 'Asia'),
('Rome', 'Italy', 'Europe');

-- 5. Create Accommodations
INSERT INTO Accommodation (Name, Type, PricePerNight, StarRating) VALUES 
('Eiffel View Suites', 'Hotel', 250.00, 4),
('Shinjuku Neon Inn', 'Hotel', 180.00, 3),
('Bali Beach Villa', 'Resort', 320.00, 5);

-- 6. Create Travel Packages
-- Varied prices, durations, and agencies to test sorting and filtering
INSERT INTO TravelPackage (Title, Description, BasePrice, DurationDays, MaxCapacity, AgencyID) VALUES 
('Romantic Paris Getaway', 'Experience the city of love with guided tours and exquisite dining.', 1500.00, 5, 12, 1),
('Ultimate Japan Explorer', 'Journey through Tokyo and Kyoto.', 2800.00, 14, 20, 2),
('Bali Island Retreat', 'Relax on pristine beaches and explore ancient temples.', 850.00, 7, 30, 1),
('Taste of Italy', 'A culinary journey through Rome.', 1200.00, 6, 15, 2),
('Tokyo Weekend Flash', 'A quick, action-packed weekend in Tokyo.', 600.00, 3, 10, 1);

-- 7. Link Packages to Destinations (Associative Table)
INSERT INTO Package_Destination (PackageID, DestID) VALUES 
(1, 1), -- Paris Getaway -> Paris
(2, 2), -- Japan Explorer -> Tokyo
(2, 3), -- Japan Explorer -> Kyoto
(3, 4), -- Bali Retreat -> Bali
(4, 5), -- Taste of Italy -> Rome
(5, 2); -- Tokyo Weekend -> Tokyo

-- 8. Link Packages to Accommodations
INSERT INTO Package_Accommodation (PackageID, AccommID) VALUES 
(1, 1),
(2, 2),
(3, 3);

-- 9. Add Some Traveller Reviews
INSERT INTO Review (TravellerID, ReviewID, Rating, Comment, TargetPackageID) VALUES 
(3, 1, 5, 'Absolutely magical experience. The hotel was perfect!', 1),
(4, 1, 4, 'Great trip, but the flight was a bit long.', 2),
(3, 2, 3, 'It was okay. Rained most of the time.', 3);