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
        $this->pdo = $this->createPdoConnection('postgres');
    }
    
    /**
     * Создать PDO соединение
     */
    public function createPdoConnection($database = 'postgres', $username = null, $password = null)
    {
        $dsn = "pgsql:host=" . env('DB_HOST') . 
               ";port=" . env('DB_PORT') . 
               ";dbname=" . $database;
        
        $connUsername = $username ?: env('DB_USERNAME');
        $connPassword = $password ?: env('DB_PASSWORD');
        
        try {
            $pdo = new PDO($dsn, $connUsername, $connPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            return $pdo;
            
        } catch (PDOException $e) {
            Log::error("Connection failed to {$database}: " . $e->getMessage());
            throw new \Exception("Не удалось подключиться к PostgreSQL: " . $e->getMessage());
        }
    }

    /**
     * Получить PDO соединение
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Полностью удалить и пересоздать БД для участника
     */
    public function recreateDatabaseForParticipant($moduleId, $participantId)
{
    $module = Module::with('event')->findOrFail($moduleId);
    $participant = EventAccount::with('user')->findOrFail($participantId);
    
    if ($participant->event_id != $module->event_id) {
        throw new \Exception('Participant not in this event');
    }
    
    $username = $participant->login; // Например: "smirnov_a_event3_e400"
    $password = $participant->password_plain ?? $participant->password;
    $dbName = $this->generateDatabaseName($module, $participant);
    
    Log::info("Recreating: DB={$dbName}, User={$username}");
    
    try {
        // 1. Удаляем старую БД если существует
        $this->dropDatabaseIfExists($dbName);
        
        // 2. Удаляем пользователя если существует
        $this->dropUserIfExists($username);
        
        // 3. Создаем пользователя с правами
        $this->createUser($username, $password);
        
        // 4. Создаем БД с owner = username ⬅️ ВАЖНО!
        $this->createDatabaseInternal($dbName, $username);
        
        // 5. Настраиваем права
        $this->setupDatabasePermissions($dbName, $username, $password);
        
        // 6. Сохраняем запись
        $database = $this->saveDatabaseRecord($moduleId, $participant, $dbName, $username, $password);
        
        return [
            'success' => true,
            'database' => $database,
            'connection_info' => [
                'host' => env('DB_HOST'),
                'port' => env('DB_PORT'),
                'database' => $dbName,
                'username' => $username,
                'password' => $password
            ]
        ];
        
    } catch (\Exception $e) {
        Log::error("Failed to recreate database: " . $e->getMessage());
        throw $e;
    }
}

    /**
     * Удалить БД если существует
     */
    private function dropDatabaseIfExists($dbName)
    {
        // Создаем новое соединение для каждой операции
        $pdo = $this->createPdoConnection('postgres');
        
        try {
            // 1. Проверяем существование БД
            $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$dbName}'");
            $exists = $stmt->fetchColumn();
            
            if (!$exists) {
                Log::info("Database {$dbName} does not exist, skipping drop");
                return;
            }
            
            // 2. Завершаем все активные соединения
            Log::info("Terminating connections to database {$dbName}");
            
            // Важно: завершаем в отдельной транзакции
            $terminateSql = "
                SELECT pg_terminate_backend(pid)
                FROM pg_stat_activity
                WHERE datname = '{$dbName}'
                AND pid <> pg_backend_pid()
                AND state = 'active'
            ";
            
            $pdo->exec($terminateSql);
            
            // 3. Небольшая пауза для завершения процессов
            usleep(500000); // 0.5 секунды
            
            // 4. Удаляем БД
            Log::info("Dropping database {$dbName}");
            $pdo->exec("DROP DATABASE IF EXISTS \"{$dbName}\"");
            
            Log::info("Database {$dbName} dropped successfully");
            
        } catch (\Exception $e) {
            Log::warning("Error dropping database {$dbName}: " . $e->getMessage());
            // Не бросаем исключение, чтобы можно было продолжать
        } finally {
            $pdo = null; // Закрываем соединение
        }
    }

    /**
     * Удалить пользователя если существует
     */
    private function dropUserIfExists($username)
    {
        // Создаем новое соединение
        $pdo = $this->createPdoConnection('postgres');
        
        try {
            // Проверяем существование пользователя
            $stmt = $pdo->query("SELECT 1 FROM pg_roles WHERE rolname = '{$username}'");
            $exists = $stmt->fetchColumn();
            
            if (!$exists) {
                Log::info("User {$username} does not exist, skipping drop");
                return;
            }
            
            // Сначала проверяем, какие БД у пользователя
            $stmt = $pdo->query("SELECT datname FROM pg_database WHERE datdba = (SELECT oid FROM pg_roles WHERE rolname = '{$username}')");
            $userDatabases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Удаляем пользователя только если у него нет БД
            if (empty($userDatabases)) {
                Log::info("Dropping user {$username}");
                $pdo->exec("DROP USER IF EXISTS \"{$username}\"");
                Log::info("User {$username} dropped successfully");
            } else {
                Log::info("User {$username} has databases, cannot drop user");
            }
            
        } catch (\Exception $e) {
            Log::warning("Error dropping user {$username}: " . $e->getMessage());
            // Не бросаем исключение
        } finally {
            $pdo = null;
        }
    }

    /**
     * Создать пользователя
     */
    private function createUser($username, $password, $isLocked = false)
{
    $pdo = $this->createPdoConnection('postgres');
    
    try {
        $cleanUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
        $escapedPassword = str_replace("'", "''", $password);
        
        $stmt = $pdo->query("SELECT 1 FROM pg_roles WHERE rolname = '{$cleanUsername}'");
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            Log::info("User {$cleanUsername} exists, updating");
            $pdo->exec("ALTER USER \"{$cleanUsername}\" WITH PASSWORD '{$escapedPassword}'");
        } else {
            Log::info("Creating user {$cleanUsername}");
            
            // Если пользователь заблокирован - устанавливаем дату истечения
            if ($isLocked) {
                $validUntil = date('Y-m-d H:i:s', strtotime('+1 minute')); // Через 1 минуту истечет
                $sql = "CREATE USER \"{$cleanUsername}\" 
                        WITH PASSWORD '{$escapedPassword}'
                        NOSUPERUSER
                        NOCREATEDB
                        NOCREATEROLE
                        NOINHERIT
                        LOGIN
                        CONNECTION LIMIT 1
                        VALID UNTIL '{$validUntil}'"; // Автоматическая блокировка
            } else {
                $sql = "CREATE USER \"{$cleanUsername}\" 
                        WITH PASSWORD '{$escapedPassword}'
                        NOSUPERUSER
                        NOCREATEDB
                        NOCREATEROLE
                        NOINHERIT
                        LOGIN
                        CONNECTION LIMIT 5
                        VALID UNTIL 'infinity'";
            }
            
            $pdo->exec($sql);
        }
        
        return $cleanUsername;
        
    } catch (\Exception $e) {
        Log::error("Error creating user {$username}: " . $e->getMessage());
        throw $e;
    } finally {
        $pdo = null;
    }
}

