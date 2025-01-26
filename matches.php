<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';
try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if 'year' parameter exists in the URL
    if (!isset($_GET['year']) || empty($_GET['year'])) {
        // Redirect to the matches.php page with the current year
        header('Location: matches.php?year=' . date('Y'));
        exit; // Always exit after a header redirect
    }

// Sanitize and retrieve the year parameter
    $yearID = intval($_GET['year']);

    // Query to get match details
    $match_details_query = "
    SELECT 
        m.matchID, 
        m.homeTeamID, 
        m.awayTeamID, 
        ht.teamName AS homeTeamName, 
        at.teamName AS awayTeamName, 
        m.weeknum, 
        m.yearnum,
        (SELECT SUM(s.score) 
         FROM heatinformation s 
         WHERE s.currentPlayerTeamID = m.homeTeamID AND m.matchID=s.matchID) AS homeTeamScore,
        (SELECT SUM(s.projectedPoints) 
         FROM heatinformation s 
         WHERE s.currentPlayerTeamID = m.homeTeamID AND m.matchID=s.matchID) AS homeTeamProjectedScore,
        (SELECT SUM(s.score) 
         FROM heatinformation s 
         WHERE s.currentPlayerTeamID = m.awayTeamID AND m.matchID=s.matchID) AS awayTeamScore,
        (SELECT SUM(s.projectedPoints) 
         FROM heatinformation s 
         WHERE s.currentPlayerTeamID = m.awayTeamID AND m.matchID=s.matchID) AS awayTeamProjectedScore
    FROM matches m
    LEFT JOIN team ht ON m.homeTeamID = ht.teamID
    LEFT JOIN team at ON m.awayTeamID = at.teamID
    WHERE m.yearnum=$yearID
    ORDER BY m.yearnum, m.weeknum;"; // Grouping and sorting by year and week

    // Execute the query
    $stmt = $pdo->query($match_details_query);

    // Fetch results
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize matches by week and year
    $matchesByWeek = [];
    foreach ($results as $row) {
        $key = "Week {$row['weeknum']} - {$row['yearnum']}";
        $matchesByWeek[$key][] = $row;
    }
} catch (PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
    <style>
        /* General body styling */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
        }

        /* Match header styling */
        .match-header {
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 18px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
            text-align: center;
        }

        /* Team section and form styling */
        .team-section {
            margin-bottom: 30px;
        }

        form {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            padding: 10px; /* Added padding for spacing */
        }

        .team-select {
            display: flex;
            align-items: center;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 10px 20px;
            border-radius: 5px;
            flex: 1; /* Ensures flexibility for mobile screens */
            min-width: 250px; /* Prevents too small containers */
            max-width: 100%; /* Ensures the form remains responsive */
        }

        .team-select label {
            color: white;
            padding-right: 10px;
            font-size: 16px;
        }

        .team-select select {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: none;
            width: 100%; /* Takes full width on smaller screens */
        }

        /* Heat table spacing */
        .heat-table {
            margin-bottom: 40px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .match-header {
                font-size: 16px;
                padding: 8px 15px;
            }

            form {
                gap: 10px; /* Reduce gap between elements */
                padding: 5px; /* Reduce padding */
            }

            .team-select {
                flex-direction: column; /* Stack label and select vertically */
                align-items: flex-start; /* Align items to the start */
            }

            .team-select label {
                padding-right: 0;
                margin-bottom: 5px; /* Adds space between label and select */
            }
        }

        @media (max-width: 480px) {
            .match-header {
                font-size: 14px;
                padding: 6px 10px;
            }

            .team-select {
                min-width: 200px; /* Further reduce minimum width */
            }
        }
    </style>
</head>

<body>
<?php
$counterin1=0;
$counterin2=0;
$wrongwinner=0;
// Display matches grouped by week
$yearquery = "SELECT DISTINCT yearnum FROM matches ORDER BY yearnum ASC";
$stmt = $pdo->prepare($yearquery);
$stmt->execute();
$years = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<form method="post">
    <div class="team-select">
        <label for="year">Year: </label>
        <select name="year" id="year" required onchange="goToYear()">
            <option value="">--Select Year--</option>
            <?php foreach ($years as $year): ?>
                <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<script>
    function goToYear() {
        const year = document.getElementById("year").value;
        if (year) {
            // Redirect to URL with selected year in query string
            window.location.href = `?year=${year}`;
        }
    }
</script>
<?php
foreach ($matchesByWeek as $weekHeader => $matches) {
    echo "<div class='match-header'><strong>$weekHeader</strong></div><br>";
    echo "<table class='heat-table'>";
    echo "<tr>
            <th>Match ID</th>
            <th>Home Team</th>
            <th>Score (Projected)</th>
            <th>Away Team</th>
            <th>Score (Projected)</th>
          </tr>";

    foreach ($matches as $row) {
        echo "<tr>";

        $matchID = $row['matchID'];
        $homeTeamName = $row['homeTeamName'];
        $awayTeamName = $row['awayTeamName'];

        // Check if heat information exists
        $heatInfoQuery = "
        SELECT COUNT(*) 
        FROM heatinformation 
        WHERE matchID = :matchID
        ";
        $heatInfoStmt = $pdo->prepare($heatInfoQuery);
        $heatInfoStmt->execute([':matchID' => $matchID]);
        $heatInfoExists = $heatInfoStmt->fetchColumn();

        if ($heatInfoExists > 0) {
            // Use heat information for the match
            $homeScore = $row['homeTeamScore'] ?? 0;
            $homeProjected = $row['homeTeamProjectedScore'] ?? 0;
            $awayScore = $row['awayTeamScore'] ?? 0;
            $awayProjected = $row['awayTeamProjectedScore'] ?? 0;

            echo "<td><a href='matchprofile.php?match={$matchID}'>{$matchID}</a></td>";
            echo "<td>{$homeTeamName}</td>";
            echo "<td>$homeScore({$homeProjected})</td>";
            echo "<td>{$awayTeamName}</td>";
            echo "<td>$awayScore({$awayProjected})</td>";
        } else {
            // No heat information, use futurematch projections or defaults
            $futureMatchQuery = "
            SELECT 
                homeTeamScore AS homeProjectedScore, 
                awayTeamScore AS awayProjectedScore
            FROM futurematches
            WHERE matchID = :matchID
            ";
            $futureMatchStmt = $pdo->prepare($futureMatchQuery);
            $futureMatchStmt->execute([':matchID' => $matchID]);
            $futureMatchResult = $futureMatchStmt->fetch(PDO::FETCH_ASSOC);

            $nogameplayed="Select heatsInMatch from matches WHERE matchID = :matchID";

            $nogame = $pdo->prepare($nogameplayed);
            $nogame->execute([':matchID' => $matchID]);
            $nogameresult = $nogame->fetch(PDO::FETCH_ASSOC);



            $homeProjected = $futureMatchResult['homeProjectedScore'] ?? 0;
            $awayProjected = $futureMatchResult['awayProjectedScore'] ?? 0;

            echo "<td><a href='futurematchprofile.php?match={$matchID}'>{$matchID}</a></td>";
            echo "<td>{$homeTeamName}</td>";
            ?>
            <td>
<?php
if($nogameresult['heatsInMatch']==0)
{
    echo "0";
}
echo "(".$homeProjected.")";
?></td><?php
            echo "<td>{$awayTeamName}</td>";
            ?>
             <td>
<?php
if($nogameresult['heatsInMatch']==0)
{
    echo "0";
}
echo "(".$awayProjected.")";
?></td><?php
        }

        // Display the match information

        echo "</tr>";
    }

    echo "</table>";
}
?>

</body>
</html>
