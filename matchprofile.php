<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';


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
    $matchid = $match_details['matchID'];

    $yearnum = $match_details['yearnum'];
    $weeknum = $match_details['weeknum'];
    $elo_table = "eloarchive" . $yearnum;
    $hometeamID = $match_details['homeTeamID'];
    $awayteamID = $match_details['awayTeamID'];


// Fetch race results grouped by heat with Elo ratings
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
    rr.winChance,
    rr.projectedPoints,
    rr.PreRaceElo,
    rr.PostRaceElo,
    rr.PPO,
    rr.PPA,
    t.teamName AS teamName,
    rr.substitutionID,
    s.firstName AS subFirstName,
    s.lastName AS subLastName,
    IFNULL(NULLIF(e.$weeknum, 0), p.Elo) AS eloRating
FROM heatinformation rr
LEFT JOIN player p ON rr.playerID = p.playerID
LEFT JOIN team t ON rr.currentplayerteamID = t.teamID
LEFT JOIN player s ON rr.substitutionID = s.playerID
LEFT JOIN $elo_table e ON p.playerID = e.playerID
WHERE rr.matchID = :match_id
ORDER BY rr.heatNumber, rr.startingpositionID
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

foreach ($results_by_heat as $heat_number => $results):
    $homeprojectedscore = $awayprojectedscore = $homeheatscore = $awayheatscore = 0;
foreach ($results as $result):
$playerID = $result['playerID'];

// Initialize player data if not already set
if (!isset($playerPPOData[$playerID])) {
    $playerPPOData[$playerID] = [
         'playerMatchNum'=>0,
        'playerID' => $result['playerID'],
        'firstName' => $result['firstName'],
        'lastName' => $result['lastName'],
        'teamName' => $result['teamName'],
        'Score' => 0,
        'Bonus' => 0,
        'projected_points' => 0,
        'ppo' => 0,
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
                'playerMatchNum'=>0,
                'playerID' => $subPlayerID,
                'firstName' => $result['subFirstName'],
                'lastName' => $result['subLastName'],
                'teamName' => $result['teamName'],
                'Score' => 0,
                'Bonus' => 0,
                'pointBreakdown' => [], // Initialize an empty array ONCE
            ];
        }
        // Update the substitute's point breakdown
        $playerPPOData[$subPlayerID]['pointBreakdown'][] = '- ';
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

// Query and logic for team scoring (home/away)
$getteamQuery = $pdo->prepare("SELECT currentplayerteamID FROM heatinformation WHERE playerID = :playerID");
$getteamQuery->execute(['playerID' => $playerID]);
$getteam = $getteamQuery->fetchColumn();

    $matchNumberMap = [
        1 => [ // Heat 1
            1 => ['home' => 9, 'away' => 1],
            2 => ['home' => 9, 'away' => 1],
            3 => ['home' => 11, 'away' => 3],
            4 => ['home' => 11, 'away' => 3],
        ],
        2 => [ // Heat 2
            1 => ['home' => 15, 'away' => 6],
            2 => ['home' => 15, 'away' => 6],
            3 => ['home' => 14, 'away' => 7],
            4 => ['home' => 14, 'away' => 7],
        ],
        3 => [ // Heat 3
            1 => ['home' => 12, 'away' => 5],
            2 => ['home' => 12, 'away' => 5],
            3 => ['home' => 13, 'away' => 2],
            4 => ['home' => 13, 'away' => 2],
        ],
        4 => [ // Heat 4
            1 => ['home' => 14, 'away' => 4],
            2 => ['home' => 14, 'away' => 4],
            3 => ['home' => 10, 'away' => 6],
            4 => ['home' => 10, 'away' => 6],
        ],
    ];

    $teamType = ($hometeamID == $getteam) ? 'home' : (($awayteamID == $getteam) ? 'away' : null);

    if ($teamType && isset($matchNumberMap[$result['heatNumber']][$result['startingpositionID']])) {
        $playerPPOData[$playerID]['playerMatchNum'] = $matchNumberMap[$result['heatNumber']][$result['startingpositionID']][$teamType];
    }
    if ($hometeamID == $getteam && !$playerPPOData[$playerID]['playerMatchNum']) {
        $playerPPOData[$playerID]['playerMatchNum'] = 16;
    }
    if ($awayteamID == $getteam && !$playerPPOData[$playerID]['playerMatchNum']) {
        $playerPPOData[$playerID]['playerMatchNum'] = 8;
    }




