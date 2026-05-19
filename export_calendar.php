<?php
require_once 'includes/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Traveller') {
    http_response_code(403);
    die("Access denied. Authorized travelers only.");
}

if (!isset($_GET['booking_id'])) {
    http_response_code(400);
    die("Invalid request. Missing Booking ID parameter.");
}

$booking_id = (int)$_GET['booking_id'];
$traveller_id = (int)$_SESSION['user_id'];

try {
    // Fetch Booking details, Package details, GroupTrip dates, and Agency name
    $stmt = $pdo->prepare("
        SELECT b.BookingID, b.BookingDate, b.TotalAmount, b.PartySize,
               gt.StartDate, gt.EndDate,
               tp.Title AS PackageTitle, tp.Description AS PackageDesc,
               ta.AgencyName
        FROM Booking b
        JOIN GroupTrip gt ON b.Trip_PackageID = gt.PackageID AND b.Trip_TripDateID = gt.TripDateID
        JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
        JOIN TravelAgency ta ON tp.AgencyID = ta.UserID
        WHERE b.BookingID = ? AND b.TravellerID = ?
    ");
    $stmt->execute([$booking_id, $traveller_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        http_response_code(404);
        die("Booking not found or access unauthorized.");
    }

    // Fetch destinations for this package to add as calendar event location
    $stmtD = $pdo->prepare("
        SELECT d.Name, d.Country 
        FROM Destination d
        JOIN Package_Destination pd ON d.DestID = pd.DestID
        WHERE pd.PackageID = (
            SELECT Trip_PackageID FROM Booking WHERE BookingID = ?
        )
    ");
    $stmtD->execute([$booking_id]);
    $dests = $stmtD->fetchAll();
    $dest_names = [];
    foreach ($dests as $d) {
        $dest_names[] = $d['Name'] . ', ' . $d['Country'];
    }
    $location = implode('; ', $dest_names);

    // Format dates according to RFC-5545 (YYYYMMDD)
    $startDate = date('Ymd', strtotime($booking['StartDate']));
    // The end date in iCal for all-day events is EXCLUSIVE, so we add 1 day to the EndDate
    $endDate = date('Ymd', strtotime($booking['EndDate'] . ' +1 day'));
    $stampDate = date('Ymd\THis\Z', time());
    
    $uid = 'booking_' . $booking['BookingID'] . '_' . strtotime($booking['BookingDate']) . '@tripistry.com';

    // Build the ICS content
    $ics = [];
    $ics[] = 'BEGIN:VCALENDAR';
    $ics[] = 'VERSION:2.0';
    $ics[] = 'PRODID:-//Tripistry//Travel Booking Sync//EN';
    $ics[] = 'CALSCALE:GREGORIAN';
    $ics[] = 'METHOD:PUBLISH';
    $ics[] = 'BEGIN:VEVENT';
    $ics[] = 'UID:' . $uid;
    $ics[] = 'DTSTAMP:' . $stampDate;
    $ics[] = 'DTSTART;VALUE=DATE:' . $startDate;
    $ics[] = 'DTEND;VALUE=DATE:' . $endDate;
    $ics[] = 'SUMMARY:Tripistry Voyage: ' . str_replace(',', '\,', $booking['PackageTitle']);
    
    $desc = "Pack your bags! Your trip is confirmed for " . $booking['PartySize'] . " traveller(s).\n\n";
    $desc .= "Package: " . $booking['PackageTitle'] . "\n";
    $desc .= "Organized by: " . $booking['AgencyName'] . "\n";
    $desc .= "Total Cost: $" . number_format($booking['TotalAmount'], 2) . "\n\n";
    $desc .= "Itinerary Overview:\n" . cleanDescription($booking['PackageDesc']);
    
    $ics[] = 'DESCRIPTION:' . escapeString($desc);
    $ics[] = 'LOCATION:' . escapeString($location ?: 'Global Destinations');
    $ics[] = 'END:VEVENT';
    $ics[] = 'END:VCALENDAR';

    $ics_content = implode("\r\n", $ics);

    // Send calendar response headers
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="tripistry_booking_' . $booking_id . '.ics"');
    header('Content-Length: ' . strlen($ics_content));
    header('Connection: close');

    echo $ics_content;
    exit;

} catch (\Exception $e) {
    http_response_code(500);
    die("An error occurred generating your calendar export: " . $e->getMessage());
}

// Helper to escape text formatting inside RFC-5545 VEVENT fields
function escapeString($string) {
    return preg_replace('/([\,;])/', '\\\$1', str_replace("\n", "\\n", str_replace("\r", "", $string)));
}

// Helper to clean HTML or multi-space strings from package descriptions
function cleanDescription($string) {
    return strip_tags(trim($string));
}
