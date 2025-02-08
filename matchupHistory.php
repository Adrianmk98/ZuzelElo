<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';

// Fetch players from the database
$sql = "SELECT PlayerID, FirstName, LastName FROM player ORDER BY LastName,FirstName";
$result = $conn->query($sql);

$players = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $players[$row['PlayerID']] = $row['FirstName'] . " " . $row['LastName'];
    }
} else {
    echo "No players found!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History</title>
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
    <link rel="stylesheet" href="includes/HistoryStyle.css">
    <style>
        .highlight { background-color: #90EE90; } /* Light Green */
        .stats-box {
            border: 2px solid #333;
            padding: 10px;
            margin: 10px 0;
            background-color: #f8f8f8;
        }
    </style>
</head>
<body>
<h1>History</h1>
<h1>Select Players</h1>
<div style="text-align: center;"><button class="history-button" type="submit" name="find_races">View History</button></div>
<br><br>
<form method="post">
    <table>
        <tr>
            <th>Player</th>
            <th>Name</th>
        </tr>
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <tr>
                <td>Player <?= $i ?></td>
                <td>
                    <select name="player_<?= $i ?>">
                        <option value="">Select Player</option>
                        <?php foreach ($players as $id => $name): ?>
                            <option value="<?= $id ?>"><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php endfor; ?>
    </table>
    <br>
</form>


<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_races'])) {
    $selectedPlayers = [];

    for ($i = 1; $i <= 4; $i++) {
        if (!empty($_POST["player_$i"])) {
            $selectedPlayers[] = $_POST["player_$i"];
        }
    }

    if (count($selectedPlayers) < 2) {
        echo "<p>Please select at least two players.</p>";
    } else {
        $playerIDs = implode(",", $selectedPlayers);
        $playerCount = count($selectedPlayers);

        $sql = "
            SELECT h.*
            FROM heatinformation h
            JOIN (
                SELECT matchID, heatNumber
                FROM heatinformation
                WHERE playerID IN ($playerIDs)
                GROUP BY matchID, heatNumber
                HAVING COUNT(DISTINCT playerID) = $playerCount
            ) valid_heats
            ON h.matchID = valid_heats.matchID AND h.heatNumber = valid_heats.heatNumber
            ORDER BY h.matchID, h.heatNumber, h.startingpositionID
        ";

        $result = $conn->query($sql);
        $races = [];
        $playerStats = [];

        if ($result->num_rows > 0) {

            while ($row = $result->fetch_assoc()) {
                $raceKey = "Match {$row['matchID']} - Heat {$row['heatNumber']}";
                $races[$raceKey][] = $row;

                if (in_array($row['playerID'], $selectedPlayers)) {
                    if (!isset($playerStats[$row['playerID']])) {
                        $playerStats[$row['playerID']] = [
                            'first' => 0, 'second' => 0, 'third' => 0, 'fourth' => 0, 'totalScore' => 0, 'races' => 0
                        ];
                    }

                    $score = $row['Score'];
                    if ($score == 3) $playerStats[$row['playerID']]['first']++;
                    elseif ($score == 2) $playerStats[$row['playerID']]['second']++;
                    elseif ($score == 1) $playerStats[$row['playerID']]['third']++;
                    elseif ($score == 0) $playerStats[$row['playerID']]['fourth']++;

                    $playerStats[$row['playerID']]['totalScore'] += $row['Score'];
                    $playerStats[$row['playerID']]['races']++;
                }
            }



            echo "<h2>Race Summary (Selected Players Only)</h2>";
            echo "<h3>Races found: ".count($races)."</h3>";
            echo "<table border='1'>";
            echo "<tr>
                <th>Player</th>
                <th>1st Place Finishes</th>
                <th>2nd Place Finishes</th>
                <th>3rd Place Finishes</th>
                <th>4th Place Finishes</th>
                <th>Average Score</th>
            </tr>";

            foreach ($selectedPlayers as $playerID) {
                if (isset($playerStats[$playerID])) {
                    $stats = $playerStats[$playerID];
                    $avgScore = $stats['races'] > 0 ? round($stats['totalScore'] / $stats['races'], 2) : 0;
                    echo "<tr>
                        <td>{$players[$playerID]}</td>
                        <td>{$stats['first']}</td>
                        <td>{$stats['second']}</td>
                        <td>{$stats['third']}</td>
                        <td>{$stats['fourth']}</td>
                        <td>{$avgScore}</td>
                    </tr>";
                } else {
                    echo "<tr>
                        <td>{$players[$playerID]}</td>
                        <td>0</td><td>0</td><td>0</td><td>0</td><td>0.00</td>
                    </tr>";
                }
            }

            echo "</table>";

            echo "<h2>Race Details Where Selected Players Participated Together</h2>";

            foreach ($races as $raceKey => $raceData) {
                echo "<h3>$raceKey</h3>";
                echo "<table border='1'>";
                echo "<tr>
                    <th>Starting Position</th>
                    <th>Player Name</th>
                    <th>Score</th>
                </tr>";

                foreach ($raceData as $row) {
                    $isHighlighted = in_array($row['playerID'], $selectedPlayers) ? 'class="highlight"' : '';
                    echo "<tr $isHighlighted>
                        <td>{$row['startingpositionID']}</td>
                        <td>{$players[$row['playerID']]}</td>
                        <td>{$row['Score']}</td>
                    </tr>";
                }

                echo "</table><br>";
            }
        } else {
            echo "<p>No heats found where all selected players participated together.</p>";
        }
    }
}

$conn->close();
?>
</body>
</html>
