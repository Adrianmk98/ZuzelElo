<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';

// Query to fetch ranked players by team
$sql = "WITH PlayerRanks AS (
    SELECT
        PlayerID,
        FirstName,
        LastName,
        YoB,
        teamID,
        Elo,
        RANK() OVER (PARTITION BY teamID ORDER BY Elo DESC) AS OverallRank,
        RANK() OVER (
            PARTITION BY teamID 
            ORDER BY CASE WHEN YEAR(CURDATE()) - YoB > 21 AND YEAR(CURDATE()) - YoB < 24 THEN Elo ELSE NULL END DESC
        ) AS Under24Over21Rank,
        RANK() OVER (
            PARTITION BY teamID 
            ORDER BY CASE WHEN YEAR(CURDATE()) - YoB > 24 THEN Elo ELSE NULL END DESC
        ) AS Over24Rank,
        RANK() OVER (
            PARTITION BY teamID 
            ORDER BY CASE WHEN YEAR(CURDATE()) - YoB < 21 THEN Elo ELSE NULL END DESC
        ) AS Under21Rank
    FROM player
)
SELECT 
    teamID,
    MAX(CASE WHEN Over24Rank = 1 THEN PlayerID END) AS id5,
    MAX(CASE WHEN Under24Over21Rank = 1 THEN PlayerID END) AS id4,
    MAX(CASE WHEN Over24Rank = 2 THEN PlayerID END) AS id1,
    MAX(CASE WHEN Over24Rank = 3 THEN PlayerID END) AS id2,
    MAX(CASE WHEN Over24Rank = 4 THEN PlayerID END) AS id3,
    MAX(CASE WHEN Under21Rank = 1 THEN PlayerID END) AS id6,
    MAX(CASE WHEN Under21Rank = 2 THEN PlayerID END) AS id7,
    MAX(CASE WHEN Under21Rank = 3 THEN PlayerID END) AS id8
FROM PlayerRanks WHERE teamID>0
GROUP BY teamID";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    ?>
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
<?php
    // Display results in an HTML table
    echo "<table border='1'>
            <tr>
                <th>Team ID</th>
                <th>ID1 (2nd Highest Over 24)</th>
                <th>ID2 (3rd Highest Over 24)</th>
                <th>ID3 (4th Highest Over 24)</th>
                <th>ID4 (Highest Elo Age 22-23)</th>
                <th>ID5 (Highest Elo Overall)</th>
                <th>ID6 (Highest Under 21)</th>
                <th>ID7 (2nd Highest Under 21)</th>
                <th>ID8 (3rd Highest Under 21)</th>
            </tr>";

    $updates = [];

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row['teamID'] . "</td>
                <td>" . $row['id1'] . "</td>
                <td>" . $row['id2'] . "</td>
                <td>" . $row['id3'] . "</td>
                <td>" . $row['id4'] . "</td>
                <td>" . $row['id5'] . "</td>
                <td>" . $row['id6'] . "</td>
                <td>" . $row['id7'] . "</td>
                <td>" . $row['id8'] . "</td>
              </tr>";
    }

    echo "</table>";

}
$conn->close();
?>
