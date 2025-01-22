<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
                WHEN (m.homeTeamID = t.teamID AND scores.homeTeamScore > scores.awayTeamScore AND 
                      (SELECT COUNT(*) FROM matches m2 
                       WHERE m2.homeTeamID IN (m.homeTeamID, m.awayTeamID) 
                       AND m2.awayTeamID IN (m.homeTeamID, m.awayTeamID) 
                       AND m2.matchID != m.matchID
                      ) = 1) THEN 1 ELSE 0
            END
        ) AS bonus,
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
        ) + SUM(
            CASE 
                WHEN (m.homeTeamID = t.teamID AND scores.homeTeamScore > scores.awayTeamScore AND 
                      (SELECT COUNT(*) FROM matches m2 
                       WHERE m2.homeTeamID IN (m.homeTeamID, m.awayTeamID) 
                       AND m2.awayTeamID IN (m.homeTeamID, m.awayTeamID) 
                       AND m2.matchID != m.matchID
                      ) = 1) THEN 1 ELSE 0
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
        m.weekNum <= 14  -- Filter for matches in weeks 1 through 14
    GROUP BY 
        t.teamID, t.teamName
    ORDER BY 
        points DESC,bonus DESC;  -- Sorting by points instead of wins
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
    <?php
    // Display the standings
    echo "<table border='1' cellspacing='0' cellpadding='5' style='background-color: #fff;'>";
    echo "<tr>
            <th>Team Name</th>
            <th>Wins</th>
            <th>Losses</th>
            <th>Ties</th>
            <th>Bonus</th>
            <th>Points</th>
          </tr>";

    foreach ($standings as $row) {
        echo "<tr>
                <td>{$row['teamName']}</td>
                <td>{$row['wins']}</td>
                <td>{$row['losses']}</td>
                <td>{$row['ties']}</td>
                <td>{$row['bonus']}</td>
                <td>{$row['points']}</td>
              </tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage());
}

?>
    </body>
</html>