<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../lib/game_init.php';

$data = get_post();
$room_id = $data['room_id'] ?? '';
$token = $data['token'] ?? '';

if (!$room_id || !$token) {
    send_json(json_error('Missing room_id or token'));
}

$path = data_path('rooms', $room_id);
$room = read_json($path);
if (!$room) send_json(json_error('Room not found'));
if ($room['host'] !== $token) send_json(json_error('Only the host can start'));
if ($room['status'] !== 'waiting') send_json(json_error('Game already started'));
if (count($room['players']) < 2) send_json(json_error('Need at least 2 players'));

$game_id = gen_id();
$game = init_game($room['players']);

write_json(data_path('games', $game_id), $game);

$room['status'] = 'started';
$room['game_id'] = $game_id;
write_json($path, $room);

send_json(json_success(['game_id' => $game_id]));
