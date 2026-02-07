<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../lib/game_logic.php';

$data = get_post();
$game_id = $data['game_id'] ?? '';
$token = $data['token'] ?? '';
$action = $data['action'] ?? '';

if (!$game_id || !$token || !$action) {
    send_json(json_error('Missing game_id, token, or action'));
}

$result = read_game_locked($game_id, function(&$game) use ($token, $action, $data) {
    $params = $data['params'] ?? [];
    return process_action($game, $token, $action, $params);
});

send_json($result);
