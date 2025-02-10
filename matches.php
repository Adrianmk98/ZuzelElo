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
    ORDER BY m.yearnum, m.weeknum,m.matchID;"; // Grouping and sorting by year and week

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

        /* Styling for each team select container */
        .team-select {
            display: flex; /* Aligns label and select side by side */
            align-items: center; /* Vertically centers content */
            background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
            padding: 10px 20px; /* Padding for better spacing */
            border-radius: 5px; /* Rounded corners */
        }

        /* Styling for labels */
        .team-select label {
            color: white; /* Text color */
            padding-right: 10px; /* Adds space between label and select box */
            font-size: 16px; /* Sets a consistent font size */
        }

        /* Styling for select dropdowns */
        .team-select select {
            padding: 10px; /* Padding inside the select box */
            font-size: 16px; /* Ensures font size consistency */
            border-radius: 5px; /* Rounded corners */
            border: none; /* Removes default border */
        }

        /* Heat table spacing */
        .heat-table {
            margin-bottom: 40px;
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
            <th>Home Team</th>
            <th>Score (Projected)</th>
            <th>Score (Projected)</th>
            <th>Away Team</th>
          </tr>";

    foreach ($matches as $row) {


        $matchID = $row['matchID'];
        $homeTeamName = $row['homeTeamName'];
        $awayTeamName = $row['awayTeamName'];
        $homeTeamID=$row['homeTeamID'];
        $awayTeamID=$row['awayTeamID'];

        // Query to get heatsInMatch
        $nogameplayed = "SELECT heatsInMatch FROM matches WHERE matchID = :matchID";
        $nogame = $pdo->prepare($nogameplayed);
        $nogame->execute([':matchID' => $matchID]);
        $nogameresult = $nogame->fetch(PDO::FETCH_ASSOC);

        // Query to check the max heat number from heat information
        $heatInfoQuery = "SELECT MAX(heatNumber) FROM heatinformation WHERE matchID = :matchID";
        $heatInfoStmt = $pdo->prepare($heatInfoQuery);
        $heatInfoStmt->execute([':matchID' => $matchID]);
        $heatInfoExists = $heatInfoStmt->fetchColumn();

        if ($heatInfoExists > 0) {
            if ($nogameresult['heatsInMatch'] > $heatInfoExists) {
                // Use future match projections if heatsInMatch is greater than the current heat number
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

                $homeProjected = $futureMatchResult['homeProjectedScore'] ?? 0;
                $awayProjected = $futureMatchResult['awayProjectedScore'] ?? 0;

                // Display match details using future projections
                echo "<tr onclick=\"window.location.href='match.php?match={$matchID}'\" style='cursor: pointer;'>";
                echo "<td>{$homeTeamName}</td>";
                echo "<td>" . ($nogameresult['heatsInMatch'] == 0 ? "0" : "") .$row['homeTeamScore']. "({$homeProjected})</td>";
                echo "<td>{$awayTeamName}</td>";
                echo "<td>" . ($nogameresult['heatsInMatch'] == 0 ? "0" : "") .$row['awayTeamScore']. "({$awayProjected})</td>";
            }else {// Use heat information for the match
                $homeScore = $row['homeTeamScore'] ?? 0;
                $homeProjected = $row['homeTeamProjectedScore'] ?? 0;
                $awayScore = $row['awayTeamScore'] ?? 0;
                $awayProjected = $row['awayTeamProjectedScore'] ?? 0;
                echo "<tr onclick=\"window.location.href='matchprofile.php?match={$matchID}'\" style='cursor: pointer;'>";
                echo "<td>{$homeTeamName}";?>
                <source media="(min-width: 650px)" srcset="teamlogos/<?php echo file_exists("teamlogos/$homeTeamID.jpg") ? $homeTeamID : 0; ?>.jpg">
                <img src="teamlogos/<?php echo file_exists("teamlogos/$homeTeamID.jpg") ? $homeTeamID : 0; ?>.jpg"
                     style="max-width: 32px; max-height: 32px; width: auto; height: auto; display: block; margin: 0 auto;">
            </picture></td>
<?php
                echo "<td><b>$homeScore</b>({$homeProjected})</td>";
                echo "<td><b>$awayScore</b>({$awayProjected})</td>";
                echo "<td>{$awayTeamName}";
                ?>
                <source media="(min-width: 650px)" srcset="teamlogos/<?php echo file_exists("teamlogos/$awayTeamID.jpg") ? $awayTeamID : 0; ?>.jpg">
                <img src="teamlogos/<?php echo file_exists("teamlogos/$awayTeamID.jpg") ? $awayTeamID : 0; ?>.jpg"
                     style="max-width: 32px; max-height: 32px; width: auto; height: auto; display: block; margin: 0 auto;">
                </picture></td>
                <?php

            }
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





            $homeProjected = $futureMatchResult['homeProjectedScore'] ?? 0;
            $awayProjected = $futureMatchResult['awayProjectedScore'] ?? 0;
            echo "<tr onclick=\"window.location.href='match.php?match={$matchID}'\" style='cursor: pointer;'>";
            echo "<td>{$homeTeamName}";
            ?>
                <source media="(min-width: 650px)" srcset="teamlogos/<?php echo file_exists("teamlogos/$homeTeamID.jpg") ? $homeTeamID : 0; ?>.jpg">
                <img src="teamlogos/<?php echo file_exists("teamlogos/$homeTeamID.jpg") ? $homeTeamID : 0; ?>.jpg"
                     style="max-width: 32px; max-height: 32px; width: auto; height: auto; display: block; margin: 0 auto;">
                </picture></td>
            <td>
<?php
if($nogameresult['heatsInMatch']==0)
{
    echo "0";
}
echo "(".$homeProjected.")";
?></td><td>
            <?php
            if($nogameresult['heatsInMatch']==0)
            {
                echo "0";
            }
            echo "(".$awayProjected.")";
            ?></td><?php

            echo "<td>{$awayTeamName}";
            ?>
            <source media="(min-width: 650px)" srcset="teamlogos/<?php echo file_exists("teamlogos/$awayTeamID.jpg") ? $awayTeamID : 0; ?>.jpg">
            <img src="teamlogos/<?php echo file_exists("teamlogos/$awayTeamID.jpg") ? $awayTeamID : 0; ?>.jpg"
                 style="max-width: 32px; max-height: 32px; width: auto; height: auto; display: block; margin: 0 auto;">
            </picture></td>
            <?php
        }

        // Display the match information

        echo "</tr>";
    }

    echo "</table>";
}
?>

</body>
</html>