/**
 * Отозвать все права пользователя на всех БД
 */
private function revokeAllDatabasePrivileges($pdo, $username)
{
    // Получаем все БД
    $stmt = $pdo->query("SELECT datname FROM pg_database WHERE datistemplate = false");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($databases as $db) {
        try {
            $pdo->exec("REVOKE ALL ON DATABASE \"{$db}\" FROM \"{$username}\"");
        } catch (\Exception $e) {
            // Игнорируем ошибки если прав нет
        }
    }
}

    /**
     * Создать БД
     */
    private function createDatabaseInternal($dbName, $owner)
{
    $pdo = $this->createPdoConnection('postgres');
    
    try {
        // Проверяем, существует ли БД
        $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$dbName}'");
        if ($stmt->fetchColumn()) {
            throw new \Exception("Database '{$dbName}' already exists");
        }
        
        // Создаем БД с указанием владельца
        $sql = "CREATE DATABASE \"{$dbName}\" 
                OWNER = \"{$owner}\"
                ENCODING = 'UTF8'
                TEMPLATE = template0
                CONNECTION LIMIT = 20";
        
        Log::info("Creating database {$dbName}, owner: {$owner}");
        $pdo->exec($sql);
        
        Log::info("Database {$dbName} created successfully with owner {$owner}");
        
    } catch (\Exception $e) {
        Log::error("Error creating database {$dbName}: " . $e->getMessage());
        throw $e;
    } finally {
        $pdo = null;
    }
}

    /**
     * Настроить права в БД
     */
