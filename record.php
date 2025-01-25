<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';
try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query to get distinct seasons
    $yearquery = "SELECT DISTINCT yearnum FROM matches ORDER BY yearnum ASC";
    $stmt = $pdo->prepare($yearquery);
    $stmt->execute();
    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
    <style>
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #f4f4f4; }
        h2 { text-align: center; margin-top: 20px; }
    </style>
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
<?php
    echo '<h2>Seasonal Summary</h2>';
    echo '<table>
            <thead>
                <tr>
                    <th>Year</th>
                    <th>Correct</th>
                    <th>Within 1</th>
                    <th>Within 2</th>
                    <th>Wrong Winner</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($years as $year) {
        // Query to fetch match details for the given year
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
        WHERE m.yearnum = :year
        ORDER BY m.weeknum";

        $stmt = $pdo->prepare($match_details_query);
        $stmt->execute([':year' => $year]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Initialize counters for this year
        $close = 0;
        $closein2 = 0;
        $wrongwinner = 0;
        $correctwinner=0;

        foreach ($matches as $row) {
            if (isset($row['awayTeamScore']) && isset($row['homeTeamScore'])) {
                // Count close matches
                if (abs($row['awayTeamScore'] - $row['awayTeamProjectedScore']) < 1) {
                    $close++;
                }
                if (abs($row['awayTeamScore'] - $row['awayTeamProjectedScore']) < 2) {
                    $closein2++;
                }

                // Count wrong winner cases
                if (
                    ($row['awayTeamProjectedScore'] > 45 && $row['awayTeamScore'] < $row['homeTeamScore']) ||
                    ($row['homeTeamProjectedScore'] > 45 && $row['homeTeamScore'] < $row['awayTeamScore'])
                ) {
                    $wrongwinner++;
                }else{
                    $correctwinner++;
                }
            }
        }

        // Output the results for this year
        echo "<tr>
                <td>{$year}</td>
                <td>{$correctwinner}</td>
                <td>{$close}</td>
                <td>{$closein2}</td>
                <td>{$wrongwinner}</td>
              </tr>";
    }

    echo '</tbody></table>';
} catch (PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage());
}
?>