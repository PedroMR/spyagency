<?php
require_once __DIR__ . '/cards.php';

function init_game(array $players): array {
    $market_deck = build_market_deck();
    $mission_decks = build_mission_decks();

    // Fill mission grid: 3 slots per tier, each slot is a stack (array of card IDs)
    // When a drawn card matches an existing slot, stack it and draw again
    $mission_grid = [1 => [], 2 => [], 3 => []];
    foreach ([1, 2, 3] as $tier) {
        while (count($mission_grid[$tier]) < 3 && !empty($mission_decks[$tier])) {
            $drawn = array_shift($mission_decks[$tier]);
            $stacked = false;
            foreach ($mission_grid[$tier] as &$slot) {
                if (!empty($slot) && $slot[0] === $drawn) {
                    $slot[] = $drawn;
                    $stacked = true;
                    break;
                }
            }
            unset($slot);
            if (!$stacked) {
                $mission_grid[$tier][] = [$drawn];
            }
        }
    }

    // Fill marketplace: 7 slots, each is a stack (array of card IDs)
    // When a drawn card matches an existing slot, stack it and draw again
    $marketplace = [[], [], [], [], [], [], []];
    for ($i = 0; $i < 7; $i++) {
        while (!empty($market_deck)) {
            $drawn = array_shift($market_deck);
            // Check if this card matches an existing slot
            $stacked = false;
            for ($j = 0; $j < $i; $j++) {
                if (!empty($marketplace[$j]) && $marketplace[$j][0] === $drawn) {
                    $marketplace[$j][] = $drawn;
                    $stacked = true;
                    break;
                }
            }
            if (!$stacked) {
                $marketplace[$i][] = $drawn;
                break;
            }
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
            'is_ai' => $player['is_ai'] ?? false,
            'deck' => $starter,
            'hand' => $hand,
            'discard' => [],
            'play_area' => [],
            'money' => 0,
            'gems' => 0,
            'stars' => 0,
            'buys_this_turn' => 0,
            'backup_agent' => null, // for Got Your Back!
            'extra_missions' => 0,
            'missions_this_turn' => 0,
            'extra_buys' => 0,
            'base' => ['card' => 'safe_house', 'agent' => null, 'tech' => []],
        ];
    }

    $first_player = random_int(0, count($player_states) - 1);

    $game = [
        'current_player' => $first_player,
        'first_player' => $first_player,
        'market_deck' => $market_deck,
        'marketplace' => $marketplace,
        'mission_decks' => $mission_decks,
        'mission_grid' => $mission_grid,
        'players' => $player_states,
        'version' => 1,
        'log' => ['Game started!', $player_states[$first_player]['name'] . ' goes first.'],
        'status' => 'active',
        'turn_number' => 1,
        'round' => 1,
        'final_round' => false,
        'final_round_starter' => null,
        'ended' => false,
    ];

    return $game;
}
