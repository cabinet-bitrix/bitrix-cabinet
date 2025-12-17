<?php
// test.php - тест сервера
header('Content-Type: application/json');

$SESSIONS_FILE = 'sessions.json';

if (!file_exists($SESSIONS_FILE)) {
    file_put_contents($SESSIONS_FILE, json_encode([]));
}

$data = [
    'file_exists' => file_exists($SESSIONS_FILE),
    'file_writable' => is_writable($SESSIONS_FILE),
    'file_size' => filesize($SESSIONS_FILE),
    'server_time' => date('Y-m-d H:i:s'),
    'sessions' => json_decode(file_get_contents($SESSIONS_FILE), true)
];

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>