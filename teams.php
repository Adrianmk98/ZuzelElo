<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';


// Fetch teams and their players from the database
$sql = "SELECT p.PlayerID, p.FirstName, p.LastName, p.TeamID, t.teamName,t.TeamID 
        FROM player p
        LEFT JOIN team t ON p.TeamID = t.TeamID
        WHERE p.TeamID != 0
        ORDER BY t.teamName, p.LastName"; // Sorting by team name, then by player last name
$result = $conn->query($sql);

$teams = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Group players by TeamID
        $teams[$row['TeamID']]['teamName'] = $row['teamName'];
        $teams[$row['TeamID']]['teamID'] = $row['TeamID'];
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
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
    <style>

        .container {
            width: 80%;
            margin: 0 auto;
            padding: 20px;
        }

        .team {
            background-color: #fff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
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
        .no-link {
            text-decoration: none;
            color: inherit;
        }

    </style>
</head>
<body>

    <h1>Player Profiles by Team</h1>

    <?php foreach ($teams as $team): ?>
        <h2>
            <a class="no-link" href="team.php?id=<?php echo $team['teamID']; ?>">
                <?php echo $team['teamName']; ?>
            </a></h2>
    <div class="container">
        <div class="team">
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
    </div>
    <?php endforeach; ?>

</body>
</html>