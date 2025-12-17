<?php
// test-server.php
header('Content-Type: application/json');

// Простой тест без зависимостей
$testData = [
    'action' => 'test',
    'message' => 'Server is working!',
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown'
];

echo json_encode($testData, JSON_PRETTY_PRINT);
?>