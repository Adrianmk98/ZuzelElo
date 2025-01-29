<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
</head>
<style>
    /* Container for the team tables */
    .team-tables {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 20px;
        background-color: rgba(0, 0, 0, 0.5);
        border-radius: 10px;
        flex-wrap: wrap;
        position: relative;
    }

    /* Individual team table styling */
    .team-table {
        flex: 1;
        max-width: 100%;
        background-color: rgba(255, 255, 255, 0.8);
        border-radius: 10px;
        padding: 15px;
        box-sizing: border-box;
        position: relative;
    }

    /* Header section for team logo, score, and name */
    .team-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
    }

    /* Align logo and score horizontally */
    .team-logo-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Team logo styling */
    .team-logo {
        max-width: 128px;
        max-height: 128px;
    }

    /* Score next to the logo */
    .team-score {
        font-size: 24px;
        font-weight: bold;
        color: #fff;
        background-color: rgba(0, 0, 0, 0.7); /* Dark background for contrast */
        padding: 10px 15px;
        border-radius: 8px;
        display: inline-block;
        min-width: 50px;
        text-align: center;
    }

    /* House emoji in the top right */
    .home-icon {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 24px;
    }

    /* Team name styling */
    .team-table h2 {
        text-align: center;
        background-color: rgba(0, 0, 0, 0.5);
        color: white;
        padding: 10px;
        border-radius: 5px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
        width: 100%;
    }

    /* Table styling */
    .team-table table {
        border-collapse: collapse;
        width: 100%;
        margin-top: 10px;
    }

    .team-table table th, .team-table table td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: left;
        border-radius: 5px;
    }

    /* Table header background */
    .team-table table th {
        background-color: rgba(0, 0, 0, 0.5);
        color: white;
    }

    /* Row hover effect */
    .team-table table tr:hover {
        background-color: rgba(0, 0, 0, 0.1);
    }

    /* Responsive design for smaller screens */
    @media (max-width: 768px) {
        .team-tables {
            flex-direction: column;
        }

        .team-table {
            max-width: 100%;
            margin-bottom: 20px;
        }
    }

    /* Button styling */
    #download-btn {
        background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
        color: white; /* Text color */
        padding: 15px 30px; /* Padding for better button size */
        border-radius: 5px; /* Rounded corners */
        font-size: 18px; /* Font size */
        cursor: pointer; /* Adds pointer cursor on hover */
        text-align: center;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7); /* Text shadow for contrast */
    }



</style><br><br>
<html>
<div class="team-tables" id="teamTables">
    <?php

// Group players by team
$playersByTeam = [];
foreach ($playerPPOData as $player) {
    $playersByTeam[$player['teamID']][] = $player;
}


foreach ($playersByTeam as $teamID => $players) {
    $teamID = trim((string)$teamID); // Ensure consistent type and trim whitespace

    echo "<div class='team-table'>";

    // Team logo
    $logoPath = "teamlogos/$teamID.jpg";
    if (!file_exists($logoPath)) {
        $logoPath = "teamlogos/0.jpg";
    }

    echo "<div class='team-header'>";
    echo "<div class='team-logo-container'>";
    echo "<img src='$logoPath' class='team-logo' alt='Team Logo'>";
    echo "<div class='team-score'>" . $cumulativeSingleTeamPoints[$teamID] . "</div>";
    echo "</div>";

    if ($teamID === $hometeamID) {
        echo "<div class='home-icon'>🏠</div>";
    }

    echo "<h2>" . htmlspecialchars($players[0]['teamName']) . "</h2>";
    echo "</div>";

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
        <td>" . htmlspecialchars($player['firstName']) . "<br>" . htmlspecialchars($player['lastName']) . "</td>
        <td>" . htmlspecialchars($player['Score']);
        if ($player['Bonus']) {
            echo "<sup>+" . htmlspecialchars($player['Bonus']) . "</sup>";
        }
        echo "</td>
        <td style='font-size: 12px'>(" . htmlspecialchars($pointBreakdown) . ")</td>
    </tr>";
    }

    echo "</tbody>
</table>";
    echo "</div>"; // Close team-table
}
?>

    <button id="download-btn"
            data-hometeam="<?php echo $match_details['homeTeamName']; ?>"
            data-awayteam="<?php echo $match_details['awayTeamName']; ?>"
            onclick="downloadImage()">Download
    </button>


    <script>
        function downloadImage() {
            const teamTables = document.getElementById("teamTables");

            // Get the home and away team names from the button's data attributes
            const button = document.getElementById("download-btn");
            const homeTeam = button.getAttribute("data-hometeam").replace(/\s+/g, "_"); // Replace spaces with underscores
            const awayTeam = button.getAttribute("data-awayteam").replace(/\s+/g, "_");

            // Generate the filename dynamically
            const fileName = `${homeTeam}_vs_${awayTeam}.jpg`;

            html2canvas(teamTables, { backgroundColor: null }).then(canvas => {
                let link = document.createElement("a");
                link.href = canvas.toDataURL("image/jpeg", 1.0);
                link.download = fileName;
                link.click();
            });
        }

    </script>
</div>
</html>