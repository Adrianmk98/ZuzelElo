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
    header('Location: tabela.php?year=' . date('Y'));
    exit; // Always exit after a header redirect
}

// Sanitize and retrieve the year parameter
$yearID = intval($_GET['year']);

    // Query to calculate wins
    $wins_query = "
    SELECT 
        t.teamID,
        t.teamName,
        SUM(
            CASE 
                WHEN (m.homeTeamID = t.teamID AND scores.homeTeamScore > scores.awayTeamScore) OR 
                     (m.awayTeamID = t.teamID AND scores.awayTeamScore > scores.homeTeamScore) 
                THEN 1 ELSE 0 
            END
        ) AS wins,
        SUM(
            CASE 
                WHEN (m.homeTeamID = t.teamID AND scores.homeTeamScore < scores.awayTeamScore) OR 
                     (m.awayTeamID = t.teamID AND scores.awayTeamScore < scores.homeTeamScore) 
                THEN 1 ELSE 0 
            END
        ) AS losses,
        SUM(
            CASE 
                WHEN scores.homeTeamScore = scores.awayTeamScore THEN 1 ELSE 0 
            END
        ) AS ties,
        SUM(
            CASE 
                WHEN (m.homeTeamID = t.teamID AND scores.homeTeamScore > scores.awayTeamScore) OR 
                     (m.awayTeamID = t.teamID AND scores.awayTeamScore > scores.homeTeamScore) 
                THEN 2 ELSE 0 
            END
        ) + SUM(
            CASE 
                WHEN scores.homeTeamScore = scores.awayTeamScore THEN 1 ELSE 0 
            END
        ) AS points
    FROM 
        team t
    LEFT JOIN 
        matches m 
        ON t.teamID = m.homeTeamID OR t.teamID = m.awayTeamID
    LEFT JOIN 
        (
            SELECT 
                h.matchID,
                m.homeTeamID,
                m.awayTeamID,
                SUM(CASE WHEN h.currentPlayerTeamID = m.homeTeamID THEN h.score ELSE 0 END) AS homeTeamScore,
                SUM(CASE WHEN h.currentPlayerTeamID = m.awayTeamID THEN h.score ELSE 0 END) AS awayTeamScore
            FROM 
                heatinformation h
            LEFT JOIN 
                matches m
                ON h.matchID = m.matchID
            GROUP BY h.matchID, m.homeTeamID, m.awayTeamID
        ) scores
        ON m.matchID = scores.matchID
    WHERE 
        m.weekNum <= 14 AND m.yearnum=$yearID  -- Filter for matches in weeks 1 through 14
    GROUP BY 
        t.teamID, t.teamName
    ORDER BY 
        points DESC;  -- Sorting by points instead of wins
";


// Execute the query
    $stmt = $pdo->prepare($wins_query);
    $stmt->execute();

    // Fetch results
    $standings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabels</title></head>
<br><br>
    <body>
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
    <style>
        .team-section {
            margin-bottom: 30px;
        }
        /* Styling for form container */
        form {
            display: flex; /* Makes form elements align horizontally */
            gap: 20px; /* Adds space between form elements */
            justify-content: center; /* Centers the form content */
            flex-wrap: wrap; /* Allows wrapping if necessary on smaller screens */
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
    </style>



    <?php

    $sql = "
SELECT 
    LEAST(m.homeTeamID, m.awayTeamID) AS team1,
    GREATEST(m.homeTeamID, m.awayTeamID) AS team2,
    m.yearnum,
    -- Sum the scores for team1, regardless of whether it's home or away
    SUM(CASE 
            WHEN hi.currentplayerteamID = LEAST(m.homeTeamID, m.awayTeamID) THEN hi.Score
            ELSE 0
        END) AS team1TotalPoints,
    -- Sum the scores for team2, regardless of whether it's home or away
    SUM(CASE 
            WHEN hi.currentplayerteamID = GREATEST(m.homeTeamID, m.awayTeamID) THEN hi.Score
            ELSE 0
        END) AS team2TotalPoints,
    -- Determine the winning team based on total points
    CASE 
        WHEN SUM(CASE WHEN hi.currentplayerteamID = LEAST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) = 0
             AND SUM(CASE WHEN hi.currentplayerteamID = GREATEST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) = 0 THEN 0
        WHEN SUM(CASE WHEN hi.currentplayerteamID = LEAST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) >
             SUM(CASE WHEN hi.currentplayerteamID = GREATEST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) THEN 
             LEAST(m.homeTeamID, m.awayTeamID)
        WHEN SUM(CASE WHEN hi.currentplayerteamID = LEAST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) < 
             SUM(CASE WHEN hi.currentplayerteamID = GREATEST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) THEN 
             GREATEST(m.homeTeamID, m.awayTeamID)
        ELSE 0
    END AS winningTeamID
FROM 
    matches m
JOIN 
    heatinformation hi ON m.matchID = hi.matchID
WHERE 
    m.yearnum = :yearID AND m.weekNum <= 14
GROUP BY 
    LEAST(m.homeTeamID, m.awayTeamID), GREATEST(m.homeTeamID, m.awayTeamID), m.yearnum
";

    // Prepare and execute the first query
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['yearID' => $yearID]);

    // Fetch results for the first table
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);



    // Second query: To get the frequency of each winningTeamID (excluding 0)
    $sql_frequency = "
