-- Create Database
DROP DATABASE IF EXISTS `tripistry-cos221`;
CREATE DATABASE `tripistry-cos221`;
USE `tripistry-cos221`;

-- Create Tables
-- 1. BASE SUPERCLASS 
CREATE TABLE User (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    Email VARCHAR(255) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    DateJoined DATE NOT NULL,
    LastLoginTime DATETIME,
    AccountStatus VARCHAR(50) DEFAULT 'Active',
    CONSTRAINT chk_AccountStatus CHECK (AccountStatus IN ('Active', 'Suspended', 'Pending', 'Banned'))
);

-- 2. SUBCLASSES
CREATE TABLE Traveller (
    UserID INT PRIMARY KEY,
    FirstName VARCHAR(100) NOT NULL,
    LastName VARCHAR(100) NOT NULL,
    DOB DATE NOT NULL,
    SoloBudget DECIMAL(10,2) DEFAULT 0.00,
    CONSTRAINT chk_SoloBudget CHECK (SoloBudget >= 0),
    FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
);

CREATE TABLE TravelAgency (
    UserID INT PRIMARY KEY,
    AgencyName VARCHAR(150) NOT NULL,
    RegistrationNumber VARCHAR(100) NOT NULL UNIQUE,
    AverageRating DECIMAL(3,2) DEFAULT 0.00,
    Address_Street VARCHAR(255) NOT NULL,
    Address_City VARCHAR(100) NOT NULL,
    Address_Zip VARCHAR(20) NOT NULL,
    CONSTRAINT chk_AverageRating CHECK (AverageRating >= 0 AND AverageRating <= 5),
    FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
);

-- 3. MULTIVALUED ATTRIBUTES
CREATE TABLE Traveller_Preferences (
    UserID INT NOT NULL,
    Preference VARCHAR(100) NOT NULL,
    PRIMARY KEY (UserID, Preference),
    FOREIGN KEY (UserID) REFERENCES Traveller(UserID) ON DELETE CASCADE
);

CREATE TABLE TravelAgency_Contacts (
    UserID INT NOT NULL,
    ContactNumber VARCHAR(20) NOT NULL,
    PRIMARY KEY (UserID, ContactNumber),
    FOREIGN KEY (UserID) REFERENCES TravelAgency(UserID) ON DELETE CASCADE
);

-- 4. REGULAR INDEPENDENT ENTITIES
CREATE TABLE Destination (
    DestID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(150) NOT NULL,
    Country VARCHAR(100) NOT NULL,
    Region VARCHAR(100),
    PopularityScore INT DEFAULT 0,
    Coordinates_Lat DECIMAL(10,8),
    Coordinates_Long DECIMAL(11,8),
    ImageURL VARCHAR(500) DEFAULT NULL,
    CONSTRAINT chk_Dest_Lat CHECK (Coordinates_Lat BETWEEN -90 AND 90),
    CONSTRAINT chk_Dest_Long CHECK (Coordinates_Long BETWEEN -180 AND 180)
);

CREATE TABLE Flight (
    FlightID INT AUTO_INCREMENT PRIMARY KEY,
    Airline VARCHAR(100) NOT NULL,
    FlightNum VARCHAR(20) NOT NULL,
    DepTime DATETIME NOT NULL,
    ArrTime DATETIME NOT NULL,
    Cost DECIMAL(10,2) NOT NULL,
    DepAirport_Code CHAR(3) NOT NULL,
    DepAirport_Name VARCHAR(150),
    ArrAirport_Code CHAR(3) NOT NULL,
    ArrAirport_Name VARCHAR(150),
    CONSTRAINT chk_FlightCost CHECK (Cost >= 0),
    CONSTRAINT chk_ArrAfterDep CHECK (ArrTime > DepTime)
);

CREATE TABLE Accommodation (
    AccommID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(150) NOT NULL,
    Type VARCHAR(50) NOT NULL,
    PricePerNight DECIMAL(10,2) NOT NULL,
    StarRating INT,
    Address_Street VARCHAR(255),
    Address_City VARCHAR(100),
    Address_Zip VARCHAR(20),
    Coordinates_Lat DECIMAL(10,8),
    Coordinates_Long DECIMAL(11,8),
    ImageURL VARCHAR(500) DEFAULT NULL,
    CONSTRAINT chk_AccommPrice CHECK (PricePerNight >= 0),
    CONSTRAINT chk_StarRating CHECK (StarRating BETWEEN 1 AND 5)
);

