<?php
$botToken = '7992834659:AAFLuj0-5S5HvNy0OoEf1_a8wAOWz5acBgY';
$webhookUrl = 'https://ваш-домен.com/bot.php';

$url = "https://api.telegram.org/bot{$botToken}/setWebhook?url={$webhookUrl}";
$response = file_get_contents($url);
echo $response;
?>