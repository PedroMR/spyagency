<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../lib/game_ai.php';

$data = get_post();
$game_id = $data['game_id'] ?? '';

if (!$game_id) {
    send_json(json_error('Missing game_id'));
}

$result = read_game_locked($game_id, function (array &$game): array {
    return run_all_ai_actions($game);
});

send_json($result);
