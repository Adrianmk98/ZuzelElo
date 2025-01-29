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

// Fetch teams from the database
$stmt = $pdo->query("SELECT teamID, teamName FROM team");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize selected team variables
$selected_team_1 = null;
$selected_team_2 = null;
$players_team_1 = [];
$players_team_2 = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if 'team_1' and 'team_2' are set
    if (isset($_POST['team_1']) && isset($_POST['team_2'])) {
        $selected_team_1 = $_POST['team_1'];
        $selected_team_2 = $_POST['team_2'];
        $hometeamID=$selected_team_1;
        $awayteamID=$selected_team_2;

        // Fetch players from Team 1
        $stmt = $pdo->prepare("SELECT PlayerID, FirstName, lastName,Elo FROM player WHERE teamID = ?");
        $stmt->execute([$selected_team_1]);
        $players_team_1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch players from Team 2
        $stmt->execute([$selected_team_2]);
        $players_team_2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Process the roster form submission
    if (isset($_POST['roster']) && !empty($_POST['roster'])) {
        $roster = $_POST['roster'];

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
        $team_stmt->execute([$selected_team_1, $selected_team_2]);
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
            echo "<tr><th>Slot</th><th>Player</th><th>Simulated Points</th><th>Projected Points</th><th>Win Chance</th><th>2nd Place Probability</th><th>3rd Place Probability</th><th>4th Place Probability</th></tr>";

            // Get Elo ratings for players in this heat
            $playerIDs = [];
            $eloRatings = [];
            $teamPoints = [];  // Initialize an empty array to hold team points for this heat
            $teamSinglePoints=[];
            $playerteamIDs=[];

            // Collect player IDs and Elo ratings, and reset team points
            foreach ($slots as $slot => $playerID) {




                $player_stmt = $pdo->prepare("SELECT Elo, teamID FROM player WHERE PlayerID = ?");
                $player_stmt->execute([$playerID]);
                $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
                $playerIDs[] = $playerID;
                $eloRatings[] = $player['Elo'];
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
            $results = runFutureMonteCarloSimulation($playerIDs, $eloRatings,$playerteamIDs,$selected_team_1, 1000, 32);
            $single_results = runFutureSingleMonteCarloSimulation($playerIDs, $eloRatings,$playerteamIDs,$selected_team_1, 1, 32);


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
                $teamSinglePoints[$teamID]+=$singlePlayerResults['projected_points'];

                // Check if the player has 3 projected points
                $goldStyle = ($singlePlayerResults['projected_points'] == 3) ? "background: gold;" : "";

                echo "<tr style='$goldStyle'>";
                echo "<td>$slot</td>";
                echo "<td>" . $player['FirstName'] . " " . $player['lastName'] . "</td>";
                if ($singlePlayerResults['projected_points'] == 3) {
                    echo "<td style='background: gold'>" . round($singlePlayerResults['projected_points'], 2) . "</td>";
                } else {
                    echo "<td>" . round($singlePlayerResults['projected_points'], 2) . "</td>";
                }
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
                        'teamID' => $teamID,
                        'Score' => 0,
                        'Bonus' => 0,
                        'projected_points' => 0,
                        'ppo' => 0,
                        'ppa'=>0,
                        'pointBreakdown' => [], // Initialize an empty array ONCE
                    ];
                }

                $playerPPOData[$playerID]['Score'] += $singlePlayerResults['projected_points'];
                $playerPPOData[$playerID]['ppo'] += $singlePlayerResults['projected_points']-$playerResults['projected_points'];
                $ppoAdjustment = $playerPPOData[$playerID]['ppo'] ?? 0;
                $playerPPOData[$playerID]['pointBreakdown'][] = (string)$singlePlayerResults['projected_points'];
                // Now, update Heat 14 and Heat 15 outside the loop
                if ($heat == 13) {
                    // Organize players' scores by their teams
                    $teamRankings = [
                        $selected_team_1 => [],
                        $selected_team_2 => []
                    ];

                    // Populate team scores from the cumulative player performance
                    foreach ($playerPPOData as $pID => $data) {
                        $teamID = $data['teamID'];
                        if ($teamID == $selected_team_1 || $teamID == $selected_team_2) {
                            $teamRankings[$teamID][$pID] = $data['Score'];
                        }
                    }

                    // Sort players within each team by score (descending order)
                    foreach ($teamRankings as &$teamPlayers) {
                        arsort($teamPlayers); // Sort by score descending
                    }

                    // Get players for Heat 14 and Heat 15 based on sorted rankings
                    $team1Players = array_keys($teamRankings[$selected_team_1]);
                    $team2Players = array_keys($teamRankings[$selected_team_2]);

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
            usort($teams, function($a, $b) {
                return $b['cumulativeSingleTeamPoints'] <=> $a['cumulativeSingleTeamPoints'];
            });

// Display the table headers
            echo "<table border='1'>";
            echo "<tr><th>Projected Points</th><th>Total Projected Points</th><th>Team</th><th>Simulated Points</th><th>Total Simulated Points</th></tr>";

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
            // Update cumulative team points for future heats
            foreach ($teamSinglePoints as $teamID => $teamSinglePointsValue) {
                $cumulativeSingleTeamPoints[$teamID] = ($cumulativeSingleTeamPoints[$teamID] ?? 0) + $teamSinglePointsValue;
            }
        }
        //include 'FutureMatchPlayerPPOtable.php';
        $content = ob_get_clean();
        include 'matchsimulatorScoreTable.php';
        echo $content;
?><?php
    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Roster</title>
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
    <link rel="stylesheet" href="includes/matchSimulatorStyle.css">
</head>
<body>
    <h1>Create Roster</h1>

    <form method="post">
        <div class="team-select">
            <label for="team_2">Gospodarz: </label>
            <select name="team_1" id="team_1" required>
                <option value="">--Select Team--</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= $team['teamID'] ?>" <?= ($selected_team_1 == $team['teamID']) ? 'selected' : '' ?>><?= $team['teamName'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="team-select">
            <label for="team_2">Gosc: </label>
            <select name="team_2" id="team_2" required>
                <option value="">--Select Team--</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= $team['teamID'] ?>" <?= ($selected_team_2 == $team['teamID']) ? 'selected' : '' ?>><?= $team['teamName'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="matchSim-button">Fetch Players</button>
    </form>

    <?php if (!empty($players_team_1) && !empty($players_team_2)): ?>
        <h2>Select Players for the Roster</h2>
        <form method="post">
            <input type="hidden" name="team_1" value="<?= $selected_team_1 ?>">
            <input type="hidden" name="team_2" value="<?= $selected_team_2 ?>">

            <?php
            $future_roster_team_1 = [];
            $future_roster_team_2 = [];

            // Query for Team 1's roster
            $sql_team1 = "SELECT id1, id2, id3, id4, id5, id6, id7, id8 FROM futurematchroster WHERE teamID = $selected_team_1";
            $result_team1 = $conn->query($sql_team1);

            if ($result_team1->num_rows > 0) {
            $future_roster_team_1 = $result_team1->fetch_assoc();
            }

            // Query for Team 2's roster
            $sql_team2 = "SELECT id1, id2, id3, id4, id5, id6, id7, id8 FROM futurematchroster WHERE teamID = $selected_team_2";
            $result_team2 = $conn->query($sql_team2);

            if ($result_team2->num_rows > 0) {
            $future_roster_team_2 = $result_team2->fetch_assoc();
            }

            // Fetch players for the dropdown lists
            function fetch_players($teamID, $conn) {
            $sql = "SELECT PlayerID, FirstName, LastName FROM player WHERE teamID = $teamID ORDER BY Elo DESC";
            $result = $conn->query($sql);
            $players = [];
            if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
            $players[] = $row;
            }
            }
            return $players;
            }

            $players_team_1 = fetch_players($selected_team_1, $conn);
            $players_team_2 = fetch_players($selected_team_2, $conn);

            $conn->close();
            ?>

            <div class="roster-form">
                <button type="submit" class="matchSim-button">Simulate Match</button>
                <h3>Home Team</h3>
                <div class="slot-container">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <div class="slot">
                            <label for="slot<?= $i ?>">Slot <?= $i ?>:</label>
                            <select name="roster[<?= $i ?>]" id="slot<?= $i ?>" required>
                                <option value="">--Select Player--</option>
                                <?php foreach ($players_team_1 as $player): ?>
                                    <option value="<?= $player['PlayerID'] ?>"
                                        <?= isset($future_roster_team_1["id$i"]) && $future_roster_team_1["id$i"] == $player['PlayerID'] ? 'selected' : '' ?>>
                                        <?= $player['FirstName'] . " " . $player['LastName'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endfor; ?>
                </div>

                <h3>Away Team</h3>
                <div class="slot-container">
                    <?php for ($i = 9; $i <= 16; $i++): ?>
                        <div class="slot">
                            <label for="slot<?= $i ?>">Slot <?= $i ?>:</label>
                            <select name="roster[<?= $i ?>]" id="slot<?= $i ?>" required>
                                <option value="">--Select Player--</option>
                                <?php foreach ($players_team_2 as $player): ?>
                                    <option value="<?= $player['PlayerID'] ?>"
                                        <?= isset($future_roster_team_2["id" . ($i - 8)]) && $future_roster_team_2["id" . ($i - 8)] == $player['PlayerID'] ? 'selected' : '' ?>>
                                        <?= $player['FirstName'] . " " . $player['LastName'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>


        </form>
    <?php endif; ?>
</body>
</html>

