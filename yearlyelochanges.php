<?php
// Database connection details
$servername = "localhost";  // Your database server
$dbusername = "root";
$dbpassword = "";
$dbname = "zuzelelo";


?>
<?php
// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$currentYear=2025;
$lastyear=$currentYear-1;
$archive='eloarchive'.$lastyear;
//$totalScore=SELECT p.playerID, p.firstName, p.lastName, SUM(h.Score+h.bonus) AS totalScore, COUNT(*) AS racesParticipated, ROUND(SUM(h.Score) * 1.0 / COUNT(DISTINCT h.matchID), 2) AS avgPPAPerGame FROM heatinformation h JOIN matches m ON h.matchID = m.matchID JOIN player p ON h.playerID = p.playerID GROUP BY p.playerID, p.firstName, p.lastName;
//$ages="Select playerID,SUM($currentYear-YoB) from player";
// Query to calculate player statistics, including the latest non-zero score
// Query to get all player IDs
$query = "SELECT playerID, firstName, lastName, YoB, Elo FROM player";
$result = mysqli_query($conn, $query); // Replace $conn with your DB connection variable

if ($result) {
    echo "<table border='1'>";
    echo "<tr>
        <th>Player ID</th>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Age Sum</th>
        <th>Percent Drop</th>
        <th>Latest Score</th>
        <th>Over 1500</th>
        <th>Age Adjustment</th>
        <th>Adjusted Elo</th>
        <th>Score</th>
        <th>Races</th>
        <th>Race Weight</th>
        <th>Anti-Race Weight</th>
        <th>AVG per Race</th>
        <th>Elo After Adjust</th>
    </tr>";

    $currentYear = date('Y'); // Current year for age calculation

    while ($player = mysqli_fetch_assoc($result)) {
        $playerID = $player['playerID'];
        $firstName = $player['firstName'];
        $lastName = $player['lastName'];
        $ageSum = $currentYear - $player['YoB'];
        $latest = $player['Elo'];

        // Fetch total score and race data for this player
        $queryStats = "SELECT 
            SUM(h.Score + h.bonus) AS totalScore,
            COUNT(*) AS racesParticipated
        FROM heatinformation h
        JOIN matches m ON h.matchID = m.matchID
        WHERE h.playerID = $playerID";

        $statsResult = mysqli_query($conn, $queryStats);
        $stats = mysqli_fetch_assoc($statsResult);

        $totalScore = $stats['totalScore'] ?? 0;
        $racesParticipated = $stats['racesParticipated'] ?? 0;

        $pointsPerRace = $racesParticipated > 0 ? number_format($totalScore / $racesParticipated, 2) : 0;
        $pointsPerRaceScaled = $pointsPerRace * 1000;

        // Calculate age adjustment
        $percentDrop = ($latest > 1500 && $ageSum > 25) ? (5 + ($ageSum - 25)) : 0;
        $numberDrop = (($latest - 1500) * $percentDrop) / 100;
        $eloAfterAgeAdjust = $latest - $numberDrop;

        // Determine weights dynamically based on races participated
        $raceWeight = min(1, $racesParticipated / 100) * 0.6; // Scale to a maximum of 1
        $eloWeight = 1 - $raceWeight;

        // Calculate ELO adjustments based on points per race and race participation
        $eloAfterAllAdjustments = ($eloAfterAgeAdjust * $eloWeight) + ($pointsPerRaceScaled * $raceWeight);
        /*

        // Check if playerID exists in $archive
        $carchive='eloarchive'.$currentYear;
        $checkQuery = "SELECT 1 FROM `$carchive` WHERE `playerID` = $playerID LIMIT 1";
        $checkResult = mysqli_query($conn, $checkQuery);

        if (mysqli_num_rows($checkResult) > 0) {
            // Update if playerID exists
            $updateQuery = "UPDATE `$carchive` SET `1` = $eloAfterAllAdjustments WHERE `playerID` = $playerID";
            $updateResult = mysqli_query($conn, $updateQuery);

            if (!$updateResult) {
                echo "Error updating archive for player $playerID: " . mysqli_error($conn) . "<br>";
            }
        } else {
            // Insert a new row if playerID does not exist
            $insertQuery = "INSERT INTO `$carchive` (`playerID`, `1`) VALUES ($playerID, $eloAfterAllAdjustments)";
            $insertResult = mysqli_query($conn, $insertQuery);

            if (!$insertResult) {
                echo "Error inserting into archive for player $playerID: " . mysqli_error($conn) . "<br>";
            }
        }*/

        // Output the results for the player
        echo "<tr>";
        echo "<td>$playerID</td>";
        echo "<td>$firstName</td>";
        echo "<td>$lastName</td>";
        echo "<td>$ageSum</td>";
        echo "<td>$percentDrop</td>";
        echo "<td>$latest</td>";
        echo "<td>" . ($latest - 1500) . "</td>";
        echo "<td>$numberDrop</td>";
        echo "<td>$eloAfterAgeAdjust</td>";
        echo "<td>$totalScore</td>";
        echo "<td>$racesParticipated</td>";
        echo "<td>$raceWeight</td>";
        echo "<td>$eloWeight</td>";
        echo "<td>$pointsPerRaceScaled</td>";
        echo "<td>$eloAfterAllAdjustments</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "Error fetching players: " . mysqli_error($conn);
}


?>