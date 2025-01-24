<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';

// Fetch players from the database
$sql = "SELECT PlayerID, FirstName, LastName, Elo FROM player";  // Assuming you have a 'player' table
$result = $conn->query($sql);

// Check if there are players in the database
$players = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $players[] = $row; // Store each player's details in the $players array
    }
} else {
    echo "No players found!";
    exit; // Stop further execution if no players exist
}


// Randomly select 4 players
if (count($players) >= 4) {
    $randomKeys = array_rand($players, 4);
    $selectedPlayers = array_map(fn($key) => $players[$key], $randomKeys);
} else {
    echo "Not enough players in the database!";
    exit; // Stop if there aren't at least 4 players
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main</title>
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
    <style>
        body { font-family: Arial, sans-serif; }
    </style>
</head>
<body>
<h1>Zuzel Elo</h1>
<h2>Random Race</h2>
<table>
    <tr>
        <th>Name</th>
        <th>Elo Rating</th>
        <th>Win Probability (%)</th>
        <th>Expected Points</th>
    </tr>
    <?php
    // Helper functions
    function calculateRankedProbabilities($ratings) {
        $n = count($ratings);
        $players = array_keys($ratings);
        $pairwiseProbabilities = [];

        foreach ($ratings as $playerA => $ratingA) {
            foreach ($ratings as $playerB => $ratingB) {
                $pairwiseProbabilities[$playerA][$playerB] = $playerA === $playerB ? 0.5 : 1 / (1 + pow(10, ($ratingB - $ratingA) / 1600));
            }
        }

        $permutations = generatePermutations($players);
        $positionProbabilities = [];

        foreach ($players as $player) {
            $positionProbabilities[$player] = array_fill(0, $n, 0);
        }

        foreach ($permutations as $permutation) {
            $probability = 1;
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $probability *= $pairwiseProbabilities[$permutation[$i]][$permutation[$j]];
                }
            }

            for ($i = 0; $i < $n; $i++) {
                $positionProbabilities[$permutation[$i]][$i] += $probability;
            }
        }

        foreach ($positionProbabilities as $player => &$probabilities) {
            $total = array_sum($probabilities);
            if ($total > 0) {
                $probabilities = array_map(fn($p) => $p / $total, $probabilities);
            }
        }

        return $positionProbabilities;
    }

    function generatePermutations($items) {
        if (count($items) === 1) {
            return [$items];
        }

        $permutations = [];
        foreach ($items as $index => $item) {
            $remainingItems = array_merge(array_slice($items, 0, $index), array_slice($items, $index + 1));
            foreach (generatePermutations($remainingItems) as $permutation) {
                $permutations[] = array_merge([$item], $permutation);
            }
        }
        return $permutations;
    }

    $ratings = [];
    foreach ($selectedPlayers as $index => $player) {
        $ratings[$index + 1] = $player['Elo'];
    }

    $probabilities = calculateRankedProbabilities($ratings);

    foreach ($selectedPlayers as $index => $player) {
        $playerProbabilities = $probabilities[$index + 1] ?? [];
        $expectedPoints = 0;
        for ($i = 0; $i < 4; $i++) {
            $expectedPoints += ($playerProbabilities[$i] ?? 0) * (3 - $i);
        }

        echo "<tr>";
        ?>
    <td><a href="profile.php?id=<?php echo $player['PlayerID']; ?>">
            <?php echo $player['FirstName'] . ' ' . $player['LastName']; ?></a></td>
            <?php
        echo "<td>" . htmlspecialchars($player['Elo']) . "</td>";
        echo "<td>" . round(($playerProbabilities[0] ?? 0) * 100, 2) . "%</td>";
        echo "<td>" . round($expectedPoints, 2) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Fetch a random row to get its matchID and heatNumber
    $randomRowQuery = "SELECT `matchID`, `heatNumber` FROM `heatinformation` ORDER BY RAND() LIMIT 1";
    $result = $conn->query($randomRowQuery);

    if ($result->num_rows > 0) {
        // Get the random matchID and heatNumber
        $row = $result->fetch_assoc();
        $matchID = $row['matchID'];
        $heatNumber = $row['heatNumber'];

        // Fetch all rows with the same matchID and heatNumber
        $dataQuery = "
    SELECT h.`raceID`, h.`matchID`, h.`heatNumber`, h.`startingpositionID`, p.`playerID` as playerID,p.`FirstName` as FirstName,p.`LastName` as LastName, h.`Score`, 
           h.`bonus`, h.`Status`, t.`teamName` AS TeamName, h.`winChance`, 
           h.`projectedPoints`
    FROM `heatinformation` h
    LEFT JOIN `team` t ON h.`currentplayerteamID` = t.`teamID`
    LEFT JOIN `player` p on h.`playerID`=p.`playerID`
    WHERE h.`matchID` = $matchID AND h.`heatNumber` = $heatNumber";


        $dataResult = $conn->query($dataQuery);

        if ($dataResult->num_rows > 0) {

            // Display the data in an HTML table
            ?>
    <?php
            echo "<h2>Match Number: $matchID<br>
Heat Number: $heatNumber</h2>";
            echo "<table border='1'>
                <tr>
                    <th>Player</th>
                    <th>Team</th>
                    <th>Score</th>
                    <th>Win Chance</th>
                    <th>Projected Points</th>
                </tr>";

            while ($dataRow = $dataResult->fetch_assoc()) {
                ?><tr>
                    <td><a href="profile.php?id=<?php echo $dataRow['playerID']; ?>">
    <?php echo $dataRow['FirstName'] . ' ' . $dataRow['LastName']; ?></a></td>
        <?php
                    echo"<td>{$dataRow['TeamName']}</td>";
                if($dataRow['bonus']) {
                    echo "<td>".$dataRow['Score'].'<sup>+1'."</sup>"."</td>";
                }else{
                    if($dataRow['Status'])
                    {
                        echo "<td>".$dataRow['Status']."</td>";
                    }else {
                        echo "<td>" . $dataRow['Score'] . "</td>";
                    }
                }
                echo"
                    
                    <td>{$dataRow['winChance']}%</td>
                    <td>{$dataRow['projectedPoints']}</td>
                </tr>";
            }

            echo "</table>";
        } else {
            echo "No data found for the selected raceID.";
        }
    } else {
        echo "No raceID found.";
    }
    ?>
</body>
</html>
