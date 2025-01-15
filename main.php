<?php

// Database connection details
$servername = "localhost";  // Your database server
$dbusername = "root";
$dbpassword = "";
$dbname = "zuzelelo";
      

// Database connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch teams and their players from the database
$sql = "SELECT p.PlayerID, p.FirstName, p.LastName, p.TeamID, t.teamName 
        FROM player p
        LEFT JOIN team t ON p.TeamID = t.TeamID
        ORDER BY t.teamName, p.LastName"; // Sorting by team name, then by player last name
$result = $conn->query($sql);

$teams = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Group players by TeamID
        $teams[$row['TeamID']]['teamName'] = $row['teamName'];
        $teams[$row['TeamID']]['players'][] = [
            'PlayerID' => $row['PlayerID'],
            'FirstName' => $row['FirstName'],
            'LastName' => $row['LastName']
        ];
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
    <title>Player Profiles</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            width: 80%;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        .team {
            background-color: #fff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .team h2 {
            margin-bottom: 15px;
        }
        .player-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
        }
        .player {
            background-color: #fff;
            padding: 15px;
            margin: 10px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 200px;
        }
        .player a {
            text-decoration: none;
            color: #333;
            font-size: 18px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Player Profiles by Team</h1>
        
        <!-- Link to the Elo Checker Page -->
        <div class="link-section">
            <a href="elochecker.php">Go to Elo Checker</a><br>
            <a href="matches.php">Go to matches</a>
        </div>
        
        <?php foreach ($teams as $team): ?>
            <div class="team">
                <h2><?php echo $team['teamName']; ?></h2>
                <div class="player-list">
                    <?php foreach ($team['players'] as $player): ?>
                        <div class="player">
                            <a href="profile.php?id=<?php echo $player['PlayerID']; ?>">
                                <?php echo $player['FirstName'] . ' ' . $player['LastName']; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>