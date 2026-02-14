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

// Compute live star count for a player from all owned cards
function compute_stars(array $player, array $catalog): int {
    $stars = 0;
    $all = array_merge($player['deck'], $player['hand'], $player['discard'], $player['play_area']);
    foreach ($all as $cid) {
        if (isset($catalog[$cid]) && ($catalog[$cid]['stars'] ?? 0) > 0) {
            $stars += $catalog[$cid]['stars'];
        }
    }
    return $stars;
}

// Build filtered player data
$players_data = [];
foreach ($game['players'] as $i => $p) {
    $live_stars = compute_stars($p, $catalog);
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
            'money' => $p['money'],
            'gems' => $p['gems'] ?? 0,
            'stars' => $live_stars,
            'buys_this_turn' => $p['buys_this_turn'] ?? 0,
            'extra_buys' => $p['extra_buys'] ?? 0,
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
            'money' => $p['money'],
            'gems' => $p['gems'] ?? 0,
            'stars' => $live_stars,
        ];
    }
}

$state = [
    'changed' => true,
    'version' => $game['version'],
    'current_player' => $game['current_player'],
    'my_index' => $my_index,
    'is_my_turn' => $game['current_player'] === $my_index,
    'marketplace' => array_map(fn($stack) => !empty($stack) ? $stack[0] : null, $game['marketplace']),
    'marketplace_counts' => array_map(fn($stack) => count($stack), $game['marketplace']),
    'market_deck_count' => count($game['market_deck']),
    'mission_grid' => array_map(fn($tier_slots) => array_map(
        fn($slot) => is_array($slot) ? ($slot[0] ?? null) : $slot,
        $tier_slots
    ), $game['mission_grid']),
    'mission_grid_counts' => array_map(fn($tier_slots) => array_map(
        fn($slot) => is_array($slot) ? count($slot) : 1,
        $tier_slots
    ), $game['mission_grid']),
    'mission_deck_counts' => [
        1 => count($game['mission_decks'][1]),
        2 => count($game['mission_decks'][2]),
        3 => count($game['mission_decks'][3]),
    ],
    'players' => $players_data,
    'log' => array_slice($game['log'], -2000),
    'status' => $game['status'],
    'ended' => $game['ended'] ?? false,
    'final_round' => $game['final_round'] ?? false,
    'scores' => $game['scores'] ?? null,
    'turn_number' => $game['turn_number'] ?? 1,
    'round' => $game['round'] ?? 1,
    'catalog' => $catalog,
    'rematch_vote_count' => count($game['rematch_votes'] ?? []),
    'rematch_my_vote' => isset(($game['rematch_votes'] ?? [])[$token]),
    'rematch_game_id' => $game['rematch_game_id'] ?? null,
    'attack_pending' => isset($game['pending_attack']),
    'attack_card' => isset($game['pending_attack']) ? $game['pending_attack']['card'] : null,
    'attack_attacker_name' => isset($game['pending_attack']) ? $game['players'][$game['pending_attack']['attacker']]['name'] : null,
    'attack_must_defend' => isset($game['pending_attack']) && in_array($my_index, $game['pending_attack']['defenders']) && !isset($game['pending_attack']['responses'][$my_index]),
    'attack_defenders_remaining' => isset($game['pending_attack']) ? count($game['pending_attack']['defenders']) - count($game['pending_attack']['responses']) : 0,
];

send_json(json_success($state));