private function setupDatabasePermissions($dbName, $username, $password)
{
    try {
        Log::info("Setting up permissions for DB: {$dbName}, User: {$username}");
        
        // 1. Подключаемся к postgres как администратор для глобальных операций
        $adminPdo = $this->createPdoConnection('postgres');
        
        // 2. Даем доступ ТОЛЬКО к этой БД
        $adminPdo->exec("GRANT CONNECT ON DATABASE \"{$dbName}\" TO \"{$username}\"");
        
        // 3. Делаем пользователя владельцем БД
        $adminPdo->exec("ALTER DATABASE \"{$dbName}\" OWNER TO \"{$username}\"");
        
        Log::info("Database {$dbName} owner changed to {$username}");
        
        // 4. Явно запрещаем доступ ко всем остальным БД
        $this->denyAccessToOtherDatabases($adminPdo, $username, $dbName);
        
        // Закрываем первое соединение
        $adminPdo = null;
        
        // 5. Подключаемся к самой БД как администратор для настройки схем
        $dbAdminPdo = $this->createPdoConnection($dbName, env('DB_USERNAME'), env('DB_PASSWORD'));
        
        // 6. В БД даем полные права на public схему
        $dbAdminPdo->exec("GRANT ALL ON SCHEMA public TO \"{$username}\" WITH GRANT OPTION");
        $dbAdminPdo->exec("GRANT ALL ON ALL TABLES IN SCHEMA public TO \"{$username}\" WITH GRANT OPTION");
        $dbAdminPdo->exec("GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO \"{$username}\" WITH GRANT OPTION");
        $dbAdminPdo->exec("GRANT ALL ON ALL FUNCTIONS IN SCHEMA public TO \"{$username}\" WITH GRANT OPTION");
        // УБРАТЬ ЭТУ СТРОКУ: $dbAdminPdo->exec("GRANT ALL ON ALL PROCEDURES IN SCHEMA public TO \"{$username}\" WITH GRANT OPTION");
        
        // 7. Права по умолчанию для будущих объектов
        $dbAdminPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public 
                         GRANT ALL ON TABLES TO \"{$username}\" WITH GRANT OPTION");
        $dbAdminPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public 
                         GRANT ALL ON SEQUENCES TO \"{$username}\" WITH GRANT OPTION");
        $dbAdminPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public 
                         GRANT ALL ON FUNCTIONS TO \"{$username}\" WITH GRANT OPTION");
        // УБРАТЬ ЭТУ СТРОКУ: $dbAdminPdo->exec("ALTER DEFAULT PRIVILEGES IN SCHEMA public 
        //                  GRANT ALL ON PROCEDURES TO \"{$username}\" WITH GRANT OPTION");
        
        // 8. Создаем личную схему
        $dbAdminPdo->exec("CREATE SCHEMA IF NOT EXISTS \"private_{$username}\"");
        $dbAdminPdo->exec("GRANT ALL ON SCHEMA \"private_{$username}\" TO \"{$username}\" WITH GRANT OPTION");
        $dbAdminPdo->exec("ALTER SCHEMA \"private_{$username}\" OWNER TO \"{$username}\"");
        
        // 9. Запрещаем доступ к системным схемам
        $dbAdminPdo->exec("REVOKE ALL ON SCHEMA information_schema FROM \"{$username}\"");
        $dbAdminPdo->exec("REVOKE ALL ON SCHEMA pg_catalog FROM \"{$username}\"");
        
        // 10. Даем возможность создавать временные таблицы
        $dbAdminPdo->exec("GRANT TEMPORARY ON DATABASE \"{$dbName}\" TO \"{$username}\"");
        
        Log::info("Full isolation permissions set for {$username} in {$dbName}");
        
        // Закрываем соединение
        $dbAdminPdo = null;
        
        // 11. Тестовое подключение от имени пользователя (проверка)
        try {
            $userPdo = $this->createPdoConnection($dbName, $username, $password);
            $stmt = $userPdo->query("SELECT current_user, current_database()");
            $result = $stmt->fetch();
            Log::info("Test connection successful: {$result['current_user']} @ {$result['current_database']}");
            $userPdo = null;
        } catch (\Exception $e) {
            Log::warning("Test connection failed but continuing: " . $e->getMessage());
        }
        
    } catch (\Exception $e) {
        Log::error("Error setting isolation permissions: " . $e->getMessage());
        throw new \Exception("Ошибка настройки прав: " . $e->getMessage());
    }
}

/**
 * Явно запретить доступ ко всем БД кроме указанной
 */
