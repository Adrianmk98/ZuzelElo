<?php
// Database connection
$host = 'localhost';  // Database host
$dbname = 'zuzelelo'; // Database name
$username = 'root';    // Database username
$password = '';        // Database password


try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

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

       // echo "<h3>Roster Created:</h3>";
        //echo "<table border='1'>";
       // echo "<tr><th>Slot</th><th>Player</th><th>Elo</th></tr>";

        foreach ($roster as $slot => $player_id) {
            $player_stmt = $pdo->prepare("SELECT FirstName, lastName, Elo, teamID FROM player WHERE PlayerID = ?");
            $player_stmt->execute([$player_id]);
            $player = $player_stmt->fetch(PDO::FETCH_ASSOC);
            //echo "<tr><td>$slot</td><td>" . $player['FirstName'] . " " . $player['lastName'] . "</td><td>" . $player['Elo'] . "</td></tr>";
        }
        //echo "</table>";

        // Sort players by Elo score in descending order
     //   usort($players_team_1, function ($a, $b) {
        //    return $b['Elo'] - $a['Elo']; // Compare Elo scores in descending order
        //});
//
      //  usort($players_team_2, function ($a, $b) {
        //    return $b['Elo'] - $a['Elo']; // Compare Elo scores in descending order
       // });

// Assign the top 4 players from each team to Heat 14 and 15
        //$numberA1 = $players_team_1[0]['PlayerID'];
        //$numberA2 = $players_team_1[1]['PlayerID'];
        //$numberA3 = $players_team_1[2]['PlayerID'];
        //$numberA4 = $players_team_1[3]['PlayerID'];

        //$numberB1 = $players_team_2[0]['PlayerID'];
        //$numberB2 = $players_team_2[1]['PlayerID'];
        //$numberB3 = $players_team_2[2]['PlayerID'];
        //$numberB4 = $players_team_2[3]['PlayerID'];

        // Match number map (heat slots corresponding to players)
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
            14 => [ // Heat 13
                1 => $roster[2],
                2 => $roster[10],
                3 => $roster[3],
                4 => $roster[11],
            ],
            15 => [ // Heat 15
                1 => $roster[1],
                2 => $roster[9],
                3 => $roster[5],
                4 => $roster[13],
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

// Display the match number map with player assignments
        echo "<h3>Match Number Map:</h3>";

        foreach ($matchNumberMap as $heat => $slots) {
            echo "<h4>Heat $heat:</h4>";
            echo "<table border='1'>";
            echo "<tr><th>Slot</th><th>Player</th><th>Projected Points</th><th>Win Chance</th><th>2nd Place Probability</th><th>3rd Place Probability</th><th>4th Place Probability</th><th>Simulated Points</th></tr>";

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
                $playerResults = $results[$playerID];
                $singlePlayerResults=$single_results[$playerID];

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

                // Display player results in the table
                echo "<tr>";
                echo "<td>$slot</td>";
                echo "<td>" . $player['FirstName'] . " " . $player['lastName'] . "</td>";
                echo "<td>" . round($playerResults['projected_points'], 2) . "</td>";
                echo "<td>" . round($playerResults['win_chance'] * 100, 2) . "%</td>";
                echo "<td>" . round($playerResults['finishing_probs'][1] * 100, 2) . "%</td>";
                echo "<td>" . round($playerResults['finishing_probs'][2] * 100, 2) . "%</td>";
                echo "<td>" . round($playerResults['finishing_probs'][3] * 100, 2) . "%</td>";
                echo "<td>" . round($singlePlayerResults['projected_points'], 2) . "</td>";
                echo "</tr>";
                // Initialize player data if not already set
                if (!isset($playerPPOData[$playerID])) {
                    $playerPPOData[$playerID] = [
                        'playerID' => $player['PlayerID'],
                        'firstName' => $player['FirstName'],
                        'lastName' => $player['lastName'],
                        'teamName' => $player['teamName'],
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



            }
            echo "</table>";


            // After the race, display the total projected points for each team
            echo "<h4>Projected Points for Heat $heat:</h4>";
            echo "<table border='1'>";
            echo "<tr><th>Team</th><th>Current Heat Points</th><th>Cumulative Points</th> </tr>";
            foreach ($teamPoints as $teamID => $teamPointsValue) {
                echo "<tr><td>" . $teamNames[$teamID] . "</td><td>" . round($teamPointsValue, 2) . "</td><td>" . round(($cumulativeTeamPoints[$teamID] ?? 0) + $teamPointsValue, 2) . "</td></tr>";
            }
            echo "</table>";

            // After the race, display the total projected points for each team
            echo "<h4>Simulation for Heat $heat:</h4>";
            echo "<table border='1'>";
            echo "<tr><th>Team</th><th>Single Heat</th><th>Cumulative Total</th> </tr>";
            foreach ($teamSinglePoints as $teamID => $teamSinglePointsValue) {
                echo "<tr><td>" . $teamNames[$teamID] . "</td><td>" . round($teamSinglePointsValue, 2) . "</td><td>" . round(($cumulativeSingleTeamPoints[$teamID] ?? 0) + $teamSinglePointsValue, 2) . "</td></tr>";
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
        include 'FutureMatchPlayerPPOtable.php';
?><button>Simulate</button><?php
    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Roster</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .team-select, .slot {
            margin-bottom: 10px;
        }
        .slot select {
            width: 200px;
        }
        .team-select {
            margin-bottom: 20px;
        }
        .roster-form {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .slot {
            width: 45%;
        }
        .team-section {
            margin-bottom: 30px;
        }
        h2 {
            margin-top: 20px;
        }
        .roster-form h3 {
            width: 100%;
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        .slot-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .slot-container .slot {
            width: 48%;
        }
    </style>
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

        <button type="submit">Fetch Players</button>
    </form>

    <?php if (!empty($players_team_1) && !empty($players_team_2)): ?>
        <h2>Select Players for the Roster</h2>
        <form method="post">
            <input type="hidden" name="team_1" value="<?= $selected_team_1 ?>">
            <input type="hidden" name="team_2" value="<?= $selected_team_2 ?>">

            <div class="roster-form">
                <h3>Team 1 (Slots 1-8)</h3>
                <div class="slot-container">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <div class="slot">
                            <label for="slot<?= $i ?>">Slot <?= $i ?>:</label>
                            <select name="roster[<?= $i ?>]" id="slot<?= $i ?>" required>
                                <option value="">--Select Player--</option>
                                <?php foreach ($players_team_2 as $player): ?>
                                    <option value="<?= $player['PlayerID'] ?>"><?= $player['FirstName'] . " " . $player['lastName'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endfor; ?>
                </div>

                <h3>Team 2 (Slots 9-16)</h3>
                <div class="slot-container">
                    <?php for ($i = 9; $i <= 16; $i++): ?>
                        <div class="slot">
                            <label for="slot<?= $i ?>">Slot <?= $i ?>:</label>
                            <select name="roster[<?= $i ?>]" id="slot<?= $i ?>" required>
                                <option value="">--Select Player--</option>
                                <?php foreach ($players_team_1 as $player): ?>
                                    <option value="<?= $player['PlayerID'] ?>"><?= $player['FirstName'] . " " . $player['lastName'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <button type="submit">Create Roster</button>
        </form>
    <?php endif; ?>
</body>
</html>

