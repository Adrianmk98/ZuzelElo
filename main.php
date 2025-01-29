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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif;
            .chart-container {
                width: 20%;
                margin: auto;
            }}
    </style>
</head>
<body>
<h1>Zuzel Elo</h1>
<h2>Random Race</h2>

    <?php
    // Helper functions


    $ratings = [];
    foreach ($selectedPlayers as $index => $player) {
        $ratings[$index + 1] = $player['Elo'];
    }

    $probabilities = calculateRankedProbabilities($ratings);
    ?>

    <div class="chart-container">
        <canvas id="winChart"></canvas>
    </div>
        <?php
        $chartData = [];
        $colors = ['#FF0000', '#0000FF', '#FFFFFF', '#FFFF00'];
        // Initialize arrays to store player data
        // Initialize arrays to store player data
        $players = [];
        $winProbabilities = [];
        $expectedPoints = [];

        // Collect data for chart and table
        foreach ($selectedPlayers as $index => $player) {
            $winProbability = round(($probabilities[$index + 1][0] ?? 0) * 100, 2);
            $expectedPointsValue = 0;

            for ($i = 0; $i < 4; $i++) {
                $expectedPointsValue += ($probabilities[$index + 1][$i] ?? 0) * (3 - $i);
            }

            // Store data for each player
            $players[] = $player['FirstName'] . ' ' . $player['LastName'];
            $winProbabilities[] = $winProbability;
            $expectedPoints[] = round($expectedPointsValue, 2);

            $playerID = $player['PlayerID'];

            // Collect chart data (with players as columns)
            $chartData[] = [
                'label' => $player['FirstName'] . ' ' . $player['LastName'],
                'probability' => $winProbability,
                'expectedPoints' => round($expectedPointsValue, 2),
                'color' => $colors[$index] ?? '#CCCCCC'
            ];
        }

        // Table Layout
        echo "<table>";
        echo "<thead><tr><th></th>";  // First column for labels
        foreach ($selectedPlayers as $index => $player) {
            $playerID = $player['PlayerID'];

            // Debugging: Ensure playerID is correct
            // echo "Player ID: $playerID<br>";

            echo "<th>" . $player['FirstName'] . ' ' . $player['LastName'] . "";

            ?>
            <picture style="display: flex; justify-content: center; align-items: center;">
                <source media="(min-width: 650px)" srcset="playerlogos/<?php echo file_exists("playerlogos/$playerID.jpg") ? $playerID : 0; ?>.jpg">
                <img src="playerlogos/<?php echo file_exists("playerlogos/$playerID.jpg") ? $playerID : 0; ?>.jpg"
                     style="max-width: 32px; max-height: 32px; width: auto; height: auto; display: block; margin: 0 auto;">
            </picture></th>
            <?php
        }
        echo "</tr></thead><tbody>";

        // Create rows for each data point (Name, Picture, Elo, Expected Points)
        $labels = ['Elo', 'Expected Points'];

        foreach ($labels as $label) {
            echo "<tr><td>$label</td>";  // Display label in the first column
            foreach ($selectedPlayers as $index => $player) {
                switch ($label) {
                    case 'Elo':
                        // Output the player's Elo
                        echo "<td>" . htmlspecialchars($player['Elo']) . "</td>";
                        break;
                    case 'Expected Points':
                        // Output the player's expected points
                        $expectedPointsValue = $expectedPoints[$index] ?? '-';
                        echo "<td>" . round($expectedPointsValue, 2) . "</td>";
                        break;
                }
            }
            echo "</tr>";
        }
        echo "</tbody></table>";
?>


    <script>
        const chartData = <?php echo json_encode($chartData); ?>;
        const labels = chartData.map(player => player.label);
        const probabilities = chartData.map(player => player.probability);
        const colors = chartData.map(player => player.color);
        const ctx = document.getElementById('winChart').getContext('2d');

        const winChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: probabilities,
                    backgroundColor: colors,
                    hoverOffset: 10,
                }]
            },
            options: {
                responsive: true,
                cutout: '30%',
                rotation: -90,
                circumference: 180,
                aspectRatio: 2,  // Adjust this value to create the oval effect
                plugins: {
                    legend: {
                        display: false // Remove the legend
                    }
                },
                layout: {
                    padding: {
                        top: 0, // Remove any extra padding from the top
                        left: 0,
                        right: 0,
                        bottom: 0
                    }
                }
            }
        });


        document.querySelectorAll('tr[data-index]').forEach(row => {
            row.addEventListener('mouseover', function() {
                let index = this.getAttribute('data-index');

                // Trigger the tooltip and highlight the section
                winChart.tooltip.setActiveElements([{
                    datasetIndex: 0,
                    index: index
                }], {x: 0, y: 0});  // You can adjust the position if needed

                // Update the chart to show the tooltip and highlight the section
                winChart.update();
            });

            row.addEventListener('mouseout', function() {
                // Hide the tooltip when the mouse leaves the row
                winChart.tooltip.setActiveElements([], {x: 0, y: 0});
                winChart.update();
            });
        });



    </script>
<?php
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
    WHERE h.`matchID` = $matchID AND h.`heatNumber` = $heatNumber ORDER BY h.startingpositionID";


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

<?php
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
?>