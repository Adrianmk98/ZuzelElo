<?php

// Database connection details
$servername = "localhost";  // Your database server
$dbusername = "root";
$dbpassword = "";
$dbname = "zuzelelo";


include 'topbar.php';
try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; }
    h1, h2 { color: #333; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    table th, table td { border: 1px solid #ddd; padding: 10px; text-align: center; }
    table th { background-color: #f4f4f4; }
    .match-header { margin-bottom: 10px; font-size: 1.2em; color: #444; }
    .heat-table { margin-bottom: 40px; }
</style>

<?php
$counterin1=0;
$counterin2=0;
$wrongwinner=0;
// Display matches grouped by week
foreach ($matchesByWeek as $weekHeader => $matches) {
    echo "<div class='match-header'><strong>$weekHeader</strong></div>";
    echo "<table class='heat-table'>";
    echo "<tr>
            <th>Match ID</th>
            <th>Home Team</th>
            <th>Score</th>
            <th>Away Team</th>
            <th>Score</th>
          </tr>";
    foreach ($matches as $row) {
        echo "<tr>";
        echo "<td><a href='matchprofile.php?match={$row['matchID']}'>{$row['matchID']}</a></td>";
        echo "<td>{$row['homeTeamName']}</td>";
        if(isset ($row['homeTeamScore']))
        {
            echo "<td>".$row['homeTeamScore']."(".$row['homeTeamProjectedScore'].")"."</td>";
        }else{
            echo "<td>0</td>";
        }
        echo "<td>{$row['awayTeamName']}</td>";
        if(isset ($row['awayTeamScore']))
        {
            echo "<td>".$row['awayTeamScore']."(".$row['awayTeamProjectedScore'].")"."</td>";
            if ($row['awayTeamScore'] - $row['awayTeamProjectedScore'] < 1 && $row['awayTeamScore'] - $row['awayTeamProjectedScore'] > -1) {
                $counterin1++;
            }
            if ($row['awayTeamScore'] - $row['awayTeamProjectedScore'] < 2 && $row['awayTeamScore'] - $row['awayTeamProjectedScore'] > -2) {
                $counterin2++;
            }

        }else{
            echo "<td>0</td>";
        }
        echo "</tr>";
        if($row['awayTeamProjectedScore']>45 && $row['awayTeamScore']<$row['homeTeamScore'] || $row['homeTeamProjectedScore']>45 && $row['homeTeamScore']<$row['awayTeamScore'])
        {$wrongwinner++;
        echo "WRONGWINNER DETECTED";}
    }
    echo "</table>";

}
echo "Close: ".$counterin1.'<br>';
echo "Close in 2: ".$counterin2.'<br>';
echo "Wrong Winner: ".$wrongwinner;
?>


