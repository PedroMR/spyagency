<?php
require_once __DIR__ . '/helpers.php';

$data = get_post();
$room_id = $data['room_id'] ?? '';
$player_name = trim($data['player_name'] ?? '');
$token = $data['token'] ?? '';

if (!$room_id || !$player_name || !$token) {
    send_json(json_error('Missing room_id, player_name, or token'));
}

$path = data_path('rooms', $room_id);
$room = read_json($path);
if (!$room) send_json(json_error('Room not found'));
if ($room['status'] !== 'waiting') send_json(json_error('Game already started'));
if (count($room['players']) >= 4) send_json(json_error('Room is full'));

// Check if already joined
foreach ($room['players'] as $p) {
    if ($p['token'] === $token) {
        send_json(json_success(['room_id' => $room_id, 'already_joined' => true]));
    }
}

$room['players'][] = ['token' => $token, 'name' => $player_name];
write_json($path, $room);
send_json(json_success(['room_id' => $room_id]));