private function denyAccessToOtherDatabases($pdo, $username, $allowedDb)
{
    try {
        // Получаем все не-системные БД кроме разрешенной
        $stmt = $pdo->query("SELECT datname FROM pg_database 
                             WHERE datistemplate = false 
                             AND datname NOT IN ('{$allowedDb}', 'postgres')");
        $otherDatabases = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($otherDatabases as $db) {
            try {
                // Явно запрещаем CONNECT - пользователь не сможет даже подключиться
                $pdo->exec("REVOKE CONNECT ON DATABASE \"{$db}\" FROM \"{$username}\"");
                $pdo->exec("REVOKE ALL ON DATABASE \"{$db}\" FROM PUBLIC"); // От всех
                Log::debug("Revoked access to database {$db} for user {$username}");
            } catch (\Exception $e) {
                // Если нет прав - игнорируем
                if (!str_contains($e->getMessage(), 'не имеет права')) {
                    Log::warning("Could not revoke access to {$db}: " . $e->getMessage());
                }
            }
        }
        
        Log::info("Access denied to all other databases for user {$username}");
        
    } catch (\Exception $e) {
        Log::error("Error in denyAccessToOtherDatabases: " . $e->getMessage());
        throw $e;
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
     * Создать БД для всех участников модуля (без удаления существующих)
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
                
                // Создаем БД (используем тот же метод, что и в recreate)
                $dbName = $this->generateDatabaseName($module, $participant);
                $username = $participant->login;
                $password = $participant->password_plain ?? $participant->password;
                
                // Создаем пользователя
                $this->createUser($username, $password);
                
                // Создаем БД
                $this->createDatabaseInternal($dbName, $username);
                
                // Настраиваем права
                $this->setupDatabasePermissions($dbName, $username, $password);
                
                // Сохраняем запись
                $database = $this->saveDatabaseRecord($moduleId, $participant, $dbName, $username, $password);
                
                $results['successful']++;
                $results['databases'][] = [
                    'success' => true,
                    'database_id' => $database->id,
                    'database_name' => $database->name,
                    'username' => $database->username,
                    'password' => $password,
                    'participant_id' => $participant->id,
                    'participant_name' => $participant->user->name ?? 'Unknown',
                    'participant_login' => $participant->login,
                    'connection_info' => [
                        'host' => env('DB_HOST'),
                        'port' => env('DB_PORT'),
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
     * Пересоздать БД для ВСЕХ участников модуля
     */
    public function recreateForAllParticipants($moduleId)
    {
        $module = Module::with('event')->findOrFail($moduleId);
        
        // Находим всех участников
        $participantRole = Role::where('name', 'Участник')->first();
        $participants = EventAccount::where('event_id', $module->event_id)
            ->where('role_id', $participantRole->id)
            ->get();
        
        if ($participants->isEmpty()) {
            throw new \Exception('No participants found');
        }
        
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($participants as $participant) {
            try {
                // Для каждого участника создаем новое соединение!
                $result = $this->recreateDatabaseForParticipant($moduleId, $participant->id);
                
                $results[] = [
                    'success' => true,
                    'participant_id' => $participant->id,
                    'participant_login' => $participant->login,
                    'database_name' => $result['database']->name,
                    'username' => $participant->login,
                    'message' => 'Database recreated successfully'
                ];
                
                $successCount++;
                
                // Небольшая пауза между созданиями
                usleep(100000); // 0.1 секунды
                
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'participant_id' => $participant->id,
                    'participant_login' => $participant->login,
                    'error' => $e->getMessage(),
                    'database_name' => 'none'
                ];
                $errorCount++;
                
                // Продолжаем с другими участниками, несмотря на ошибку
                Log::error("Failed to recreate database for {$participant->login}: " . $e->getMessage());
            }
        }
        
        return [
            'success' => $errorCount === 0,
            'message' => "Recreated {$successCount} databases, failed: {$errorCount}",
            'summary' => [
                'total' => count($participants),
                'successful' => $successCount,
                'failed' => $errorCount
            ],
            'results' => $results
        ];
    }

    /**
     * Сохранить запись о БД в нашей системе
     */
    private function saveDatabaseRecord($moduleId, $participant, $dbName, $username, $password)
    {
        // Удаляем старую запись если есть
        Database::where('module_id', $moduleId)
                ->where('event_account_id', $participant->id)
                ->delete();
        
        // Создаем новую запись
        $database = Database::create([
            'name' => $dbName,
            'username' => $username,
            'password' => $password,
            'server_id' => $this->getPostgresServerId(),
            'type_id' => $this->getDatabaseTypeId(),
            'event_account_id' => $participant->id,
            'module_id' => $moduleId,
            'status_id' => $this->getActiveDatabaseStatusId(),
            'is_active' => true,
            'is_public' => false,
            'has_demo_data' => false,
            'is_empty' => true,
            'metadata' => [
                'created_at' => now()->toISOString(),
                'participant_login' => $participant->login,
                'participant_name' => $participant->user->name ?? 'Participant',
                'recreated_at' => now()->toISOString()
            ]
        ]);
        
        return $database;
    }

    /**
     * Генерация имени БД
     */
    private function generateDatabaseName(Module $module, EventAccount $participant)
    {
        try {
        // 1. Извлекаем фамилию участника
        $familyName = $this->extractFamilyName($participant);
        
        // 2. Очищаем фамилию: только латинские буквы, нижний регистр
        $cleanFamily = preg_replace('/[^a-zA-Z]/', '', $familyName);
        $cleanFamily = strtolower($cleanFamily);
        
        // 3. Если фамилия пустая или слишком короткая, используем логин
        if (strlen($cleanFamily) < 2) {
            $login = $participant->login ?? '';
            $cleanFamily = preg_replace('/[^a-zA-Z]/', '', $login);
            $cleanFamily = strtolower($cleanFamily);
            
            // Если и логин не подходит, используем 'user'
            if (strlen($cleanFamily) < 2) {
                $cleanFamily = 'user';
            }
        }
        
        // 4. Ограничиваем длину фамилии (чтобы осталось место для суффикса)
        $maxFamilyLength = 20; // Оставляем место для суффикса
        if (strlen($cleanFamily) > $maxFamilyLength) {
            $cleanFamily = substr($cleanFamily, 0, $maxFamilyLength);
        }
        
        // 5. Генерируем случайный суффикс (4 hex символа)
        $randomSuffix = bin2hex(random_bytes(2)); // 2 байта = 4 hex символа
        
        // 6. Формируем имя БД
        $dbName = $cleanFamily . '_' . 'm' . $module->id . '_'. $randomSuffix;
        
        // 7. Проверяем уникальность имени в рамках модуля
        $dbName = $this->ensureUniqueName($dbName, $module->id);
        
        Log::info("Generated secure DB name: {$dbName} for participant: {$participant->login}");
        
        return $dbName;
        
    } catch (\Exception $e) {
        // Fallback на простой формат с timestamp
        Log::warning("Error generating secure DB name: " . $e->getMessage());
        return 'db_' . time() . '_' . rand(1000, 9999);
    }
    }

    /**
     * Извлечение фамилии из данных участника
     */
    private function extractFamilyName(EventAccount $participant)
    {
        $user = $participant->user;
        
        if ($user && !empty($user->name)) {
            // Пытаемся извлечь фамилию (первое слово в ФИО)
            $nameParts = preg_split('/\s+/', trim($user->name));
            if (!empty($nameParts[0])) {
                return $nameParts[0];
            }
        }
        
        // Если нет имени, используем логин
        $login = $participant->login ?? '';
        
        // Пытаемся извлечь фамилию из логина (например, ivanov_exam -> ivanov)
        if (str_contains($login, '_')) {
            $loginParts = explode('_', $login);
            return $loginParts[0];
        }
        
        return $login;
    }

    /**
 * Обеспечение уникальности имени БД
 */
private function ensureUniqueName($baseName, $moduleId)
{
    $originalName = $baseName;
    $counter = 1;
    
    // Проверяем в PostgreSQL, существует ли такая БД
    $pdo = $this->createPdoConnection('postgres');
    
    while ($counter < 100) { // Защита от бесконечного цикла
        $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$baseName}'");
        $exists = $stmt->fetchColumn();
        
        if (!$exists) {
            // Также проверяем в нашей системе
            $dbRecord = Database::where('name', $baseName)
                ->where('module_id', $moduleId)
                ->first();
            
            if (!$dbRecord) {
                return $baseName;
            }
        }
        
        // Добавляем суффикс и пробуем снова
        $baseName = $originalName . '_' . $counter;
        $counter++;
    }
    
    // Если не нашли уникальное имя, добавляем timestamp
    return $originalName . '_' . time();
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
     * Получить ID сервера PostgreSQL
     */
    private function getPostgresServerId()
    {
        $postgresType = Type::where('name', 'База данных PostgreSQL')
            ->whereHas('context', function($q) {
                $q->where('name', 'server');
            })->first();
        
        if (!$postgresType) {
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