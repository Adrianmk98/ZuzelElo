<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';

// Fetch players from the database
$sql = "SELECT PlayerID, FirstName, LastName, Elo FROM player";  // Assuming you have a 'players' table
$result = $conn->query($sql);

// Check if there are players in the database
$players = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $players[] = $row; // Store each player's details in the $players array
    }
} else {
    echo "No players found!";
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elo Simulator</title>
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
    <link rel="stylesheet" href="includes/raceSimulatorStyle.css">
    <script>
        // JavaScript to update player details when a player is selected from dropdown
        // JavaScript to update player details when a player is selected from dropdown
function updatePlayerDetails(playerId, playerIndex) {
    const players = <?php echo json_encode($players); ?>; // Get player data as a JavaScript object

    // Find the selected player based on PlayerID
    const selectedPlayer = players.find(player => player.PlayerID == playerId);

    if (selectedPlayer) {
        // Set Elo rating in the corresponding Elo input field
        document.getElementById('elo' + playerIndex).value = selectedPlayer.Elo;
    } else {
        // If no player is selected, clear the Elo field
        document.getElementById('elo' + playerIndex).value = '';
    }
}


    </script>
</head>
<body>
<h1>Elo Simulator</h1>
    <form method="post">
        <h1>Player Elo Ratings</h1>
        <div style="text-align: center;"><button class="raceSim-button" type="submit" name="simulate">Simulate Race</button></div>
        <br><br>
        <table>
            <tr>
                <th>Player</th>
                <?php
                for ($i = 1; $i <= 4; $i++) {
                    if($i==1){
                    echo"<th>🟥 Player $i</th>";}
                    elseif($i==2){
                    echo"<th>🟦 Player $i</th>";}
                    elseif($i==3){
                    echo"<th>⬜ Player $i</th>";}
                    elseif($i==4){
                    echo"<th>🟨 Player $i</th>";}
                    else{
                    echo"<th>Player $i</th>";}
                }
                ?>
            </tr>
            <tr>
                <th>Name</th>
                <?php
    for ($i = 1; $i <= 4; $i++) {
        echo "<td>
                <select name='player_$i' id='player_$i' onchange='updatePlayerDetails(this.value, $i)'>
                    <option value=''>Select Player</option>";
        foreach ($players as $player) {
            echo "<option value='" . $player['PlayerID'] . "'>" . $player['FirstName'] . " " . $player['LastName'] . "</option>";
        }
        echo "</select>
              </td>";
    }
    ?>
            </tr>
            <tr>
                <th>Rating</th>
                <?php
                for ($i = 1; $i <= 4; $i++) {
                    echo "<td><input type='number' name='elo_$i' id='elo$i'></td>";
                }
                ?>
            </tr>
        </table>


    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {


        // Collect player names based on dropdown selection
$playerNames = [];
for ($i = 1; $i <= 4; $i++) {
    if (isset($_POST["player_$i"]) && $_POST["player_$i"] !== '') {
        // Find the selected player based on PlayerID
        $selectedPlayerId = $_POST["player_$i"];
        $selectedPlayer = array_filter($players, function($player) use ($selectedPlayerId) {
            return $player['PlayerID'] == $selectedPlayerId;
        });
        $selectedPlayer = array_values($selectedPlayer)[0] ?? null;

        // Use the player's full name if found
        if ($selectedPlayer) {
            $playerNames[$i] = $selectedPlayer['FirstName'] . ' ' . $selectedPlayer['LastName'];
        } else {
            $playerNames[$i] = "Player $i"; // Default name if no selection made
        }
    } else {
        // Default player name if no player is selected
        $playerNames[$i] = "Player $i";
    }
}


        // Helper functions
        function calculateWinProbabilities($ratings) {
    $probabilities = [];
    $totalProbability = 0;

    // Calculate win probability for each player based on Elo relative to others
    for ($i = 1; $i <= 4; $i++) {
        if ($ratings[$i] !== null) {
            $probabilities[$i] = 1;
            for ($j = 1; $j <= 4; $j++) {
                if ($i !== $j && $ratings[$j] !== null) {
                    // Calculate pairwise probability based on Elo difference between players
                    $probabilities[$i] *= 1 / (1 + pow(10, ($ratings[$j] - $ratings[$i]) / 1600));
                }
            }
            $totalProbability += $probabilities[$i];  // Accumulate total probability
        }
    }

    // Normalize probabilities to ensure they sum to 1 (100%)
    foreach ($probabilities as $i => $prob) {
        $probabilities[$i] /= $totalProbability;
    }

    return $probabilities;
}

function calculateRankedProbabilities($ratings) {
    $n = count($ratings);
    $players = array_keys($ratings);
    $pairwiseProbabilities = [];

    // Calculate pairwise probabilities
    foreach ($ratings as $playerA => $ratingA) {
        foreach ($ratings as $playerB => $ratingB) {
            if ($playerA === $playerB) {
                $pairwiseProbabilities[$playerA][$playerB] = 0.5;
            } else {
                $pairwiseProbabilities[$playerA][$playerB] = 1 / (1 + pow(10, ($ratingB - $ratingA) / 1600));
            }
        }
    }

    // Generate all permutations of players
    $permutations = generatePermutations($players);
    $positionProbabilities = [];

    // Initialize probabilities
    foreach ($players as $player) {
        $positionProbabilities[$player] = array_fill(0, $n, 0);
    }

    // Calculate probabilities for each permutation
    foreach ($permutations as $permutation) {
        $probability = 1;

        // Calculate the probability of this specific permutation
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $probability *= $pairwiseProbabilities[$permutation[$i]][$permutation[$j]];
            }
        }

        // Add the probability to the corresponding position for each player
        for ($i = 0; $i < $n; $i++) {
            $positionProbabilities[$permutation[$i]][$i] += $probability;
        }
    }

    // Normalize probabilities for each player
    foreach ($positionProbabilities as $player => &$probabilities) {
        $total = array_sum($probabilities);
        if ($total > 0) {
            $probabilities = array_map(function($p) use ($total) {
                return $p / $total;
            }, $probabilities);
        }
    }

    return $positionProbabilities;
}

