<style>
    .team-tables {
        display: flex;
        justify-content: space-between;
        gap: 20px;
    }

    .team-table {
        flex: 1;
        max-width: 48%; /* Adjust width if needed */
    }

    .team-table h2 {
        text-align: center;
    }

    .team-table table {
        border-collapse: collapse;
        width: 100%;
        margin-top: 10px;
    }

    .team-table table th, .team-table table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }

    .team-table table th {
        background-color: #f4f4f4;
    }

</style>
<div class="team-tables">
    <?php
    // Sort players by playerMatchNum
    usort($playerPPOData, function ($a, $b) {
        return $a['playerMatchNum'] <=> $b['playerMatchNum'];
    });

    // Group players by team
    $playersByTeam = [];
    foreach ($playerPPOData as $player) {
        $playersByTeam[$player['teamName']][] = $player;
    }

    // Generate tables for each team
    foreach ($playersByTeam as $teamName => $players) {
        echo "<div class='team-table'>";
        echo "<h2>" . htmlspecialchars($teamName) . "</h2>";
        echo "<table>
        <thead>
        <tr>
            <th>Nr</th>
            <th>Player</th>
            <th>Total Score</th>
            <th>Point Breakdown</th>
        </tr>
        </thead>
        <tbody>";

        foreach ($players as $player) {
            $playerID = $player['playerID'];
            $pointBreakdown = "N/A";
            foreach ($playerPPOData as $playerData) {
                if ($playerData['playerID'] == $playerID) {
                    $pointBreakdown = implode(' , ', $playerData['pointBreakdown']);
                    break;
                }
            }

            echo "<tr>
            <td>" . $player['playerMatchNum'] . "</td>
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
</div>