endforeach;
endforeach;


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Match Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        h1, h2 {
            background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent black background */
            color: white; /* Text color */
            padding: 15px 30px; /* Adds more space for headers */
            border-radius: 5px; /* Optional: rounds the corners for a softer look */
            font-size: inherit; /* Inherit the font size from the default styles for consistency */
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7); /* Adds a shadow behind the text for better contrast */
            text-align: center;
        }

        /* Optional: Adjust h1 and h2 sizes for emphasis */
        h1 {
            font-size: 36px; /* Larger font for h1 */
        }

        h2 {
            font-size: 28px; /* Slightly smaller for h2 */
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
            background-color: #fff;
        }

        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        table th {
            background-color: #f4f4f4;
        }

        .match-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .scoreboard {
            display: inline-block;
            width: auto;
            background: #f4f4f4;
            color: #333;
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 15px;
            text-align: center;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Align items at the top */
            padding: 10px;
        }

        .team {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }

        .team-label {
            font-size: 1.5em;
            font-weight: bold;
            color: #444;
            margin-bottom: 5px;
        }

        .team-name {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 5px;
            text-align: center;
            word-wrap: break-word;
            overflow: hidden;
            line-height: 1.4em; /* Consistent line spacing */
            height: 2.8em; /* Reserve space for up to two lines of text */
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .score {
            font-size: 1.8em;
            font-weight: bold;
            color: #0056b3;
            margin-top: 5px;
            min-height: 1.8em; /* Ensure consistent height for the score block */
        }



        .vs {
            font-size: 1.2em;
            color: #666;
            margin: 0 15px;
        }

        .details {
            margin-top: 15px;
            display: flex;
            justify-content: space-around;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .info {
            font-size: 1em;
        }

        .label {
            color: #666;
        }

        .value {
            font-weight: bold;
            color: #333;
        }



    </style>
</head>
<body>

<h1>Match Details</h1>


<div class="match-header">
    <div class="scoreboard">
        <div class="header">
            <!-- Home Team -->
            <div class="team">
                <div class="team-label">H</div>
                <div class="team-name"><?= htmlspecialchars($match_details['homeTeamName']); ?></div>
                <div class="score" id="home-score">
                    <?php
                    $hometeamID = $match_details['homeTeamID'];
                    $stmt = $pdo->prepare("SELECT SUM(score) FROM heatinformation WHERE currentplayerteamID = :teamID AND matchID = $matchid");
                    $stmt->execute(['teamID' => $hometeamID]);
                    $totalhomeScore = $stmt->fetchColumn();
                    echo htmlspecialchars($totalhomeScore);
                    ?>
                </div>
            </div>

            <!-- VS Separator -->
            <div class="vs">VS</div>

            <!-- Away Team -->
            <div class="team">
                <div class="team-label">A</div>
                <div class="team-name"><?= htmlspecialchars($match_details['awayTeamName']); ?></div>
                <div class="score" id="away-score">
                    <?php
                    $awayteamID = $match_details['awayTeamID'];
                    $stmt = $pdo->prepare("SELECT SUM(score) FROM heatinformation WHERE currentplayerteamID = :teamID AND matchID =$matchid");
                    $stmt->execute(['teamID' => $awayteamID]);
                    $totalawayScore = $stmt->fetchColumn();
                    echo htmlspecialchars($totalawayScore);
                    ?>
                </div>
            </div>
        </div>

        <!-- Match Details -->
        <div class="details">
            <div class="info">
                <span class="label">Kolejka:</span>
                <span class="value"><?= htmlspecialchars($match_details['weeknum']); ?></span>
            </div>
            <div class="info">
                <span class="label">Gospodarze:</span>
                <span class="value"><?= htmlspecialchars($match_details['homeTeamName']); ?></span>
            </div>
        </div>
    </div>
</div>




<?php
//include 'totalscorepiechart.php';
include 'matchTeamScoreBreakdownTable.php';
//include 'winprobabiltycalculator.php';
//include 'elochangecalculator.php';
?>

<h2>Race Results by Heat</h2>
<?php
$playerPPOData = [];
$updatedEloRatings = [];  // Maintain Elo ratings for all players across heats

foreach ($results_by_heat as $heat_number => $results):

    // Calculate player stats and Elo changes for this heat
    //$raceChances = calculatePlayerStats($playerIDs, $eloRatings, $hometeamID, $awayteamID);
    //$elochangestat = elo_rating_4way($playerIDs, $eloRatings, $scores);

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



                // Calculate the PPO (Projected Points) based on the current Elo data
                $ppo = $result['PPO'];

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
                        'ppa' =>0,
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
                                'ppa' =>0,
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
                $playerPPOData[$playerID]['projected_points'] += $result['projectedPoints'];
                $playerPPOData[$playerID]['ppo'] += $ppo;
                $playerPPOData[$playerID]['ppa'] += $result['PPA'];

                // Query and logic for team scoring (home/away)
                $getteamQuery = $pdo->prepare("SELECT currentplayerteamID FROM heatinformation WHERE playerID = :playerID");
                $getteamQuery->execute(['playerID' => $playerID]);
                $getteam = $getteamQuery->fetchColumn();



                if ($getteam == $hometeamID) {
                    $homeprojectedscore += $result['projectedPoints'];
                    $homecurrentprojectedscore += $result['projectedPoints'];
                    $homeheatscore += $result['Score'];
                    $homematchcurrentscore+=$result['Score'];
                } elseif ($getteam == $awayteamID) {
                    $awayprojectedscore += $result['projectedPoints'];
                    $awaycurrentprojectedscore += $result['projectedPoints'];
                    $awayheatscore += $result['Score'];
                    $awaymatchcurrentscore+=$result['Score'];
                }
                ?>
                <tr>
                    <td>
                        <?php
                        if ($result['startingpositionID'] == 1) {
                            if($getteam==$hometeamID)
                            {
                                echo "🟥";
                            }else{
                                echo "⬜";
                            }

                        } elseif ($result['startingpositionID'] == 2) {
                            if($getteam==$hometeamID)
                            {
                                echo "🟥";
                            }else{
                                echo "⬜";
                            }
                        } elseif ($result['startingpositionID'] == 3) {
                            if($getteam==$hometeamID)
                            {
                                echo "🟦";
                            }else{
                                echo "🟨";
                            }
                        } elseif ($result['startingpositionID'] == 4) {
                            if($getteam==$hometeamID)
                            {
                                echo "🟦";
                            }else{
                                echo "🟨";
                            }
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
                    <td><?= round($result['winChance'], 2) ?>%</td>
                    <td><?= round($result['projectedPoints'], 2);

                        $projected_points = round($result['projectedPoints'], 2); // Rounding the value




                    ?> (<?= round($ppo, 2); ?>)
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
                    echo " - ";
                    echo "<strong>" . round($homematchcurrentscore, 2) . "</strong> (" . round($homecurrentprojectedscore, 2) . ")";
                    ?>
                </td>
                <td>
                    <?php
                    echo "<strong>" . round($awayheatscore, 2) . "</strong> (" . round($awayprojectedscore, 2) . ")";
                    echo " - ";
                    echo "<strong>" . round($awaymatchcurrentscore, 2) . "</strong> (" . round($awaycurrentprojectedscore, 2) . ")";
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
}

?>



<?php
include 'matchPlayerPPOtable.php';

?>







