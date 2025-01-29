<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';
include 'winprobabiltycalculator.php';
$cumulativeTeamPoints = [];  // Initialize empty array for all teams
$teamNames = [];
$playerPPOData = [];
ob_start();

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
    SELECT m.matchID, m.homeTeamID, m.awayTeamID, ht.teamName AS homeTeamName, at.teamName AS awayTeamName, m.weeknum, m.yearnum,m.heatsInMatch
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
    $numheats=$match_details['heatsInMatch'];


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

    $numheatinData = "SELECT MAX(rr.heatNumber) as heatsEntered 
                  FROM heatinformation rr
                  LEFT JOIN player p ON rr.playerID = p.playerID
                  LEFT JOIN team t ON rr.currentplayerteamID = t.teamID
                  WHERE rr.matchID = :match_id";

    $heatnum = $pdo->prepare($numheatinData);
    $heatnum->execute(['match_id' => $match_id]);

// Fetch a single row
    $numheat = $heatnum->fetch(PDO::FETCH_ASSOC);

// Get the specific value
    $heatsEntered = $numheat['heatsEntered'] ?? null;


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
                'teamID' => $teamID ?? null,
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
                        'teamID' => $teamID ?? null,
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
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
    <style>

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


<?php
//include 'totalscorepiechart.php';
//include 'matchTeamScoreBreakdownTable.php';
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



                $team_stmt = $pdo->prepare("SELECT teamID, teamName FROM team WHERE teamID IN (?, ?) ORDER BY teamName DESC");
                $team_stmt->execute([$awayteamID, $hometeamID]);
                while ($team = $team_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $teamNames[$team['teamID']] = $team['teamName'];
                }

                $teams = [];
                foreach ($teamNames as $teamID => $teamName) {
                    $teams[] = [
                        'teamID' => $teamID,
                        'teamName' => $teamName,
                        'cumulativeTeamPoints' => $cumulativeTeamPoints[$teamID] ?? 0
                    ];
                }



                if ($getteam == $hometeamID) {
                    $homeprojectedscore += $result['projectedPoints'];
                    $homecurrentprojectedscore += $result['projectedPoints'];
                    $homeheatscore += $result['Score'];
                    $homematchcurrentscore+=$result['Score'];

                    $cumulativeTeamPoints[$hometeamID] = $cumulativeTeamPoints[$hometeamID] ?? 0;
                    $cumulativeTeamPoints[$hometeamID] += $result['Score'];



                } elseif ($getteam == $awayteamID) {
                    $awayprojectedscore += $result['projectedPoints'];
                    $awaycurrentprojectedscore += $result['projectedPoints'];
                    $awayheatscore += $result['Score'];
                    $awaymatchcurrentscore+=$result['Score'];
                    $cumulativeTeamPoints[$awayteamID] = $cumulativeTeamPoints[$awayteamID] ?? 0;
                    $cumulativeTeamPoints[$awayteamID] += $result['Score'];


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

$roster_team_1 = [];
$roster_team_2 = [];

// Query for Team 1's roster
$sql_team1 = "SELECT id1 as `1`, id2 as `2`, id3 as `3`, id4 as `4`, id5 as `5`, id6 as `6`, id7 as `7`, id8 as `8` FROM futurematchroster WHERE teamID = $awayteamID";
$result_team1 = $conn->query($sql_team1);

if ($result_team1->num_rows > 0) {
    $roster_team_1 = $result_team1->fetch_assoc();
}

// Query for Team 2's roster
$sql_team2 = "SELECT id1 as `9`, id2 as `10`, id3 as `11`, id4 as `12`, id5 as `13`, id6 as `14`, id7 as `15`, id8 as `16` FROM futurematchroster WHERE teamID = $hometeamID";
$result_team2 = $conn->query($sql_team2);

if ($result_team2->num_rows > 0) {
    $roster_team_2 = $result_team2->fetch_assoc();
}

// Combine both teams' rosters
$roster = array_merge($roster_team_1, $roster_team_2);

// Reindex the array to start from 1
$roster = array_values($roster);
array_unshift($roster, null);

$matchNumberMap = [
    1 => [ // Heat 1
        1 => $roster[1], // Slot 1
        2 => $roster[9], // Slot 2
        3 => $roster[3], // Slot 3
        4 => $roster[11], // Slot 4
    ],
    2 => [ // Heat 2
        1 => $roster[15],
        2 => $roster[6],
        3 => $roster[14],
        4 => $roster[7],
    ],
    3 => [ // Heat 3
        1 => $roster[5],
        2 => $roster[12],
        3 => $roster[2],
        4 => $roster[13],
    ],
    4 => [ // Heat 4
        1 => $roster[14],
        2 => $roster[4],
        3 => $roster[10],
        4 => $roster[6],
    ],
    5 => [ // Heat 5
        1 => $roster[11],
        2 => $roster[3],
        3 => $roster[12],
        4 => $roster[4],
    ],
    6 => [ // Heat 6
        1 => $roster[13],
        2 => $roster[2],
        3 => $roster[15],
        4 => $roster[1],
    ],
    7 => [ // Heat 7
        1 => $roster[7],
        2 => $roster[10],
        3 => $roster[5],
        4 => $roster[9],
    ],
    8 => [ // Heat 7
        1 => $roster[3],
        2 => $roster[13],
        3 => $roster[4],
        4 => $roster[14],
    ],
    9 => [ // Heat 7
        1 => $roster[9],
        2 => $roster[1],
        3 => $roster[10],
        4 => $roster[2],
    ],
    10 => [ // Heat 7
        1 => $roster[6],
        2 => $roster[11],
        3 => $roster[5],
        4 => $roster[12],
    ],
    11 => [ // Heat 7
        1 => $roster[12],
        2 => $roster[4],
        3 => $roster[9],
        4 => $roster[1],
    ],
    12 => [ // Heat 7
        1 => $roster[2],
        2 => $roster[15],
        3 => $roster[7],
        4 => $roster[11],
    ],
    13 => [ // Heat 13
        1 => $roster[10],
        2 => $roster[5],
        3 => $roster[13],
        4 => $roster[3],
    ],


];



// Fetch match details
        $match_details_query = "
    SELECT m.matchID, m.homeTeamID, m.awayTeamID, ht.teamName AS homeTeamName, at.teamName AS awayTeamName, m.weeknum, m.yearnum
    FROM matches m
    LEFT JOIN team ht ON m.homeTeamID = ht.teamID
    LEFT JOIN team at ON m.awayTeamID = at.teamID
    WHERE m.matchID = :match_id
";

         // Shift array to start from index 1

// Loop over combined roster to fetch player information
        foreach ($roster as $slot => $player_id) {
            $player_stmt = $pdo->prepare("SELECT FirstName, lastName, Elo, teamID FROM player WHERE PlayerID = ?");
            $player_stmt->execute([$player_id]);
            $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
        }



// Initialize cumulative points for teams

        $cumulativeSingleTeamPoints = [];


// Fetch team names for display (using teamIDs dynamically)



        uasort($teamNames, function ($a, $b) {
            return strcasecmp($a, $b); // Case-insensitive alphabetical comparison
        });

// Loop through each heat and run the Monte Carlo simulation
         // Include the simulation function

        $updatedMatchNumberMap = false;

        foreach ($matchNumberMap as $heat => &$slots) {
            if ($heat <= $heatsEntered && $heat <= $numheats) {

                continue;
            }
            echo "<h2>Heat $heat:</h2>";
            echo "<table border='1' style='background-color: #fff;'>";
            echo "<tr><th>Slot</th><th>Player</th><th>Projected Points</th><th>Win Chance</th><th>2nd Place Probability</th><th>3rd Place Probability</th><th>4th Place Probability</th></tr>";

            // Get Elo ratings for players in this heat
            $playerIDs = [];
            $eloRatings = [];
            $teamPoints = [];  // Initialize an empty array to hold team points for this heat
            $teamSinglePoints = [];
            $playerteamIDs = [];

            // Collect player IDs and Elo ratings, and reset team points
            foreach ($slots as $slot => $playerID) {


                // Dynamically construct the SQL query to check weeks back to 1
                $columns_to_check = [];
                for ($i = $weeknum; $i > 0; $i--) {
                    $columns_to_check[] = "NULLIF(`$i`, 0)"; // Use NULLIF to treat 0 as null
                }
                $columns_to_check = implode(", ", $columns_to_check);

                $elo_query = "
    SELECT COALESCE(
        " . $columns_to_check . ",
        (SELECT Elo FROM player WHERE PlayerID = ?)
    ) AS Elo 
    FROM $elo_table 
    WHERE PlayerID = ?
    LIMIT 1
";

                $elo_stmt = $pdo->prepare($elo_query);
                $elo_stmt->execute([$playerID, $playerID]);
                $elo_data = $elo_stmt->fetch(PDO::FETCH_ASSOC);

// Get teamID from the player table
                $player_stmt = $pdo->prepare("SELECT teamID FROM player WHERE PlayerID = ?");
                $player_stmt->execute([$playerID]);
                $player = $player_stmt->fetch(PDO::FETCH_ASSOC);

// Use the fetched Elo and teamID
                $eloRatings[] = $elo_data['Elo'];
                $playerIDs[] = $playerID;
                $teamID = $player['teamID'];
                array_push($playerteamIDs, $teamID);


                // Initialize team points for this team if not already initialized
                if (!isset($teamPoints[$teamID])) {
                    $teamPoints[$teamID] = 0;  // Set initial points to 0 for each team
                }
                if (!isset($teamSinglePoints[$teamID])) {
                    $teamSinglePoints[$teamID] = 0;  // Set initial points to 0 for each team
                }
            }


            // Run the Monte Carlo simulation for this heat (with 1000 iterations and K=32)
            $results = runFutureMonteCarloSimulation($playerIDs, $eloRatings, $playerteamIDs, $hometeamID, 1000, 32);
            //$single_results = runFutureSingleMonteCarloSimulation($playerIDs, $eloRatings, $playerteamIDs, $hometeamID, 1, 32);


            // Calculate total points for each team and update cumulative points
            foreach ($slots as $slot => $playerID) {

                // Check if playerID exists in results
                if (!isset($results[$playerID])) {
                    echo "Player ID $playerID not found in results!<br>";
                    continue;  // Skip this iteration if the player ID is not found in results
                }

                // Now you can safely access $results[$playerID]
                $playerResults = $results[$playerID];
                //$singlePlayerResults = $single_results[$playerID];

                $player_stmt = $pdo->prepare("
    SELECT p.PlayerID, p.FirstName, p.lastName, t.teamName 
    FROM player p
    JOIN team t ON p.teamID = t.teamID
    WHERE p.PlayerID = ?");
                $player_stmt->execute([$playerID]);
                $player = $player_stmt->fetch(PDO::FETCH_ASSOC);


                $team_stmt = $pdo->prepare("SELECT teamID FROM player WHERE PlayerID = ?");
                $team_stmt->execute([$playerID]);
                $team = $team_stmt->fetch(PDO::FETCH_ASSOC);
                $teamID = $team['teamID'];

                // Add the projected points for each player to their team's total
                $teamPoints[$teamID] += $playerResults['projected_points'];
                //$teamSinglePoints[$teamID] += $singlePlayerResults['projected_points'];

                // Check if the player has 3 projected points
                //$goldStyle = ($singlePlayerResults['projected_points'] == 3) ? "background: gold;" : "";

                //echo "<tr style='$goldStyle'>";
                echo "<td>$slot</td>";
                echo "<td>" . $player['FirstName'] . " " . $player['lastName'] . "</td>";
                echo "<td>" . round($playerResults['projected_points'], 2) . "</td>";
                echo "<td>" . round($playerResults['win_chance'] * 100, 2) . "%</td>";
                echo "<td>" . round($playerResults['finishing_probs'][1] * 100, 2) . "%</td>";
                echo "<td>" . round($playerResults['finishing_probs'][2] * 100, 2) . "%</td>";
                echo "<td>" . round($playerResults['finishing_probs'][3] * 100, 2) . "%</td>";

// For the points column, keep the conditional formatting


                echo "</tr>";

                // Initialize player data if not already set
                if (!isset($playerPPOData[$playerID])) {
                    $playerPPOData[$playerID] = [
                        'playerID' => $player['PlayerID'],
                        'firstName' => $player['FirstName'],
                        'lastName' => $player['lastName'],
                        'teamName' => $player['teamName'],
                        'teamID' => $teamID ?? null,
                        'Score' => 0,
                        'Bonus' => 0,
                        'projected_points' => 0,
                        'ppo' => 0,
                        'ppa' => 0,
                        'pointBreakdown' => [], // Initialize an empty array ONCE
                    ];
                }
                $playerPPOData[$playerID]['teamID']=$teamID;

                $playerPPOData[$playerID]['projected_points'] += $playerResults['projected_points'];
                $playerPPOData[$playerID]['Score'] += $playerResults['projected_points'];
                //$playerPPOData[$playerID]['ppo'] += $singlePlayerResults['projected_points'] - $playerResults['projected_points'];
                $ppoAdjustment = $playerPPOData[$playerID]['ppo'] ?? 0;
                $playerPPOData[$playerID]['pointBreakdown'][] = (double)$playerResults['projected_points'];


                // Now, update Heat 14 and Heat 15 outside the loop
                if ($heat == 13) {
                    // Organize players' scores by their teams
                    $teamRankings = [
                        $hometeamID => [],
                        $awayteamID => []
                    ];

                    // Populate team scores from the cumulative player performance
                    foreach ($playerPPOData as $pID => $data) {
                        $teamID = $data['teamID'];
                        if ($teamID == $hometeamID || $teamID == $awayteamID) {
                            $teamRankings[$teamID][$pID] = $data['projected_points'];
                        }
                    }

                    // Sort players within each team by score (descending order)
                    foreach ($teamRankings as &$teamPlayers) {
                        arsort($teamPlayers); // Sort by score descending
                    }

                    // Get players for Heat 14 and Heat 15 based on sorted rankings
                    $team1Players = array_keys($teamRankings[$hometeamID]);
                    $team2Players = array_keys($teamRankings[$awayteamID]);

                    // Assign slots for Heat 14 and Heat 15

                    $matchNumberMap[14] = [
                        1 => $team1Players[2], // Team 1, 3rd best
                        2 => $team2Players[2], // Team 2, 3rd best
                        3 => $team1Players[3], // Team 1, 4th best
                        4 => $team2Players[3], // Team 2, 4th best
                    ];

                    $matchNumberMap[15] = [
                        1 => $team2Players[0], // Team 2, best
                        2 => $team1Players[0], // Team 1, best
                        3 => $team2Players[1], // Team 2, 2nd best
                        4 => $team1Players[1], // Team 1, 2nd best

                    ];


                }


            }
            echo "</table>";


            echo "<h4>Projected Points and Simulation for Heat $heat:</h4>";
            // Create an array of team data including teamID, teamName, and cumulativeSingleTeamPoints



// Display the table headers
            echo "<table border='1' style='background-color: #fff;'>";
            echo "<tr><th>Team</th><th>Projected Points</th><th>Total Projected Points</th></tr>";

            $team_stmt = $pdo->prepare("SELECT teamID, teamName FROM team WHERE teamID IN (?, ?) ORDER BY teamName DESC");
            $team_stmt->execute([$awayteamID, $hometeamID]);
            while ($team = $team_stmt->fetch(PDO::FETCH_ASSOC)) {
                $teamNames[$team['teamID']] = $team['teamName'];
            }

            $teams = [];
            foreach ($teamNames as $teamID => $teamName) {
                $teams[] = [
                    'teamID' => $teamID,
                    'teamName' => $teamName,
                    'cumulativeTeamPoints' => $cumulativeTeamPoints[$teamID] ?? 0
                ];
            }

// Loop through the sorted teams and display the data
            foreach ($teams as $team) {
                $teamID = $team['teamID'];
                $teamName = $team['teamName'];

                $currentCumulativePoints = round(($cumulativeTeamPoints[$teamID] ?? 0) + ($teamPoints[$teamID] ?? 0), 2);
                $simulatedHeatPoints = round($teamSinglePoints[$teamID] ?? 0, 2);
                //$simulatedCumulativePoints = round(($cumulativeSingleTeamPoints[$teamID] ?? 0) + $simulatedHeatPoints, 2);

                echo "<tr>      
        <td>" . $teamName . "</td> 
        <td>" . round($teamPoints[$teamID] ?? 0, 2) . "</td>
        <td>" . $currentCumulativePoints . "</td>


      </tr>";
            }

            echo "</table>";

// Update cumulative team points for future heats
            foreach ($teamPoints as $teamID => $teamPointsValue) {
                $cumulativeTeamPoints[$teamID] = ($cumulativeTeamPoints[$teamID] ?? 0) + $teamPointsValue;
            }

        }

// Add SQL query to update the futurematches table
        $homeTeamScore = $cumulativeTeamPoints[$hometeamID] ?? 0;
        $awayTeamScore = $cumulativeTeamPoints[$awayteamID] ?? 0;

        $updatefuturematchScores = "
    INSERT INTO futurematches (matchID, hometeamID, awayteamID, homeTeamScore, awayTeamScore)
    VALUES (:matchID, :hometeamID, :awayteamID, :homeTeamScore, :awayTeamScore)
    ON DUPLICATE KEY UPDATE 
        homeTeamScore = :homeTeamScore, 
        awayTeamScore = :awayTeamScore
";

// Use PDO to prepare and execute the query securely
        $stmt = $pdo->prepare($updatefuturematchScores);
        $stmt->execute([
            ':matchID' => $match_id,
            ':hometeamID' => $hometeamID,
            ':awayteamID' => $awayteamID,
            ':homeTeamScore' => $homeTeamScore,
            ':awayTeamScore' => $awayTeamScore,
        ]);

// Include any additional scripts or cleanup
// include 'FutureMatchPlayerPPOtable.php';
$content = ob_get_clean();
include 'futurematchTeamScoreBreakdownTable.php';
echo $content;


//include 'matchPlayerPPOtable.php';

?>







