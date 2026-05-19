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

### 4. Import the Schema & Seed Data
You can set up the database using either the automatic CLI script or manual phpMyAdmin import.

#### Option A: One-Click Automatic CLI Command (Recommended)
If you have PHP installed in your terminal, run the helper utility:
*   **Mac/Linux**:
    ```bash
    /Applications/XAMPP/xamppfiles/bin/php -f scratch/reset_db.php
    ```
*   **Windows**:
    ```cmd
    php scratch\reset_db.php
    ```
This utility automatically drops old tables, configures all foreign key triggers, compiles indices, and inserts fully-populated seed datasets.

#### Option B: Manual phpMyAdmin Import
1. Navigate to `http://localhost/phpmyadmin` in your web browser.
2. Click **New** in the left sidebar and create a database named `tripistry-cos221` with `utf8mb4_general_ci` collation.
3. Click on the new database, select the **Import** tab on the top menu.
4. Upload and import `Tripistry_schema.sql` first.
5. Once complete, select **Import** again, upload and import `seed.sql` to populate initial datasets.

---

## 🔑 Seeded Mock Accounts
To test all aspects of the application without registering new accounts, use these pre-loaded mock credentials (all accounts share the password **`password123`**):

### 🏢 Travel Agency Accounts
Use these to manage catalogs, schedule date ranges, and view CRM customer lists:
*   **Wanderlust Travels**:
    *   **Email**: `contact@wanderlust.com`
    *   **Password**: `password123`
*   **GlobeTrek Expeditions**:
    *   **Email**: `info@globetrek.com`
    *   **Password**: `password123`

### 🧑‍🚀 Explorer Traveller Accounts
Use these to explore itineraries, test mapping, write sentiment reviews, and book group trips:
*   **Alice Smith**:
    *   **Email**: `alice@gmail.com`
    *   **Password**: `password123`
*   **Bob Jones**:
    *   **Email**: `bob@gmail.com`
    *   **Password**: `password123`
*   **Charlie Brown**:
    *   **Email**: `charlie@gmail.com`
    *   **Password**: `password123`

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
