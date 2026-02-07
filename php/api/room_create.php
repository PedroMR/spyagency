<?php
require_once __DIR__ . '/helpers.php';

$data = get_post();
$name = trim($data['room_name'] ?? '');
$player_name = trim($data['player_name'] ?? '');
$token = $data['token'] ?? '';

if (!$name || !$player_name || !$token) {
    send_json(json_error('Missing room_name, player_name, or token'));
}

$room_id = gen_id();
$room = [
    'id' => $room_id,
    'name' => $name,
    'status' => 'waiting',
    'host' => $token,
    'players' => [['token' => $token, 'name' => $player_name]],
    'game_id' => null,
];

write_json(data_path('rooms', $room_id), $room);
send_json(json_success(['room_id' => $room_id]));
