<?php

namespace App\Services;

use App\Models\Database;
use App\Models\EventAccount;
use App\Models\Module;
use App\Models\Role;
use App\Models\Server;
use App\Models\Type;
use App\Models\Status;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;

class DatabaseService
{
    protected $pdo;
    
    public function __construct()
    {
        // Подключаемся к PostgreSQL
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', 5432);
        $user = env('DB_USERNAME', 'postgres');
        $password = env('DB_PASSWORD', '061241angel');
        
        try {
            $this->pdo = new PDO(
                "pgsql:host={$host};port={$port}",
                $user,
                $password
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new \Exception("Не удалось подключиться к PostgreSQL: " . $e->getMessage());
        }
    }
    
    /**
     * Проверить подключение к PostgreSQL
     */
    public function testPostgresConnection()
    {
        try {
            $stmt = $this->pdo->query("SELECT version()");
            $version = $stmt->fetchColumn();
            
            return [
                'status' => 'connected',
                'message' => 'PostgreSQL сервер доступен',
                'version' => $version,
                'current_user' => env('DB_USERNAME', 'postgres')
            ];
            
        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Создать БД для всех участников модуля
     */
    public function createDatabasesForModule($moduleId)
    {
        $module = Module::findOrFail($moduleId);
        
        if (!$module->event_id) {
            throw new \Exception('Module not attached to event');
        }
        
        $participantRoleId = Role::where('name', 'Участник')->value('id');
        
        if (!$participantRoleId) {
            throw new \Exception('Role "Participant" not found');
        }
        
        $participants = EventAccount::where('event_id', $module->event_id)
            ->where('role_id', $participantRoleId)
            ->with(['user'])
            ->get();
        
        if ($participants->isEmpty()) {
            throw new \Exception('No participants with role "Participant" in event');
        }
        
        $serverId = $this->getPostgresServerId();
        $typeId = $this->getDatabaseTypeId();
        
        $results = [
            'total' => $participants->count(),
            'successful' => 0,
            'failed' => 0,
            'databases' => []
        ];
        
        foreach ($participants as $participant) {
            try {
                // Проверяем, не существует ли уже БД
                $existingDatabase = Database::where('module_id', $moduleId)
                    ->where('event_account_id', $participant->id)
                    ->first();
                
                if ($existingDatabase) {
                    throw new \Exception("Database for participant already exists");
                }
                
                // Создаем реальную БД
                $database = $this->createDatabase($module, $participant, $serverId, $typeId);
                
                $results['successful']++;
                $results['databases'][] = [
                    'success' => true,
                    'database_id' => $database->id,
                    'database_name' => $database->name,
                    'username' => $database->username,
                    'password' => $participant->password,
                    'participant_id' => $participant->id,
                    'participant_name' => $participant->user->name ?? 'Unknown',
                    'participant_login' => $participant->login,
                    'connection_info' => [
                        'host' => 'localhost',
                        'port' => 5432,
                        'database' => $database->name,
                        'username' => $database->username
                    ]
                ];
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['databases'][] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'participant_id' => $participant->id,
                    'participant_name' => $participant->user->name ?? 'Unknown'
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Создать реальную БД PostgreSQL
     */
    private function createDatabase(Module $module, EventAccount $participant, $serverId, $typeId)
    {
        // Генерируем уникальное имя БД
        $dbName = $this->generateDatabaseName($module, $participant);
        $username = $participant->login;
        $password = $participant->password;
        
        // 1. Создаем пользователя
        $this->createDatabaseUser($username, $password);
        
        // 2. Создаем базу данных
        $this->createDatabaseInternal($dbName, $username);
        
        // 3. Настраиваем права
        $this->setupDatabasePermissions($dbName, $username, $password);
        
        // Получаем статус "Активна" для БД
        $activeStatusId = $this->getActiveDatabaseStatusId();
        
        // Упрощенный metadata без русских символов
        $metadata = [
            'created_at' => now()->toISOString(),
            'participant_login' => $participant->login,
            'participant_name' => $this->sanitizeString($participant->user->name ?? 'Participant'),
            'event_name' => $this->sanitizeString($module->event->name ?? 'Event'),
            'module_name' => $this->sanitizeString($module->name),
            'is_empty' => true,
            'has_demo_tables' => false,
            'connection_info' => [
                'host' => 'localhost',
                'port' => 5432,
                'database' => $dbName,
                'username' => $username
            ]
        ];
        
        // Сохраняем запись в нашей системе
        $database = Database::create([
            'name' => $dbName,
            'username' => $username,
            'password' => $password,
            'server_id' => $serverId,
            'type_id' => $typeId,
            'event_account_id' => $participant->id,
            'module_id' => $module->id,
            'status_id' => $activeStatusId,
            'is_active' => true,
            'is_public' => false,
            'has_demo_data' => false,
            'is_empty' => true,
            'metadata' => $metadata
        ]);
        
        return $database;
    }
    
    /**
     * Очистка строки от проблемных символов
     */
    private function sanitizeString($string)
    {
        if (empty($string)) {
            return '';
        }
        
        // Удаляем не-UTF8 символы
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        
        // Удаляем управляющие символы
        $string = preg_replace('/[\x00-\x1F\x7F]/u', '', $string);
        
        return $string;
    }
    
    /**
     * Создать пользователя PostgreSQL
     */
    private function createDatabaseUser($username, $password)
    {
        // Проверяем, существует ли пользователь
        $stmt = $this->pdo->query("SELECT 1 FROM pg_roles WHERE rolname = '{$username}'");
        if ($stmt->fetch()) {
            Log::info("User {$username} already exists");
            return;
        }
        
        // Экранируем пароль
        $escapedPassword = str_replace("'", "''", $password);
        
        // Создаем пользователя
        $sql = "CREATE USER \"{$username}\" WITH 
                PASSWORD '{$escapedPassword}'
                NOSUPERUSER
                NOCREATEDB
                NOCREATEROLE
                LOGIN";
        
        $this->pdo->exec($sql);
        Log::info("Created PostgreSQL user: {$username}");
    }
    
    /**
     * Создать базу данных
     */
    private function createDatabaseInternal($dbName, $owner)
    {
        // Проверяем, существует ли БД
        $stmt = $this->pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$dbName}'");
        if ($stmt->fetch()) {
            throw new \Exception("Database '{$dbName}' already exists");
        }
        
        // Создаем БД с владельцем
        $sql = "CREATE DATABASE \"{$dbName}\" 
                OWNER = \"{$owner}\"
                ENCODING = 'UTF8'
                LC_COLLATE = 'Russian_Russia.1251'
                LC_CTYPE = 'Russian_Russia.1251'
                TEMPLATE = template0";
        
        $this->pdo->exec($sql);
        Log::info("Created database: {$dbName}, owner: {$owner}");
    }
    
    /**
     * Настроить права в БД
     */
    private function setupDatabasePermissions($dbName, $username, $password)
    {
        // Подключаемся к новой БД от имени пользователя
        $pdoUser = new PDO(
            "pgsql:host=localhost;port=5432;dbname={$dbName}",
            $username,
            $password
        );
        $pdoUser->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Пользователь уже владелец БД, поэтому имеет все права
        // Можно оставить пустым или добавить базовые настройки
        $pdoUser->exec("GRANT ALL ON SCHEMA public TO \"{$username}\"");
        
        Log::info("Set permissions for user {$username} in database {$dbName}");
    }
    
    /**
     * Генерация имени БД
     */
    private function generateDatabaseName(Module $module, EventAccount $participant)
    {
        $eventId = $module->event_id;
        $moduleId = $module->id;
        $userId = $participant->id;
        $timestamp = substr(time(), -6);
        
        $name = "exam_event{$eventId}_module{$moduleId}_user{$userId}_{$timestamp}";
        
        // Ограничиваем длину 63 символа (максимум в PostgreSQL)
        if (strlen($name) > 63) {
            $name = substr($name, 0, 63);
        }
        
        return $name;
    }
    
    /**
     * Получить ID сервера PostgreSQL
     */
    private function getPostgresServerId()
    {
        $postgresType = Type::where('name', 'База данных PostgreSQL')
            ->whereHas('context', function($q) {
                $q->where('name', 'server');
            })->first();
        
        if (!$postgresType) {
            // Возвращаем null если не найден
            return null;
        }
        
        $server = Server::where('type_id', $postgresType->id)
            ->where('is_active', true)
            ->first();
        
        if (!$server) {
            return null;
        }
        
        return $server->id;
    }
    
    /**
     * Получить ID типа базы данных
     */
    private function getDatabaseTypeId()
    {
        $context = \App\Models\Context::where('name', 'database')->first();
        
        if (!$context) {
            return null;
        }
        
        $type = Type::where('context_id', $context->id)->first();
        
        if (!$type) {
            return null;
        }
        
        return $type->id;
    }
    
    /**
     * Получить ID статуса "Активна" для БД
     */
    private function getActiveDatabaseStatusId()
    {
        $databaseContext = \App\Models\Context::where('name', 'database')->first();
        
        if (!$databaseContext) {
            return null;
        }
        
        $activeStatus = Status::where('name', 'Активна')
            ->where('context_id', $databaseContext->id)
            ->first();
        
        if (!$activeStatus) {
            return null;
        }
        
        return $activeStatus->id;
    }
}