SELECT 
    winningTeamID,
    COUNT(*) AS frequency
FROM (
    SELECT 
        LEAST(m.homeTeamID, m.awayTeamID) AS team1,
        GREATEST(m.homeTeamID, m.awayTeamID) AS team2,
        m.yearnum,
        SUM(CASE 
                WHEN hi.currentplayerteamID = LEAST(m.homeTeamID, m.awayTeamID) THEN hi.Score
                ELSE 0
            END) AS team1TotalPoints,
        SUM(CASE 
                WHEN hi.currentplayerteamID = GREATEST(m.homeTeamID, m.awayTeamID) THEN hi.Score
                ELSE 0
            END) AS team2TotalPoints,
        CASE 
            WHEN SUM(CASE WHEN hi.currentplayerteamID = LEAST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) = 0
                 AND SUM(CASE WHEN hi.currentplayerteamID = GREATEST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) = 0 THEN 0
            WHEN SUM(CASE WHEN hi.currentplayerteamID = LEAST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) >
                 SUM(CASE WHEN hi.currentplayerteamID = GREATEST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) THEN 
                 LEAST(m.homeTeamID, m.awayTeamID)
            WHEN SUM(CASE WHEN hi.currentplayerteamID = LEAST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) < 
                 SUM(CASE WHEN hi.currentplayerteamID = GREATEST(m.homeTeamID, m.awayTeamID) THEN hi.Score ELSE 0 END) THEN 
                 GREATEST(m.homeTeamID, m.awayTeamID)
            ELSE 0
        END AS winningTeamID
    FROM 
        matches m
    JOIN 
        heatinformation hi ON m.matchID = hi.matchID
    WHERE 
        m.yearnum = :yearID AND m.weekNum <= 14
    GROUP BY 
        LEAST(m.homeTeamID, m.awayTeamID), GREATEST(m.homeTeamID, m.awayTeamID), m.yearnum
) AS subquery
WHERE 
    winningTeamID != 0  -- Exclude rows where winningTeamID is 0
GROUP BY 
    winningTeamID
ORDER BY 
    frequency DESC;
";


    // Prepare and execute the second query
    $stmt_frequency = $pdo->prepare($sql_frequency);
    $stmt_frequency->execute(['yearID' => $yearID]);

    // Fetch results for the second table
    $frequencies = $stmt_frequency->fetchAll(PDO::FETCH_ASSOC);

    // Merge standings and frequencies
    $merged_data = [];
    foreach ($standings as $standing) {
        // Default frequency to 0 if not found
        $frequency = 0;
        foreach ($frequencies as $freq) {
            if ($standing['teamID'] == $freq['winningTeamID']) {
                $frequency = $freq['frequency'];
                break;
            }
        }
        // Add frequency to points
        $standing['combinedPoints'] = $standing['points'] + $frequency;
        $standing['frequency'] = $frequency; // Keep the frequency value
        $merged_data[] = $standing;
    }

    // Sort by combined points (points + frequency) first, then by frequency if points are the same
    usort($merged_data, function($a, $b) {
        // Compare first by combined points
        if ($a['combinedPoints'] == $b['combinedPoints']) {
            return $b['frequency'] - $a['frequency']; // If points are the same, compare by frequency
        }
        return $b['combinedPoints'] - $a['combinedPoints']; // Sort by points descending
    });

    // Display merged data


    // Fetch distinct years from the matches table
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
    echo "<h2>Standings</h2>";
    echo "<table border='1' cellspacing='0' cellpadding='5' style='background-color: #fff;'>
