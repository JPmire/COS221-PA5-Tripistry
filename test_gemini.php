<?php
// 1. Load the key from your .env file
$env = parse_ini_file(__DIR__ . '/.env');
$apiKey = $env['GEMINI_API_KEY'] ?? null;

if (!$apiKey) {
    die("Error: API key not found. Check your .env file.");
}

// 2. Set up the endpoint for Gemini 1.5 Pro
$endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=" . $apiKey;
// 3. Create a simple test payload
$payload = json_encode([
    "contents" => [
        ["parts" => [["text" => "Say hello and give me a random travel tip!"]]]
    ]
]);

// 4. Execute the cURL request
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

// Note: If you are developing on local (like XAMPP) and get SSL errors, uncomment the line below for testing only.
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 5. Output the result
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "Success! Gemini says:\n\n";
    echo $data['candidates'][0]['content']['parts'][0]['text'];
} else {
    echo "Failed with HTTP Code: $httpCode\n";
    echo "Response: $response";
}
?>