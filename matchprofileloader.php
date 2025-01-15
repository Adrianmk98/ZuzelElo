<?php

// Database connection details
$servername = "localhost";  // Your database server
$dbusername = "root";
$dbpassword = "";
$dbname = "zuzelelo";


try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if 'match' parameter exists in the URL
    if (!isset($_GET['match']) || empty($_GET['match'])) {
        die("Error: Match ID is required. Use ?match=<id> in the URL.");
    }

    // Sanitize and retrieve match ID
    $match_id = intval($_GET['match']);

    // Fetch match details
    $match_details_query = "
    SELECT m.matchID, m.homeTeamID, m.awayTeamID, ht.teamName AS homeTeamName, at.teamName AS awayTeamName, m.weeknum, m.yearnum
    FROM matches m
    LEFT JOIN team ht ON m.homeTeamID = ht.teamID
    LEFT JOIN team at ON m.awayTeamID = at.teamID
    WHERE m.matchID = :match_id
";
    $stmt = $pdo->prepare($match_details_query);
    $stmt->execute(['match_id' => $match_id]);
    $match_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$match_details) {
        die("Error: Match not found.");
    }


    $yearnum = $match_details['yearnum'];
    $weeknum = $match_details['weeknum'];
    $elo_table = "eloarchive" . $yearnum;

    echo $yearnum." ".$weeknum." ".$elo_table;


    $coalesce_parts = [];
    for ($i = $weeknum; $i > 0; $i--) {
        $coalesce_parts[] = "NULLIF(e.`$i`, 0)";
    }
    $coalesce_formula = implode(', ', $coalesce_parts);

// Final Elo formula
    $formula ="COALESCE($coalesce_formula, p.Elo)";

// SQL Query
    $race_results_query = "
SELECT 
    rr.raceID,
    p.playerID,
    rr.heatNumber,
    rr.startingpositionID,
    p.firstName,
    p.lastName,
    rr.Score,
    rr.Status,
    rr.bonus,
    rr.currentplayerteamID,
    t.teamName AS teamName,
    rr.substitutionID,
    s.firstName AS subFirstName,
    s.lastName AS subLastName,
    $formula AS eloRating
FROM heatinformation rr
LEFT JOIN player p ON rr.playerID = p.playerID
LEFT JOIN team t ON rr.currentplayerteamID = t.teamID
LEFT JOIN player s ON rr.substitutionID = s.playerID
LEFT JOIN $elo_table e ON p.playerID = e.playerID
WHERE rr.matchID = :match_id
ORDER BY rr.heatNumber, rr.startingpositionID;
";

    $stmt = $pdo->prepare($race_results_query);
    $stmt->execute(['match_id' => $match_id]);
    $race_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group results by heat number
    $results_by_heat = [];
    foreach ($race_results as $result) {
        $results_by_heat[$result['heatNumber']][] = $result;
    }
} catch (PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage());
}

