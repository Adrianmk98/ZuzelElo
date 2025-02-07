<?php
include 'includes/sqlCall.php';
include 'includes/topbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Match Details</title>
    <link rel="stylesheet" href="includes/tableStyle.css">
    <link rel="stylesheet" href="includes/headerStyle.css">
    <style>

        .match-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .scoreboard {
            display: inline-block;
            width: auto;
            background: #f4f4f4;
            color: #333;
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 15px;
            text-align: center;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Align items at the top */
            padding: 10px;
        }

        .team {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }

        .team-label {
            font-size: 1.5em;
            font-weight: bold;
            color: #444;
            margin-bottom: 5px;
        }

        .team-name {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 5px;
            text-align: center;
            word-wrap: break-word;
            overflow: hidden;
            line-height: 1.4em; /* Consistent line spacing */
            height: 2.8em; /* Reserve space for up to two lines of text */
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .score {
            font-size: 1.8em;
            font-weight: bold;
            color: #0056b3;
            margin-top: 5px;
            min-height: 1.8em; /* Ensure consistent height for the score block */
        }



        .vs {
            font-size: 1.2em;
            color: #666;
            margin: 0 15px;
        }

        .details {
            margin-top: 15px;
            display: flex;
            justify-content: space-around;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .info {
            font-size: 1em;
        }

        .label {
            color: #666;
        }

        .value {
            font-weight: bold;
            color: #333;
        }
        .card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 5px; }



    </style>
</head>
<body>
<div class="container">
    <h1>Formula Reference</h1>

    <div class="card">
        <h2>Elo Rating System</h2>
        <p>The Elo rating system calculates the expected probability of a player winning a race using the formula:</p>
        <p><code>P(A) = \frac{1}{1 + 10^{(R_B - R_A) / 1600}}</code></p>
        <p>Where:</p>
        <ul>
            <li><code>P(A)</code> = Probability of player A winning</li>
            <li><code>R_A</code> = Elo rating of player A</li>
            <li><code>R_B</code> = Elo rating of player B</li>
            <li><code>R_C</code> = Elo rating of player C</li>
            <li><code>R_D</code> = Elo rating of player D</li>
        </ul>
        <p>Since Speedway races consist of 4 players. For each race, we must calculate A vs {B,C,D} followed by similar pairwise compairsons with
        the other players</p>
    </div>

    <div class="card">
        <h2>Elo Rating Adjustment</h2>
        <p>Before a match Elos are adjusted for a variety of factors:</p>
        <p><code>R_A' = R_A * 1.05</code></p>
        <p>Where:</p>
        <ul>
            <li>Player is on the Home team</li>
        </ul>
        <p><code>R_A' = R_A * 0.95</code></p>
        <p>Where:</p>
        <ul>
            <li>Player is on the Away team</li>
        </ul>
        <p>During a match Elos are adjusted for a variety of factors:</p>
        <p><code>R_A' = R_A+(R_A*((PPO / 100) * 4))</code></p>
        <p>Where:</p>
        <ul>
            <li>PAP is how well a player is overperforming their projections in a given match. This only applies if there are concrete results, since it uses Points vs Expected points in order to be calculated </li>
        </ul>
    </div>

    <div class="card">
        <h2>Monte Carlo Simulation</h2>
        <p>Monte Carlo simulations involve running multiple trials to estimate outcomes. For example:</p>
        <p><code>average_result = \frac{\sum trials}{total_trials}</code></p>
    </div>

    <div class="card">
        <h2>Points Above Average (PAA)</h2>
        <p>PAA is calculated by comparing a player's performance to the league average:</p>
        <p><code>PAA_A = PlayerScore - LAS</code></p>
        <p>Where:</p>
        <ul>
            <li>LAS is LeagueAverageScore</li>
            <li>LAS for that particular player in the consists of all players for Seniors and only for Youth Players among Youth players but with the player replaced with the average player</li>
        </ul>
    </div>

    <div class="card">
        <h2>Points Above Projection (PPO)</h2>
        <p>PPO is calculated by comparing a player's performance to their projection:</p>
        <p><code>PPO_A = Score - ExpectedScore</code></p>
        <p>Where:</p>
        <ul>
            <li> is LeagueAverageScore</li>
            <li>LAS for that particular player in the consists of all players for Seniors and only for Youth Players among Youth players but with the player replaced with the average player</li>
        </ul>
    </div>


</div>
</body>
</html>
