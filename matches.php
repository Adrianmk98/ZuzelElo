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
    body { font-family: Arial, sans-serif;  margin: 0; padding: 20px; }
    .match-header {
        background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent black background */
        color: white; /* Text color */
        padding: 10px 20px; /* Adds some space around the text */
        border-radius: 5px; /* Optional: rounds the corners for a softer look */
        font-size: 18px; /* Optional: adjust the font size */
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7); /* Optional: adds a shadow behind the text to make it stand out */
        text-align: center;

    }

    .heat-table { margin-bottom: 40px; }
</style>
</head>
<body>
<?php
$counterin1=0;
$counterin2=0;
$wrongwinner=0;
// Display matches grouped by week
foreach ($matchesByWeek as $weekHeader => $matches) {
    echo "<div class='match-header'><strong>$weekHeader</strong></div><br>";
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
        //echo "WRONGWINNER DETECTED";
            }
    }
    echo "</table>";

}
//echo "Close: ".$counterin1.'<br>';
//echo "Close in 2: ".$counterin2.'<br>';
//echo "Wrong Winner: ".$wrongwinner;
?>

</body>
</html>
