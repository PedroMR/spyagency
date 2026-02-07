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

    // Fill marketplace: 5 cards
    $marketplace = [];
    for ($i = 0; $i < 5 && count($market_deck) > 0; $i++) {
        $marketplace[] = array_shift($market_deck);
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
                ['type' => 'hideaway', 'agent' => null, 'tech' => []],
            ],
            'money' => 0,
            'stars' => 0,
            'extra_base_count' => 0,
            'backup_agent' => null, // for Got Your Back!
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