CREATE TABLE Attraction (
    AttractionID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(150) NOT NULL,
    Category VARCHAR(100),
    EntryFee DECIMAL(10,2) DEFAULT 0.00,
    Coordinates_Lat DECIMAL(10,8),
    Coordinates_Long DECIMAL(11,8),
    ImageURL VARCHAR(500) DEFAULT NULL,
    CONSTRAINT chk_EntryFee CHECK (EntryFee >= 0)
);

CREATE TABLE Restaurant (
    RestaurantID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(150) NOT NULL,
    CuisineType VARCHAR(100),
    AverageCost DECIMAL(10,2) DEFAULT 0.00,
    Coordinates_Lat DECIMAL(10,8),
    Coordinates_Long DECIMAL(11,8),
    ImageURL VARCHAR(500) DEFAULT NULL,
    CONSTRAINT chk_AverageCost CHECK (AverageCost >= 0)
);

-- 5. RELATIONAL ENTITIES (1:N DEPS)
CREATE TABLE TravelPackage (
    PackageID INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(200) NOT NULL,
    Description TEXT,
    BasePrice DECIMAL(10,2) NOT NULL,
    DurationDays INT NOT NULL,
    MaxCapacity INT NOT NULL,
    AIGeneratedSummary TEXT,
    AgencyID INT NOT NULL,
    ImageURL VARCHAR(500) DEFAULT NULL,
    CONSTRAINT chk_BasePrice CHECK (BasePrice >= 0),
    CONSTRAINT chk_DurationDays CHECK (DurationDays > 0),
    CONSTRAINT chk_MaxCapacity CHECK (MaxCapacity > 0),
    FOREIGN KEY (AgencyID) REFERENCES TravelAgency(UserID) ON DELETE RESTRICT
);

CREATE INDEX idx_package_price ON TravelPackage(BasePrice);

CREATE TABLE GroupTrip (
    PackageID INT NOT NULL,
    TripDateID INT NOT NULL,
    StartDate DATE NOT NULL,
    EndDate DATE NOT NULL,
    Status VARCHAR(50) DEFAULT 'Scheduled',
    PRIMARY KEY (PackageID, TripDateID),
    CONSTRAINT chk_TripStatus CHECK (Status IN ('Scheduled', 'Ongoing', 'Completed', 'Cancelled')),
    CONSTRAINT chk_Dates CHECK (EndDate >= StartDate),
    FOREIGN KEY (PackageID) REFERENCES TravelPackage(PackageID) ON DELETE CASCADE
);

CREATE TABLE Booking (
    BookingID INT AUTO_INCREMENT PRIMARY KEY,
    BookingDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    TotalAmount DECIMAL(10,2) NOT NULL,
    PaymentStatus VARCHAR(50) DEFAULT 'Pending',
    PartySize INT NOT NULL,
    TravellerID INT NOT NULL,
    Trip_PackageID INT NOT NULL,
    Trip_TripDateID INT NOT NULL,
    CONSTRAINT chk_TotalAmount CHECK (TotalAmount >= 0),
    CONSTRAINT chk_PartySize CHECK (PartySize > 0),
    CONSTRAINT chk_PaymentStatus CHECK (PaymentStatus IN ('Pending', 'Paid', 'Failed', 'Refunded')),
    FOREIGN KEY (TravellerID) REFERENCES Traveller(UserID) ON DELETE RESTRICT,
    FOREIGN KEY (Trip_PackageID, Trip_TripDateID) REFERENCES GroupTrip(PackageID, TripDateID) ON DELETE RESTRICT
);

