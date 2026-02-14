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
if (!$room) send_json(json_error('Room not found'));
if ($room['status'] !== 'waiting') send_json(json_error('Game already started'));

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
    send_json(json_error('Player not in room'));
}

// If room is now empty, delete it
if (empty($room['players'])) {
    @unlink($path);
    send_json(json_success());
}

// If the host left, promote the next player
if ($room['host'] === $token) {
    $room['host'] = $room['players'][0]['token'];
}

write_json($path, $room);
send_json(json_success());
