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

// Compute live star count for a player from runtime counter + card-embedded stars
function compute_stars(array $player, array $catalog): int {
    $stars = $player['stars'] ?? 0; // segment rewards accumulated at runtime
    $all = array_merge($player['deck'], $player['hand'], $player['discard'], $player['play_area'], $player['mission_area'] ?? []);
    // Include cards in base
    $base = $player['base'] ?? null;
    if (is_array($base)) {
        if ($base['agent']) $all[] = $base['agent'];
        foreach ($base['tech'] ?? [] as $t) $all[] = $t;
    }
    foreach ($all as $cid) {
        if (isset($catalog[$cid]) && ($catalog[$cid]['stars'] ?? 0) > 0) {
            $stars += $catalog[$cid]['stars'];
        }
    }
    return $stars;
}

function normalize_base_for_client($raw): array {
    if (is_string($raw)) return ['card' => $raw, 'agent' => null, 'tech' => []];
    if (is_array($raw)) return $raw;
    return ['card' => 'safe_house', 'agent' => null, 'tech' => []];
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
            'deck' => $p['deck'],
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
            'pending_trashes' => $p['pending_trashes'] ?? 0,
            'base' => normalize_base_for_client($p['base'] ?? null),
            'mission_area' => $p['mission_area'] ?? [],
        ];
    } else {
        $players_data[] = [
            'name' => $p['name'],
            'is_me' => false,
            'is_ai' => $p['is_ai'] ?? false,
            'hand_count' => count($p['hand']),
            'deck_count' => count($p['deck']),
            'discard_count' => count($p['discard']),
            'play_area' => $p['play_area'],
            'money' => $p['money'],
            'gems' => $p['gems'] ?? 0,
            'stars' => $live_stars,
            'base' => normalize_base_for_client($p['base'] ?? null),
            'mission_area' => $p['mission_area'] ?? [],
        ];
    }
}

// Determine if any AI player needs to act
$needs_ai_action = false;
if (!($game['ended'] ?? false) && $game['status'] === 'active') {
    $cp = $game['current_player'];
    if (($game['players'][$cp]['is_ai'] ?? false) && !isset($game['pending_attack'])) {
        $needs_ai_action = true;
    }
    if (isset($game['pending_attack'])) {
        foreach ($game['pending_attack']['defenders'] as $di) {
            if (!isset($game['pending_attack']['responses'][$di]) && ($game['players'][$di]['is_ai'] ?? false)) {
                $needs_ai_action = true;
                break;
            }
        }
    }
}

$state = [
    'changed' => true,
    'version' => $game['version'],
    'needs_ai_action' => $needs_ai_action,
    'current_player' => $game['current_player'],
    'my_index' => $my_index,
    'is_my_turn' => $game['current_player'] === $my_index,
    'marketplace' => array_map(fn($stack) => !empty($stack) ? $stack[0] : null, $game['marketplace']),
    'marketplace_counts' => array_map(fn($stack) => count($stack), $game['marketplace']),
    'market_deck_count' => count($game['market_deck']),
    'ops_grid' => array_map(
        fn($slot) => is_array($slot) ? ($slot[0] ?? null) : $slot,
        $game['ops_grid']
    ),
    'ops_grid_counts' => array_map(
        fn($slot) => is_array($slot) ? count($slot) : 1,
        $game['ops_grid']
    ),
    'ops_deck_count' => count($game['ops_deck']),
    'players' => $players_data,
    'log' => array_slice($game['log'], -2000),
    'status' => $game['status'],
    'ended' => $game['ended'] ?? false,
    'final_round' => $game['final_round'] ?? false,
    'scores' => $game['scores'] ?? null,
    'turn_number' => $game['turn_number'] ?? 1,
    'round' => $game['round'] ?? 1,
    'catalog' => $catalog,
    'rematch_vote_count' => count(array_filter(
        $game['rematch_votes'] ?? [],
        fn($_, $tok) => !in_array($tok, array_map(fn($p) => $p['token'], array_filter($game['players'], fn($p) => $p['is_ai'] ?? false))),
        ARRAY_FILTER_USE_BOTH
    )),
    'rematch_human_total' => count(array_filter($game['players'], fn($p) => !($p['is_ai'] ?? false))),
    'rematch_my_vote' => isset(($game['rematch_votes'] ?? [])[$token]),
    'rematch_game_id' => $game['rematch_game_id'] ?? null,
    'room_id' => $game['room_id'] ?? null,
    'attack_pending' => isset($game['pending_attack']),
    'attack_card' => isset($game['pending_attack']) ? $game['pending_attack']['card'] : null,
    'attack_attacker_name' => isset($game['pending_attack']) ? $game['players'][$game['pending_attack']['attacker']]['name'] : null,
    'attack_must_defend' => isset($game['pending_attack']) && in_array($my_index, $game['pending_attack']['defenders']) && !isset($game['pending_attack']['responses'][$my_index]),
    'attack_defenders_remaining' => isset($game['pending_attack']) ? count($game['pending_attack']['defenders']) - count($game['pending_attack']['responses']) : 0,
];

send_json(json_success($state));
