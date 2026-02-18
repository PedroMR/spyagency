<?php
require_once __DIR__ . '/helpers.php';

$rooms_dir = __DIR__ . '/../data/rooms/';
$rooms = [];

if (is_dir($rooms_dir)) {
    foreach (glob($rooms_dir . '*.json') as $file) {
        $room = read_json($file);
        if ($room) {
            $rooms[] = [
                'id' => $room['id'],
                'name' => $room['name'],
                'status' => $room['status'],
                'host' => $room['host'] ?? null,
                'player_count' => count($room['players']),
                'players' => array_map(fn($p) => ['name' => $p['name'], 'is_ai' => $p['is_ai'] ?? false], $room['players']),
                'game_id' => $room['game_id'],
            ];
        }
    }
}

send_json(json_success(['rooms' => $rooms]));