CREATE TABLE Review (
    TravellerID INT NOT NULL,
    ReviewID INT NOT NULL,
    Rating INT NOT NULL,
    Comment TEXT,
    DatePosted DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    SentimentScore DECIMAL(3,2),
    TargetAgencyID INT DEFAULT NULL,
    TargetPackageID INT DEFAULT NULL,
    PRIMARY KEY (TravellerID, ReviewID),
    CONSTRAINT chk_ReviewRating CHECK (Rating BETWEEN 1 AND 5),
    CONSTRAINT chk_SentimentScore CHECK (SentimentScore BETWEEN -1.00 AND 1.00),
    CONSTRAINT chk_ReviewTarget CHECK (
        (TargetAgencyID IS NOT NULL AND TargetPackageID IS NULL) OR 
        (TargetAgencyID IS NULL AND TargetPackageID IS NOT NULL)
    ),
    FOREIGN KEY (TravellerID) REFERENCES Traveller(UserID) ON DELETE CASCADE,
    FOREIGN KEY (TargetAgencyID) REFERENCES TravelAgency(UserID) ON DELETE CASCADE,
    FOREIGN KEY (TargetPackageID) REFERENCES TravelPackage(PackageID) ON DELETE CASCADE
);

-- 6. ASSOCIATIVE TABLES (M:N)
CREATE TABLE Package_Destination (
    PackageID INT NOT NULL,
    DestID INT NOT NULL,
    PRIMARY KEY (PackageID, DestID),
    FOREIGN KEY (PackageID) REFERENCES TravelPackage(PackageID) ON DELETE CASCADE,
    FOREIGN KEY (DestID) REFERENCES Destination(DestID) ON DELETE CASCADE
);

CREATE TABLE Package_Flight (
    PackageID INT NOT NULL,
    FlightID INT NOT NULL,
    PRIMARY KEY (PackageID, FlightID),
    FOREIGN KEY (PackageID) REFERENCES TravelPackage(PackageID) ON DELETE CASCADE,
    FOREIGN KEY (FlightID) REFERENCES Flight(FlightID) ON DELETE CASCADE
);

CREATE TABLE Package_Accommodation (
    PackageID INT NOT NULL,
    AccommID INT NOT NULL,
    PRIMARY KEY (PackageID, AccommID),
    FOREIGN KEY (PackageID) REFERENCES TravelPackage(PackageID) ON DELETE CASCADE,
    FOREIGN KEY (AccommID) REFERENCES Accommodation(AccommID) ON DELETE CASCADE
);

CREATE TABLE Package_Attraction (
    PackageID INT NOT NULL,
    AttractionID INT NOT NULL,
    PRIMARY KEY (PackageID, AttractionID),
    FOREIGN KEY (PackageID) REFERENCES TravelPackage(PackageID) ON DELETE CASCADE,
    FOREIGN KEY (AttractionID) REFERENCES Attraction(AttractionID) ON DELETE CASCADE
);

CREATE TABLE Package_Restaurant (
    PackageID INT NOT NULL,
    RestaurantID INT NOT NULL,
    PRIMARY KEY (PackageID, RestaurantID),
    FOREIGN KEY (PackageID) REFERENCES TravelPackage(PackageID) ON DELETE CASCADE,
    FOREIGN KEY (RestaurantID) REFERENCES Restaurant(RestaurantID) ON DELETE CASCADE
);

CREATE TABLE Notification (
    NotificationID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    Title VARCHAR(255) NOT NULL,
    Message TEXT NOT NULL,
    IsRead TINYINT(1) DEFAULT 0,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
);

-- -- Populate DB
-- 1. SUPERCLASS: USERS
-- IDs 1-3 are Agencies. IDs 4-9 are Travellers.
INSERT INTO User (UserID, Email, PasswordHash, DateJoined, LastLoginTime, AccountStatus) VALUES
(1, 'rebecca@agency.tripistry.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', '2025-01-01', NOW(), 'Active'),
(2, 'cecil@agency.tripistry.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', '2025-01-05', NOW(), 'Active'),
(3, 'ashley@agency.tripistry.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', '2025-01-10', NOW(), 'Active'),
(4, 'ted.lasso@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', '2025-02-01', NOW(), 'Active'),
(5, 'roy.kent@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', '2025-02-02', NOW(), 'Active'),
(6, 'mark.grayson@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', '2025-02-03', NOW(), 'Active'),
(7, 'nolan@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', '2025-02-04', NOW(), 'Active'),
(8, 'butcher@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', '2025-02-05', NOW(), 'Active'),
(9, 'hughie@gmail.com', '$2y$10$P7haEohp.oAKm3RQ4RNq3O0atIICjeQus556jn9odsG4SlB7OiuOC', '2025-02-06', NOW(), 'Active');

