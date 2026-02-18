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
if ($room['host'] !== $token) send_json(json_error('Only the host can add AI'));
if ($room['status'] !== 'waiting') send_json(json_error('Game already started'));
if (count($room['players']) >= 4) send_json(json_error('Room is full (max 4 players)'));

// Pick a name for this AI slot
$ai_names = ['Agent Alpha', 'Agent Beta', 'Agent Gamma', 'Agent Delta'];
$existing_ai_count = count(array_filter($room['players'], fn($p) => $p['is_ai'] ?? false));
$ai_name = $ai_names[$existing_ai_count] ?? 'Agent Omega';

$ai_token = 'ai_' . gen_id();
$room['players'][] = ['token' => $ai_token, 'name' => $ai_name, 'is_ai' => true];
write_json($path, $room);

send_json(json_success(['name' => $ai_name]));
