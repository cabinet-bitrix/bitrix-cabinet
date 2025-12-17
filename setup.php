<?php
// setup.php - установка вебхука
$BOT_TOKEN = '7992834659:AAFLuj0-5S5HvNy0OoEf1_a8wAOWz5acBgY';

// Получите свой домен (например: https://ваш-сайт.ru/bot.php)
$webhook_url = 'https://cb237414.tw1.ru/bot.php';

// Устанавливаем вебхук
$url = "https://api.telegram.org/bot{$BOT_TOKEN}/setWebhook?url={$webhook_url}";

$result = file_get_contents($url);

echo "<pre>";
echo "Установка вебхука:\n";
echo "URL: {$url}\n";
echo "Результат: {$result}\n\n";

// Проверяем вебхук
$url = "https://api.telegram.org/bot{$BOT_TOKEN}/getWebhookInfo";
$result = file_get_contents($url);

echo "Проверка вебхука:\n";
print_r(json_decode($result, true));
echo "</pre>";
?>