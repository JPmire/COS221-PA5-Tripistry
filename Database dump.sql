--Create Database
CREATE DATABASE Tripistry;

--Create Tables
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

--Populate DB
-- 1. SUPERCLASS: USERS
-- IDs 1-3 are Agencies. IDs 4-9 are Travellers.
INSERT INTO User (UserID, Email, PasswordHash, DateJoined, LastLoginTime, AccountStatus) VALUES
(1, 'rebecca@richmond.co.uk', '$2y$10$LXS0cfgAFi0nB9DKe8oaLuqmgftHfBfVv.PI26EEGWYDLqh6mENlK', '2025-01-01', NOW(), 'Active'),
(2, 'cecil@gda.gov', '$2y$10$uAeZwmYuE/SCOq0MlI8N7uGAyc1wZBIUeFEBMy2DMEjdro4hPXdmW', '2025-01-05', NOW(), 'Active'),
(3, 'ashley@vought.com', '$2y$10$IiXY3lwhKW8kRSeopxP0xe7XzOapYQOVnxRd0qxSdUXZGhRzLb.KW', '2025-01-10', NOW(), 'Active'),
(4, 'ted.lasso@gmail.com', '$2y$10$rqsrImW79UwznHkpNNO3KOVZCP2uPWMuqlNdM/yDsO8kuDCTzdl7O', '2025-02-01', NOW(), 'Active'),
(5, 'roy.kent@chelsea.com', '$2y$10$OM2tcDY.hfs3UpxLJ.d6f.KOUkZXGLmgnjpE/wdBRKI90KEyv6rE6', '2025-02-02', NOW(), 'Active'),
(6, 'mark.grayson@highschool.edu', '$2y$10$kZwOw4MW0Q6N13UCP1tQ2eg8fLzLPlFX9SQSozln4PtGB.lXZrRMW', '2025-02-03', NOW(), 'Active'),
(7, 'nolan@viltrum.org', '$2y$10$VXI2408QOMpAnplv0WRhp.6aX8UuLZse1U93HrwQzCEUWzGRw1ALa', '2025-02-04', NOW(), 'Active'),
(8, 'butcher@theboys.co.uk', '$2y$10$AWfKDazV5gCIOpdWJYUfx.6tKubfsXF127c5rXs/0N9IsB.3brByC', '2025-02-05', NOW(), 'Active'),
(9, 'hughie@electronics.com', '$2y$10$1FreBYSAyLR5ZAPg50L/vOB3gcz3/Aph9fEz7lRu13RZsEKBFZVnS', '2025-02-06', NOW(), 'Active');

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
INSERT INTO Destination (DestID, Name, Country, Region, PopularityScore, Coordinates_Lat, Coordinates_Long) VALUES
(1, 'Richmond Borough', 'United Kingdom', 'Greater London', 85, 51.4613, -0.3037),
(2, 'GDA Headquarters', 'United States', 'Illinois', 75, 41.8781, -87.6298),
(3, 'Vought Square', 'United States', 'New York', 99, 40.7128, -74.0060);

INSERT INTO Flight (FlightID, Airline, FlightNum, DepTime, ArrTime, Cost, DepAirport_Code, DepAirport_Name, ArrAirport_Code, ArrAirport_Name) VALUES
(1, 'Believe Airways', 'BA-10', '2026-06-01 08:00:00', '2026-06-01 10:00:00', 350.00, 'JFK', 'JFK Intl', 'LHR', 'Heathrow'),
(2, 'Stealth Aviation', 'SA-99', '2026-07-15 23:00:00', '2026-07-16 02:00:00', 1200.00, 'LHR', 'Heathrow', 'ORD', 'O-Hare Intl'),
(3, 'VoughtAir VIP', 'VGT-7', '2026-08-10 14:00:00', '2026-08-10 16:30:00', 2500.00, 'ORD', 'O-Hare Intl', 'JFK', 'JFK Intl');

