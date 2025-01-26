<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';

// Get the player ID from the URL parameter (e.g., player_profile.php?id=1)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $teamID = $_GET['id'];
} else {
    die("Player ID is required.");
}

// Query to fetch ranked players by team
$sql = "SELECT
        id1,id2,id3,id4,id5,id6,id7,id8
FROM futurematchroster WHERE teamID=$teamID
GROUP BY teamID";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    ?>
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
    <style>
        table {
            background-color: #fff; /* White background for the table */
            margin: 0 auto; /* Centers the table horizontally */
            border-collapse: collapse; /* Ensures borders between table cells collapse into a single border */
            width: 50%; /* Adjust the width to control the table's size */
        }
        .no-link {
            text-decoration: none;
            color: inherit;
        }
    </style>
    <h2>Projected Roster</h2>
<?php
    echo "<table border='1'>
        <tr>
        <th>Slot</th>
            <th>Name</th>
        </tr>";

    while ($row = $result->fetch_assoc()) {
        // Array to hold all the player IDs
        $playerIDs = [$row['id1'], $row['id2'], $row['id3'], $row['id4'], $row['id5'], $row['id6'], $row['id7'], $row['id8']];

        // Prepare the query to fetch player details for all IDs at once
        $placeholders = implode(',', array_fill(0, count($playerIDs), '?'));
        $query = "SELECT playerID, FirstName, LastName FROM player WHERE playerID IN ($placeholders)";
        $stmt = $conn->prepare($query);

        // Bind parameters dynamically
        $types = str_repeat('i', count($playerIDs)); // 'i' for integer type
        $stmt->bind_param($types, ...$playerIDs);

        // Execute the query
        $stmt->execute();

        // Fetch all the player details
        $players = [];
        $stmt->bind_result($playerID, $firstName, $lastName);
        while ($stmt->fetch()) {
            $players[$playerID] = [$firstName, $lastName];
        }

        // Display player names
        $counter = 1;
        foreach ($playerIDs as $id) {
            if (isset($players[$id])) {
                list($firstName, $lastName) = $players[$id];
                echo "<tr>
                    <td>$counter</td><td> $firstName $lastName</td>
                  </tr>";
                $counter++;
            }
        }
    }

    echo "</table>";

}
?>
<h2>Full Roster</h2>
<?php
$fullRosterQuery = "SELECT PlayerID, FirstName, LastName, YoB, teamID,Elo FROM player WHERE teamID=$teamID ORDER BY Elo DESC";
$rosterResult = $conn->query($fullRosterQuery);

// Check if the query was successful
if (!$rosterResult) {
    // Output the error if the query fails
    die("Query failed: " . $conn->error);
}

// Check if there are any rows returned
if ($rosterResult->num_rows > 0) {
    echo "<table border='1'>
        <tr>
            <th>Name</th>
            <th>Year of Birth(Age this Year)</th>
            <th>Elo</th>
        </tr>";

    while ($row = $rosterResult->fetch_assoc()) {
        ?>
<tr>
                <td><a class="no-link" href="profile.php?id=<?php echo $row['PlayerID']; ?>">
                    <?php echo $row['FirstName'] . ' ' . $row['LastName']; ?></td>
        <?php
                echo"<td>".$row['YoB'] ."(".date('Y')-$row['YoB'].")". "</td>
                <td>" . $row['Elo'] . "</td>
              </tr>";
    }
    echo "</table><br><br>";
} else {
    echo "No results found.";
}




$conn->close();

?>
