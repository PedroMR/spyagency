<?php
require_once __DIR__ . '/cards.php';

function calculate_scores(array &$game): array {
    $catalog = get_card_catalog();
    $scores = [];
    foreach ($game['players'] as &$p) {
        $stars = 0;
        $missions = 0;
        // Include base agent/tech in owned cards
        $base = $p['base'] ?? null;
        $base_cards = [];
        if (is_array($base)) {
            if ($base['agent'] ?? null) $base_cards[] = $base['agent'];
            foreach ($base['tech'] ?? [] as $t) $base_cards[] = $t;
        }
        $all_cards = array_merge($p['deck'], $p['hand'], $p['discard'], $p['play_area'], $base_cards);
        foreach ($all_cards as $card_id) {
            if (!isset($catalog[$card_id])) continue;
            if (($catalog[$card_id]['stars'] ?? 0) > 0) {
                $stars += $catalog[$card_id]['stars'];
            }
            if ($catalog[$card_id]['type'] === TYPE_MISSION) {
                $missions++;
            }
        }
        $p['stars'] = $stars;
        $scores[] = ['name' => $p['name'], 'stars' => $stars, 'missions' => $missions];
    }
    // Sort: most stars first; tiebreaker: fewest missions
    usort($scores, function ($a, $b) {
        if ($b['stars'] !== $a['stars']) return $b['stars'] - $a['stars'];
        return $a['missions'] - $b['missions'];
    });
    return $scores;
}

function check_game_end(array &$game): bool {
    // Check if any mission deck is empty or market deck is empty
    $trigger = false;
    if (empty($game['market_deck'])) {
        $trigger = true;
    }
    foreach ($game['mission_decks'] as $tier => $deck) {
        // Deck is empty AND the grid for that tier has been depleted
        if (empty($deck)) {
            $trigger = true;
        }
    }

    if ($trigger && !$game['final_round']) {
        $game['final_round'] = true;
        $game['final_round_starter'] = $game['current_player'];
        $game['log'][] = 'Final round triggered! Finishing the round so all players get equal turns.';
    }

    return $trigger;
}

function is_game_over(array &$game): bool {
    if (!$game['final_round']) return false;
    // Game is over when we've come back around to the player who triggered the final round
    // after going through all other players
    $num_players = count($game['players']);
    $next = ($game['current_player'] + 1) % $num_players;
    return $next === $game['final_round_starter'];
}

function finalize_game(array &$game): void {
    $game['ended'] = true;
    $game['status'] = 'finished';
    $game['scores'] = calculate_scores($game);
    $top = $game['scores'][0];
    $winners = array_values(array_filter($game['scores'], fn($s) =>
        $s['stars'] === $top['stars'] && $s['missions'] === $top['missions']
    ));
    if (count($winners) > 1) {
        $names = implode(' and ', array_map(fn($s) => $s['name'], $winners));
        $game['log'][] = "Game over! It's a tie between {$names} with {$top['stars']} stars and {$top['missions']} missions!";
    } else {
        $game['log'][] = "Game over! Winner: {$top['name']} with {$top['stars']} stars!";
    }
    $game['version']++;
}
