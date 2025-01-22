<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';

// Get the player ID from the URL parameter (e.g., player_profile.php?id=1)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $player_id = $_GET['id'];
} else {
    die("Player ID is required.");
}

// Fetch player information
$sql_player = "SELECT FirstName, LastName, elo, teamID FROM player WHERE PlayerID = ?";
$stmt_player = $conn->prepare($sql_player);
$stmt_player->bind_param("i", $player_id);
$stmt_player->execute();
$result_player = $stmt_player->get_result();

// Fetch player information
$sql_matchinfo = "
    SELECT 
        h.heatNumber, 
        h.Score, 
        h.PPA,
        h.projectedPoints, 
        h.matchID,
        CASE 
            WHEN m.homeTeamID = p.teamID THEN t2.teamName 
            WHEN m.awayTeamID = p.teamID THEN t1.teamName 
        END AS opponentTeam
    FROM 
        heatinformation h
    JOIN 
        matches m 
    ON 
        h.matchID = m.matchID
    JOIN 
        player p 
    ON 
        h.playerID = p.playerID
    JOIN 
        team t1 
    ON 
        m.homeTeamID = t1.teamID
    JOIN 
        team t2 
    ON 
        m.awayTeamID = t2.teamID
    WHERE 
        h.playerID = ?";
$stmt_matchinfo= $conn->prepare($sql_matchinfo);
$stmt_matchinfo->bind_param("i", $player_id);
$stmt_matchinfo->execute();
$result_matchinfo = $stmt_matchinfo->get_result();

$sql_career= "SELECT 
                    m.yearnum AS Year,
                    t.teamName AS Team,
                    SUM(h.Score) AS Score,
                    SUM(h.Bonus) As Bonus,
                    COUNT(h.heatNumber) AS TotalRaces,
                    SUM(h.PPA) AS TotalPPA
                FROM 
                    heatinformation h
                JOIN 
                    team t ON h.currentplayerteamID = t.teamID
                JOIN 
                    matches m ON h.matchID = m.matchID
                WHERE 
                    h.playerID = ?
                GROUP BY 
                    m.yearnum, t.teamName
                ORDER BY 
                    m.yearnum DESC, t.teamName;
            ";
$stmt_careerinfo= $conn->prepare($sql_career);
$stmt_careerinfo->bind_param("i", $player_id);
$stmt_careerinfo->execute();
$result_careerinfo = $stmt_careerinfo->get_result();

$AdvancedAnalytics="
SELECT SUM(h.PPO) as PPO, SUM(h.PPA) as PPA,COUNT(DISTINCT h.matchID) AS gamesPlayed,
    COUNT(*) AS racesParticipated
FROM heatinformation h
JOIN matches m ON h.matchID = m.matchID
WHERE h.playerID = ?
AND m.yearNum = 2024;
";

$stmt_asplayer = $conn->prepare($AdvancedAnalytics);
$stmt_asplayer->bind_param("i", $player_id);
$stmt_asplayer->execute();
$asresult_player = $stmt_asplayer->get_result();

if ($asresult_player->num_rows > 0) {
    $ASplayer = $asresult_player->fetch_assoc();
    $PPO = $ASplayer['PPO'];
    $PPA = $ASplayer['PPA'];
    $racesnum = $ASplayer['racesParticipated'];
    $gamesplayed = $ASplayer['gamesPlayed'];

} else {
    die("Player not found.");
}

if ($result_player->num_rows > 0) {
    $player = $result_player->fetch_assoc();
    $first_name = $player['FirstName'];
    $last_name = $player['LastName'];
    $elo = $player['elo'];
    $team_id = $player['teamID'];
} else {
    die("Player not found.");
}

// Fetch team information using the team_id
$sql_team = "SELECT teamName FROM team WHERE teamID = ?";
$stmt_team = $conn->prepare($sql_team);
$stmt_team->bind_param("i", $team_id);
$stmt_team->execute();
$result_team = $stmt_team->get_result();

