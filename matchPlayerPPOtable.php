<?php
usort($playerPPOData, function ($a, $b) {
    return $b['ppa'] <=> $a['ppa'];
});
?>
<h2>All Players Sorted by PPO</h2>
<table>
    <thead>
    <tr>
        <th>Player</th>
        <th>Team</th>
        <th>Total Score</th>
        <th>Total Projected Points</th>
        <th>Total PPO</th>
        <th>Total PPA</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($playerPPOData as $player): ?>
        <tr>
            <td><?= htmlspecialchars($player['firstName'] . ' ' . $player['lastName']); ?></td>
            <td><?= htmlspecialchars($player['teamName']); ?></td>
            <td><?= htmlspecialchars($player['Score']); ?>
                <sup><?php
                    if($player['Bonus'])
                    {echo("+".$player['Bonus']);} ?></sup></td>
            <td><?= round($player['projected_points'], 2); ?></td>
            <td><?= round($player['ppo'], 2); ?></td>
            <td><?= round($player['ppa'], 2); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>