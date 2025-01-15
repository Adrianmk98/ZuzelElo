<?php
// Define the base URL for the match profile
$baseUrl = "http://localhost:63343/ZuzelElo/matchprofileloader.php?match=";

// Loop through matches from 1 to 50
for ($match_id = 1; $match_id <= 50; $match_id++) {
    // Construct the full URL
    $url = $baseUrl . $match_id;

    // Make a request to the URL (this updates values on the server)
    $response = file_get_contents($url);

    // Optional: Log or display a message for each request
    echo "Opened: $url<br>";
}

echo "All match profiles processed!";
?>

