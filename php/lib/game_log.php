<?php

function game_log(string $line): void {
    $path = __DIR__ . '/../data/games.log';
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $ts = date('Y-m-d H:i:s');
    file_put_contents($path, "[{$ts}] {$line}\n", FILE_APPEND | LOCK_EX);
}
