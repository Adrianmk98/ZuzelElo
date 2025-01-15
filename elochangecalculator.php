<?php

// Function to calculate the Probability
function probability($rating1, $rating2) {
    $prob = 1.0 / (1 + pow(10, ($rating2 - $rating1) / 400.0));
    return $prob;
}

// Function to calculate Elo ratings for 4 players
function elo_rating_4way($playerIDs, $ratings, $outcomes,$week) {
    // $ratings: Array of current ratings for 4 players [R1, R2, R3, R4]
    // $K: The constant used in Elo rating adjustment
    // $outcomes: Array of scores for 4 players [S1, S2, S3, S4], where scores represent match results

    $n = count($ratings);
    $K = 12;
    $updated_ratings = $ratings;

    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if ($i != $j) { // Avoid self-comparison
                $expected_score = probability($ratings[$i], $ratings[$j]);
                $actual_score = ($outcomes[$i] - $outcomes[$j] > 0) ? 1 : 0;
                $adjustment = $K * ($actual_score - $expected_score);
                $earlySeasonModifier=(2/$week)+1;
                $updated_ratings[$i] += $adjustment*$earlySeasonModifier;
            }
        }
    }

    // Generate results for better referencing
    $results = [];
    foreach ($playerIDs as $index => $id) {
        $results[$id] = [
            'oldelo' => $ratings[$index],
            'newelo' => $updated_ratings[$index]
        ];

    }

    return $results;
}

?>
