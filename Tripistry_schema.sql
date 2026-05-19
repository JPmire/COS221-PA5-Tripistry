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
    CONSTRAINT chk_EntryFee CHECK (EntryFee >= 0)
);

CREATE TABLE Restaurant (
    RestaurantID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(150) NOT NULL,
    CuisineType VARCHAR(100),
    AverageCost DECIMAL(10,2) DEFAULT 0.00,
    Coordinates_Lat DECIMAL(10,8),
    Coordinates_Long DECIMAL(11,8),
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

-- 7. NOTIFICATION SYSTEM TABLE
CREATE TABLE Notification (
    NotificationID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    Title VARCHAR(255) NOT NULL,
    Message TEXT NOT NULL,
    IsRead TINYINT(1) DEFAULT 0,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
);