-- 2. SUBCLASSES: AGENCIES & TRAVELLERS
INSERT INTO TravelAgency (UserID, AgencyName, RegistrationNumber, AverageRating, Address_Street, Address_City, Address_Zip) VALUES
(1, 'Richmond AFC Tours', 'REG-TL001', 4.80, '1 Nelson Road', 'London', 'TW9 1EW'),
(2, 'GDA Defense Travel', 'REG-INV002', 4.10, 'Classified Underground Base', 'Chicago', '60007'),
(3, 'Vought International Getaways', 'REG-VGT003', 4.90, '99 Vought Tower', 'New York', '10001');

INSERT INTO Traveller (UserID, FirstName, LastName, DOB, SoloBudget) VALUES
(4, 'Ted', 'Lasso', '1976-09-18', 5000.00),
(5, 'Roy', 'Kent', '1980-08-15', 12000.00),
(6, 'Mark', 'Grayson', '2003-04-12', 800.00),
(7, 'Nolan', 'Grayson', '1000-01-01', 50000.00),
(8, 'Billy', 'Butcher', '1978-05-23', 2500.00),
(9, 'Hugh', 'Campbell', '1995-10-15', 1200.00);

-- 3. MULTIVALUED ATTRIBUTES
INSERT INTO Traveller_Preferences (UserID, Preference) VALUES
(4, 'Sports'), (4, 'Pubs'), (5, 'Quiet Areas'), (6, 'Action'), (8, 'Espionage'), (9, 'Safe Zones');

INSERT INTO TravelAgency_Contacts (UserID, ContactNumber) VALUES
(1, '+44 20 7946 0958'), (2, 'CLASSIFIED-911'), (3, '1-800-VOUGHT');

