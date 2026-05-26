# Tripistry Travel Platform

Tripistry is an ultra-premium, interactive web-based travel package platform designed to bridge the gap between adventurous **Explorer Travellers** and verified **Agency Partners**. With custom-tailored smart recommendation systems, real-time transactional checkout maps, multi-relational asset catalogs, and sentiment analysis review frameworks, Tripistry offers a state-of-the-art interactive experience.

---

## 🌟 Premium Features Overview

### 🧑‍💻 traveller Experience
*   **Tailored Smart Recommendations**: Implements an advanced compatibility matcher that intersection-checks travel interests and preferences against package attributes (+25% per tag match, capped at 50%) and filters by solo budget headroom limits (proportionally penalizing options exceeding the traveller's wallet threshold).
*   **Leaflet.js Interactive Flow Maps**: Itinerary details automatically map accommodations, landmarks, and dining assets on a live graphical canvas, complete with sequential geodesic flight/flow paths and rich popup details.
*   **Multi-Relational Asset Explorer**: Browse flights, hotels, restaurants, and attractions individually to discover which active itineraries feature them.
*   **iCalendar Calendar Sync**: One-click calendar sync exporter generates fully RFC-5545 compliant `.ics` calendar events compatible with Google Calendar, Apple Calendar, and Outlook.
*   **Wallet Checkout Gateway**: Transactional reservation checkout system supporting debiting from `SoloBudget` wallets or test payment gateways, with strict transactional row-level database locks (`FOR UPDATE`) to prevent race-condition overbooking.

### 🏢 Travel Agency Suite
*   **Sleek Sales Analytics**: Interactive Chart.js business widgets summarizing revenue share by package, monthly sentiment review curves, and live booking funnel stages (paid vs pending).
*   **CRM Insights Directory**: Comprehensive customer relationship management (CRM) portfolio displaying booking customer ages, lifetime financial contributions, and lead generator matches.
*   **Transactional Package & Asset Catalog**: Detailed creators catalog allowing multi-relational asset linking with inline AJAX asset creation modals.
*   **Phone hotlines & Multi-value Profiles**: Manage base company attributes and dynamic multi-value phone directories in transaction-safe updates.

### 🔒 Core Security & Dynamic Features
*   **IP Login Rate Limiting**: Automatic failed login attempts tracking (locked out after 5 consecutive failures) with an isolated `security_audit.log` auditor.
*   **Live Background Notification Hub**: Periodic async JS polling engine checking the database for transaction receipts, status updates, and custom notifications.

---

## 🛠️ Local Installation & Setup

To get Tripistry running locally on your own computer, follow these simple setup instructions.

### 1. Prerequisites
Ensure you have the following installed on your local environment:
*   **PHP 8.x** or higher
*   **Apache Web Server** & **MariaDB/MySQL Database** (e.g. via **XAMPP**, **MAMP**, or **WAMP**)
*   **Git**

### 2. File Location Setup
Clone or place this repository directly into your local web server's public directory. 
*   **For XAMPP on macOS**: `/Applications/XAMPP/xamppfiles/htdocs/cos221/COS221-PA5-Tripistry`
*   **For XAMPP on Windows**: `C:\xampp\htdocs\cos221\COS221-PA5-Tripistry`
*   **For MAMP**: `/Applications/MAMP/htdocs/cos221/COS221-PA5-Tripistry`

### 3. Database Credentials Configuration
The database connection relies on a ignored `config.php` file for local development.
1. Copy the provided sample config file:
   ```bash
   cp config.php.example config.php
   ```
2. Open `config.php` in your code editor and fill in your local MySQL host, database name, username, and password:
   ```php
   $host = '127.0.0.1';       // Your local database host
   $db   = 'tripistry-cos221'; // Database name
   $user = 'root';             // Database username (default: 'root')
   $pass = '';                 // Database password (default: empty on XAMPP)
   ```

### 4. Database Setup & SQL Files Overview
The repository includes three database files:
1.  **`Tripistry_schema.sql`**: Contains the complete relational database table definitions, foreign keys, check constraints, and referential triggers.
2.  **`seed.sql`**: Contains all SQL query inserts to pre-populate the tables with realistic, curated mock data (matching the accounts below).
3.  **`Database dump.sql`**: A unified database dump containing **both** table schemas and the population seeds in one single file.

You can set up the database using any of the following three options:

#### Option A: One-Click Automatic CLI Command (Recommended)
If you have PHP in your CLI, run the helper reset script which drops existing tables, creates the schema, and seeds all mock data automatically:
*   **Mac/Linux**:
    ```bash
    /Applications/XAMPP/xamppfiles/bin/php -f scratch/reset_db.php
    ```
*   **Windows**:
    ```cmd
    php scratch\reset_db.php
    ```

#### Option B: Manual phpMyAdmin Unified Import (Simplest Manual Option)
1. **Open phpMyAdmin**: Navigate to [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. **Create Database**: Click **New**, enter `tripistry-cos221` as the database name, select **`utf8mb4_general_ci`** collation, and click **Create**.
3. **Import Unified Dump**:
   * Select `tripistry-cos221` from the left list.
   * Click the **Import** tab on the top menu.
   * Choose **`Database dump.sql`** and click **Import** (or **Go**). Your schema and data are now fully set up in one step!

#### Option C: Split Schema & Seed Import
1. **Create Database**: Follow step 1 and 2 above.
2. **Import Schema**: Select `tripistry-cos221`, click **Import**, choose **`Tripistry_schema.sql`**, and import it to create all tables.
3. **Import Seeds**: Click **Import** once more, choose **`seed.sql`**, and import it to populate all tables.

---

## 🔑 Seeded Mock Accounts & Datasets

To test all aspects of the application without registering new accounts, use these pre-loaded mock credentials (**all accounts share the password `password123`**):

### 🏢 Travel Agency Accounts
Use these to manage catalogs, schedule date ranges, view Chart.js analytics, and interact with the CRM insights directory:
*   **Richmond AFC Tours** (Rating: 4.80):
    *   **Email**: `rebecca@richmond.co.uk`
    *   **Password**: `password123`
*   **GDA Defense Travel** (Rating: 4.10):
    *   **Email**: `cecil@gda.gov`
    *   **Password**: `password123`
*   **Vought International Getaways** (Rating: 4.90):
    *   **Email**: `ashley@vought.com`
    *   **Password**: `password123`

### 🧑‍🚀 Explorer Traveller Accounts
Use these to explore itineraries, test Leaflet.js geodesic maps, write reviews to trigger sentiment scores, check out trips, and sync calendar downloads:
*   **Ted Lasso** (Budget: R 5,000.00):
    *   **Email**: `ted.lasso@gmail.com`
    *   **Password**: `password123`
*   **Roy Kent** (Budget: R 12,000.00):
    *   **Email**: `roy.kent@chelsea.com`
    *   **Password**: `password123`
*   **Mark Grayson** (Budget: R 800.00):
    *   **Email**: `mark.grayson@highschool.edu`
    *   **Password**: `password123`
*   **Nolan Grayson** (Budget: R 50,000.00):
    *   **Email**: `nolan@viltrum.org`
    *   **Password**: `password123`
*   **Billy Butcher** (Budget: R 2,500.00):
    *   **Email**: `butcher@theboys.co.uk`
    *   **Password**: `password123`
*   **Hugh Campbell** (Budget: R 1,200.00):
    *   **Email**: `hughie@electronics.com`
    *   **Password**: `password123`

### 📊 Seeded Datasets Included

*   **Bookings Ledger**: Built-in historical records (Paid, Pending, Failed, Refunded) which immediately populate the agency dashboard performance charts (bookings funnel, revenue shares) and customer lifetime financial contributions inside the CRM lead generator directory.
*   **Agency Contact Hotlines**: Multi-valued telephone directories are fully seeded for agencies to test profile updates.
*   **Sentiment Review Entries**: Pre-loaded with positive, negative, and neutral comment strings to test the Custom Sentiment Lexicon engine and automatic rating re-calculation.
*   ** AppBar Notifications**: Seeding includes read and unread messages for various accounts, enabling direct visual verification of the notification hub bell icon.


---

## 🌿 Git Team Workflow

To keep code stable and ensure smooth collaboration, we work on isolated **feature branches** before committing or merging into `develop` or `main`.

### Creating a Feature Branch
Before starting changes, branch out from the latest `develop`:
```bash
git checkout develop
git pull origin develop
git checkout -b feature/your-feature-name
```

### Staging & Committing Changes
Always verify status and diff before staging files:
```bash
git status
git add .
git commit -m "feat: integrate premium styling to explore lists and detail panels"
```

### Merging / Overwriting `develop` Locally
To update or overwrite your local `develop` branch with your completed feature branch:
```bash
git checkout develop
# Merge feature branch (resolving conflicts if any)
git merge feature/your-feature-name
# Or push feature branch to origin for peer review
git push origin feature/your-feature-name
```