$homematchcurrentscore=0;
$awaymatchcurrentscore=0;
$homecurrentprojectedscore=0;
$awaycurrentprojectedscore=0;



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Match Details</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; }
        h1, h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table th { background-color: #f4f4f4; }
        .match-header { margin-bottom: 20px; }
        .heat-table { margin-bottom: 40px; }
    </style>
</head>
<body>

<h1>Match Details</h1>


<div class="match-header">
    <p><strong>Match ID:</strong> <?= htmlspecialchars($match_details['matchID']) ?></p>
    <p>
        <strong>Drużyna Gospodarzy:</strong> <?= htmlspecialchars($match_details['homeTeamName']); ?>
        <?php

        $hometeamID = $match_details['homeTeamID'];

        $stmt = $pdo->prepare("SELECT SUM(score) FROM heatinformation WHERE currentplayerteamID = :teamID");
        $stmt->execute(['teamID' => $hometeamID]);
        $totalhomeScore = $stmt->fetchColumn();
        echo "<br><strong>Wynik Gospodarzy:</strong> " . htmlspecialchars($totalhomeScore);
        ?>
    </p>
    <p>
        <strong>Drużyna Gości:</strong> <?= htmlspecialchars($match_details['awayTeamName']); ?>
        <?php
        $awayteamID = $match_details['awayTeamID'];
        $stmt = $pdo->prepare("SELECT SUM(score) FROM heatinformation WHERE currentplayerteamID = :teamID");
        $stmt->execute(['teamID' => $awayteamID]);
        $totalawayScore = $stmt->fetchColumn();
        echo "<br><strong>Wynik Gości:</strong> " . htmlspecialchars($totalawayScore);
        ?>
    </p>
</div>

<?php
include 'totalscorepiechart.php';
include 'winprobabiltycalculator.php';
include 'elochangecalculator.php';
?>

<h2>Race Results by Heat</h2>
<?php
$playerPPOData = [];
$updatedEloRatings = [];  // Maintain Elo ratings for all players across heats

foreach ($results_by_heat as $heat_number => &$results):
    $playerIDs = [];
    $eloRatings = [];
    $scores = [];
    $ctID=[];

    // Store initial Elo ratings and player IDs
    foreach ($results as $result) {
        array_push($playerIDs, $result['playerID']);
        // Use the most up-to-date Elo rating if available
        array_push($eloRatings, $updatedEloRatings[$result['playerID']] ?? $result['eloRating']);
        array_push($scores, $result['Score']);
        array_push($ctID, $result['currentplayerteamID']);

    }


    // Calculate player stats and Elo changes for this heat
    $raceChances = calculatePlayerStats($playerIDs, $eloRatings, $hometeamID, $ctID,$heat_number,$match_id,$pdo,$weeknum,$yearnum);
    $elochangestat = elo_rating_4way($playerIDs, $eloRatings, $scores,$weeknum);

    ?>
    <div class="heat-table">
        <h3>Bieg <?= htmlspecialchars($heat_number) ?></h3>
        <table>
            <thead>
            <tr>
                <th>Zawodnik</th>
                <th>Drużyna</th>
                <th>Wynik</th>
                <th>Szansa na wygranie</th>
                <th>Prognozowane punkty(PPO)</th>
            </tr>
            </thead>
            <tbody>
            <?php

            // Initialize scores and projections for teams
            $homeprojectedscore = $awayprojectedscore = $homeheatscore = $awayheatscore = 0;

            // Loop over the players in the current heat
            foreach ($results as &$result):
                $playerID = $result['playerID'];
                $elochangefromRace = $elochangestat[$playerID]['oldelo'] - $elochangestat[$playerID]['newelo'];
                $eloData = $raceChances[$playerID] ?? ['win_chance' => 0, 'projected_points' => 0];

                // Store the updated Elo rating in the global array
                $updatedEloRatings[$playerID] = $elochangestat[$playerID]['newelo'];

                // Apply updated Elo rating to the current player's result
                $result['eloRating'] = $updatedEloRatings[$playerID]; // Set updated Elo rating


                // Calculate the PPO (Projected Points) based on the current Elo data
                $ppo = $result['Score'] - $eloData['projected_points'];
                $ppa=$result['Score']-$eloData['points_above_average'];

                // Initialize player data if not already set
                if (!isset($playerPPOData[$playerID])) {
                    $playerPPOData[$playerID] = [
                        'playerID' => $result['playerID'],
                        'firstName' => $result['firstName'],
                        'lastName' => $result['lastName'],
                        'teamName' => $result['teamName'],
                        'Score' => 0,
                        'Bonus' => 0,
                        'projected_points' => 0,
                        'ppo' => 0,
                        'ppa'=>0,
                        'pointBreakdown' => [], // Initialize an empty array ONCE
                    ];
                }

                // Determine the player's result for the current heat
                if (!empty($result['Status'])) {
                    $heatResult = $result['Status']; // Example: "d" for defect
                } elseif (!empty($result['subFirstName']) && !empty($result['subLastName'])) {
                    // Handle substitution: Update the substitute's point breakdown
                    $subPlayerID = $result['substitutionID'] ?? null; // Ensure we have a unique ID for the substitute
                    if ($subPlayerID) {
                        if (!isset($playerPPOData[$subPlayerID])) {
                            echo "Initializing substitute data for: " . htmlspecialchars($result['subFirstName'] . " " . $result['subLastName']) . "<br>";
                            $playerPPOData[$subPlayerID] = [
                                'playerID' => $subPlayerID,
                                'firstName' => $result['subFirstName'],
                                'lastName' => $result['subLastName'],
                                'teamName' => $result['teamName'],
                                'Score' => 0,
                                'Bonus' => 0,
                                'projected_points' => 0,
                                'ppo' => 0,
                                'ppa'=>0,
                                'pointBreakdown' => [], // Initialize an empty array ONCE
                            ];
                        }
                        // Update the substitute's point breakdown
                        $playerPPOData[$subPlayerID]['pointBreakdown'][] = '-';
                        $heatResult = $result['Score'];
                    }
                } else {
                    $heatResult = $result['Score']; // Normal score
                }

                // Append the current heat result to the point breakdown
                if ($result['bonus']) {
                    $playerPPOData[$playerID]['pointBreakdown'][] = (string)$heatResult . '*';
                } else {
                    $playerPPOData[$playerID]['pointBreakdown'][] = (string)$heatResult;
                }
                $playerPPOData[$playerID]['Score'] += $result['Score'];
                $playerPPOData[$playerID]['Bonus'] += $result['bonus'];
                $playerPPOData[$playerID]['projected_points'] += $eloData['projected_points'];
                $playerPPOData[$playerID]['ppa'] += $result['Score']-$eloData['points_above_average'];


                $playerPPOData[$playerID]['ppo'] += $ppo;

                // Query and logic for team scoring (home/away)
                $getteamQuery = $pdo->prepare("SELECT currentplayerteamID FROM heatinformation WHERE playerID = :playerID");
                $getteamQuery->execute(['playerID' => $playerID]);
                $getteam = $getteamQuery->fetchColumn();

                $hometeamQuery = $pdo->prepare("SELECT hometeamID FROM matches WHERE matchID = :matchID");
                $hometeamQuery->execute(['matchID' => $match_id]);
                $hometeamID = $hometeamQuery->fetchColumn();

                $awayteamQuery = $pdo->prepare("SELECT awayteamID FROM matches WHERE matchID = :matchID");
                $awayteamQuery->execute(['matchID' => $match_id]);
                $awayteamID = $awayteamQuery->fetchColumn();

                if ($getteam == $hometeamID) {
                    $homeprojectedscore += $eloData['projected_points'];
                    $homeheatscore += $result['Score'];
                } elseif ($getteam == $awayteamID) {
                    $awayprojectedscore += $eloData['projected_points'];
                    $awayheatscore += $result['Score'];
                }
                ?>
                <tr>
                    <td>
                        <?php
                        if ($result['startingpositionID'] == 1) {
                            echo "🟥";
                        } elseif ($result['startingpositionID'] == 2) {
                            echo "🟦";
                        } elseif ($result['startingpositionID'] == 3) {
                            echo "⬜";
                        } elseif ($result['startingpositionID'] == 4) {
                            echo "🟨";
                        }
                        ?>
                        <?= htmlspecialchars($result['firstName'] . ' ' . $result['lastName']); ?>
                        <br>
                        <del>
                            <?php
                            if ($result['subFirstName'] && $result['subLastName']) {
                                echo htmlspecialchars($result['subFirstName'] . ' ' . $result['subLastName']);
                            }
                            ?>
                        </del>
                    </td>
                    <td><?= htmlspecialchars($result['teamName']) ?></td>
                    <td><?= htmlspecialchars($result['Score']); ?>
                        <sup><?php if ($result['bonus']) { echo("+".$result['bonus']); } ?></sup>
                        <?php if ($result['Status']) { echo htmlspecialchars("(".$result['Status'].")"); } ?>
                    </td>
                    <td><?= round($eloData['win_chance'] * 100, 2) ?>%</td>
                    <td><?= round($eloData['projected_points'], 2);

                        $projected_points = round($eloData['projected_points'], 2); // Rounding the value

                        $sql = "UPDATE heatinformation
        SET projectedPoints = :projected_points,
            winChance=:win_Chance,
            preRaceElo=:preElo,
            postRaceElo=:postElo,
            PPO=:pointsaboveProjection,
            PPA=:pointsaboveAverage
        WHERE raceID = :race_id";

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':projected_points' => round($eloData['projected_points'], 2),
                            ':win_Chance' => round($eloData['win_chance'] * 100, 2),
                            ':preElo' => round($elochangestat[$playerID]['oldelo'], 2),
                            ':postElo' => round($elochangestat[$playerID]['newelo'], 2),
                            ':pointsaboveProjection' =>round($ppo, 2),
                            ':pointsaboveAverage' =>round($ppa , 2),
                            ':race_id' => $result['raceID']

                        ]);




                    ?> (<?= round($ppo, 2); ?>)
                        (<?= round($ppa, 2); ?>)
                        <?php //echo "(New".$elochangestat[$playerID]['newelo'].")(Old". $elochangestat[$playerID]['oldelo'].")";
                        //echo "<br>";
                        ?>
                    </td>
                </tr>
            <?php endforeach;

            unset($result);
            ?>
            </tbody>
        </table>
        <!-- Team score table -->
        <table>
            <tr>
                <th><?= htmlspecialchars($match_details['homeTeamName']); ?></th>
                <th><?= htmlspecialchars($match_details['awayTeamName']); ?></th>
            </tr>
            <tr>
                <td>
                    <?php
                    echo "<strong>" . round($homeheatscore, 2) . "</strong> (" . round($homeprojectedscore, 2) . ")";
                    ?>
                </td>
                <td>
                    <?php
                    echo "<strong>" . round($awayheatscore, 2) . "</strong> (" . round($awayprojectedscore, 2) . ")";
                    ?>
                </td>
            </tr>
        </table>
    </div>
<?php endforeach;
unset($results);

// Assuming that $yearnum (2024) and $weeknum are already defined and represent the current year and week number
foreach ($updatedEloRatings as $playerID => $finalElo) {
    // Use backticks to safely reference the table and column names
    $tableName = "eloarchive" . $yearnum; // Table name based on the year (e.g., eloarchive2024)
    $columnName = $weeknum + 1; // The next week column (weeknum + 1)

    // Prepare the query to update the Elo rating for the player in the next week's column
    $updateQuery = $pdo->prepare(
        "UPDATE `$tableName`
         SET `$columnName` = :finalElo
         WHERE playerID = :playerID"
    );

    // Execute the update query
    $updateQuery->execute([
        'finalElo' => $finalElo,
        'playerID' => $playerID
    ]);
}

?>



<?php
include 'matchPlayerPPOtable.php';
//include 'matchTeamScoreBreakdownTable.php';
?>







