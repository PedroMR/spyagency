<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../lib/cards.php';

$game_id = $_GET['game_id'] ?? '';
$token = $_GET['token'] ?? '';
$client_version = (int)($_GET['version'] ?? 0);

if (!$game_id || !$token) {
    send_json(json_error('Missing game_id or token'));
}

$game = read_json(data_path('games', $game_id));
if (!$game) send_json(json_error('Game not found'));

// If client is up to date, return no change
if ($client_version >= $game['version']) {
    send_json(json_success(['changed' => false, 'version' => $game['version']]));
}

$catalog = get_card_catalog();

// Filter state by player
$my_index = -1;
foreach ($game['players'] as $i => $p) {
    if ($p['token'] === $token) {
        $my_index = $i;
        break;
    }
}

if ($my_index < 0) {
    send_json(json_error('Player not in game'));
}

// Build filtered player data
$players_data = [];
foreach ($game['players'] as $i => $p) {
    if ($i === $my_index) {
        $players_data[] = [
            'name' => $p['name'],
            'is_me' => true,
            'hand' => $p['hand'],
            'hand_count' => count($p['hand']),
            'deck_count' => count($p['deck']),
            'discard_count' => count($p['discard']),
            'discard' => $p['discard'],
            'play_area' => $p['play_area'],
            'bases' => $p['bases'],
            'money' => $p['money'],
            'stars' => $p['stars'],
            'extra_base_count' => $p['extra_base_count'],
            'backup_agent' => $p['backup_agent'] ?? null,
            'extra_missions' => $p['extra_missions'] ?? 0,
            'missions_this_turn' => $p['missions_this_turn'] ?? 0,
        ];
    } else {
        $players_data[] = [
            'name' => $p['name'],
            'is_me' => false,
            'hand_count' => count($p['hand']),
            'deck_count' => count($p['deck']),
            'discard_count' => count($p['discard']),
            'play_area' => $p['play_area'],
            'bases' => $p['bases'],
            'money' => $p['money'],
            'stars' => $p['stars'],
            'extra_base_count' => $p['extra_base_count'],
        ];
    }
}

$state = [
    'changed' => true,
    'version' => $game['version'],
    'current_player' => $game['current_player'],
    'my_index' => $my_index,
    'is_my_turn' => $game['current_player'] === $my_index,
    'marketplace' => $game['marketplace'],
    'market_deck_count' => count($game['market_deck']),
    'mission_grid' => $game['mission_grid'],
    'mission_deck_counts' => [
        1 => count($game['mission_decks'][1]),
        2 => count($game['mission_decks'][2]),
        3 => count($game['mission_decks'][3]),
    ],
    'players' => $players_data,
    'log' => array_slice($game['log'], -20),
    'status' => $game['status'],
    'ended' => $game['ended'] ?? false,
    'final_round' => $game['final_round'] ?? false,
    'scores' => $game['scores'] ?? null,
    'turn_number' => $game['turn_number'] ?? 1,
    'catalog' => $catalog,
];

send_json(json_success($state));