-- 4. REGULAR ENTITIES (Locations, Flights, etc.)
INSERT INTO Destination (DestID, Name, Country, Region, PopularityScore, Coordinates_Lat, Coordinates_Long, ImageURL) VALUES
(1, 'Richmond Borough', 'United Kingdom', 'Greater London', 85, 51.4613, -0.3037, 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=600&q=80'),
(2, 'GDA Headquarters', 'United States', 'Illinois', 75, 41.8781, -87.6298, 'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=600&q=80'),
(3, 'Vought Square', 'United States', 'New York', 99, 40.7128, -74.0060, 'https://images.unsplash.com/photo-1496442226666-8d4d0e62e6e9?auto=format&fit=crop&w=600&q=80'),
(4, 'Cape Town', 'South Africa', 'Western Cape', 92, -33.9249, 18.4241, 'https://images.unsplash.com/photo-1580060839134-75a5edca2e99?auto=format&fit=crop&w=600&q=80'),
(5, 'Tokyo', 'Japan', 'Kanto', 98, 35.6762, 139.6503, 'https://images.unsplash.com/photo-1540959733332-eab4deceeaf7?auto=format&fit=crop&w=600&q=80');

INSERT INTO Flight (FlightID, Airline, FlightNum, DepTime, ArrTime, Cost, DepAirport_Code, DepAirport_Name, ArrAirport_Code, ArrAirport_Name) VALUES
(1, 'Believe Airways', 'BA-10', '2026-06-01 08:00:00', '2026-06-01 10:00:00', 350.00, 'JFK', 'JFK Intl', 'LHR', 'Heathrow'),
(2, 'Stealth Aviation', 'SA-99', '2026-07-15 23:00:00', '2026-07-16 02:00:00', 1200.00, 'LHR', 'Heathrow', 'ORD', 'O-Hare Intl'),
(3, 'VoughtAir VIP', 'VGT-7', '2026-08-10 14:00:00', '2026-08-10 16:30:00', 2500.00, 'ORD', 'O-Hare Intl', 'JFK', 'JFK Intl'),
(4, 'Safari Express', 'SE-101', '2026-09-01 06:00:00', '2026-09-01 18:00:00', 950.00, 'LHR', 'Heathrow', 'CPT', 'Cape Town Intl'),
(5, 'Ninja Airways', 'NA-404', '2026-10-10 10:00:00', '2026-10-11 02:00:00', 1100.00, 'JFK', 'JFK Intl', 'NRT', 'Narita Airport');

INSERT INTO Accommodation (AccommID, Name, Type, PricePerNight, StarRating, Address_Street, Address_City, Address_Zip, Coordinates_Lat, Coordinates_Long, ImageURL) VALUES
(1, 'The Crown & Anchor Inn', 'B&B', 150.00, 3, '22 Richmond Green', 'London', 'TW9 1NL', 51.4610, -0.3030, 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=600&q=80'),
(2, 'GDA Pentagon Safehouse', 'Bunker', 50.00, 1, 'Classified', 'Chicago', '60007', 41.8780, -87.6290, 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?auto=format&fit=crop&w=600&q=80'),
(3, 'The Seven Luxury Suites', 'Hotel', 1000.00, 5, 'Vought Tower Top Floor', 'New York', '10001', 40.7130, -74.0065, 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=600&q=80'),
(4, 'Table Mountain Lodge', 'Hotel', 250.00, 5, '100 Tafelberg Road', 'Cape Town', '8001', -33.9250, 18.4240, 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=600&q=80'),
(5, 'Shibuya Capsule Hotel', 'Hostel', 45.00, 3, '2-1 Shibuya', 'Tokyo', '150-0002', 35.6760, 139.6500, 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=600&q=80');

INSERT INTO Attraction (AttractionID, Name, Category, EntryFee, Coordinates_Lat, Coordinates_Long, ImageURL) VALUES
(1, 'Nelson Road Stadium', 'Sports', 45.00, 51.4615, -0.3040, 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80'),
(2, 'Guardians of the Globe Base', 'Museum', 15.00, 41.8790, -87.6300, 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=600&q=80'),
(3, 'Dawn of the Seven Premiere', 'Entertainment', 250.00, 40.7135, -74.0070, 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=600&q=80'),
(4, 'Kirstenbosch Gardens', 'Nature', 10.00, -33.9890, 18.4320, 'https://images.unsplash.com/photo-1564507592333-c60657eea523?auto=format&fit=crop&w=600&q=80'),
(5, 'Shibuya Crossing', 'Sightseeing', 0.00, 35.6595, 139.7006, 'https://images.unsplash.com/photo-1503899036084-c55cdd92da26?auto=format&fit=crop&w=600&q=80');

INSERT INTO Restaurant (RestaurantID, Name, CuisineType, AverageCost, Coordinates_Lat, Coordinates_Long, ImageURL) VALUES
(1, 'Taste of Athens', 'Greek', 40.00, 51.4620, -0.3050, 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=600&q=80'),
(2, 'Burger Mart', 'Fast Food', 12.00, 41.8770, -87.6280, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=600&q=80'),
(3, 'Vought A Burger', 'Fast Food', 85.00, 40.7120, -74.0050, 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=600&q=80'),
(4, 'Mama Africa Cuisine', 'African', 30.00, -33.9230, 18.4210, 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=600&q=80'),
(5, 'Ichiraku Ramen Shibuya', 'Japanese', 15.00, 35.6580, 139.7010, 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=600&q=80');

-- 5. RELATIONAL ENTITIES: PACKAGES & TRIPS
INSERT INTO TravelPackage (PackageID, Title, Description, BasePrice, DurationDays, MaxCapacity, AIGeneratedSummary, AgencyID, ImageURL) VALUES
(1, 'The Richmond Way', 'Experience football, tea, and biscuits with the Greyhounds.', 850.00, 5, 20, 'A heartwarming, optimistic sports tour featuring local pubs and stadium access.', 1, 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80'),
(2, 'Hero Training Camp', 'Survive the GDA obstacle courses. Viltrumite attacks not covered by insurance.', 1500.00, 7, 10, 'High-intensity survival and combat training in undisclosed locations.', 2, 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?auto=format&fit=crop&w=600&q=80'),
(3, 'The Seven VIP Weekend', 'Meet Homelander, drink Fresca, and stay in pure luxury.', 5000.00, 3, 5, 'Ultra-luxurious, corporately sanitized superhero meet-and-greet experience.', 3, 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=600&q=80'),
(4, 'African Safari Adventure', 'Explore Table Mountain, Kirstenbosch botanical gardens, and local cuisine.', 1200.00, 6, 12, 'A scenic exploration of South Africa\'s botanical and coastal beauty.', 1, 'https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=600&q=80'),
(5, 'Tokyo Neon Lights', 'Immerse yourself in Shibuya crossing, traditional gardens, and ramen tasting.', 2200.00, 5, 8, 'An electric neon-filled tour through Tokyo\'s most iconic city streets and sights.', 3, 'https://images.unsplash.com/photo-1503899036084-c55cdd92da26?auto=format&fit=crop&w=600&q=80');

-- Linking M:N Relationships (Associative Tables)
INSERT INTO Package_Destination VALUES (1, 1), (2, 2), (3, 3), (4, 4), (5, 5);
INSERT INTO Package_Flight VALUES (1, 1), (2, 2), (3, 3), (4, 4), (5, 5);
INSERT INTO Package_Accommodation VALUES (1, 1), (2, 2), (3, 3), (4, 4), (5, 5);
INSERT INTO Package_Attraction VALUES (1, 1), (2, 2), (3, 3), (4, 4), (5, 5);
INSERT INTO Package_Restaurant VALUES (1, 1), (2, 2), (3, 3), (4, 4), (5, 5);

-- Group Trips (Weak Entity: Identifies by PackageID and TripDateID)
INSERT INTO GroupTrip (PackageID, TripDateID, StartDate, EndDate, Status) VALUES
(1, 1, '2026-06-01', '2026-06-06', 'Scheduled'),
(1, 2, '2026-06-15', '2026-06-20', 'Scheduled'),
(2, 1, '2026-07-15', '2026-07-22', 'Scheduled'),
(3, 1, '2026-08-10', '2026-08-13', 'Scheduled'),
(4, 1, '2026-09-01', '2026-09-07', 'Scheduled'),
(5, 1, '2026-10-10', '2026-10-15', 'Scheduled');

-- 6. BOOKINGS & REVIEWS
-- Ted Lasso books the Training Camp
INSERT INTO Booking (BookingDate, TotalAmount, PaymentStatus, PartySize, TravellerID, Trip_PackageID, Trip_TripDateID) VALUES 
(NOW(), 1500.00, 'Paid', 1, 4, 2, 1),
-- Mark Grayson books the Richmond trip
(NOW(), 1700.00, 'Pending', 2, 6, 1, 1),
-- Butcher books the Vought weekend
(NOW(), 5000.00, 'Paid', 1, 8, 3, 1),
-- Roy Kent books Package 4 (African Safari)
(NOW(), 1200.00, 'Paid', 1, 5, 4, 1),
-- Nolan Grayson books Package 5 (Tokyo Neon Lights)
(NOW(), 4400.00, 'Paid', 2, 7, 5, 1);

-- Reviews (Weak Entity: ReviewID starts at 1 for each TravellerID)
-- Constraint: TargetAgencyID OR TargetPackageID, NOT both.
INSERT INTO Review (TravellerID, ReviewID, Rating, Comment, TargetAgencyID, TargetPackageID) VALUES
-- Roy Kent reviews the Vought Agency (Hates it)
(5, 1, 1, 'Too corporate. Absolute rubbish. Grrr.', 3, NULL),
-- Mark Grayson reviews the Richmond Package (Loves it)
(6, 1, 5, 'Great break from almost dying every week!', NULL, 1),
-- Butcher reviews the Vought Package 
(8, 1, 1, 'Diabolical. Homelander was a right prick.', NULL, 3),
-- Ted Lasso reviews the GDA Agency
(4, 1, 4, 'Well howdy! The bunker was a bit dark, but Cecil meant well.', 2, NULL),
-- Roy Kent reviews Package 4
(5, 2, 5, 'Absolutely brilliant. Saw a lion. Top class.', NULL, 4),
-- Nolan Grayson reviews Package 5
(7, 1, 4, 'Interesting culture. Fast travel.', NULL, 5);

-- 7. NOTIFICATIONS
INSERT INTO Notification (UserID, Title, Message, IsRead, CreatedAt) VALUES
(1, 'New Booking Received', 'Roy Kent has booked African Safari Adventure!', 0, NOW()),
(3, 'New Booking Received', 'Nolan Grayson has booked Tokyo Neon Lights!', 0, NOW()),
(4, 'Trip Scheduled', 'Your Hero Training Camp trip is scheduled for 2026-07-15.', 0, NOW()),
(5, 'Payment Success', 'Your payment of R 1,200.00 for African Safari Adventure was successful.', 0, NOW()),
(6, 'Booking Pending', 'Your booking for The Richmond Way is pending agency confirmation.', 0, NOW());