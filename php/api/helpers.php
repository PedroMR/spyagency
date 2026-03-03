<?php

function data_path(string $type, string $id): string {
    return __DIR__ . '/../data/' . $type . '/' . $id . '.json';
}

function read_json(string $path): ?array {
    if (!file_exists($path)) return null;
    $fp = fopen($path, 'r');
    if (!$fp) return null;
    flock($fp, LOCK_SH);
    $data = json_decode(stream_get_contents($fp), true);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $data;
}

function write_json(string $path, array $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $fp = fopen($path, 'c');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function read_game_locked(string $game_id, callable $callback): array {
    $path = data_path('games', $game_id);
    $fp = fopen($path, 'c+');
    if (!$fp) return json_error('Cannot open game file');
    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $game = $content ? json_decode($content, true) : null;
    if (!$game) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return json_error('Game not found');
    }
    $result = $callback($game);
    if ($result['ok'] ?? false) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($game, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}

function json_success(array $data = []): array {
    return array_merge(['ok' => true], $data);
}

function json_error(string $msg): array {
    return ['ok' => false, 'error' => $msg];
}

function send_json(array $data): void {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../lib/game_log.php';

function gen_id(): string {
    return bin2hex(random_bytes(8));
}

function get_post(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
