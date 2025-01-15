<?php
function calculatePlayerStats($playerIDs, $eloRatings, $hometeamID, $ctID, $heatnum, $matchID, $pdo, $weeknum, $yearnum, $iterations = 10000, $k = 16) {
    if (count($playerIDs) !== 4 || count($eloRatings) !== 4) {
        throw new Exception("Exactly 4 player IDs and Elo ratings are required.");
    }

    $nPlayers = count($eloRatings);
    $adjustedEloRatings = [];

    // Construct the dynamic table name based on the year
    $tableName = "eloarchive" . $yearnum;
    $dobCutoff = $yearnum - 21;

    // Fetch average Elo points for the specified week column
    echo "DEBUG: Fetching average Elo points for table: $tableName, week: $weeknum\n";
    $query = $pdo->prepare("
        SELECT AVG(`$weeknum`) as avgEloPoints 
        FROM `$tableName` WHERE playerID IN (
        SELECT playerID 
        FROM player 
        WHERE YoB < :dobCutoff and `$weeknum`>1
    )
    ");
    $query->bindParam(':dobCutoff', $dobCutoff, PDO::PARAM_INT);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    $averageEloPoints = $result['avgEloPoints'] ?? 0; // Default to 0 if no data available



    echo "DEBUG: Fetching average Youth Elo points for table: $tableName, week: $weeknum\n";
    $queryyouth = $pdo->prepare("
    SELECT AVG(`$weeknum`) as avgEloPoints 
    FROM `$tableName` 
    WHERE playerID IN (
        SELECT playerID 
        FROM player 
        WHERE YoB > :dobCutoff and `$weeknum`>1
    )
");

    $queryyouth->bindParam(':dobCutoff', $dobCutoff, PDO::PARAM_INT);

// Execute the query
    $queryyouth->execute();

// Fetch the result
    $resultyouth = $queryyouth->fetch(PDO::FETCH_ASSOC);
    $avgEloPointsyouth = $resultyouth['avgEloPoints'];

    echo "DEBUG: Average Elo points: $averageEloPoints\n";
    echo "DEBUG: Average Youth Elo points: $avgEloPointsyouth\n";

    // Calculate adjusted Elo ratings for each player
    foreach ($playerIDs as $index => $playerID) {
        echo "DEBUG: Calculating adjustments for Player ID: $playerID\n";

        // Fetch the maximum Elo rating for scaling
        $queryMaxElo = $pdo->prepare("
    SELECT MAX(`$weeknum`) as maxEloPoints 
    FROM `$tableName`
");
        $queryMaxElo->execute();
        $resultMaxElo = $queryMaxElo->fetch(PDO::FETCH_ASSOC);
        $maxEloPoints = $resultMaxElo['maxEloPoints'] ?? 0;

        if ($maxEloPoints > 0) {
            // Fetch total PPO and calculate adjusted Elo ratings
            $query = $pdo->prepare("
        SELECT SUM(PPO) as totalPPO 
        FROM heatinformation 
        WHERE playerID = :playerID 
          AND matchID = :matchID 
          AND heatNumber < :heatnum
    ");
            $query->execute([
                'playerID' => $playerID,
                'matchID' => $matchID,
                'heatnum' => $heatnum
            ]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            $ppoAdjustment = $result['totalPPO'] ?? 0;
            echo "DEBUG: Total PPO for Player ID $playerID: $ppoAdjustment<br>";

            // Scale the adjustment based on the player's Elo rating
            //$ratingFactor = 1 - ($eloRatings[$index] / $maxEloPoints); // Higher ratings get smaller factors
            //$minScale = 1;  // Corresponds to ($ppoAdjustment / 100) * 1
            //$maxScale = 10; // Corresponds to ($ppoAdjustment / 100) * 10

            //$adjustmentScale = $minScale + ($ratingFactor * ($maxScale - $minScale));
            $boostMultiplier = ($ppoAdjustment / 100) * 4;
            //echo "DEBUG: AdjustmentScale: {$adjustmentScale}<br>";
            echo "DEBUG: Elo (before PPO): {$eloRatings[$index]}<br>";

            // Adjust the Elo rating
            if ($ppoAdjustment >= 0) {
                $adjustedEloRatings[$index] = $eloRatings[$index] * (1 + $boostMultiplier);
            } else {
                $adjustedEloRatings[$index] = $eloRatings[$index] * (1 + ($boostMultiplier*2)); // Smaller penalty
            }
        }

        #$stmt = $pdo->prepare("
    #SELECT
    #    SUM(score) AS total_score,
   #     SUM(projectedPoints) AS total_projected_points
   # FROM heatinformation
   # WHERE
   #     currentplayerteamID = :teamID
    #    AND matchID < :matchID
   #     AND matchID IN (SELECT matchID FROM matches WHERE yearnum = :yearnum)
#");

 #       $stmt->execute([
   #         ':teamID' => $ctID[$index],
   #         ':matchID' => $matchID,
    #        ':yearnum' => $yearnum,
    #    ]);

     #   $result = $stmt->fetch(PDO::FETCH_ASSOC);
     #   $totalScore = $result['total_score'];
     #   $totalProjectedPoints = $result['total_projected_points'];
     #   if($totalScore>0)
     #   {

      #      $teamRatio=$totalScore/$totalProjectedPoints;
      #      echo "DEBUG: totalScore and totalProjected:".$teamRatio." ".$totalScore.":".$totalProjectedPoints."<br>";
      #      echo "DEBUG: Adjusted Elo (before teamRatio): {$adjustedEloRatings[$index]}<br>";
      #      $adjustedEloRatings[$index] *=$teamRatio;
      #  }




     #   echo "DEBUG: Adjusted Elo (before home/away): {$adjustedEloRatings[$index]}<br>";

        if ($hometeamID === $ctID[$index]) {
            $adjustedEloRatings[$index] *= 1.05; // Home team advantage
            echo "DEBUG: Adjusted Elo with home advantage: {$adjustedEloRatings[$index]}<br>";
        } else {
            $adjustedEloRatings[$index] *= 0.95; // Away team disadvantage
            echo "DEBUG: Adjusted Elo with away disadvantage: {$adjustedEloRatings[$index]}<br>";
        }
    }

    // Run the original simulation (all players)
    echo "DEBUG: Starting Monte Carlo simulation for actual players<br>";
    $playerResults = runMonteCarloSimulation($playerIDs, $adjustedEloRatings, $iterations, $k);

    // Run simulations with each player replaced by the average Elo
    echo "DEBUG: Starting Monte Carlo simulations for average player replacements<br>";
    $averageSimulations = [];
    foreach ($playerIDs as $index => $playerID) {
        $replacementRatings = $adjustedEloRatings;

        // Fetch player's YoB to determine which average Elo to use
        $queryYoB = $pdo->prepare("SELECT YoB FROM player WHERE playerID = :playerID");
        $queryYoB->execute(['playerID' => $playerID]);
        $resultYoB = $queryYoB->fetch(PDO::FETCH_ASSOC);
        $playerYoB = $resultYoB['YoB'] ?? null;

        // Determine which average Elo to use based on age
        if ($playerYoB !== null && ($yearnum - $playerYoB) <= 21) {
            $replacementRatings[$index] = $avgEloPointsyouth; // Use youth average Elo
            echo "DEBUG: Replacing Player $playerID with youth average Elo ($avgEloPointsyouth) for simulation<br>";
        } else {
            $replacementRatings[$index] = $averageEloPoints; // Use general average Elo
            echo "DEBUG: Replacing Player $playerID with average Elo ($averageEloPoints) for simulation<br>";
        }

        $averageSimulations[$index] = runMonteCarloSimulation($playerIDs, $replacementRatings, $iterations, $k);
    }


    // Calculate Points Above Average (PAA)
    echo "DEBUG: Calculating final results with Points Above Average<br>";
    $results = [];
    foreach ($playerIDs as $index => $id) {
        $projectedPoints = $playerResults[$id]['projected_points'];
        $averageProjectedPoints = $averageSimulations[$index][$id]['projected_points'];
        $performanceAboveAverage = $averageProjectedPoints;

        echo "DEBUG: Player $id Average Projected Points (replacement simulation): $averageProjectedPoints<br>";
        echo "DEBUG: Player $id Performance Above Average: $performanceAboveAverage<br>";

        $results[$id] = [
            'win_chance' => $playerResults[$id]['win_chance'],
            'finishing_probs' => $playerResults[$id]['finishing_probs'],
            'projected_points' => $projectedPoints,
            'elo_change' => $playerResults[$id]['elo_change'],
            'points_above_average' => $performanceAboveAverage
        ];
    }

    return $results;
}

// Helper function to run a Monte Carlo simulation
function runMonteCarloSimulation($playerIDs, $eloRatings, $iterations, $k) {
    $nPlayers = count($eloRatings);
    $winCounts = array_fill(0, $nPlayers, 0);
    $positionCounts = array_fill(0, $nPlayers, [0, 0, 0, 0]);
    $expectedPoints = array_fill(0, $nPlayers, 0);
    $eloChanges = array_fill(0, $nPlayers, 0);

    mt_srand(2); // Seed for reproducibility
    for ($i = 0; $i < $iterations; $i++) {
        $playerProbabilities = [];
        foreach ($eloRatings as $elo) {
            $playerProbabilities[] = pow(10, $elo / 1600);
        }

        $playerScores = [];
        foreach ($playerProbabilities as $index => $prob) {
            $playerScores[$index] = mt_rand() / mt_getrandmax() * $prob;
        }

        arsort($playerScores);
        $players = array_keys($playerScores);

        $playerExpectedScores = [];
        foreach ($players as $playerIndex) {
            $expectedScore = 0;
            foreach ($players as $opponentIndex) {
                if ($playerIndex !== $opponentIndex) {
                    $expectedScore += 1 / (1 + pow(10, ($eloRatings[$opponentIndex] - $eloRatings[$playerIndex]) / 1600));
                }
            }
            $playerExpectedScores[$playerIndex] = $expectedScore;
        }

        foreach ($players as $position => $playerIndex) {
            $positionCounts[$playerIndex][$position]++;
            $expectedPoints[$playerIndex] += (3 - $position);
            if ($position === 0) {
                $winCounts[$playerIndex]++;
            }

            $actualScore = (3 - $position);
            $eloChange = $k * ($actualScore - $playerExpectedScores[$playerIndex]);
            $eloChanges[$playerIndex] += $eloChange;
        }
    }

    $results = [];
    foreach ($playerIDs as $index => $id) {
        $results[$id] = [
            'win_chance' => $winCounts[$index] / $iterations,
            'finishing_probs' => array_map(
                fn($count) => $count / $iterations,
                $positionCounts[$index]
            ),
            'projected_points' => $expectedPoints[$index] / $iterations,
            'elo_change' => $eloChanges[$index] / $iterations
        ];
    }

    return $results;
}

function runFutureMonteCarloSimulation($playerIDs, $eloRatings,$teamID,$HomeTeamID, $iterations, $k) {
    $nPlayers = count($eloRatings);
    $winCounts = array_fill(0, $nPlayers, 0);
    $positionCounts = array_fill(0, $nPlayers, [0, 0, 0, 0]);
    $expectedPoints = array_fill(0, $nPlayers, 0);
    $eloChanges = array_fill(0, $nPlayers, 0);

    mt_srand(2); // Seed for reproducibility

    // Calculate adjusted Elo ratings for each player
    foreach ($playerIDs as $index => $playerID) {

        #   echo "DEBUG: Adjusted Elo (before home/away): {$adjustedEloRatings[$index]}<br>";

        if ($HomeTeamID === $teamID[$index]) {
            $eloRatings[$index] *= 1.05; // Home team advantage
        } else {
            $eloRatings[$index] *= 0.95; // Away team disadvantage
        }
    }

    for ($i = 0; $i < $iterations; $i++) {
        $playerProbabilities = [];
        foreach ($eloRatings as $elo) {
            $playerProbabilities[] = pow(10, $elo / 1600);
        }

        $playerScores = [];
        foreach ($playerProbabilities as $index => $prob) {
            $playerScores[$index] = mt_rand() / mt_getrandmax() * $prob;
        }



        arsort($playerScores);
        $players = array_keys($playerScores);

        $playerExpectedScores = [];
        foreach ($players as $playerIndex) {
            $expectedScore = 0;
            foreach ($players as $opponentIndex) {
                if ($playerIndex !== $opponentIndex) {
                    $expectedScore += 1 / (1 + pow(10, ($eloRatings[$opponentIndex] - $eloRatings[$playerIndex]) / 1600));
                }
            }
            $playerExpectedScores[$playerIndex] = $expectedScore;
        }

        foreach ($players as $position => $playerIndex) {
            $positionCounts[$playerIndex][$position]++;
            $expectedPoints[$playerIndex] += (3 - $position);
            if ($position === 0) {
                $winCounts[$playerIndex]++;
            }

            $actualScore = (3 - $position);
            $eloChange = $k * ($actualScore - $playerExpectedScores[$playerIndex]);
            $eloChanges[$playerIndex] += $eloChange;
        }
    }

    $results = [];
    foreach ($playerIDs as $index => $id) {
        $results[$id] = [
            'win_chance' => $winCounts[$index] / $iterations,
            'finishing_probs' => array_map(
                fn($count) => $count / $iterations,
                $positionCounts[$index]
            ),
            'projected_points' => $expectedPoints[$index] / $iterations,
            'elo_change' => $eloChanges[$index] / $iterations
        ];
    }

    return $results;
}

function runFutureSingleMonteCarloSimulation($playerIDs, $eloRatings,$teamID,$HomeTeamID, $iterations, $k) {
    $nPlayers = count($eloRatings);
    $winCounts = array_fill(0, $nPlayers, 0);
    $positionCounts = array_fill(0, $nPlayers, [0, 0, 0, 0]);
    $expectedPoints = array_fill(0, $nPlayers, 0);
    $eloChanges = array_fill(0, $nPlayers, 0);


    // Calculate adjusted Elo ratings for each player

    foreach ($playerIDs as $index => $playerID) {

        #   echo "DEBUG: Adjusted Elo (before home/away): {$adjustedEloRatings[$index]}<br>";

        if ($HomeTeamID === $teamID[$index]) {
            $eloRatings[$index] *= 1.05; // Home team advantage
        } else {
            $eloRatings[$index] *= 0.95; // Away team disadvantage
        }
    }
    for ($i = 0; $i < $iterations; $i++) {
        $playerProbabilities = [];
        foreach ($eloRatings as $elo) {
            $playerProbabilities[] = pow(10, $elo / 1600);
        }

        $playerScores = [];
        mt_srand((int) (microtime(true) * 1000000)); // Seed based on microseconds
        foreach ($playerProbabilities as $index => $prob) {
            // Add a random factor to the score calculation

            $playerScores[$index] = ($prob) * mt_rand() / mt_getrandmax();
        }


        arsort($playerScores);
        $players = array_keys($playerScores);

        $playerExpectedScores = [];
        foreach ($players as $playerIndex) {
            $expectedScore = 0;
            foreach ($players as $opponentIndex) {
                if ($playerIndex !== $opponentIndex) {
                    $expectedScore += 1 / (1 + pow(10, ($eloRatings[$opponentIndex] - $eloRatings[$playerIndex]) / 1600));
                }
            }
            $playerExpectedScores[$playerIndex] = $expectedScore;
        }

        foreach ($players as $position => $playerIndex) {
            $positionCounts[$playerIndex][$position]++;
            $expectedPoints[$playerIndex] += (3 - $position);
            if ($position === 0) {
                $winCounts[$playerIndex]++;
            }

            $actualScore = (3 - $position);
            $eloChange = $k * ($actualScore - $playerExpectedScores[$playerIndex]);
            $eloChanges[$playerIndex] += $eloChange;
        }
    }

    $results = [];
    foreach ($playerIDs as $index => $id) {
        $results[$id] = [
            'win_chance' => $winCounts[$index] / $iterations,
            'finishing_probs' => array_map(
                fn($count) => $count / $iterations,
                $positionCounts[$index]
            ),
            'projected_points' => $expectedPoints[$index] / $iterations,
            'elo_change' => $eloChanges[$index] / $iterations
        ];
    }

    return $results;
}





?>
