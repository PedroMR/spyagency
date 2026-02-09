<?php
require_once __DIR__ . '/cards.php';

function init_game(array $players): array {
    $market_deck = build_market_deck();
    $mission_decks = build_mission_decks();

    // Fill mission grid: 3 from each tier
    $mission_grid = [1 => [], 2 => [], 3 => []];
    foreach ([1, 2, 3] as $tier) {
        for ($i = 0; $i < 3 && count($mission_decks[$tier]) > 0; $i++) {
            $mission_grid[$tier][] = array_shift($mission_decks[$tier]);
        }
    }

    // Fill marketplace: 5 unique cards (dedup: if drawn card matches one already present, put back and draw again)
    $marketplace = [];
    for ($i = 0; $i < 5; $i++) {
        if (empty($market_deck)) break;
        $drawn = array_shift($market_deck);
        if (in_array($drawn, $marketplace)) {
            // Try to find a different card
            $market_deck[] = $drawn; // put to bottom
            $found = false;
            $attempts = 0;
            while (!empty($market_deck) && $attempts < count($market_deck)) {
                $next = array_shift($market_deck);
                if (!in_array($next, $marketplace)) {
                    $marketplace[] = $next;
                    $found = true;
                    break;
                }
                $market_deck[] = $next;
                $attempts++;
            }
            if (!$found) {
                // All remaining are duplicates, just place one
                $marketplace[] = array_shift($market_deck);
            }
        } else {
            $marketplace[] = $drawn;
        }
    }

    // Init player states
    $player_states = [];
    foreach ($players as $idx => $player) {
        $starter = get_starter_deck();
        $hand = array_splice($starter, 0, 5);
        $player_states[] = [
            'token' => $player['token'],
            'name' => $player['name'],
            'deck' => $starter,
            'hand' => $hand,
            'discard' => [],
            'play_area' => [],
            'bases' => [
                ['type' => 'safehouse', 'agent' => null, 'tech' => []],
                ['type' => 'safehouse', 'agent' => null, 'tech' => []],
            ],
            'money' => 0,
            'stars' => 0,
            'extra_base_count' => 0,
            'backup_agent' => null, // for Got Your Back!
            'extra_missions' => 0,
            'missions_this_turn' => 0,
        ];
    }

    return [
        'current_player' => 0,
        'market_deck' => $market_deck,
        'marketplace' => $marketplace,
        'mission_decks' => $mission_decks,
        'mission_grid' => $mission_grid,
        'players' => $player_states,
        'version' => 1,
        'log' => ['Game started!'],
        'status' => 'active',
        'turn_number' => 1,
        'final_round' => false,
        'final_round_starter' => null,
        'ended' => false,
    ];
}
