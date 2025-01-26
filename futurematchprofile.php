<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';


try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}
ob_start();

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
array_unshift($roster, null); // Shift array to start from index 1

// Loop over combined roster to fetch player information
foreach ($roster as $slot => $player_id) {
    $player_stmt = $pdo->prepare("SELECT FirstName, lastName, Elo, teamID FROM player WHERE PlayerID = ?");
    $player_stmt->execute([$player_id]);
    $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
}
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


    // Initialize cumulative points for teams
    $cumulativeTeamPoints = [];  // Initialize empty array for all teams
    $cumulativeSingleTeamPoints=[];
    $playerPPOData = [];

// Fetch team names for display (using teamIDs dynamically)
    $teamNames = [];
    $team_stmt = $pdo->prepare("SELECT teamID, teamName FROM team WHERE teamID IN (?, ?) ORDER BY teamName DESC");
    $team_stmt->execute([$awayteamID, $hometeamID]);
    while ($team = $team_stmt->fetch(PDO::FETCH_ASSOC)) {
        $teamNames[$team['teamID']] = $team['teamName'];
    }

    uasort($teamNames, function ($a, $b) {
        return strcasecmp($a, $b); // Case-insensitive alphabetical comparison
    });

// Loop through each heat and run the Monte Carlo simulation
    include 'winprobabiltycalculator.php';  // Include the simulation function

    $updatedMatchNumberMap = false;

    foreach ($matchNumberMap as $heat => &$slots) {
        echo "<h2>Heat $heat:</h2>";
        echo "<table border='1' style='background-color: #fff;'>";
        echo "<tr><th>Slot</th><th>Player</th><th>Projected Points</th><th>Win Chance</th><th>2nd Place Probability</th><th>3rd Place Probability</th><th>4th Place Probability</th><th>Simulated Points</th></tr>";

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
        $single_results = runFutureSingleMonteCarloSimulation($playerIDs, $eloRatings, $playerteamIDs, $hometeamID, 1, 32);


        // Calculate total points for each team and update cumulative points
        foreach ($slots as $slot => $playerID) {

            // Check if playerID exists in results
            if (!isset($results[$playerID])) {
                echo "Player ID $playerID not found in results!<br>";
                continue;  // Skip this iteration if the player ID is not found in results
            }

            // Now you can safely access $results[$playerID]
            $playerResults = $results[$playerID];
            $singlePlayerResults = $single_results[$playerID];

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
            $teamSinglePoints[$teamID] += $singlePlayerResults['projected_points'];

            // Check if the player has 3 projected points
            $goldStyle = ($singlePlayerResults['projected_points'] == 3) ? "background: gold;" : "";

            echo "<tr style='$goldStyle'>";
            echo "<td>$slot</td>";
            echo "<td>" . $player['FirstName'] . " " . $player['lastName'] . "</td>";
            echo "<td>" . round($playerResults['projected_points'], 2) . "</td>";
            echo "<td>" . round($playerResults['win_chance'] * 100, 2) . "%</td>";
            echo "<td>" . round($playerResults['finishing_probs'][1] * 100, 2) . "%</td>";
            echo "<td>" . round($playerResults['finishing_probs'][2] * 100, 2) . "%</td>";
            echo "<td>" . round($playerResults['finishing_probs'][3] * 100, 2) . "%</td>";

// For the points column, keep the conditional formatting
            if ($singlePlayerResults['projected_points'] == 3) {
                echo "<td style='background: gold'>" . round($singlePlayerResults['projected_points'], 2) . "</td>";

            } else {
                echo "<td>" . round($singlePlayerResults['projected_points'], 2) . "</td>";
            }

            echo "</tr>";

            // Initialize player data if not already set
            if (!isset($playerPPOData[$playerID])) {
                $playerPPOData[$playerID] = [
                    'playerID' => $player['PlayerID'],
                    'firstName' => $player['FirstName'],
                    'lastName' => $player['lastName'],
                    'teamName' => $player['teamName'],
                    'teamID' => $teamID,
                    'Score' => 0,
                    'Bonus' => 0,
                    'projected_points' => 0,
                    'ppo' => 0,
                    'ppa' => 0,
                    'pointBreakdown' => [], // Initialize an empty array ONCE
                ];
            }

            $playerPPOData[$playerID]['projected_points'] += $playerResults['projected_points'];
            $playerPPOData[$playerID]['Score'] += $singlePlayerResults['projected_points'];
            $playerPPOData[$playerID]['ppo'] += $singlePlayerResults['projected_points'] - $playerResults['projected_points'];
            $ppoAdjustment = $playerPPOData[$playerID]['ppo'] ?? 0;
            $playerPPOData[$playerID]['pointBreakdown'][] = (string)$singlePlayerResults['projected_points'];


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
        $teams = [];
        foreach ($teamNames as $teamID => $teamName) {
            $teams[] = [
                'teamID' => $teamID,
                'teamName' => $teamName,
                'cumulativeSingleTeamPoints' => $cumulativeSingleTeamPoints[$teamID] ?? 0
            ];
        }

// Sort teams by cumulativeSingleTeamPoints in descending order
        usort($teams, function ($a, $b) {
            return $b['cumulativeSingleTeamPoints'] <=> $a['cumulativeSingleTeamPoints'];
        });

// Display the table headers
        echo "<table border='1' style='background-color: #fff;'>";
        echo "<tr><th>Projected Points</th><th>Total Projected Points</th><th>Team</th><th>Simulated Points</th><th>Total Simulated Points</th></tr>";

// Loop through the sorted teams and display the data
// Loop through the sorted teams and display the data
        foreach ($teams as $team) {
            $teamID = $team['teamID'];
            $teamName = $team['teamName'];

            $currentCumulativePoints = round(($cumulativeTeamPoints[$teamID] ?? 0) + ($teamPoints[$teamID] ?? 0), 2);
            $simulatedHeatPoints = round($teamSinglePoints[$teamID] ?? 0, 2);
            $simulatedCumulativePoints = round(($cumulativeSingleTeamPoints[$teamID] ?? 0) + $simulatedHeatPoints, 2);

            echo "<tr>       
        <td>" . round($teamPoints[$teamID] ?? 0, 2) . "</td>
        <td>" . $currentCumulativePoints . "</td>
        <td>" . $teamName . "</td>
        <td>" . $simulatedHeatPoints . "</td>
        <td>" . $simulatedCumulativePoints . "</td>
      </tr>";
        }

        echo "</table>";

// Update cumulative team points for future heats
        foreach ($teamPoints as $teamID => $teamPointsValue) {
            $cumulativeTeamPoints[$teamID] = ($cumulativeTeamPoints[$teamID] ?? 0) + $teamPointsValue;
        }
        foreach ($teamSinglePoints as $teamID => $teamSinglePointsValue) {
            $cumulativeSingleTeamPoints[$teamID] = ($cumulativeSingleTeamPoints[$teamID] ?? 0) + $teamSinglePointsValue;
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
        body {
            font-family: Arial, sans-serif;
            margin: 0;
        }

        .match-header {
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.5em; /* Adjust font size */
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
            max-width: 100%; /* Ensure scoreboard fits within the screen */
            box-sizing: border-box; /* Include padding in width calculation */
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Align items at the top */
            padding: 10px;
            flex-wrap: wrap; /* Allow wrapping for small screens */
            gap: 10px; /* Add space between items when wrapped */
        }

        .team {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            min-width: 120px; /* Minimum width to avoid overly narrow columns */
        }

        .team-label {
            font-size: 1.3em; /* Slightly smaller for mobile */
            font-weight: bold;
            color: #444;
            margin-bottom: 5px;
            text-align: center;
        }

        .team-name {
            font-size: 1.1em; /* Adjust font size */
            font-weight: bold;
            margin-bottom: 5px;
            text-align: center;
            word-wrap: break-word;
            overflow: hidden;
            line-height: 1.4em; /* Consistent line spacing */
            max-height: 2.8em; /* Reserve space for up to two lines of text */
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0 10px; /* Add padding for better readability */
        }

        .score {
            font-size: 1.5em; /* Adjust for smaller screens */
            font-weight: bold;
            color: #0056b3;
            margin-top: 5px;
            min-height: 1.8em; /* Ensure consistent height */
        }

        .vs {
            font-size: 1em; /* Reduce size */
            color: #666;
            margin: 0 10px; /* Adjust spacing */
        }

        .details {
            margin-top: 15px;
            display: flex;
            flex-direction: column; /* Stack details vertically for small screens */
            gap: 10px; /* Add space between detail sections */
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .info {
            font-size: 0.9em; /* Reduce font size slightly */
            text-align: center; /* Center-align details */
        }

        .label {
            color: #666;
        }

        .value {
            font-weight: bold;
            color: #333;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .header {
                flex-direction: column; /* Stack teams vertically */
                align-items: center;
            }

            .team {
                min-width: unset; /* Allow team sections to shrink naturally */
                margin-bottom: 10px; /* Add spacing between teams */
            }

            .team-label, .team-name, .score {
                font-size: 1em; /* Reduce font sizes for better fit */
            }

            .vs {
                font-size: 0.9em; /* Smaller text for "vs" */
            }

            .details {
                gap: 8px; /* Slightly reduce gap */
            }
        }

        @media (max-width: 480px) {
            .scoreboard {
                padding: 10px; /* Compact padding */
                width: 100%; /* Fit scoreboard within narrow screens */
            }

            .team-label, .team-name, .score, .vs {
                font-size: 0.8em; /* Further reduce text sizes for small screens */
            }

            .details {
                gap: 5px; /* Compact gap */
            }

            .info {
                font-size: 0.8em; /* Reduce info text size */
            }
        }


    </style>
</head>




<?php
//include 'totalscorepiechart.php';
//include 'matchTeamScoreBreakdownTable.php';
//include 'winprobabiltycalculator.php';
//include 'elochangecalculator.php';
?>
<br><br><br><br>