// Helper function to generate all permutations of an array
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



        function simulateRace($ratings) {
            $probabilities = calculateWinProbabilities($ratings);
            $players = array_keys($probabilities);
            usort($players, function($a, $b) use ($probabilities) {
                return $probabilities[$b] <=> $probabilities[$a];
            });
            return $players;
        }

        function updateRatings($ratings, $outcomes, $kFactor = 20) {
            $scores = [];
            $expectedScores = [];
            $eloChanges = [];

            foreach ($outcomes as $rank => $player) {
                $scores[$player] = 1 - ($rank / 3); // 1st = 1, 2nd = 0.6667, 3rd = 0.3333, 4th = 0
            }

            foreach ($outcomes as $player) {
                $expectedScores[$player] = 0;
                foreach ($outcomes as $opponent) {
                    if ($player !== $opponent) {
                        $expectedScores[$player] += 1 / (1 + pow(10, ($ratings[$opponent] - $ratings[$player]) / 1600));
                    }
                }
                $expectedScores[$player] /= 3; // Normalize
            }

            foreach ($outcomes as $player) {
                $change = $kFactor * ($scores[$player] - $expectedScores[$player]);
                $eloChanges[$player] = $change;
                $ratings[$player] += $change;
            }

            return [$ratings, $eloChanges];
        }

        // Process Elo inputs
        $ratings = [];
$order=[];
        for ($i = 1; $i <= 4; $i++) {
            $order[$i]=$i;
            $ratings[$i] = isset($_POST["elo_$i"]) && $_POST["elo_$i"] !== '' ? intval($_POST["elo_$i"]) : null;
        }

        // Determine outcomes
        $outcomes = [];
        if (isset($_POST['simulate'])) {
    // Simulate race and calculate probabilities
    $outcomes = simulateRace($ratings);
    $probabilities = calculateRankedProbabilities($ratings);

    // Define the points system
    $pointsSystem = [3, 2, 1, 0];

    // Prepare data for sorting
    $playerData = [];
    foreach ($ratings as $player => $rating) {
        if ($rating !== null) {
            $expectedPoints = 0;
            $totalProbabilities = array_sum($probabilities[$player]);

            // Normalize probabilities (optional step for extra safety)
            $normalizedProbabilities = array_map(function($prob) use ($totalProbabilities) {
                return $totalProbabilities ? $prob / $totalProbabilities : 0;
            }, $probabilities[$player]);

            // Calculate expected points
            for ($i = 0; $i < 4; $i++) {
                $expectedPoints += $normalizedProbabilities[$i] * $pointsSystem[$i];
            }

            // Store player data for sorting
            $playerData[] = [
                'player' => $player,
                'probabilities' => $normalizedProbabilities,
                'expectedPoints' => $expectedPoints
            ];
        }
    }


    // Display sorted table
    echo "<h2 style='text-align: center;'>Probabilities and Expected Points</h2><table>";
    echo "<tr><th>Player</th><th>1st Place Probability (%)</th><th>2nd Place Probability (%)</th><th>3rd Place Probability (%)</th><th>4th Place Probability (%)</th><th>Expected Points</th></tr>";
$i=1;
    foreach ($playerData as $data) {
        $player = $data['player'];
        $probabilities = $data['probabilities'];
        $expectedPoints = $data['expectedPoints'];


        //🟥 🟦 ⬜ 🟨
        if ($i == 1) {
            echo "<tr><td>🟥". htmlspecialchars($playerNames[$data['player']] ?? "Player 1") . "</td>";
        } elseif ($i == 2) {
            echo "<tr><td>🟦". htmlspecialchars($playerNames[$data['player']] ?? "Player 2") . "</td>";
        } elseif ($i == 3) {
            echo "<tr><td>⬜" . htmlspecialchars($playerNames[$data['player']] ?? "Player 3") . "</td>";
        } elseif ($i == 4) {
            echo "<tr><td>🟨". htmlspecialchars($playerNames[$data['player']] ?? "Player 4") . "</td>";
        } else {
            echo "<tr><td>Player $player</td>";
        }



        echo"
                <td>" . round($probabilities[0] * 100, 2) . "%</td>
                <td>" . round($probabilities[1] * 100, 2) . "%</td>
                <td>" . round($probabilities[2] * 100, 2) . "%</td>
                <td>" . round($probabilities[3] * 100, 2) . "%</td>
                <td>" . round($expectedPoints, 2) . "</td>
              </tr>";
              $i++;
    }
    echo "</table>";

}
 else {
     exit;
}




        // Validate and calculate Elo
        if (count($outcomes) === 4) {
            // Update ratings based on the outcomes
            [$ratings, $eloChanges] = updateRatings($ratings, $outcomes);

            // Display the podium in a separate block
            displayPodium($outcomes, $playerNames,$playerData);
        } else {
            echo "<p>Please provide valid results for the race or simulate one.</p>";
        }

