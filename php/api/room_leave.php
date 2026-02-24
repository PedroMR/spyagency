<?php
require_once __DIR__ . '/helpers.php';

$data = get_post();
$room_id = $data['room_id'] ?? '';
$token = $data['token'] ?? '';

if (!$room_id || !$token) {
    send_json(json_error('Missing room_id or token'));
}

$path = data_path('rooms', $room_id);
$room = read_json($path);
if (!$room) send_json(json_success()); // already gone, that's fine

// Find and remove the player
$found = false;
$room['players'] = array_values(array_filter($room['players'], function($p) use ($token, &$found) {
    if ($p['token'] === $token) {
        $found = true;
        return false;
    }
    return true;
}));

if (!$found) {
    send_json(json_success()); // not in room, nothing to do
}

// If room is now empty, delete it and all associated game files
if (empty($room['players'])) {
    @unlink($path);
    if ($room['game_id'] ?? null) {
        @unlink(data_path('games', $room['game_id']));
    }
    foreach ($room['previous_game_ids'] ?? [] as $old_id) {
        @unlink(data_path('games', $old_id));
    }
    send_json(json_success());
}

// For waiting rooms: promote host if they left
if ($room['status'] === 'waiting' && $room['host'] === $token) {
    $room['host'] = $room['players'][0]['token'];
}

write_json($path, $room);
send_json(json_success());
