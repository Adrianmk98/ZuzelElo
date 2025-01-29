<style>
    /* Container for the team tables */
    .team-tables {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 20px; /* Adds padding around the entire container */
        background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
        border-radius: 10px; /* Rounded corners for the container */
        flex-wrap: wrap; /* Allow wrapping for smaller screens */
    }

    /* Styling for each individual team table */
    .team-table {
        flex: 1;
        max-width: 100%; /* Adjust width for desktop view */
        background-color: rgba(255, 255, 255, 0.8); /* Semi-transparent white for each table */
        border-radius: 10px; /* Rounded corners for each table */
        padding: 15px; /* Padding for each table */
        box-sizing: border-box; /* Ensures padding doesn't affect width */
    }

    /* Heading styling for each team table */
    .team-table h2 {
        text-align: center;
        background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent black background */
        color: white; /* White text */
        padding: 10px; /* Padding for header */
        border-radius: 5px; /* Rounded corners for header */
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7); /* Text shadow for contrast */
    }

    /* Table styling */
    .team-table table {
        border-collapse: collapse;
        width: 100%;
        margin-top: 10px;
    }

    /* Table header and data cell styling */
    .team-table table th, .team-table table td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: left;
        border-radius: 5px; /* Rounded corners for each cell */
    }

    /* Table header background */
    .team-table table th {
        background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent black background for headers */
        color: white; /* White text in headers */
    }

    /* Optional: Table row hover effect */
    .team-table table tr:hover {
        background-color: rgba(0, 0, 0, 0.1); /* Light background on row hover */
    }

    /* Responsive styles for smaller screens */
    @media (max-width: 768px) {
        .team-tables {
            flex-direction: column; /* Stack tables vertically */
        }

        .team-table {
            max-width: 100%; /* Full width for each table */
            margin-bottom: 20px; /* Add space between stacked tables */
        }
    }



</style><br><br>
<html>
<div class="team-tables">
    <?php

// Group players by team
$playersByTeam = [];
foreach ($playerPPOData as $player) {
    $playersByTeam[$player['teamID']][] = $player;
}


foreach ($playersByTeam as $teamID => $players) {
    $teamID = trim((string)$teamID); // Ensure consistent type and trim whitespace

    $teamClass = '';  // Reset class
    $teamPrefix = ''; // Reset prefix

    if ($teamID === $hometeamID) {
        $teamClass = 'home-team';
        $teamPrefix = 'H-';
    } elseif ($teamID === $awayteamID) {
        $teamClass = 'away-team';
        $teamPrefix = 'A-';
    } else {
        // If the team doesn't match either home or away, skip rendering
        continue;
    }

    echo "<div class='team-table $teamClass'>";  // Add home-team or away-team class
    echo "<h2>" . htmlspecialchars($teamPrefix . $players[0]['teamName']) . " (" . $cumulativeTeamPoints[$teamID] . ")</h2>";
    echo "<table>
    <thead>
    <tr>
        <th>Player</th>
        <th>Total Score</th>
        <th>Point Breakdown</th>
    </tr>
    </thead>
    <tbody>";

    foreach ($players as $player) {
        $playerID = $player['playerID'];
        $pointBreakdown = "N/A";
        if (!empty($player['pointBreakdown'])) {
            $pointBreakdown = implode(' , ', $player['pointBreakdown']);
        }

        echo "<tr>
        <td>" . htmlspecialchars($player['firstName'] . ' ' . $player['lastName']) . "</td>
        <td>" . htmlspecialchars($player['Score']);
        if ($player['Bonus']) {
            echo "<sup>+" . htmlspecialchars($player['Bonus']) . "</sup>";
        }
        echo "</td>
        <td>(" . htmlspecialchars($pointBreakdown) . ")</td>
    </tr>";
    }

    echo "</tbody>
</table>";
    echo "</div>"; // Close team-table
}
?>

    ?>
</div>
</html>