<tr>
    <th>Team Name</th>
    <th>Wins</th>
    <th>Losses</th>
    <th>Ties</th>
    <th>Bonus</th>
    <th>Points</th>
</tr>";

    foreach ($merged_data as $row) {
        echo "<tr>
        <td>{$row['teamName']}</td>
        <td>{$row['wins']}</td>
        <td>{$row['losses']}</td>
        <td>{$row['ties']}</td>
        <td>{$row['frequency']}</td>
        <td>{$row['combinedPoints']}</td>
      
    </tr>";
    }

    echo "</table>";


    // Fetch future matches
    $future_matches_query = "
SELECT 
    LEAST(m.homeTeamID, m.awayTeamID) AS team1,
    GREATEST(m.homeTeamID, m.awayTeamID) AS team2,
    fm.matchID, 
    fm.homeTeamID, 
    fm.awayTeamID, 
    fm.homeTeamScore, 
    fm.awayTeamScore
FROM 
    futurematches fm
JOIN 
    matches m 
    ON fm.matchID = m.matchID
WHERE 
    m.yearnum = :yearID AND m.weekNum <= 14"; // Only future matches

    $future_stmt = $pdo->prepare($future_matches_query);
    $future_stmt->execute(['yearID' => $yearID]);
    $future_matches = $future_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add future match stats to the projected table, but exclude already completed matches
    $projected_standings = $merged_data; // Start with the current standings

    foreach ($future_matches as $match) {
        $matchID = $match['matchID'];

        // Check if there are any records in `heatinformation` for the given `matchID`
        $check_heat_query = "
    SELECT COUNT(*) 
    FROM heatinformation 
    WHERE matchID = :matchID";

        $check_heat_stmt = $pdo->prepare($check_heat_query);
        $check_heat_stmt->execute(['matchID' => $matchID]);
        $heat_count = $check_heat_stmt->fetchColumn();

        // If there are any heat information records, it means the match has already been played
        if ($heat_count > 0) {
            continue; // Skip this match since it has already happened
        }

        // If not a duplicate, add the match to the projected standings
        $homeTeamID = $match['homeTeamID'];
        $awayTeamID = $match['awayTeamID'];
        $homeTeamScore = $match['homeTeamScore'];
        $awayTeamScore = $match['awayTeamScore'];

        foreach ($projected_standings as &$team) {
            if ($team['teamID'] == $homeTeamID) {
                // Update stats for the home team
                if ($homeTeamScore > $awayTeamScore) {
                    $team['wins']++;
                    $team['points'] += 2;
                } elseif ($homeTeamScore < $awayTeamScore) {
                    $team['losses']++;
                } else {
                    $team['ties']++;
                    $team['points'] += 1;
                }
            }

            if ($team['teamID'] == $awayTeamID) {
                // Update stats for the away team
                if ($awayTeamScore > $homeTeamScore) {
                    $team['wins']++;
                    $team['points'] += 2;
                } elseif ($awayTeamScore < $homeTeamScore) {
                    $team['losses']++;
                } else {
                    $team['ties']++;
                    $team['points'] += 1;
                }
            }
        }
    }

    // Sort projected standings by points and then by frequency
    usort($projected_standings, function ($a, $b) {
        if ($a['points'] == $b['points']) {
            return $b['frequency'] - $a['frequency'];
        }
        return $b['points'] - $a['points'];
    });

    // Display the projected table
    echo "<h2>Projected to End of Season</h2>";
    echo "<table border='1' cellspacing='0' cellpadding='5' style='background-color: #fff;'>
<tr>
    <th>Team Name</th>
    <th>Wins</th>
    <th>Losses</th>
    <th>Ties</th>
</tr>";

    foreach ($projected_standings as $row) {
        echo "<tr>
        <td>{$row['teamName']}</td>
        <td>{$row['wins']}</td>
        <td>{$row['losses']}</td>
        <td>{$row['ties']}</td>
    </tr>";
    }

    echo "</table><br><br>";





} catch (PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage());
}

?>
    </body>
</html>