INSERT INTO Accommodation (AccommID, Name, Type, PricePerNight, StarRating, Address_Street, Address_City, Address_Zip, Coordinates_Lat, Coordinates_Long) VALUES
(1, 'The Crown & Anchor Inn', 'B&B', 150.00, 3, '22 Richmond Green', 'London', 'TW9 1NL', 51.4610, -0.3030),
(2, 'GDA Pentagon Safehouse', 'Bunker', 50.00, 1, 'Classified', 'Chicago', '60007', 41.8780, -87.6290),
(3, 'The Seven Luxury Suites', 'Hotel', 1000.00, 5, 'Vought Tower Top Floor', 'New York', '10001', 40.7130, -74.0065);

INSERT INTO Attraction (AttractionID, Name, Category, EntryFee, Coordinates_Lat, Coordinates_Long) VALUES
(1, 'Nelson Road Stadium', 'Sports', 45.00, 51.4615, -0.3040),
(2, 'Guardians of the Globe Base', 'Museum', 15.00, 41.8790, -87.6300),
(3, 'Dawn of the Seven Premiere', 'Entertainment', 250.00, 40.7135, -74.0070);

INSERT INTO Restaurant (RestaurantID, Name, CuisineType, AverageCost, Coordinates_Lat, Coordinates_Long) VALUES
(1, 'Taste of Athens', 'Greek', 40.00, 51.4620, -0.3050),
(2, 'Burger Mart', 'Fast Food', 12.00, 41.8770, -87.6280),
(3, 'Vought A Burger', 'Fast Food', 85.00, 40.7120, -74.0050);

-- 5. RELATIONAL ENTITIES: PACKAGES & TRIPS
INSERT INTO TravelPackage (PackageID, Title, Description, BasePrice, DurationDays, MaxCapacity, AIGeneratedSummary, AgencyID) VALUES
(1, 'The Richmond Way', 'Experience football, tea, and biscuits with the Greyhounds.', 850.00, 5, 20, 'A heartwarming, optimistic sports tour featuring local pubs and stadium access.', 1),
(2, 'Hero Training Camp', 'Survive the GDA obstacle courses. Viltrumite attacks not covered by insurance.', 1500.00, 7, 10, 'High-intensity survival and combat training in undisclosed locations.', 2),
(3, 'The Seven VIP Weekend', 'Meet Homelander, drink Fresca, and stay in pure luxury.', 5000.00, 3, 5, 'Ultra-luxurious, corporately sanitized superhero meet-and-greet experience.', 3);

-- Linking M:N Relationships (Associative Tables)
INSERT INTO Package_Destination VALUES (1, 1), (2, 2), (3, 3);
INSERT INTO Package_Flight VALUES (1, 1), (2, 2), (3, 3);
INSERT INTO Package_Accommodation VALUES (1, 1), (2, 2), (3, 3);
INSERT INTO Package_Attraction VALUES (1, 1), (2, 2), (3, 3);
INSERT INTO Package_Restaurant VALUES (1, 1), (2, 2), (3, 3);

-- Group Trips (Weak Entity: Identifies by PackageID and TripDateID)
INSERT INTO GroupTrip (PackageID, TripDateID, StartDate, EndDate, Status) VALUES
(1, 1, '2026-06-01', '2026-06-06', 'Scheduled'),
(1, 2, '2026-06-15', '2026-06-20', 'Scheduled'),
(2, 1, '2026-07-15', '2026-07-22', 'Scheduled'),
(3, 1, '2026-08-10', '2026-08-13', 'Scheduled');

-- 6. BOOKINGS & REVIEWS
-- Ted Lasso books the Training Camp
INSERT INTO Booking (BookingDate, TotalAmount, PaymentStatus, PartySize, TravellerID, Trip_PackageID, Trip_TripDateID) VALUES 
(NOW(), 1500.00, 'Paid', 1, 4, 2, 1),
-- Mark Grayson books the Richmond trip
(NOW(), 1700.00, 'Pending', 2, 6, 1, 1),
-- Butcher books the Vought weekend
(NOW(), 5000.00, 'Paid', 1, 8, 3, 1);

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
(4, 1, 4, 'Well howdy! The bunker was a bit dark, but Cecil meant well.', 2, NULL);