if ($result_team->num_rows > 0) {
    $team = $result_team->fetch_assoc();
    $team_name = $team['teamName'];
} else {
    $team_name = "Team not found.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .profile-container {
            border: 1px solid #ddd;
            padding: 20px;
            max-width: 400px;
            margin: 0 auto;
            background-color: #f9f9f9;
        }
        .profile-container h1 {
            text-align: center;
            color: #333;
        }
        .profile-container p {
            font-size: 16px;
            color: #555;
        }
        .profile-container .details {
            margin-bottom: 10px;
        }
        .profile-container .details span {
            font-weight: bold;
        }
        .profile-containercareer {
            border: 1px solid #ddd;
            padding: 20px;
            max-width: 400px;
            margin: 0 auto;
            background-color: #f9f9f9;
        }
        .profile-containercareer h1 {
            text-align: center;
            color: #333;
        }
        .profile-containercareer p {
            font-size: 16px;
            color: #555;
        }
        .profile-containercareer .details {
            margin-bottom: 10px;
        }
        .profile-containercareer .details span {
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="profile-container">

    <h1><?php echo htmlspecialchars($first_name); ?> <?php echo htmlspecialchars($last_name); ?></h1>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
    <?php
    // Dynamically build the SELECT query for columns 1–20
    $columns = [];
    for ($i = 1; $i <= 20; $i++) {
        $columns[] = "eloarchive2024.`$i` AS `2024_$i`, eloarchive2025.`$i` AS `2025_$i`";
    }
    $columns_list = implode(", ", $columns);

    $sql_player = "SELECT $columns_list
               FROM eloarchive2024 
               JOIN eloarchive2025 ON eloarchive2024.PlayerID = eloarchive2025.PlayerID
               WHERE eloarchive2024.PlayerID = ?";
    $stmt_player = $conn->prepare($sql_player);
    $stmt_player->bind_param("i", $player_id);
    $stmt_player->execute();
    $result_player = $stmt_player->get_result();

    // Initialize arrays
    $xValues = range(1, 20); // Labels for columns 1 to 20
    $yValues2024 = array_fill(0, 20, null);
    $yValues2025 = array_fill(0, 20, null);

    // Process the row and filter out zeros
    if ($row = $result_player->fetch_assoc()) {
        foreach ($xValues as $column) {
            // Process 2024 data
            $value2024 = $row["2024_$column"];
            $yValues2024[$column - 1] = ($value2024 != 0) ? $value2024 : null;

            // Process 2025 data
            $value2025 = $row["2025_$column"];
            $yValues2025[$column - 1] = ($value2025 != 0) ? $value2025 : null;
        }
    }

    // Close the database connection
    $conn->close();
    ?>
    <body>
    <canvas id="myChart" style="width:100%;max-width:600px"></canvas>

    <script>
        // PHP arrays to JavaScript
        const xValues = <?php echo json_encode($xValues); ?>; // Columns 1–20
        const yValues2024 = <?php echo json_encode($yValues2024); ?>; // Data for 2024
        const yValues2025 = <?php echo json_encode($yValues2025); ?>; // Data for 2025

        new Chart("myChart", {
            type: "line",
            data: {
                labels: xValues,
                datasets: [
                    {
                        label: "2024 Data",
                        fill: false,
                        lineTension: 0,
                        backgroundColor: "rgba(0,0,255,1.0)",
                        borderColor: "rgba(0,0,255,0.1)",
                        data: yValues2024
                    },
                    {
                        label: "2025 Data",
                        fill: false,
                        lineTension: 0,
                        backgroundColor: "rgba(255,0,0,1.0)",
                        borderColor: "rgba(255,0,0,0.1)",
                        data: yValues2025
                    }
                ]
            },
            options: {
                legend: { display: true },
                scales: {
                    yAxes: [{
                        ticks: { min: 0 } // Adjust as needed
                    }]
                }
            }
        });
    </script>
    <div class="details">
        <p><span>Elo Rating:</span> <?php echo htmlspecialchars($elo); ?></p>
        <p><span>Team:</span> <?php echo htmlspecialchars($team_name); ?></p>
        <?php
        if($PPA !=0)
        {
        ?>
        <p><span>Points Above Average (Per Race):</span> <?php echo htmlspecialchars($PPA) . " (" . round($PPA / $racesnum, 2) . ")"; ?></p>
        <?php
        }else{
            ?>
            <p><span>Points Above Average (Per Race):</span> <?php echo htmlspecialchars($PPA) . " (" . round(0, 2) . ")"; ?></p>
        <?php
        }

        ?>
        <button id="toggleButton">Show Race Information</button>
        <button id="toggleButtonCareer">Show Career Info</button>
    </div>
</div>


<div class="profile-container" id="profileContainer" style="display: none;">
    <h1>Race Information</h1>
    <div class="details">
        <table border="1">
            <thead>
            <tr>
                <th>Opponent</th>
                <th>Heat Number</th>
                <th>Score</th>
                <th>Projected Points</th>
                <th>PPA</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = $result_matchinfo->fetch_assoc()): ?>
                <tr>
                    <td><a href="matchprofile.php?match=<?php echo htmlspecialchars($row['matchID']); ?>"><?php echo htmlspecialchars($row['opponentTeam']); ?></a></td>
                    <td><?php echo htmlspecialchars($row['heatNumber']); ?></td>
                    <td><?php echo htmlspecialchars($row['Score']); ?></td>
                    <td><?php echo htmlspecialchars($row['projectedPoints']); ?></td>
                    <td><?php echo htmlspecialchars($row['PPA']); ?></td>

                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="profile-containercareer" id="profileContainercareer" style="display: none; ">
    <h1>Career</h1>
    <div class="details">
        <table border="1">
            <thead>
            <tr>
                <th>Year</th>
                <th>Team</th>
                <th>Score</th>
                <th>Bonus</th>
                <th>Races</th>
                <th>PPA</th>
            </tr>
            </thead>
            <tbody>
            <?php



            while ($row = $result_careerinfo->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['Year']); ?></td>
                    <td><?php echo htmlspecialchars($row['Team']); ?></td>
                    <td><?php echo htmlspecialchars($row['Score']); ?></td>
                    <td><?php echo htmlspecialchars($row['Bonus']); ?></td>
                    <td><?php echo htmlspecialchars($row['TotalRaces']); ?></td>
                    <td><?php echo htmlspecialchars($row['TotalPPA']); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const toggleButton = document.getElementById('toggleButton');
    const profileContainer = document.getElementById('profileContainer');

    toggleButton.addEventListener('click', () => {
        const isHidden = profileContainer.style.display === 'none';
        profileContainer.style.display = isHidden ? 'block' : 'none';
        toggleButton.textContent = isHidden ? 'Hide Race Information' : 'Show Race Information';
    });

    const toggleButtonCareer = document.getElementById('toggleButtonCareer');
    const profileContainercareer = document.getElementById('profileContainercareer');

    toggleButtonCareer.addEventListener('click', () => {
        const isHidden = profileContainercareer.style.display === 'none';
        profileContainercareer.style.display = isHidden ? 'block' : 'none';
        toggleButtonCareer.textContent = isHidden ? 'Hide Career Info' : 'Show Career Info';
    });
</script>
</body>
</html>