// Function to display podium


    }
    // Function to display podium
    function displayPodium($outcomes, $playerNames, $playerData) {
        echo "<h2 style='text-align: center;'>Podium</h2>";
        echo "<div class='podium'>";

        // Merge the data
        $mergedData = [];

        foreach ($playerData as $player) {
            // Get player ID
            $playerId = $player['player'];

            // Get player name based on the ID (adjust indexing if needed)
            $playerName = isset($playerNames[$playerId]) ? $playerNames[$playerId] : null;

            // Merge the data
            $mergedData[] = [
                'player' => $playerId,
                'name' => $playerName,
                'probabilities' => $player['probabilities'],
                'expectedPoints' => $player['expectedPoints']
            ];
        }

        // Generate a random podium based on probabilities
        $randomizedOutcomes = simulateRaceBasedOnProbabilities($mergedData);

        // Ensure $randomizedOutcomes has at least 4 entries
        $randomizedOutcomes = array_pad($randomizedOutcomes, 4, null);

        // Display positions in order
        echo "<div class='place place-2'>2nd<br>" . htmlspecialchars($mergedData[$randomizedOutcomes[1]]['name'] ?? 'Unknown Player') . "</div>"; // 2nd place
        echo "<div class='place place-1'>1st<br>" . htmlspecialchars($mergedData[$randomizedOutcomes[0]]['name'] ?? 'Unknown Player') . "</div>"; // 1st place (center)
        echo "<div class='place place-3'>3rd<br>" . htmlspecialchars($mergedData[$randomizedOutcomes[2]]['name'] ?? 'Unknown Player') . "</div>"; // 3rd place

        // Check if the index exists and is valid for 4th place
        echo "<div class='place place-4'>4th<br>" . htmlspecialchars($mergedData[$randomizedOutcomes[3]]['name'] ?? 'Unknown Player') . "</div>"; // 4th place

        echo "</div><br>";
    }


    // Function to simulate the race based on probabilities
    // Function to simulate the race based on probabilities
    function simulateRaceBasedOnProbabilities($mergedData) {
        $randomizedOutcomes = [-1, -1, -1, -1]; // Initialize with invalid values (-1)

        // Iterate over each player and determine position based on their probabilities
        foreach ($mergedData as $index => $player) {
            $probabilities = $player['probabilities'];
            $randomValue = mt_rand() / mt_getrandmax(); // Generates a random number between 0 and 1

            $totalProb = 0;
            $assignedPosition = -1;

            // Determine the position based on the random value and probability ranges
            foreach ($probabilities as $pos => $prob) {
                $totalProb += $prob;
                if ($randomValue <= $totalProb) {
                    $assignedPosition = $pos;
                    break;
                }
            }

            // Check if the assigned position is already taken
            if ($assignedPosition !== -1 && $randomizedOutcomes[$assignedPosition] === -1) {
                // Assign the position to the player
                $randomizedOutcomes[$assignedPosition] = $index;
            } else {
                // If the position is taken, find the next available position
                for ($i = 0; $i < count($randomizedOutcomes); $i++) {
                    if ($randomizedOutcomes[$i] === -1) {
                        $randomizedOutcomes[$i] = $index;
                        break;
                    }
                }
            }
        }

        return $randomizedOutcomes;
    }

    ?>
</body>
</html>
