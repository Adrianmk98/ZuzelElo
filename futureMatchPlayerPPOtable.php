<?php
usort($playerPPOData, function ($a, $b) {
    return $b['teamName'] <=> $a['teamName'];
});
?>
<h2>All Players Sorted by Score</h2>
<table border='1'>
    <thead>
    <tr>
        <th>Player</th>
        <th>Team Name</th>
        <th>Total Score</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($playerPPOData as $player): ?>
        <tr>
            <td><?= htmlspecialchars($player['firstName'] . ' ' . $player['lastName']); ?></td>
            <td><?= htmlspecialchars($player['teamName']); ?>
            <td><?= htmlspecialchars($player['Score']); ?>
                <sup><?php
                    if($player['Bonus'])
                    {echo("+".$player['Bonus']);} ?></sup></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>