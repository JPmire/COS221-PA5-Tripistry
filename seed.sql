-- 1. Create Mock Users (2 Agencies, 2 Travellers)
-- All passwords are hashed using BCRYPT corresponding to the string 'password123'
INSERT INTO User (Email, PasswordHash, DateJoined, AccountStatus) VALUES 
('contact@wanderlust.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', CURDATE(), 'Active'),
('info@globetrek.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', CURDATE(), 'Active'),
('alice@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', CURDATE(), 'Active'),
('bob@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', CURDATE(), 'Active'),
('charlie@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', CURDATE(), 'Active');

-- 2. Define the Agencies (IDs 1 and 2)
INSERT INTO TravelAgency (UserID, AgencyName, RegistrationNumber, AverageRating, Address_Street, Address_City, Address_Zip) VALUES 
(1, 'Wanderlust Travels', 'REG-1001', 4.8, '123 Explorer St', 'New York', '10001'),
(2, 'GlobeTrek Expeditions', 'REG-2002', 3.2, '45 Adventure Blvd', 'London', 'E1 6AN');

-- 3. Define the Travellers (IDs 3, 4 and 5)
INSERT INTO Traveller (UserID, FirstName, LastName, DOB, SoloBudget) VALUES 
(3, 'Alice', 'Smith', '1995-05-15', 3500.00),
(4, 'Bob', 'Jones', '1990-10-20', 1600.00),
(5, 'Charlie', 'Brown', '1988-03-25', 2500.00);

-- 3b. Define Traveller Preferences (Multivalued Attribute)
INSERT INTO Traveller_Preferences (UserID, Preference) VALUES
(3, 'Paris'),
(3, 'Japan'),
(3, 'Tokyo'),
(3, 'Kyoto'),
(3, 'Eiffel Tower'),
(4, 'Bali'),
(4, 'Asia'),
(4, 'Indonesian Cuisine'),
(5, 'Tokyo'),
(5, 'Japan'),
(5, 'Rome'),
(5, 'Colosseum');

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
('Bali Beach Villa', 'Resort', 320.00, 5),
('Rome Palace Hotel', 'Hotel', 210.00, 4);

-- 6. Create Flights
INSERT INTO Flight (Airline, FlightNum, DepTime, ArrTime, Cost, DepAirport_Code, DepAirport_Name, ArrAirport_Code, ArrAirport_Name) VALUES
('Air France', 'AF015', DATE_ADD(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 10 DAY) + INTERVAL 8 HOUR, 450.00, 'JFK', 'John F. Kennedy International Airport', 'CDG', 'Charles de Gaulle Airport'),
('Japan Airlines', 'JL006', DATE_ADD(NOW(), INTERVAL 15 DAY), DATE_ADD(NOW(), INTERVAL 15 DAY) + INTERVAL 14 HOUR, 850.00, 'LAX', 'Los Angeles International Airport', 'HND', 'Haneda Airport'),
('Emirates', 'EK201', DATE_ADD(NOW(), INTERVAL 20 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY) + INTERVAL 12 HOUR, 600.00, 'DXB', 'Dubai International Airport', 'FCO', 'Leonardo da Vinci-Fiumicino Airport'),
('Qantas', 'QF001', DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 5 DAY) + INTERVAL 22 HOUR, 1200.00, 'SYD', 'Sydney Airport', 'DPS', 'Ngurah Rai International Airport');

-- 7. Create Attractions
INSERT INTO Attraction (Name, Category, EntryFee, Coordinates_Lat, Coordinates_Long) VALUES
('Eiffel Tower', 'Landmark', 25.00, 48.85840000, 2.29450000),
('Louvre Museum', 'Art Museum', 17.00, 48.86060000, 2.33760000),
('Senso-ji Temple', 'Buddhist Temple', 0.00, 35.71480000, 139.79670000),
('Fushimi Inari Shrine', 'Shinto Shrine', 0.00, 34.96710000, 135.77270000),
('Ubud Monkey Forest', 'Nature Reserve', 5.00, -8.51860000, 115.25830000),
('Colosseum', 'Amphitheater', 16.00, 41.89020000, 12.49220000);

-- 8. Create Restaurants
INSERT INTO Restaurant (Name, CuisineType, AverageCost, Coordinates_Lat, Coordinates_Long) VALUES
('Le Jules Verne', 'French Fine Dining', 200.00, 48.85840000, 2.29450000),
('Shinjuku Ramen Haru', 'Japanese Ramen', 15.00, 35.69090000, 139.70030000),
('Ubud Organic Cafe', 'Vegetarian', 12.00, -8.50690000, 115.26250000),
('Roma Trattoria Da Enzo', 'Italian Pasta', 35.00, 41.89020000, 12.49220000);

-- 9. Create Travel Packages
INSERT INTO TravelPackage (Title, Description, BasePrice, DurationDays, MaxCapacity, AgencyID) VALUES 
('Romantic Paris Getaway', 'Experience the city of love with guided tours and exquisite dining.', 1500.00, 5, 12, 1),
('Ultimate Japan Explorer', 'Journey through Tokyo and Kyoto.', 2800.00, 14, 20, 2),
('Bali Island Retreat', 'Relax on pristine beaches and explore ancient temples.', 850.00, 7, 30, 1),
('Taste of Italy', 'A culinary journey through Rome.', 1200.00, 6, 15, 2),
('Tokyo Weekend Flash', 'A quick, action-packed weekend in Tokyo.', 600.00, 3, 10, 1);

-- 10. Link Packages to Destinations (Associative Table)
INSERT INTO Package_Destination (PackageID, DestID) VALUES 
(1, 1), -- Paris Getaway -> Paris
(2, 2), -- Japan Explorer -> Tokyo
(2, 3), -- Japan Explorer -> Kyoto
(3, 4), -- Bali Retreat -> Bali
(4, 5), -- Taste of Italy -> Rome
(5, 2); -- Tokyo Weekend -> Tokyo

-- 11. Link Packages to Accommodations
INSERT INTO Package_Accommodation (PackageID, AccommID) VALUES 
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 2);

-- 12. Link Packages to Flights
INSERT INTO Package_Flight (PackageID, FlightID) VALUES
(1, 1),
(2, 2),
(3, 4),
(4, 3),
(5, 2);

-- 13. Link Packages to Attractions
INSERT INTO Package_Attraction (PackageID, AttractionID) VALUES
(1, 1), (1, 2),
(2, 3), (2, 4),
(3, 5),
(4, 6),
(5, 3);

-- 14. Link Packages to Restaurants
INSERT INTO Package_Restaurant (PackageID, RestaurantID) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 2);

-- 15. Create Scheduled Group Trips
INSERT INTO GroupTrip (PackageID, TripDateID, StartDate, EndDate, Status) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'Scheduled'),
(1, 2, DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'Scheduled'),
(2, 1, DATE_ADD(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 29 DAY), 'Scheduled'),
(3, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Scheduled'),
(4, 1, DATE_ADD(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 26 DAY), 'Scheduled'),
(5, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Scheduled');

-- 16. Add Some Traveller Reviews
INSERT INTO Review (TravellerID, ReviewID, Rating, Comment, TargetPackageID) VALUES 
(3, 1, 5, 'Absolutely magical experience. The hotel was perfect!', 1),
(4, 1, 4, 'Great trip, but the flight was a bit long.', 2),
(3, 2, 3, 'It was okay. Rained most of the time.', 3);