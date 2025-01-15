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
$query = "SELECT DISTINCT `playerID` FROM `$archive`";
$result = mysqli_query($conn, $query); // Replace $connection with your DB connection variable

if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Player ID</th>
<th>First Name</th>
<th>Last Name</th>
<th>Age Sum</th>
<th>PercentDrop</th>
<th>Latest Score</th>
<th>Over 1500</th>
<th>Age Adjustment</th>
<th>Adjusted Elo</th>
<th>Score</th>
<th>Races</th>
<th>race weight</th>
<th>anti race weight</th>
<th>AVG per Race</th>
<th>Elo After Adjust</th></tr>";

    while ($row = mysqli_fetch_assoc($result)) {
        $playerID = $row['playerID'];
        $ageSum = 0;
        $latest = 0;

        // Loop through columns 21 to 1 for this playerID
        for ($loop = 21; $loop >= 1; $loop--) {
            // Query to get ageSum and the value from the current column for this playerID
            $Query = "SELECT 
    p.playerID, 
    p.firstName, 
    p.lastName, 
    SUM(h.Score + h.bonus) AS totalScore, 
    COUNT(*) AS racesParticipated, 
    ($currentYear - p.YoB) AS ageSum, 
    a.`$loop` 
FROM 
    player p
JOIN 
    heatinformation h 
ON 
    h.playerID = p.playerID
JOIN 
    matches m 
ON 
    h.matchID = m.matchID
JOIN 
    `$archive` a 
ON 
    p.playerID = a.playerID
WHERE 
    p.playerID = $playerID
";

            $Result = mysqli_query($conn, $Query);

            if ($Result) {
                $Row = mysqli_fetch_assoc($Result);

                // Store ageSum
                $ageSum = $Row['ageSum'];
                $firstName=$Row['firstName'];
                $lastName=$Row['lastName'];
                $playerID=$Row['playerID'];
                $totalScore=$Row['totalScore'];
                $racePart=$Row['racesParticipated'];
                if($Row['totalScore']>0)
                {
                    $pointsperrace=number_format($Row['totalScore']/$Row['racesParticipated'],2);
                }else{
                    $pointsperrace=0;
                }
                $pointsperrace=$pointsperrace*1000;

                // If the column value is not 0, store it and break the loop
                if (!empty($Row[$loop]) && $Row[$loop] != 0) {
                    $latest = $Row[$loop];
                    break;
                }
            }
        }

        // Output the results for the playerID
        if($latest>1500 && $ageSum >25)
        {
            $percentdrop=(5+($ageSum-25));
        }
        else
        {
            $percentdrop=0;
        }

        $numberdrop=((($latest - 1500)*($percentdrop))/100);
        $eloafterAgeAdjust=$latest-$numberdrop;
        // Determine the weights dynamically based on races participated
        $raceWeight = min(1, $racePart / 100)*0.6; // Scale to a maximum of 1 as races increase
        $eloWeight = 1 - $raceWeight;                  // Complement of race weight for balance

// Calculate ELO adjustments based on points per race and race participation
        if ($pointsperrace < 500) {
            $eloafterAllAdjustments = ($eloafterAgeAdjust * (($eloWeight))) +
                ($pointsperrace * ($raceWeight));
        } elseif ($pointsperrace >= 500 && $pointsperrace < 1000) {
            $eloafterAllAdjustments = ($eloafterAgeAdjust * (($eloWeight))) +
                ($pointsperrace * ($raceWeight));
        } else {
            $eloafterAllAdjustments = ($eloafterAgeAdjust * (($eloWeight))) +
                ($pointsperrace * ($raceWeight));
        }


        echo "<tr>";
        echo "<td>" . $playerID . "</td>";
        echo "<td>" . $firstName . "</td>";
        echo "<td>" . $lastName . "</td>";
        echo "<td>" . $ageSum . "</td>";
        echo "<td>" . $percentdrop . "</td>";
        echo "<td>" . $latest . "</td>";
        echo "<td>" . ($latest - 1500) . "</td>";
        echo "<td>" . $numberdrop . "</td>";
        echo "<td>" . $eloafterAgeAdjust . "</td>";
        echo "<td>" . $totalScore . "</td>";
        echo "<td>" . $racePart . "</td>";
        echo "<td>" . $raceWeight . "</td>";
        echo "<td>" . $eloWeight . "</td>";
        echo "<td>" . $pointsperrace . "</td>";
        echo "<td>" . $eloafterAllAdjustments . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "Error fetching player IDs: " . mysqli_error($conn);
}

?>